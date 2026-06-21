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
 * GET  /admin/media                   → index()
 * POST /admin/media/upload            → upload()      (AJAX multipart)
 * GET  /admin/media/{id}/edit-form    → editForm($id) (AJAX modal partial)
 * POST /admin/media/{id}/update       → update($id)   (AJAX JSON)
 * POST /admin/media/{id}/delete       → delete($id)   (AJAX JSON)
 */
class MediaController extends BaseController
{
    protected string $module = 'media';

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
        $perPage = 24; // 3×8 or 4×6 grid
        $offset  = ($page - 1) * $perPage;

        $q = $this->db('media_files')
            ->select('id, filename, original_name, mime_type, size, width, height, alt_text, created_at')
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

        // Build public URL for each file
        foreach ($files as &$f) {
            $f['url'] = $this->fileUrl($f['filename']);
        }
        unset($f);

        $this->adminRender('modules/media/admin/media/index', [
            'files'  => $files,
            'total'  => $total,
            'page'   => $page,
            'pages'  => max(1, (int) ceil($total / $perPage)),
            'search' => $search,
        ], 'Media Library', 'media');
    }

    // ── Upload ─────────────────────────────────────────────────────────────────

    public function upload(): void
    {
        $this->requirePermission('media.upload');
        $this->validateCsrf();

        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $this->json(['success' => false, 'message' => 'No file received or upload error.'], 400);
        }

        $file  = $_FILES['file'];
        $ext   = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $year  = date('Y');
        $month = date('m');

        // Generate a unique, safe filename
        $stored = date('Ymd') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $dir    = ROOT . 'Public' . DS . 'uploads' . DS . 'media' . DS . $year . DS . $month . DS;

        $uploader = new Upload();
        $uploader->configure(UploadConfig::forImages());
        $uploader->setDir($dir);
        $uploader->setFileName($stored);

        if (!$uploader->upload($file)) {
            $this->json(['success' => false, 'message' => $uploader->getError()], 422);
        }

        // Read image dimensions
        $fullPath   = $dir . $stored;
        $dimensions = @getimagesize($fullPath);
        $width  = $dimensions ? $dimensions[0] : null;
        $height = $dimensions ? $dimensions[1] : null;

        $storedFilename = $year . '/' . $month . '/' . $stored;

        $id = (int) $this->db('media_files')->save([
            'filename'      => $storedFilename,
            'original_name' => basename($file['name']),
            'mime_type'     => $file['type'],
            'size'          => $file['size'],
            'width'         => $width,
            'height'        => $height,
            'uploaded_by'   => $this->currentUser['id'],
        ]);

        Auth::audit('media.upload', 'media_files', $id, ['original_name' => $file['name']]);

        $this->json([
            'success' => true,
            'message' => 'File uploaded successfully.',
            'file'    => [
                'id'       => $id,
                'url'      => $this->fileUrl($storedFilename),
                'filename' => $storedFilename,
                'name'     => basename($file['name']),
                'width'    => $width,
                'height'   => $height,
            ],
        ]);
    }

    // ── Edit form (modal partial) ──────────────────────────────────────────────

    public function editForm(int $id): void
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

    public function update(int $id): void
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

    public function delete(int $id): void
    {
        $this->requirePermission('media.delete');
        $this->validateCsrf();

        $file = $this->db('media_files')->where('id', $id)->get(1);
        if ($file) {
            $path = ROOT . 'Public' . DS . 'uploads' . DS . 'media' . DS . str_replace('/', DS, $file['filename']);
            if (file_exists($path)) {
                @unlink($path);
            }
            $this->db('media_files')->where('id', $id)->delete();
        }

        Auth::audit('media.delete', 'media_files', $id);
        $this->json(['success' => true, 'message' => 'File deleted.']);
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
