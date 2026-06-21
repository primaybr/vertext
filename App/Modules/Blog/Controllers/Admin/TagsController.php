<?php

declare(strict_types=1);

namespace App\Modules\Blog\Controllers\Admin;

use App\Controllers\Admin\BaseController;
use App\CMS\Auth;

/**
 * Blog tags CRUD + autocomplete search for vtx-tags component.
 *
 * GET  /admin/blog/tags
 * GET  /admin/blog/tags/form
 * POST /admin/blog/tags/store
 * GET  /admin/blog/tags/{id}/form
 * POST /admin/blog/tags/{id}/update
 * POST /admin/blog/tags/{id}/delete
 * GET  /admin/blog/tags/search   (returns JSON for vtx-tags autocomplete)
 */
class TagsController extends BaseController
{
    protected string $module = 'blog';

    public function __construct()
    {
        parent::__construct();
    }

    public function index(): void
    {
        $this->requirePermission('tags.view');

        $search  = trim($this->input->get('search') ?? '');
        $page    = max(1, (int) ($this->input->get('page') ?? 1));
        $perPage = 20;
        $offset  = ($page - 1) * $perPage;

        $q  = $this->db('post_tags')
            ->select('id, name, slug, created_at')
            ->orderBy('name', 'ASC')
            ->limitOffset($perPage, $offset);
        $qc = $this->db('post_tags');

        if ($search) {
            $binds = [':s' => "%{$search}%"];
            $q->whereRaw('name ILIKE :s', $binds);
            $qc->whereRaw('name ILIKE :s', $binds);
        }

        $total = (int) ($qc->totalRows() ?: 0);
        $tags  = $q->get() ?: [];

        foreach ($tags as &$tag) {
            $tag['post_count'] = (int) ($this->db('post_tag_pivot')
                ->where('tag_id', $tag['id'])
                ->totalRows() ?: 0);
        }
        unset($tag);

        $this->adminRender('modules/blog/admin/tags/index', [
            'tags'   => $tags,
            'total'  => $total,
            'page'   => $page,
            'pages'  => max(1, (int) ceil($total / $perPage)),
            'search' => $search,
        ], 'Tags', 'blog.tags');
    }

    /** JSON autocomplete for vtx-tags component. GET /admin/blog/tags/search?q=foo */
    public function search(): void
    {
        $this->requirePermission('tags.view');

        $q    = trim($this->input->get('q') ?? '');
        $rows = $this->db('post_tags')
            ->select('id, name')
            ->whereRaw('name ILIKE :s', [':s' => "%{$q}%"])
            ->orderBy('name', 'ASC')
            ->limitOffset(15, 0)
            ->get() ?: [];

        $this->json(array_values($rows));
    }

    public function createForm(): void
    {
        $this->requirePermission('tags.create');
        $this->renderPartial('modules/blog/admin/tags/_form', [
            'tag'    => null,
            'action' => $this->baseUrl . '/admin/blog/tags/store',
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('tags.create');
        $this->validateCsrf();

        $name = trim($this->input->post('name', false) ?? '');
        if (!$name) {
            $this->json(['success' => false, 'message' => 'Tag name is required.']);
        }

        $slug = $this->makeSlug($name);
        if ((int) ($this->db('post_tags')->where('slug', $slug)->totalRows() ?: 0) > 0) {
            $slug .= '-' . time();
        }

        $id = (int) $this->db('post_tags')->save(['name' => $name, 'slug' => $slug]);

        Auth::audit('tag.create', 'post_tags', $id, ['name' => $name]);
        $this->json(['success' => true, 'message' => "Tag \"{$name}\" created."]);
    }

    public function editForm(int $id): void
    {
        $this->requirePermission('tags.edit');
        $tag = $this->db('post_tags')->where('id', $id)->get(1);
        if (!$tag) {
            $this->json(['success' => false, 'message' => 'Tag not found.'], 404);
        }

        $this->renderPartial('modules/blog/admin/tags/_form', [
            'tag'    => $tag,
            'action' => $this->baseUrl . "/admin/blog/tags/{$id}/update",
        ]);
    }

    public function update(int $id): void
    {
        $this->requirePermission('tags.edit');
        $this->validateCsrf();

        $name = trim($this->input->post('name', false) ?? '');
        if (!$name) {
            $this->json(['success' => false, 'message' => 'Tag name is required.']);
        }

        $this->db('post_tags')->where('id', $id)->update(['name' => $name]);

        Auth::audit('tag.update', 'post_tags', $id, ['name' => $name]);
        $this->json(['success' => true, 'message' => 'Tag updated.']);
    }

    public function delete(int $id): void
    {
        $this->requirePermission('tags.delete');
        $this->validateCsrf();

        $this->db('post_tag_pivot')->where('tag_id', $id)->delete();
        $this->db('post_tags')->where('id', $id)->delete();

        Auth::audit('tag.delete', 'post_tags', $id);
        $this->json(['success' => true, 'message' => 'Tag deleted.']);
    }

    private function validateCsrf(): void
    {
        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->json(['success' => false, 'message' => 'Security token invalid.'], 403);
        }
    }

    private function makeSlug(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9\s\-]/', '', $slug);
        $slug = preg_replace('/[\s\-]+/', '-', $slug);
        return trim($slug, '-');
    }
}
