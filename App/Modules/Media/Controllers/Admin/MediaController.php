<?php

declare(strict_types=1);

namespace App\Modules\Media\Controllers\Admin;

use App\Controllers\Admin\BaseController;
use App\CMS\Auth;
use Core\Utilities\Upload\Upload;
use Core\Utilities\Upload\UploadConfig;
use Core\Utilities\Image\Image;
use Core\Utilities\Image\ImageConfig;

/**
 * Media library: grid list, upload, edit metadata, delete.
 *
 * GET  /admin/media                        → index()
 * POST /admin/media/upload                 → upload()             (AJAX multipart)
 * POST /admin/media/regen-thumbnails       → regenThumbnails()    (AJAX JSON)
 * GET  /admin/media/{id}/edit-form         → editForm($id)        (AJAX modal partial)
 * POST /admin/media/{id}/update            → update($id)          (AJAX JSON)
 * POST /admin/media/{id}/delete            → delete($id)          (AJAX JSON)
 */
class MediaController extends BaseController
{
    protected string $module = 'media';

    private const THUMB_SIZE    = 400;
    private const MAX_ORIG_WIDTH = 1920;
    private const IMAGE_EXTS    = ['jpg', 'jpeg', 'png', 'webp'];

    public function __construct()
    {
        parent::__construct();
    }

    // ── Grid List ──────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requirePermission('media.view');

        $search  = trim($this->input->get('search') ?? '');
        $page    = max(1, (int) ($this->input->get('page') ?? 1));
        $perPage = 24;
        $offset  = ($page - 1) * $perPage;

        $q = $this->db('media_files')
            ->select('id, filename, original_name, mime_type, size, width, height, alt_text, thumbnail_path, created_at')
            ->orderBy('created_at', 'DESC')
            ->limitOffset($perPage, $offset);

        $qc = $this->db('media_files');

        if ($search) {
            $binds = [':s' => "%{$search}%"];
            $q->whereRaw('original_name ILIKE :s', $binds);
            $qc->whereRaw('original_name ILIKE :s', $binds);
        }

        $total = (int) ($qc->totalRows() ?: 0);
        $files = $q->get() ?: [];

        foreach ($files as &$f) {
            $f['url']           = $this->fileUrl($f['filename']);
            $f['thumbnail_url'] = $f['thumbnail_path']
                ? $this->fileUrl($f['thumbnail_path'])
                : $f['url'];
        }
        unset($f);

        // Count files missing thumbnails for the regen button badge
        $missingThumbCount = (int) ($this->db('media_files')
            ->whereRaw("mime_type LIKE 'image/%'")
            ->whereRaw("(thumbnail_path IS NULL OR thumbnail_path = :empty)", [':empty' => ''])
            ->totalRows() ?: 0);

