<?php

declare(strict_types=1);

namespace App\Modules\Blog\Controllers\Admin;

use App\Controllers\Admin\BaseController;
use App\CMS\Auth;
use App\CMS\ModuleLoader;

/**
 * Blog Posts CRUD (enhanced v2).
 *
 * List:        GET  /admin/blog/posts
 * Create form: GET  /admin/blog/posts/form           (AJAX → modal partial)
 * Store:       POST /admin/blog/posts/store           (AJAX → JSON)
 * Edit form:   GET  /admin/blog/posts/{id}/form       (AJAX → modal partial)
 * Update:      POST /admin/blog/posts/{id}/update     (AJAX → JSON)
 * Delete:      POST /admin/blog/posts/{id}/delete     (AJAX → JSON)
 * Bulk:        POST /admin/blog/posts/bulk            (AJAX → JSON)
 */
class PostsController extends BaseController
{
    protected string $module = 'blog';

    public function __construct()
    {
        parent::__construct();
    }

    // ── List ───────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requirePermission('posts.view');

        $search  = trim($this->input->get('search') ?? '');
        $status  = $this->input->get('status') ?? '';
        $page    = max(1, (int) ($this->input->get('page') ?? 1));
        $perPage = 15;
        $offset  = ($page - 1) * $perPage;

        $q  = $this->db('posts')
            ->select('posts.id, posts.title, posts.slug, posts.status, posts.reading_time,
                      posts.published_at, posts.created_at, users.name AS author_name')
            ->join('users', 'users.id = posts.author_id', 'LEFT')
            ->whereNull('posts.deleted_at')
            ->orderBy('posts.created_at', 'DESC')
            ->limitOffset($perPage, $offset);

        $qc = $this->db('posts')->whereNull('deleted_at');

        // Status filter counts (for tabs)
        $counts = [];
        foreach (['published', 'draft', 'archived'] as $s) {
            $counts[$s] = (int) ($this->db('posts')->where('status', $s)->whereNull('deleted_at')->totalRows() ?: 0);
        }

        if ($status && in_array($status, ['published', 'draft', 'archived'], true)) {
            $q->where('posts.status', $status);
            $qc->where('status', $status);
        }

        if ($search) {
            $binds = [':s1' => "%{$search}%", ':s2' => "%{$search}%"];
            $q->whereRaw('(posts.title ILIKE :s1 OR posts.status ILIKE :s2)', $binds);
            $qc->whereRaw('(posts.title ILIKE :s1 OR posts.status ILIKE :s2)', $binds);
        }

        $total = (int) ($qc->totalRows() ?: 0);
        $posts = $q->get() ?: [];

        $this->adminRender('modules/blog/admin/posts/index', [
            'posts'   => $posts,
            'total'   => $total,
            'page'    => $page,
            'pages'   => max(1, (int) ceil($total / $perPage)),
            'search'  => $search,
            'status'  => $status,
            'counts'  => $counts,
        ], 'Blog Posts', 'blog.posts');
    }

    // ── Create form (modal partial) ────────────────────────────────────────────

    public function createForm(): void
    {
        $this->requirePermission('posts.create');

        $categories = $this->db('post_categories')->select('id, name')->orderBy('name')->get() ?: [];

        $blogBase = $this->db('settings')->where('key', 'blog_base_path')->get(1)['value'] ?? 'blog';

        $this->renderPartial('modules/blog/admin/posts/_form', [
            'post'          => null,
            'action'        => $this->baseUrl . '/admin/blog/posts/store',
            'categories'    => $categories,
            'postCatIds'    => [],
            'postTagNames'  => '',
            'mediaEnabled'  => ModuleLoader::isEnabled('media'),
            'blogBase'      => $blogBase,
        ]);
    }

    // ── Store ──────────────────────────────────────────────────────────────────

