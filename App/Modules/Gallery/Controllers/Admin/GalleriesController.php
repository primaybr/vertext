<?php

declare(strict_types=1);

namespace App\Modules\Gallery\Controllers\Admin;

use App\Controllers\Admin\BaseController;
use App\CMS\Auth;

/**
 * Gallery album CRUD.
 *
 * GET  /admin/gallery               → index()
 * GET  /admin/gallery/form          → createForm()
 * POST /admin/gallery/store         → store()
 * GET  /admin/gallery/{id}/form     → editForm($id)
 * POST /admin/gallery/{id}/update   → update($id)
 * POST /admin/gallery/{id}/delete   → delete($id)
 */
class GalleriesController extends BaseController
{
    protected string $module = 'gallery';

    public function __construct()
    {
        parent::__construct();
    }

    public function index(): void
    {
        $this->requirePermission('gallery.view');

        $page    = max(1, (int) ($this->input->get('page') ?? 1));
        $perPage = 20;
        $offset  = ($page - 1) * $perPage;

        $galleries = $this->db('galleries')
            ->select('galleries.id, galleries.title, galleries.slug, galleries.status,
                      galleries.created_at, galleries.cover_image_id,
                      media_files.filename AS cover_filename,
                      media_files.thumbnail_path AS cover_thumb')
            ->join('media_files', 'media_files.id = galleries.cover_image_id', 'LEFT')
            ->whereNull('galleries.deleted_at')
            ->orderBy('galleries.created_at', 'DESC')
            ->limitOffset($perPage, $offset)
            ->get() ?: [];

        $total = (int) ($this->db('galleries')->whereNull('deleted_at')->totalRows() ?: 0);

        foreach ($galleries as &$g) {
            $itemCount        = (int) ($this->db('gallery_items')->where('gallery_id', $g['id'])->whereNull('deleted_at')->totalRows() ?: 0);
            $g['item_count']  = $itemCount;
            $g['cover_url']   = $g['cover_filename']
                ? $this->baseUrl . '/uploads/media/' . ($g['cover_thumb'] ?: $g['cover_filename'])
                : '';
        }
        unset($g);

        $this->adminRender('modules/gallery/admin/galleries/index', [
            'galleries' => $galleries,
            'total'     => $total,
            'page'      => $page,
            'pages'     => max(1, (int) ceil($total / $perPage)),
        ], 'Gallery', 'gallery');
    }

    public function createForm(): void
    {
        $this->requirePermission('gallery.create');
        $this->renderPartial('modules/gallery/admin/galleries/_form', [
            'gallery'      => null,
            'action'       => $this->baseUrl . '/admin/gallery/store',
            'mediaEnabled' => true,
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('gallery.create');
        $this->validateCsrf();

        $title = trim($this->input->post('title', false) ?? '');
        if (!$title) {
            $this->json(['success' => false, 'message' => 'Title is required.']);
        }

        $rawSlug = trim($this->input->post('slug', false) ?? '');
        $slug    = $rawSlug ? $this->makeSlug($rawSlug) : $this->makeSlug($title);
        $slug    = $this->uniqueSlug($slug);

        $status  = $this->input->post('status') ?? 'draft';
        if (!in_array($status, ['draft', 'published', 'archived'])) {
            $status = 'draft';
        }

        $coverId = trim($this->input->post('cover_image_id', false) ?? '') ?: null;

        $id = (string) $this->db('galleries')->save([
            'title'            => $title,
            'slug'             => $slug,
            'description'      => trim($this->input->post('description', false) ?? ''),
            'cover_image_id'   => $coverId,
            'status'           => $status,
            'meta_title'       => trim($this->input->post('meta_title', false) ?? ''),
            'meta_description' => trim($this->input->post('meta_description', false) ?? ''),
            'created_by'       => $this->currentUser['id'],
            'updated_by'       => $this->currentUser['id'],
        ]);

        Auth::audit('gallery.create', 'galleries', $id, ['title' => $title]);
        $this->json(['success' => true, 'message' => "Album \"{$title}\" created.",
                     'redirect' => $this->baseUrl . "/admin/gallery/{$id}/items"]);
    }

    public function editForm(string $id): void
    {
        $this->requirePermission('gallery.edit');
        $gallery = $this->db('galleries')->where('id', $id)->whereNull('deleted_at')->get(1);
        if (!$gallery) {
            $this->json(['success' => false, 'message' => 'Album not found.'], 404);
        }

        if (!empty($gallery['cover_image_id'])) {
            $mf = $this->db('media_files')
                ->select('filename, thumbnail_path')
                ->where('id', $gallery['cover_image_id'])->get(1);
            $gallery['cover_url'] = $mf
                ? $this->baseUrl . '/uploads/media/' . ($mf['thumbnail_path'] ?: $mf['filename'])
                : '';
        }

        $this->renderPartial('modules/gallery/admin/galleries/_form', [
            'gallery'      => $gallery,
            'action'       => $this->baseUrl . "/admin/gallery/{$id}/update",
            'mediaEnabled' => true,
        ]);
    }

    public function update(string $id): void
    {
        $this->requirePermission('gallery.edit');
        $this->validateCsrf();

        $title = trim($this->input->post('title', false) ?? '');
        if (!$title) {
            $this->json(['success' => false, 'message' => 'Title is required.']);
        }

        $existing = $this->db('galleries')->select('slug')->where('id', $id)->whereNull('deleted_at')->get(1);
        $rawSlug  = trim($this->input->post('slug', false) ?? '');
        $newSlug  = $rawSlug ? $this->makeSlug($rawSlug) : null;
        if ($newSlug && $newSlug !== ($existing['slug'] ?? '')) {
            $newSlug = $this->uniqueSlug($newSlug, $id);
        }

        $status = $this->input->post('status') ?? 'draft';
        if (!in_array($status, ['draft', 'published', 'archived'])) {
            $status = 'draft';
        }

        $coverId = trim($this->input->post('cover_image_id', false) ?? '') ?: null;

        $data = [
            'title'            => $title,
            'description'      => trim($this->input->post('description', false) ?? ''),
            'cover_image_id'   => $coverId,
            'status'           => $status,
            'meta_title'       => trim($this->input->post('meta_title', false) ?? ''),
            'meta_description' => trim($this->input->post('meta_description', false) ?? ''),
            'updated_by'       => $this->currentUser['id'],
            'updated_at'       => date('Y-m-d H:i:s'),
        ];
        if ($newSlug) {
            $data['slug'] = $newSlug;
        }

        $this->db('galleries')->where('id', $id)->update($data);

        Auth::audit('gallery.update', 'galleries', $id, ['title' => $title]);
        $this->json(['success' => true, 'message' => 'Album updated.']);
    }

    public function delete(string $id): void
    {
        $this->requirePermission('gallery.delete');
        $this->validateCsrf();

        $now    = date('Y-m-d H:i:s');
        $userId = $this->currentUser['id'];
        $this->db('gallery_items')->where('gallery_id', $id)->whereNull('deleted_at')->update([
            'deleted_at' => $now,
            'deleted_by' => $userId,
        ]);
        $this->db('galleries')->where('id', $id)->whereNull('deleted_at')->update([
            'deleted_at' => $now,
            'deleted_by' => $userId,
        ]);

        Auth::audit('gallery.delete', 'galleries', $id);
        $this->json(['success' => true, 'message' => 'Album deleted.']);
    }

    private function validateCsrf(): void
    {
        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->json(['success' => false, 'message' => 'Security token invalid.'], 403);
        }
    }

    private function makeSlug(string $text): string
    {
        return \Core\Utilities\Text\Str::slug($text);
    }

    private function uniqueSlug(string $base, string $excludeId = ''): string
    {
        $slug   = $base;
        $suffix = 2;
        $q      = $this->db('galleries')->select('id')->where('slug', $slug);
        if ($excludeId) {
            $q->whereRaw('id != ?', [$excludeId]);
        }
        while ($q->get(1)) {
            $slug = $base . '-' . $suffix++;
            $q    = $this->db('galleries')->select('id')->where('slug', $slug);
            if ($excludeId) {
                $q->whereRaw('id != ?', [$excludeId]);
            }
        }
        return $slug;
    }
}
