<?php

declare(strict_types=1);

namespace App\Modules\Media\Controllers\Admin;

use App\Controllers\Admin\BaseController;
use App\CMS\Auth;
use Core\Utilities\Upload\Upload;
use Core\Utilities\Upload\UploadConfig;

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
        $this->ensureFolderSchema();
    }

    /** v0.0.2 runtime schema upgrade for pre-folder installs */
    private function ensureFolderSchema(): void
    {
        static $checked = false;
        if ($checked) return;
        $checked = true;
        try {
            $db = $this->db('media_files')->db;
            $db->query("CREATE TABLE IF NOT EXISTS media_folders (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                name VARCHAR(150) NOT NULL,
                parent_id UUID,
                created_at TIMESTAMP DEFAULT NOW(),
                updated_at TIMESTAMP DEFAULT NOW(),
                deleted_at TIMESTAMP,
                created_by UUID, updated_by UUID, deleted_by UUID
            )");
            $db->execute();
            $db->query("ALTER TABLE media_files ADD COLUMN IF NOT EXISTS folder_id UUID");
            $db->execute();
        } catch (\Throwable) {
        }
    }

    // ── Grid List ──────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requirePermission('media.view');

        $search  = trim($this->input->get('search') ?? '');
        $folder  = trim($this->input->get('folder') ?? '');
        $page    = max(1, (int) ($this->input->get('page') ?? 1));
        $perPage = 24;
        $offset  = ($page - 1) * $perPage;

        $q = $this->db('media_files')
            ->select('id, filename, original_name, mime_type, size, width, height, alt_text, thumbnail_path, folder_id, created_at')
            ->orderBy('created_at', 'DESC')
            ->limitOffset($perPage, $offset);

        $qc = $this->db('media_files');

        if ($search) {
            $binds = [':s' => "%{$search}%"];
            $q->whereRaw('original_name ILIKE :s', $binds);
            $qc->whereRaw('original_name ILIKE :s', $binds);
        }

        // Folder filter: '' = everything, 'unfiled' = no folder, else folder id
        $currentFolder = null;
        if ($folder === 'unfiled') {
            $q->whereNull('folder_id');
            $qc->whereNull('folder_id');
        } elseif ($folder !== '') {
            $currentFolder = $this->db('media_folders')->where('id', $folder)->whereNull('deleted_at')->get(1);
            if ($currentFolder) {
                $q->where('folder_id', $folder);
                $qc->where('folder_id', $folder);
            } else {
                $folder = '';
            }
        }

        // Folder sidebar with per-folder counts
        $folders = $this->db('media_folders')->whereNull('deleted_at')->orderBy('name', 'ASC')->get() ?: [];
        foreach ($folders as &$fRow) {
            $fRow['count'] = (int) ($this->db('media_files')->where('folder_id', (string) $fRow['id'])->totalRows() ?: 0);
        }
        unset($fRow);

        $total = (int) ($qc->totalRows() ?: 0);
        $files = $q->get() ?: [];

        foreach ($files as &$f) {
            $f['url']           = $this->fileUrl($f['filename']);
            $f['thumbnail_url'] = $f['thumbnail_path']
                ? $this->fileUrl($f['thumbnail_path'])
                : $f['url'];
        }
        unset($f);

        // Count files missing thumbnails for the regen button badge.
        // Includes files where thumbnail_path = filename (failure sentinel from a previous run).
        $missingThumbCount = (int) ($this->db('media_files')
            ->whereRaw("mime_type LIKE 'image/%'")
            ->whereRaw("(thumbnail_path IS NULL OR thumbnail_path = :emp OR thumbnail_path = filename)", [':emp' => ''])
            ->totalRows() ?: 0);

        $this->adminRender('modules/media/admin/media/index', [
            'files'             => $files,
            'total'             => $total,
            'page'              => $page,
            'pages'             => max(1, (int) ceil($total / $perPage)),
            'search'            => $search,
            'missingThumbCount' => $missingThumbCount,
            'folders'           => $folders,
            'folder'            => $folder,
            'currentFolder'     => $currentFolder,
        ], 'Media Library', 'media');
    }

    // ── Folders (v0.0.2) ───────────────────────────────────────────────────────

    /** POST /admin/media/folders/store - AJAX */
    public function storeFolder(): void
    {
        $this->requirePermission('media.upload');
        $this->validateCsrf();

        $name = trim($this->input->post('name', false) ?? '');
        if ($name === '' || mb_strlen($name) > 150) {
            $this->json(['success' => false, 'message' => 'A folder name (max 150 characters) is required.']);
        }

        $existing = $this->db('media_folders')->where('name', $name)->whereNull('deleted_at')->get(1);
        if ($existing) {
            $this->json(['success' => false, 'message' => 'A folder with that name already exists.']);
        }

        $id = (string) $this->db('media_folders')->save([
            'name'       => $name,
            'created_by' => $this->currentUser['id'] ?? null,
        ]);

        Auth::audit('media.folder_created', 'media_folders', $id, ['name' => $name]);
        $this->json(['success' => true, 'message' => "Folder \"{$name}\" created.", 'id' => $id, 'name' => $name]);
    }

    /** POST /admin/media/folders/{id}/rename - AJAX */
    public function renameFolder(string $id): void
    {
        $this->requirePermission('media.edit');
        $this->validateCsrf();

        $folder = $this->db('media_folders')->where('id', $id)->whereNull('deleted_at')->get(1);
        if (!$folder) {
            $this->json(['success' => false, 'message' => 'Folder not found.'], 404);
        }

        $name = trim($this->input->post('name', false) ?? '');
        if ($name === '' || mb_strlen($name) > 150) {
            $this->json(['success' => false, 'message' => 'A folder name (max 150 characters) is required.']);
        }

        $this->db('media_folders')->where('id', $id)->update([
            'name'       => $name,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $this->currentUser['id'] ?? null,
        ]);

        Auth::audit('media.folder_renamed', 'media_folders', $id, ['from' => $folder['name'], 'to' => $name]);
        $this->json(['success' => true, 'message' => 'Folder renamed.']);
    }

    /** POST /admin/media/folders/{id}/delete - AJAX; files fall back to Unfiled */
    public function deleteFolder(string $id): void
    {
        $this->requirePermission('media.delete');
        $this->validateCsrf();

        $folder = $this->db('media_folders')->where('id', $id)->whereNull('deleted_at')->get(1);
        if (!$folder) {
            $this->json(['success' => false, 'message' => 'Folder not found.'], 404);
        }

        // Files are kept - they just become unfiled
        $this->db('media_files')->where('folder_id', $id)->update(['folder_id' => null]);
        $this->db('media_folders')->where('id', $id)->update([
            'deleted_at' => date('Y-m-d H:i:s'),
            'deleted_by' => $this->currentUser['id'] ?? null,
        ]);

        Auth::audit('media.folder_deleted', 'media_folders', $id, ['name' => $folder['name']]);
        $this->json(['success' => true, 'message' => "Folder \"{$folder['name']}\" deleted. Its files were moved to Unfiled."]);
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

        // Optional target folder (validated) - the drop-zone appends it to the
        // upload URL as a query param, manual forms may POST it
        $folderId = trim($this->input->post('folder_id', false) ?? '');
        if ($folderId === '') {
            $folderId = trim($this->input->get('folder_id') ?? '');
        }
        if ($folderId !== '') {
            $folderRow = $this->db('media_folders')->where('id', $folderId)->whereNull('deleted_at')->get(1);
            $folderId  = $folderRow ? $folderId : '';
        }

        try {
            $id = (string) $this->db('media_files')->save([
                'filename'       => $storedFilename,
                'original_name'  => basename($file['name']),
                'mime_type'      => $file['type'],
                'size'           => (int) filesize($fullPath),
                'width'          => $width,
                'height'         => $height,
                'thumbnail_path' => $thumbnailPath,
                'resized'        => $resized,
                'folder_id'      => $folderId !== '' ? $folderId : null,
                'created_by'     => $this->currentUser['id'],
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
            ->whereRaw("(thumbnail_path IS NULL OR thumbnail_path = :emp OR thumbnail_path = filename)", [':emp' => ''])
            ->limitOffset(50, 0)
            ->get() ?: [];

        // Index by id for fast lookup when marking failures
        $filenameById = array_column($rows, 'filename', 'id');

        $done       = 0;
        $failedIds  = [];

        foreach ($rows as $row) {
            $filename = $row['filename']; // YYYY/MM/stored.ext
            $parts    = explode('/', $filename);
            if (count($parts) < 3) {
                $failedIds[] = $row['id'];
                continue;
            }
            $year   = $parts[0];
            $month  = $parts[1];
            $stored = $parts[2];
            $ext    = strtolower(pathinfo($stored, PATHINFO_EXTENSION));

            if (!in_array($ext, self::IMAGE_EXTS)) {
                $failedIds[] = $row['id'];
                continue;
            }

            $dir      = ROOT . 'Public' . DS . 'uploads' . DS . 'media' . DS . $year . DS . $month . DS;
            $fullPath = $dir . $stored;

            if (!file_exists($fullPath)) {
                $failedIds[] = $row['id'];
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
            } else {
                $failedIds[] = $row['id'];
            }
        }

        // Files that can't be thumbnailed: set thumbnail_path = filename so they
        // exit the pending queue and fall back to displaying the original image.
        foreach ($failedIds as $failedId) {
            if (isset($filenameById[$failedId])) {
                $this->db('media_files')->where('id', $failedId)->update([
                    'thumbnail_path' => $filenameById[$failedId],
                ]);
            }
        }

        $remaining = (int) ($this->db('media_files')
            ->whereRaw("mime_type LIKE 'image/%'")
            ->whereRaw("(thumbnail_path IS NULL OR thumbnail_path = :emp OR thumbnail_path = filename)", [':emp' => ''])
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

    // ── Bulk ───────────────────────────────────────────────────────────────────

    public function bulk(): void
    {
        // "move" only needs edit rights; delete keeps requiring media.delete below
        $action = $this->input->post('bulk_action') ?? '';
        $this->requirePermission($action === 'move' ? 'media.edit' : 'media.delete');
        $this->validateCsrf();

        $ids = array_filter(array_map('trim', (array) ($this->input->post('ids', false) ?? [])));

        if (empty($ids)) {
            $this->json(['success' => false, 'message' => 'No files selected.']);
        }

        $count        = count($ids);
        $placeholders = implode(',', array_fill(0, $count, '?'));

        if ($action === 'move') {
            $folderId = trim($this->input->post('folder_id', false) ?? '');
            if ($folderId !== '' && $folderId !== 'unfiled') {
                $folderRow = $this->db('media_folders')->where('id', $folderId)->whereNull('deleted_at')->get(1);
                if (!$folderRow) {
                    $this->json(['success' => false, 'message' => 'Target folder not found.']);
                }
            }

            $this->db('media_files')
                ->whereRaw("id IN ({$placeholders})", array_values($ids))
                ->update(['folder_id' => ($folderId === '' || $folderId === 'unfiled') ? null : $folderId]);

            Auth::audit('media.bulk_move', 'media_files', '', ['count' => $count, 'folder' => $folderId]);
            $this->json(['success' => true, 'message' => "{$count} file(s) moved."]);
        }

        if ($action === 'delete') {
            $files = $this->db('media_files')
                ->select('id, filename, thumbnail_path')
                ->whereRaw("id IN ({$placeholders})", array_values($ids))
                ->get() ?: [];

            $base = ROOT . 'Public' . DS . 'uploads' . DS . 'media' . DS;
            foreach ($files as $file) {
                foreach (['filename', 'thumbnail_path'] as $col) {
                    if (!empty($file[$col])) {
                        $path = $base . str_replace('/', DS, $file[$col]);
                        if (file_exists($path)) {
                            @unlink($path);
                        }
                    }
                }
            }

            $this->db('media_files')
                ->whereRaw("id IN ({$placeholders})", array_values($ids))
                ->delete();

            Auth::audit('media.bulk_delete', 'media_files', '', ['count' => $count]);
            $this->json(['success' => true, 'message' => "{$count} file(s) deleted."]);
        }

        $this->json(['success' => false, 'message' => 'Unknown bulk action.']);
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

    // ── Image editor (v0.0.2) ──────────────────────────────────────────────────

    /**
     * POST /admin/media/{id}/edit-image - AJAX
     * Body: ops = JSON [{op:"rotate",deg:90|-90} | {op:"flip",dir:"h"|"v"} |
     *                   {op:"crop",x,y,w,h}]  (crop coords in ORIGINAL pixels)
     *       mode = "copy" (default) | "overwrite"
     */
    public function editImage(string $id): void
    {
        $this->requirePermission('media.edit');
        $this->validateCsrf();

        $file = $this->db('media_files')->where('id', $id)->get(1);
        if (!$file) {
            $this->json(['success' => false, 'message' => 'File not found.'], 404);
        }

        $ext = strtolower(pathinfo((string) $file['filename'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::IMAGE_EXTS, true)) {
            $this->json(['success' => false, 'message' => 'Only JPG, PNG, and WebP images can be edited.']);
        }

        $ops = json_decode($this->input->post('ops', false) ?? '[]', true);
        if (!is_array($ops) || $ops === []) {
            $this->json(['success' => false, 'message' => 'No edit operations provided.']);
        }
        if (count($ops) > 20) {
            $this->json(['success' => false, 'message' => 'Too many operations.']);
        }

        $base     = ROOT . 'Public' . DS . 'uploads' . DS . 'media' . DS;
        $srcPath  = $base . str_replace('/', DS, (string) $file['filename']);
        $img      = $this->loadGdImage($srcPath, $ext);
        if (!$img) {
            $this->json(['success' => false, 'message' => 'Could not open the source image.']);
        }

        foreach ($ops as $op) {
            if (!is_array($op)) continue;
            switch ($op['op'] ?? '') {
                case 'rotate':
                    $deg = (int) ($op['deg'] ?? 0);
                    if (!in_array($deg, [90, -90, 180], true)) break;
                    // GD rotates counter-clockwise for positive angles
                    $rotated = imagerotate($img, -$deg, 0);
                    if ($rotated !== false) {
                        imagedestroy($img);
                        $img = $rotated;
                        if ($ext === 'png') {
                            imagealphablending($img, true);
                            imagesavealpha($img, true);
                        }
                    }
                    break;

                case 'flip':
                    imageflip($img, ($op['dir'] ?? 'h') === 'v' ? IMG_FLIP_VERTICAL : IMG_FLIP_HORIZONTAL);
                    break;

                case 'crop':
                    $w = imagesx($img);
                    $h = imagesy($img);
                    $cx = max(0, min($w - 1, (int) ($op['x'] ?? 0)));
                    $cy = max(0, min($h - 1, (int) ($op['y'] ?? 0)));
                    $cw = max(1, min($w - $cx, (int) ($op['w'] ?? 0)));
                    $ch = max(1, min($h - $cy, (int) ($op['h'] ?? 0)));
                    if ($cw < 10 || $ch < 10) break; // ignore degenerate selections
                    $cropped = imagecrop($img, ['x' => $cx, 'y' => $cy, 'width' => $cw, 'height' => $ch]);
                    if ($cropped !== false) {
                        imagedestroy($img);
                        $img = $cropped;
                        if ($ext === 'png') {
                            imagealphablending($img, true);
                            imagesavealpha($img, true);
                        }
                    }
                    break;
            }
        }

        $mode  = ($this->input->post('mode') ?? 'copy') === 'overwrite' ? 'overwrite' : 'copy';
        $year  = date('Y');
        $month = date('m');

        if ($mode === 'overwrite') {
            $destDir      = dirname($srcPath) . DS;
            $destStored   = basename($srcPath);
            $destFilename = (string) $file['filename'];
            $targetId     = $id;
        } else {
            $destStored   = date('Ymd') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $destDir      = $base . $year . DS . $month . DS;
            $destFilename = $year . '/' . $month . '/' . $destStored;
            if (!is_dir($destDir)) @mkdir($destDir, 0755, true);
            $targetId = null;
        }

        if (!$this->saveGdImage($img, $destDir . $destStored, $ext)) {
            imagedestroy($img);
            $this->json(['success' => false, 'message' => 'Could not save the edited image.']);
        }
        $newW = imagesx($img);
        $newH = imagesy($img);
        imagedestroy($img);

        // Regenerate the thumbnail for the destination
        $this->processUploadedImage($destDir . $destStored, $destDir, $destStored, $newW, $newH);
        $thumbRel = dirname($destFilename) . '/thumb_' . $destStored;

        if ($mode === 'overwrite') {
            $this->db('media_files')->where('id', $id)->update([
                'width'          => $newW,
                'height'         => $newH,
                'size'           => (int) @filesize($destDir . $destStored),
                'thumbnail_path' => $thumbRel,
                'updated_at'     => date('Y-m-d H:i:s'),
                'updated_by'     => $this->currentUser['id'] ?? null,
            ]);
            Auth::audit('media.image_edited', 'media_files', $id, ['mode' => 'overwrite']);
            $this->json(['success' => true, 'message' => 'Image updated.', 'id' => $id]);
        }

        $newId = (string) $this->db('media_files')->save([
            'filename'       => $destFilename,
            'original_name'  => pathinfo((string) $file['original_name'], PATHINFO_FILENAME) . '-edited.' . $ext,
            'mime_type'      => (string) $file['mime_type'],
            'size'           => (int) @filesize($destDir . $destStored),
            'width'          => $newW,
            'height'         => $newH,
            'thumbnail_path' => $thumbRel,
            'folder_id'      => $file['folder_id'] ?? null,
            'created_by'     => $this->currentUser['id'] ?? null,
        ]);

        Auth::audit('media.image_edited', 'media_files', $newId, ['mode' => 'copy', 'source' => $id]);
        $this->json(['success' => true, 'message' => 'Edited copy saved to the library.', 'id' => $newId]);
    }

    // ── Image Processing ───────────────────────────────────────────────────────

    /**
     * Resize original if too wide, then generate a 400×400 cover-crop thumbnail.
     * Uses raw GD functions directly to avoid blackbox failures from the Image wrapper.
     * Returns ['resized' => bool, 'width' => int, 'height' => int] on success, null on failure.
     */
    private function processUploadedImage(string $fullPath, string $dir, string $stored, ?int $width, ?int $height): ?array
    {
        $ext = strtolower(pathinfo($stored, PATHINFO_EXTENSION));

        $src = $this->loadGdImage($fullPath, $ext);
        if (!$src) return null;

        $w       = imagesx($src);
        $h       = imagesy($src);
        $resized = false;

        // Downscale original if wider than MAX_ORIG_WIDTH
        if ($w > self::MAX_ORIG_WIDTH) {
            $newH = (int) round($h * (self::MAX_ORIG_WIDTH / $w));
            $dst  = imagecreatetruecolor(self::MAX_ORIG_WIDTH, $newH);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, self::MAX_ORIG_WIDTH, $newH, $w, $h);
            imagedestroy($src);
            $src     = $dst;
            $w       = self::MAX_ORIG_WIDTH;
            $h       = $newH;
            $this->saveGdImage($src, $fullPath, $ext);
            $resized = true;
        }

        // Cover-crop thumbnail: scale to intermediate size then crop to THUMB_SIZE × THUMB_SIZE
        $sz     = self::THUMB_SIZE;
        $ratio  = max($sz / $w, $sz / $h);
        $interW = (int) ceil($w * $ratio);
        $interH = (int) ceil($h * $ratio);
        $offX   = (int) floor(($interW - $sz) / 2);
        $offY   = (int) floor(($interH - $sz) / 2);

        $inter = imagecreatetruecolor($interW, $interH);
        if ($ext === 'png') {
            imagealphablending($inter, false);
            imagesavealpha($inter, true);
        }
        imagecopyresampled($inter, $src, 0, 0, 0, 0, $interW, $interH, $w, $h);
        imagedestroy($src);

        $thumb = imagecreatetruecolor($sz, $sz);
        if ($ext === 'png') {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
            $transparent = imagecolorallocatealpha($thumb, 0, 0, 0, 127);
            imagefilledrectangle($thumb, 0, 0, $sz, $sz, $transparent);
        } else {
            $white = imagecolorallocate($thumb, 255, 255, 255);
            imagefilledrectangle($thumb, 0, 0, $sz, $sz, $white);
        }
        imagecopy($thumb, $inter, 0, 0, $offX, $offY, $sz, $sz);
        imagedestroy($inter);

        $thumbPath = $dir . 'thumb_' . $stored;
        $saved     = $this->saveGdImage($thumb, $thumbPath, $ext);
        imagedestroy($thumb);

        return ($saved && file_exists($thumbPath))
            ? ['resized' => $resized, 'width' => $w, 'height' => $h]
            : null;
    }

    /** Load a GD image resource from disk. Returns false on any failure. */
    private function loadGdImage(string $path, string $ext): \GdImage|false
    {
        if (!file_exists($path) || !is_readable($path)) return false;

        $src = match($ext) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($path),
            'png'         => @imagecreatefrompng($path),
            'webp'        => @imagecreatefromwebp($path),
            default       => false,
        };

        if (!$src) return false;

        if ($ext === 'png') {
            imagealphablending($src, true);
            imagesavealpha($src, true);
        }

        return $src;
    }

    /** Save a GD image resource to disk in the correct format. */
    private function saveGdImage(\GdImage $img, string $path, string $ext): bool
    {
        return match($ext) {
            'jpg', 'jpeg' => (bool) @imagejpeg($img, $path, 85),
            'png'         => (bool) @imagepng($img, $path, 6),
            'webp'        => (bool) @imagewebp($img, $path, 85),
            default       => false,
        };
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