    public function store(): void
    {
        $this->requirePermission('posts.create');
        $this->validateCsrf();

        $title           = trim($this->input->post('title', false) ?? '');
        $body            = $this->input->post('body', false) ?? '';
        $excerpt         = trim($this->input->post('excerpt', false) ?? '');
        $status          = $this->input->post('status') ?? 'draft';
        $metaTitle       = trim($this->input->post('meta_title', false) ?? '');
        $metaDesc        = trim($this->input->post('meta_description', false) ?? '');
        $scheduledAt     = trim($this->input->post('published_at', false) ?? '');
        $readingTime     = max(0, (int) ($this->input->post('reading_time', false) ?? 0));
        $featuredImgId   = trim((string) ($this->input->post('featured_image_id', false) ?? '')) ?: null;
        $featuredImgUrl  = trim($this->input->post('featured_image_url', false) ?? '');
        $categoryIds     = array_filter((array) ($this->input->post('category_ids', false) ?? []));
        $tagNames        = trim($this->input->post('tag_names', false) ?? '');

        if (!$title) {
            $this->json(['success' => false, 'message' => 'Post title is required.']);
        }

        if (!in_array($status, ['draft', 'published', 'archived'], true)) {
            $status = 'draft';
        }

        // Sanitize body HTML (allow safe HTML tags from Quill)
        $body = $this->sanitizeHtml($body);

        $rawSlug = trim($this->input->post('slug', false) ?? '');
        $slug    = $rawSlug ? $this->makeSlug($rawSlug) : $this->makeSlug($title);
        $slug    = $this->uniqueSlug('posts', $slug);

        $publishedAt = null;
        if ($status === 'published') {
            $publishedAt = $scheduledAt ? date('Y-m-d H:i:s', strtotime($scheduledAt)) : date('Y-m-d H:i:s');
        }

        $postId = (string) $this->db('posts')->save([
            'title'              => $title,
            'slug'               => $slug,
            'body'               => $body,
            'excerpt'            => $excerpt,
            'status'             => $status,
            'author_id'          => $this->currentUser['id'],
            'published_at'       => $publishedAt,
            'featured_image_id'  => $featuredImgId,
            'featured_image_url' => $featuredImgUrl ?: null,
            'meta_title'         => $metaTitle ?: null,
            'meta_description'   => $metaDesc ?: null,
            'reading_time'       => $readingTime,
        ]);

        $this->syncCategories($postId, $categoryIds);
        $this->syncTags($postId, $tagNames);

        Auth::audit('post.create', 'posts', $postId, ['title' => $title, 'status' => $status]);
        $this->json(['success' => true, 'message' => "Post \"{$title}\" created."]);
    }

    // ── Edit form (modal partial) ──────────────────────────────────────────────

    public function editForm(string $id): void
    {
        $this->requirePermission('posts.edit');

        $post = $this->db('posts')->where('id', $id)->whereNull('deleted_at')->get(1);
        if (!$post) {
            $this->json(['success' => false, 'message' => 'Post not found.'], 404);
        }

        $categories = $this->db('post_categories')->select('id, name')->orderBy('name')->get() ?: [];

        $postCatIds = array_column(
            ($this->db('post_category_pivot')->select('category_id')->where('post_id', $id)->get() ?: []),
            'category_id'
        );

        $tagRows = $this->db('post_tags')
            ->select('post_tags.name')
            ->join('post_tag_pivot', 'post_tag_pivot.tag_id = post_tags.id', 'INNER')
            ->where('post_tag_pivot.post_id', $id)
            ->orderBy('post_tags.name')
            ->get() ?: [];

        $postTagNames = implode(', ', array_column($tagRows, 'name'));

        // Resolve featured image URL
        if (empty($post['featured_image_url']) && !empty($post['featured_image_id'])) {
            $img = $this->db('media_files')->select('filename')->where('id', $post['featured_image_id'])->get(1);
            if ($img) {
                $post['featured_image_url'] = $this->baseUrl . '/uploads/media/' . $img['filename'];
            }
        }

        $blogBase = $this->db('settings')->where('key', 'blog_base_path')->get(1)['value'] ?? 'blog';

        $this->renderPartial('modules/blog/admin/posts/_form', [
            'post'          => $post,
            'action'        => $this->baseUrl . "/admin/blog/posts/{$id}/update",
            'categories'    => $categories,
            'postCatIds'    => $postCatIds,
            'postTagNames'  => $postTagNames,
            'mediaEnabled'  => ModuleLoader::isEnabled('media'),
            'blogBase'      => $blogBase,
        ]);
    }