        $this->adminRender('modules/media/admin/media/index', [
            'files'             => $files,
            'total'             => $total,
            'page'              => $page,
            'pages'             => max(1, (int) ceil($total / $perPage)),
            'search'            => $search,
            'missingThumbCount' => $missingThumbCount,
        ], 'Media Library', 'media');
    }

    // ── Upload ─────────────────────────────────────────────────────────────────

    public function upload(): void
    {
        $this->requirePermission('media.upload');
        $this->validateCsrf();

        if (empty($_FILES['file'])) {
            $this->json(['success' => false, 'message' => 'No file was received.'], 400);
        }

        $phpErr = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($phpErr !== UPLOAD_ERR_OK) {
            $phpMessages = [
                UPLOAD_ERR_INI_SIZE   => 'File exceeds the server\'s maximum upload size (5 MB).',
                UPLOAD_ERR_FORM_SIZE  => 'File exceeds the form upload size limit.',
                UPLOAD_ERR_PARTIAL    => 'File upload was interrupted. Please try again.',
                UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'Server configuration error: missing temporary directory.',
                UPLOAD_ERR_CANT_WRITE => 'Server could not write the uploaded file to disk.',
                UPLOAD_ERR_EXTENSION  => 'File upload was blocked by a server extension.',
            ];
            $msg = $phpMessages[$phpErr] ?? 'Upload failed (error ' . $phpErr . ').';
            $this->json(['success' => false, 'message' => $msg], 400);
        }

        $file  = $_FILES['file'];
        $ext   = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $year  = date('Y');
        $month = date('m');

        $stored = date('Ymd') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $dir    = ROOT . 'Public' . DS . 'uploads' . DS . 'media' . DS . $year . DS . $month . DS;

        $uploader = new Upload();
        $uploader->configure(UploadConfig::forImages());
        $uploader->setDir($dir);
        $uploader->setFileName($stored);

        if (!$uploader->upload($file)) {
            $this->json(['success' => false, 'message' => $uploader->getError()], 422);
        }

        $fullPath   = $dir . $stored;
        $dimensions = @getimagesize($fullPath);
        $width      = $dimensions ? $dimensions[0] : null;
        $height     = $dimensions ? $dimensions[1] : null;

        // Generate thumbnail + optionally downscale original
        $thumbnailPath = null;
        $resized       = false;

        if ($dimensions && in_array($ext, self::IMAGE_EXTS)) {
            $result = $this->processUploadedImage($fullPath, $dir, $stored, $width, $height);
            if ($result) {
                $thumbnailPath = $year . '/' . $month . '/thumb_' . $stored;
                $resized       = $result['resized'];
                $width         = $result['width'];
                $height        = $result['height'];
            }
        }

        $storedFilename = $year . '/' . $month . '/' . $stored;

        try {
            $id = (string) $this->db('media_files')->withoutTimestamps()->save([
                'filename'       => $storedFilename,
                'original_name'  => basename($file['name']),
                'mime_type'      => $file['type'],
                'size'           => (int) filesize($fullPath),
                'width'          => $width,
                'height'         => $height,
                'thumbnail_path' => $thumbnailPath,
                'resized'        => $resized,
                'uploaded_by'    => $this->currentUser['id'],
            ]);
        } catch (\Exception) {
            @unlink($fullPath);
            if ($thumbnailPath) {
                @unlink($dir . 'thumb_' . $stored);
            }
            $this->json(['success' => false, 'message' => 'File could not be saved to the media library. Please try again.'], 500);
        }

        Auth::audit('media.upload', 'media_files', $id, ['original_name' => $file['name']]);

        $url          = $this->fileUrl($storedFilename);
        $thumbnailUrl = $thumbnailPath ? $this->fileUrl($thumbnailPath) : $url;

        $this->json([
            'success'       => true,
            'message'       => 'File uploaded successfully.',
            'url'           => $url,
            'thumbnail_url' => $thumbnailUrl,
            'id'            => $id,
            'filename'      => $storedFilename,
            'file'          => [
                'id'            => $id,
                'url'           => $url,
                'thumbnail_url' => $thumbnailUrl,
                'filename'      => $storedFilename,
                'name'          => basename($file['name']),
                'width'         => $width,
                'height'        => $height,
            ],
        ]);
    }

    // ── Bulk: Regenerate Thumbnails ────────────────────────────────────────────

    public function regenThumbnails(): void
    {
        $this->requirePermission('media.edit');
        $this->validateCsrf();

        $rows = $this->db('media_files')
            ->select('id, filename, width, height')
            ->whereRaw("mime_type LIKE 'image/%'")
            ->whereRaw("(thumbnail_path IS NULL OR thumbnail_path = :empty)", [':empty' => ''])
            ->limitOffset(50, 0)
            ->get() ?: [];

        $done = 0;

        foreach ($rows as $row) {
            $filename = $row['filename']; // YYYY/MM/stored.ext
            $parts    = explode('/', $filename);
            if (count($parts) < 3) {
                continue;
            }
            $year   = $parts[0];
            $month  = $parts[1];
            $stored = $parts[2];
            $ext    = strtolower(pathinfo($stored, PATHINFO_EXTENSION));

            if (!in_array($ext, self::IMAGE_EXTS)) {
                continue;
            }

            $dir      = ROOT . 'Public' . DS . 'uploads' . DS . 'media' . DS . $year . DS . $month . DS;
            $fullPath = $dir . $stored;

            if (!file_exists($fullPath)) {
                continue;
            }

            $result = $this->processUploadedImage(
                $fullPath, $dir, $stored,
                (int) ($row['width'] ?? 0),
                (int) ($row['height'] ?? 0)
            );

            if ($result) {
                $thumbPath = $year . '/' . $month . '/thumb_' . $stored;
                $this->db('media_files')->where('id', $row['id'])->update([
                    'thumbnail_path' => $thumbPath,
                    'resized'        => $result['resized'],
                ]);
                $done++;
            }
        }

        $remaining = (int) ($this->db('media_files')
            ->whereRaw("mime_type LIKE 'image/%'")
            ->whereRaw("(thumbnail_path IS NULL OR thumbnail_path = :empty)", [':empty' => ''])
            ->totalRows() ?: 0);

        $this->json([
            'success'   => true,
            'processed' => $done,
            'remaining' => $remaining,
            'message'   => "{$done} thumbnail(s) generated." . ($remaining > 0 ? " {$remaining} still pending - run again." : ' All done.'),
        ]);
    }

    // ── Edit form (modal partial) ──────────────────────────────────────────────

    public function editForm(string $id): void
    {
        $this->requirePermission('media.edit');
        $file = $this->db('media_files')->where('id', $id)->get(1);
        if (!$file) {
            $this->json(['success' => false, 'message' => 'File not found.'], 404);
        }

        $file['url'] = $this->fileUrl($file['filename']);

        $this->renderPartial('modules/media/admin/media/_edit_form', [
            'file'   => $file,
            'action' => $this->baseUrl . "/admin/media/{$id}/update",
        ]);
    }

    // ── Update metadata ────────────────────────────────────────────────────────

    public function update(string $id): void
    {
        $this->requirePermission('media.edit');
        $this->validateCsrf();

        $altText = trim($this->input->post('alt_text', false) ?? '');
        $caption = trim($this->input->post('caption', false) ?? '');

        $this->db('media_files')->where('id', $id)->update([
            'alt_text' => $altText,
            'caption'  => $caption,
        ]);

        Auth::audit('media.update', 'media_files', $id);
        $this->json(['success' => true, 'message' => 'Media updated.']);
    }

    // ── Delete ─────────────────────────────────────────────────────────────────

    public function delete(string $id): void
    {
        $this->requirePermission('media.delete');
        $this->validateCsrf();

        $file = $this->db('media_files')->select('filename, thumbnail_path')->where('id', $id)->get(1);
        if ($file) {
            $base = ROOT . 'Public' . DS . 'uploads' . DS . 'media' . DS;
            foreach (['filename', 'thumbnail_path'] as $col) {
                if (!empty($file[$col])) {
                    $path = $base . str_replace('/', DS, $file[$col]);
                    if (file_exists($path)) {
                        @unlink($path);
                    }
                }
            }
            $this->db('media_files')->where('id', $id)->delete();
        }

        Auth::audit('media.delete', 'media_files', $id);
        $this->json(['success' => true, 'message' => 'File deleted.']);
    }

    // ── Image Processing ───────────────────────────────────────────────────────

    /**
     * Resize original if too wide, then generate a 400×400 cover-crop thumbnail.
     * Returns ['resized' => bool, 'width' => int, 'height' => int] on success, null on failure.
     */
    private function processUploadedImage(string $fullPath, string $dir, string $stored, ?int $width, ?int $height): ?array
    {
        $config = ImageConfig::fromArray([
            'maxWidth'    => 20000,
            'maxHeight'   => 20000,
            'maxFileSize' => 100 * 1024 * 1024,
            'enableLogging' => false,
        ]);

        $resized = false;

        try {
            // Downscale original if wider than MAX_ORIG_WIDTH
            if ($width && $width > self::MAX_ORIG_WIDTH) {
                $targetH = (int) round(($height ?? $width) * (self::MAX_ORIG_WIDTH / $width));
                $img     = new Image($fullPath, $config);
                if ($img->isLoaded()) {
                    $img->resize(self::MAX_ORIG_WIDTH, $targetH)->save($fullPath);
                    $width   = self::MAX_ORIG_WIDTH;
                    $height  = $targetH;
                    $resized = true;
                }
                $img->destroy();
            }

            // Generate thumbnail: cover crop to THUMB_SIZE × THUMB_SIZE
            $thumbPath = $dir . 'thumb_' . $stored;
            $img       = new Image($fullPath, $config);

            if ($img->isLoaded()) {
                $dims  = $img->getCurrentDimensions();
                $w     = $dims['width'];
                $h     = $dims['height'];
                $sz    = self::THUMB_SIZE;

                $ratio  = max($sz / $w, $sz / $h);
                $interW = max($sz, (int) ceil($w * $ratio));
                $interH = max($sz, (int) ceil($h * $ratio));
                $offX   = (int) floor(($interW - $sz) / 2);
                $offY   = (int) floor(($interH - $sz) / 2);

                $img->resize($interW, $interH)->crop($offX, $offY, $sz, $sz)->save($thumbPath);
            }
            $img->destroy();

            if (file_exists($thumbPath)) {
                return ['resized' => $resized, 'width' => $width ?? 0, 'height' => $height ?? 0];
            }
        } catch (\Throwable) {
            // Thumbnail failure must never break the upload
        }

        return null;
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function fileUrl(string $filename): string
    {
        return $this->baseUrl . '/uploads/media/' . $filename;
    }

    private function validateCsrf(): void
    {
        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            if ($this->isAjax()) {
                $this->json(['success' => false, 'message' => 'Security token invalid.'], 403);
            }
            $this->flash('error', 'Security token invalid. Please try again.');
            $this->redirect($this->baseUrl . '/admin/media');
        }
    }
}
