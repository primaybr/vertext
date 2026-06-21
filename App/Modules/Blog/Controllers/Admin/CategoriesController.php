<?php

declare(strict_types=1);

namespace App\Modules\Blog\Controllers\Admin;

use App\Controllers\Admin\BaseController;
use App\CMS\Auth;

/**
 * Blog categories CRUD.
 *
 * GET  /admin/blog/categories
 * GET  /admin/blog/categories/form
 * POST /admin/blog/categories/store
 * GET  /admin/blog/categories/{id}/form
 * POST /admin/blog/categories/{id}/update
 * POST /admin/blog/categories/{id}/delete
 */
class CategoriesController extends BaseController
{
    protected string $module = 'blog';

    public function __construct()
    {
        parent::__construct();
    }

    public function index(): void
    {
        $this->requirePermission('categories.view');

        $search  = trim($this->input->get('search') ?? '');
        $page    = max(1, (int) ($this->input->get('page') ?? 1));
        $perPage = 20;
        $offset  = ($page - 1) * $perPage;

        $q  = $this->db('post_categories')
            ->select('id, name, slug, created_at')
            ->orderBy('name', 'ASC')
            ->limitOffset($perPage, $offset);
        $qc = $this->db('post_categories');

        if ($search) {
            $binds = [':s' => "%{$search}%"];
            $q->whereRaw('name ILIKE :s', $binds);
            $qc->whereRaw('name ILIKE :s', $binds);
        }

        $total      = (int) ($qc->totalRows() ?: 0);
        $categories = $q->get() ?: [];

        // Attach post count per category
        foreach ($categories as &$cat) {
            $cat['post_count'] = (int) ($this->db('post_category_pivot')
                ->where('category_id', $cat['id'])
                ->totalRows() ?: 0);
        }
        unset($cat);

        $this->adminRender('modules/blog/admin/categories/index', [
            'categories' => $categories,
            'total'      => $total,
            'page'       => $page,
            'pages'      => max(1, (int) ceil($total / $perPage)),
            'search'     => $search,
        ], 'Categories', 'blog.categories');
    }

    public function createForm(): void
    {
        $this->requirePermission('categories.create');
        $this->renderPartial('modules/blog/admin/categories/_form', [
            'category' => null,
            'action'   => $this->baseUrl . '/admin/blog/categories/store',
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('categories.create');
        $this->validateCsrf();

        $name = trim($this->input->post('name', false) ?? '');
        $desc = trim($this->input->post('description', false) ?? '');

        if (!$name) {
            $this->json(['success' => false, 'message' => 'Category name is required.']);
        }

        $slug = $this->makeSlug($name);
        if ((int) ($this->db('post_categories')->where('slug', $slug)->totalRows() ?: 0) > 0) {
            $slug .= '-' . time();
        }

        $id = (int) $this->db('post_categories')->save([
            'name'        => $name,
            'slug'        => $slug,
            'description' => $desc,
        ]);

        Auth::audit('category.create', 'post_categories', $id, ['name' => $name]);
        $this->json(['success' => true, 'message' => "Category \"{$name}\" created."]);
    }

    public function editForm(int $id): void
    {
        $this->requirePermission('categories.edit');
        $category = $this->db('post_categories')->where('id', $id)->get(1);
        if (!$category) {
            $this->json(['success' => false, 'message' => 'Category not found.'], 404);
        }

        $this->renderPartial('modules/blog/admin/categories/_form', [
            'category' => $category,
            'action'   => $this->baseUrl . "/admin/blog/categories/{$id}/update",
        ]);
    }

    public function update(int $id): void
    {
        $this->requirePermission('categories.edit');
        $this->validateCsrf();

        $name = trim($this->input->post('name', false) ?? '');
        $desc = trim($this->input->post('description', false) ?? '');

        if (!$name) {
            $this->json(['success' => false, 'message' => 'Category name is required.']);
        }

        $this->db('post_categories')->where('id', $id)->update([
            'name'        => $name,
            'description' => $desc,
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        Auth::audit('category.update', 'post_categories', $id, ['name' => $name]);
        $this->json(['success' => true, 'message' => 'Category updated.']);
    }

    public function delete(int $id): void
    {
        $this->requirePermission('categories.delete');
        $this->validateCsrf();

        $this->db('post_category_pivot')->where('category_id', $id)->delete();
        $this->db('post_categories')->where('id', $id)->delete();

        Auth::audit('category.delete', 'post_categories', $id);
        $this->json(['success' => true, 'message' => 'Category deleted.']);
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