    // ── Update ─────────────────────────────────────────────────────────────────

    public function update(string $id): void
    {
        $this->requirePermission('posts.edit');
        $this->validateCsrf();

        $existing = $this->db('posts')->where('id', $id)->whereNull('deleted_at')->get(1);
        if (!$existing) {
            $this->json(['success' => false, 'message' => 'Post not found.'], 404);
        }

        $title           = trim($this->input->post('title', false) ?? '');
        $body            = $this->input->post('body', false) ?? '';
        $excerpt         = trim($this->input->post('excerpt', false) ?? '');
        $status          = $this->input->post('status') ?? 'draft';
        $metaTitle       = trim($this->input->post('meta_title', false) ?? '');
        $metaDesc        = trim($this->input->post('meta_description', false) ?? '');
        $scheduledAt     = trim($this->input->post('published_at', false) ?? '');
        $readingTime     = max(0, (int) ($this->input->post('reading_time', false) ?? 0));
        $featuredImgId   = trim((string) ($this->input->post('featured_image_id', false) ?? '')) ?: null;
        $featuredImgUrl  = trim($this->input->post('featured_image_url', false) ?? '');
        $categoryIds     = array_filter((array) ($this->input->post('category_ids', false) ?? []));
        $tagNames        = trim($this->input->post('tag_names', false) ?? '');

        if (!$title) {
            $this->json(['success' => false, 'message' => 'Post title is required.']);
        }

        if (!in_array($status, ['draft', 'published', 'archived'], true)) {
            $status = 'draft';
        }

        $body = $this->sanitizeHtml($body);

        $rawSlug = trim($this->input->post('slug', false) ?? '');
        $newSlug = $rawSlug ? $this->makeSlug($rawSlug) : null;
        if ($newSlug && $newSlug !== ($existing['slug'] ?? '')) {
            $newSlug = $this->uniqueSlug('posts', $newSlug, $id);
        }

        $data = [
            'title'              => $title,
            'body'               => $body,
            'excerpt'            => $excerpt,
            'status'             => $status,
            'meta_title'         => $metaTitle ?: null,
            'meta_description'   => $metaDesc ?: null,
            'reading_time'       => $readingTime,
            'featured_image_id'  => $featuredImgId,
            'featured_image_url' => $featuredImgUrl ?: null,
            'updated_at'         => date('Y-m-d H:i:s'),
        ];
        if ($newSlug) {
            $data['slug'] = $newSlug;
        }

        if ($status === 'published') {
            if (empty($existing['published_at'])) {
                $data['published_at'] = $scheduledAt
                    ? date('Y-m-d H:i:s', strtotime($scheduledAt))
                    : date('Y-m-d H:i:s');
            } elseif ($scheduledAt) {
                $data['published_at'] = date('Y-m-d H:i:s', strtotime($scheduledAt));
            }
        }

        $this->db('posts')->where('id', $id)->update($data);
        $this->syncCategories($id, $categoryIds);
        $this->syncTags($id, $tagNames);

        Auth::audit('post.update', 'posts', $id, ['status' => $status]);
        $this->json(['success' => true, 'message' => 'Post updated successfully.']);
    }

    // ── Delete ─────────────────────────────────────────────────────────────────

    public function delete(string $id): void
    {
        $this->requirePermission('posts.delete');
        $this->validateCsrf();

        $this->db('posts')->where('id', $id)->whereNull('deleted_at')->update(['deleted_at' => date('Y-m-d H:i:s')]);

        Auth::audit('post.delete', 'posts', $id);
        $this->json(['success' => true, 'message' => 'Post moved to trash.']);
    }

    // ── Bulk ───────────────────────────────────────────────────────────────────

