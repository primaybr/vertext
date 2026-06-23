<?php

declare(strict_types=1);

namespace App\Modules\Pages\Controllers\Admin;

use App\Controllers\Admin\BaseController;
use App\CMS\Auth;

/**
 * Static pages CRUD.
 *
 * GET  /admin/pages                     → index()
 * GET  /admin/pages/form                → createForm()    (AJAX modal)
 * POST /admin/pages/store               → store()         (AJAX JSON)
 * GET  /admin/pages/{id}/form           → editForm($id)   (AJAX modal)
 * POST /admin/pages/{id}/update         → update($id)     (AJAX JSON)
 * POST /admin/pages/{id}/delete         → delete($id)     (AJAX JSON)
 */
class PagesController extends BaseController
{
    protected string $module = 'pages';

    public function __construct()
    {
        parent::__construct();
    }

    public function index(): void
    {
        $this->requirePermission('pages.view');

        $search  = trim($this->input->get('search') ?? '');
        $page    = max(1, (int) ($this->input->get('page') ?? 1));
        $perPage = 20;
        $offset  = ($page - 1) * $perPage;

        $q  = $this->db('pages')
            ->select('id, title, slug, status, sort_order, created_at')
            ->orderBy('sort_order', 'ASC')
            ->orderBy('created_at', 'DESC')
            ->limitOffset($perPage, $offset);
        $qc = $this->db('pages');

        if ($search) {
            $binds = [':s' => "%{$search}%"];
            $q->whereRaw('title ILIKE :s', $binds);
            $qc->whereRaw('title ILIKE :s', $binds);
        }

        $total = (int) ($qc->totalRows() ?: 0);
        $pages = $q->get() ?: [];

        $this->adminRender('modules/pages/admin/pages/index', [
            'pages'  => $pages,
            'total'  => $total,
            'page'   => $page,
            'pages'  => max(1, (int) ceil($total / $perPage)),
            'search' => $search,
        ], 'Pages', 'pages.pages');
    }

    public function createForm(): void
    {
        $this->requirePermission('pages.create');
        $this->renderPartial('modules/pages/admin/pages/_form', [
            'page'   => null,
            'action' => $this->baseUrl . '/admin/pages/store',
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('pages.create');
        $this->validateCsrf();

        $title  = trim($this->input->post('title', false) ?? '');
        $status = $this->input->post('status') ?? 'draft';

        if (!$title) {
            $this->json(['success' => false, 'message' => 'Title is required.']);
        }

        $rawSlug = trim($this->input->post('slug', false) ?? '');
        $slug    = $rawSlug ? $this->makeSlug($rawSlug) : $this->makeSlug($title);
        $slug    = $this->uniqueSlug($slug);

        if (!in_array($status, ['draft', 'published', 'archived'])) {
            $status = 'draft';
        }

        $id = (string) $this->db('pages')->save([
            'title'            => $title,
            'slug'             => $slug,
            'content'          => trim($this->input->post('content', false) ?? ''),
            'excerpt'          => trim($this->input->post('excerpt', false) ?? ''),
            'status'           => $status,
            'meta_title'       => trim($this->input->post('meta_title', false) ?? ''),
            'meta_description' => trim($this->input->post('meta_description', false) ?? ''),
            'sort_order'       => max(0, (int) ($this->input->post('sort_order') ?? 0)),
            'created_by'       => $this->currentUser['id'],
            'updated_by'       => $this->currentUser['id'],
        ]);

        Auth::audit('page.create', 'pages', $id, ['title' => $title]);
        $this->json(['success' => true, 'message' => "Page \"{$title}\" created."]);
    }

    public function editForm(string $id): void
    {
        $this->requirePermission('pages.edit');
        $page = $this->db('pages')->where('id', $id)->get(1);
        if (!$page) {
            $this->json(['success' => false, 'message' => 'Page not found.'], 404);
        }

        $this->renderPartial('modules/pages/admin/pages/_form', [
            'page'   => $page,
            'action' => $this->baseUrl . "/admin/pages/{$id}/update",
        ]);
    }

    public function update(string $id): void
    {
        $this->requirePermission('pages.edit');
        $this->validateCsrf();

        $title  = trim($this->input->post('title', false) ?? '');
        $status = $this->input->post('status') ?? 'draft';

        if (!$title) {
            $this->json(['success' => false, 'message' => 'Title is required.']);
        }

        $existing = $this->db('pages')->select('slug')->where('id', $id)->get(1);
        $rawSlug  = trim($this->input->post('slug', false) ?? '');
        $newSlug  = $rawSlug ? $this->makeSlug($rawSlug) : null;
        if ($newSlug && $newSlug !== ($existing['slug'] ?? '')) {
            $newSlug = $this->uniqueSlug($newSlug, $id);
        }

        if (!in_array($status, ['draft', 'published', 'archived'])) {
            $status = 'draft';
        }

        $data = [
            'title'            => $title,
            'content'          => trim($this->input->post('content', false) ?? ''),
            'excerpt'          => trim($this->input->post('excerpt', false) ?? ''),
            'status'           => $status,
            'meta_title'       => trim($this->input->post('meta_title', false) ?? ''),
            'meta_description' => trim($this->input->post('meta_description', false) ?? ''),
            'sort_order'       => max(0, (int) ($this->input->post('sort_order') ?? 0)),
            'updated_by'       => $this->currentUser['id'],
            'updated_at'       => date('Y-m-d H:i:s'),
        ];
        if ($newSlug) {
            $data['slug'] = $newSlug;
        }

        $this->db('pages')->where('id', $id)->update($data);

        Auth::audit('page.update', 'pages', $id, ['title' => $title]);
        $this->json(['success' => true, 'message' => 'Page updated.']);
    }

    public function delete(string $id): void
    {
        $this->requirePermission('pages.delete');
        $this->validateCsrf();

        $this->db('pages')->where('id', $id)->delete();

        Auth::audit('page.delete', 'pages', $id);
        $this->json(['success' => true, 'message' => 'Page deleted.']);
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
        $q      = $this->db('pages')->select('id')->where('slug', $slug);
        if ($excludeId) {
            $q->whereRaw('id != ?', [$excludeId]);
        }
        while ($q->get(1)) {
            $slug = $base . '-' . $suffix++;
            $q    = $this->db('pages')->select('id')->where('slug', $slug);
            if ($excludeId) {
                $q->whereRaw('id != ?', [$excludeId]);
            }
        }
        return $slug;
    }
}