    public function bulk(): void
    {
        $this->requirePermission('posts.delete');
        $this->validateCsrf();

        $action = $this->input->post('bulk_action') ?? '';
        $ids    = array_filter((array) ($this->input->post('ids', false) ?? []));

        if (empty($ids)) {
            $this->json(['success' => false, 'message' => 'No posts selected.']);
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        match ($action) {
            'publish' => $this->db('posts')
                ->whereRaw("id IN ({$placeholders})", array_values($ids))
                ->whereNull('deleted_at')
                ->update(['status' => 'published', 'published_at' => date('Y-m-d H:i:s')]),
            'draft'   => $this->db('posts')
                ->whereRaw("id IN ({$placeholders})", array_values($ids))
                ->whereNull('deleted_at')
                ->update(['status' => 'draft']),
            'delete'  => $this->db('posts')
                ->whereRaw("id IN ({$placeholders})", array_values($ids))
                ->update(['deleted_at' => date('Y-m-d H:i:s')]),
            default   => null,
        };

        Auth::audit('posts.bulk', 'posts', '', ['action' => $action, 'count' => count($ids)]);
        $this->json(['success' => true, 'message' => 'Bulk action applied.']);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function syncCategories(string $postId, array $categoryIds): void
    {
        $this->db('post_category_pivot')->where('post_id', $postId)->delete();
        foreach (array_unique($categoryIds) as $catId) {
            if ($catId) {
                $this->db('post_category_pivot')
                    ->withoutTimestamps()
                    ->setPrimaryKey('post_id')
                    ->save(['post_id' => $postId, 'category_id' => (string) $catId]);
            }
        }
    }

    private function syncTags(string $postId, string $tagNames): void
    {
        $this->db('post_tag_pivot')->where('post_id', $postId)->delete();

        if (!trim($tagNames)) {
            return;
        }

        $names = array_unique(array_filter(array_map('trim', explode(',', $tagNames))));
        foreach ($names as $name) {
            if (!$name) {
                continue;
            }
            $slug = strtolower(preg_replace('/[^a-z0-9\-]/i', '-', $name));
            $slug = preg_replace('/-+/', '-', trim($slug, '-'));

            $existing = $this->db('post_tags')->where('slug', $slug)->get(1);
            if ($existing) {
                $tagId = (string) $existing['id'];
            } else {
                $tagId = (string) $this->db('post_tags')->save(['name' => $name, 'slug' => $slug]);
            }
            $this->db('post_tag_pivot')
                ->withoutTimestamps()
                ->setPrimaryKey('post_id')
                ->save(['post_id' => $postId, 'tag_id' => $tagId]);
        }
    }

    private function sanitizeHtml(string $html): string
    {
        // Allow only the safe subset Quill produces; strip everything else
        $allowed = '<p><br><strong><em><u><s><h1><h2><h3><h4><ul><ol><li>'
                 . '<blockquote><pre><code><a><img><span>';

        $clean = strip_tags($html, $allowed);

        // Strip event handlers and javascript: protocols from remaining tags
        $clean = preg_replace('/\s*on\w+\s*=\s*"[^"]*"/i', '', $clean);
        $clean = preg_replace('/\s*on\w+\s*=\s*\'[^\']*\'/i', '', $clean);
        $clean = preg_replace('/href\s*=\s*"javascript:[^"]*"/i', 'href="#"', $clean);

        return $clean;
    }

    private function validateCsrf(): void
    {
        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            if ($this->isAjax()) {
                $this->json(['success' => false, 'message' => 'Security token invalid.'], 403);
            }
            $this->flash('error', 'Security token invalid. Please try again.');
            $this->redirect($this->baseUrl . '/admin/blog/posts');
        }
    }

    private function makeSlug(string $text): string
    {
        return \Core\Utilities\Text\Str::slug($text);
    }

    private function uniqueSlug(string $table, string $base, string $excludeId = ''): string
    {
        $slug      = $base;
        $suffix    = 2;
        $q         = $this->db($table)->select('id')->where('slug', $slug);
        if ($excludeId) {
            $q->whereRaw('id != ?', [$excludeId]);
        }
        while ($q->get(1)) {
            $slug = $base . '-' . $suffix++;
            $q    = $this->db($table)->select('id')->where('slug', $slug);
            if ($excludeId) {
                $q->whereRaw('id != ?', [$excludeId]);
            }
        }
        return $slug;
    }
}
