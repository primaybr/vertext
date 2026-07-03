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
            ->join('users', 'users.id = posts.created_by', 'LEFT')
            ->whereNull('posts.deleted_at')
            ->orderBy('posts.created_at', 'DESC')
            ->limitOffset($perPage, $offset);

        $qc = $this->db('posts')->whereNull('deleted_at');

        // Status filter counts (for tabs)
        $counts = [];
        foreach (['published', 'scheduled', 'draft', 'archived'] as $s) {
            $counts[$s] = (int) ($this->db('posts')->where('status', $s)->whereNull('deleted_at')->totalRows() ?: 0);
        }

        if ($status && in_array($status, ['published', 'scheduled', 'draft', 'archived'], true)) {
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
        $expireAt        = trim($this->input->post('expire_at', false) ?? '') ?: null;
        $readingTime     = max(0, (int) ($this->input->post('reading_time', false) ?? 0));
        $featuredImgId   = trim((string) ($this->input->post('featured_image_id', false) ?? '')) ?: null;
        $featuredImgUrl  = trim($this->input->post('featured_image_url', false) ?? '');
        $categoryIds     = array_filter((array) ($this->input->post('category_ids', false) ?? []));
        $tagNames        = trim($this->input->post('tag_names', false) ?? '');

        if (!$title) {
            $this->json(['success' => false, 'message' => 'Post title is required.']);
        }

        if (!in_array($status, ['draft', 'published', 'scheduled', 'archived'], true)) {
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
        } elseif ($status === 'scheduled' && $scheduledAt) {
            $publishedAt = date('Y-m-d H:i:s', strtotime($scheduledAt));
        }

        try {
            $postId = (string) $this->db('posts')->save([
                'title'              => $title,
                'slug'               => $slug,
                'body'               => $body,
                'excerpt'            => $excerpt,
                'status'             => $status,
                'created_by'         => $this->currentUser['id'],
                'published_at'       => $publishedAt,
                'expire_at'          => $expireAt ? date('Y-m-d H:i:s', strtotime($expireAt)) : null,
                'featured_image_id'  => $featuredImgId,
                'featured_image_url' => $featuredImgUrl ?: null,
                'meta_title'         => $metaTitle ?: null,
                'meta_description'   => $metaDesc ?: null,
                'reading_time'       => $readingTime,
                'lang'               => $this->postLang(),
            ]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }

        $this->syncCategories($postId, $categoryIds);
        $this->syncTags($postId, $tagNames);

        Auth::audit('post.create', 'posts', $postId, ['title' => $title, 'status' => $status]);
        \App\CMS\PageCache::flushPages();
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
        $expireAt        = trim($this->input->post('expire_at', false) ?? '') ?: null;
        $readingTime     = max(0, (int) ($this->input->post('reading_time', false) ?? 0));
        $featuredImgId   = trim((string) ($this->input->post('featured_image_id', false) ?? '')) ?: null;
        $featuredImgUrl  = trim($this->input->post('featured_image_url', false) ?? '');
        $categoryIds     = array_filter((array) ($this->input->post('category_ids', false) ?? []));
        $tagNames        = trim($this->input->post('tag_names', false) ?? '');

        if (!$title) {
            $this->json(['success' => false, 'message' => 'Post title is required.']);
        }

        if (!in_array($status, ['draft', 'published', 'scheduled', 'archived'], true)) {
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
            'expire_at'          => $expireAt ? date('Y-m-d H:i:s', strtotime($expireAt)) : null,
            'lang'               => $this->postLang(),
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
        } elseif ($status === 'scheduled' && $scheduledAt) {
            $data['published_at'] = date('Y-m-d H:i:s', strtotime($scheduledAt));
        }

        // Snapshot current state before overwriting
        $revisionError = $this->snapshotRevision('post', $id, $existing);

        $this->db('posts')->where('id', $id)->update($data);
        $this->syncCategories($id, $categoryIds);
        $this->syncTags($id, $tagNames);

        Auth::audit('post.update', 'posts', $id, ['status' => $status]);
        \App\CMS\PageCache::flushPages();
        $response = ['success' => true, 'message' => 'Post updated successfully.'];
        if ($revisionError) {
            $response['_revision_error'] = $revisionError;
        }
        $this->json($response);
    }

    // ── Delete ─────────────────────────────────────────────────────────────────

    public function delete(string $id): void
    {
        $this->requirePermission('posts.delete');
        $this->validateCsrf();

        $this->db('posts')->where('id', $id)->whereNull('deleted_at')->update([
            'deleted_at' => date('Y-m-d H:i:s'),
            'deleted_by' => $this->currentUser['id'],
        ]);

        Auth::audit('post.delete', 'posts', $id);
        \App\CMS\PageCache::flushPages();
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

        $namedBinds = [];
        $namedPlaceholders = [];
        foreach (array_values($ids) as $i => $id) {
            $key = ":bulk_id_{$i}";
            $namedPlaceholders[] = $key;
            $namedBinds[$key]    = $id;
        }
        $inClause = implode(',', $namedPlaceholders);

        match ($action) {
            'publish' => $this->db('posts')
                ->whereRaw("id IN ({$inClause})", $namedBinds)
                ->whereNull('deleted_at')
                ->update(['status' => 'published', 'published_at' => date('Y-m-d H:i:s')]),
            'draft'   => $this->db('posts')
                ->whereRaw("id IN ({$inClause})", $namedBinds)
                ->whereNull('deleted_at')
                ->update(['status' => 'draft']),
            'delete'  => $this->db('posts')
                ->whereRaw("id IN ({$inClause})", $namedBinds)
                ->update(['deleted_at' => date('Y-m-d H:i:s'), 'deleted_by' => $this->currentUser['id']]),
            default   => null,
        };

        Auth::audit('posts.bulk', 'posts', '', ['action' => $action, 'count' => count($ids)]);
        $this->json(['success' => true, 'message' => 'Bulk action applied.']);
    }

    // ── Revisions ──────────────────────────────────────────────────────────────

    private function ensureRevisionsTable(): void
    {
        static $checked = false;
        if ($checked) return;
        $checked = true;
        try {
            $db = (new \Core\Model('content_revisions'))->db;
            $db->query("CREATE TABLE IF NOT EXISTS content_revisions (
                id              UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
                content_type    VARCHAR(20)  NOT NULL,
                content_id      UUID         NOT NULL,
                revision_number INT          NOT NULL DEFAULT 1,
                title           VARCHAR(255),
                body            TEXT,
                status          VARCHAR(20),
                created_at      TIMESTAMP    DEFAULT NOW(),
                updated_at      TIMESTAMP    DEFAULT NOW(),
                created_by      UUID,
                updated_by      UUID
            )");
            $db->execute();
            $db->query("CREATE INDEX IF NOT EXISTS idx_content_revisions_content ON content_revisions (content_type, content_id)");
            $db->execute();
            // Add missing columns on existing tables created without them
            foreach ([
                "ALTER TABLE content_revisions ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT NOW()",
                "ALTER TABLE content_revisions ADD COLUMN IF NOT EXISTS updated_by UUID",
                "ALTER TABLE content_revisions ADD COLUMN IF NOT EXISTS slug VARCHAR(255)",
                "ALTER TABLE content_revisions ADD COLUMN IF NOT EXISTS excerpt TEXT",
                "ALTER TABLE content_revisions ADD COLUMN IF NOT EXISTS meta_title VARCHAR(255)",
                "ALTER TABLE content_revisions ADD COLUMN IF NOT EXISTS meta_description TEXT",
            ] as $ddl) {
                $db->query($ddl);
                $db->execute();
            }
        } catch (\Exception) {}
    }

    public function revisions(string $id): void
    {
        $this->ensureRevisionsTable();
        $this->requirePermission('posts.edit');

        $post = $this->db('posts')->select('id, title, slug')->where('id', $id)->whereNull('deleted_at')->get(1);
        if (!$post) {
            $this->flash('error', 'Post not found.');
            $this->redirect($this->baseUrl . '/admin/blog/posts');
        }

        $revisions = $this->db('content_revisions')
            ->select('content_revisions.id, content_revisions.revision_number, content_revisions.title,
                      content_revisions.status, content_revisions.created_at, users.name AS created_by_name')
            ->join('users', 'users.id = content_revisions.created_by', 'LEFT')
            ->where('content_type', 'post')
            ->where('content_id', $id)
            ->orderBy('revision_number', 'DESC')
            ->get() ?: [];

        $this->adminRender('modules/blog/admin/posts/revisions', [
            'post'      => $post,
            'revisions' => $revisions,
        ], 'Revisions: ' . $post['title'], 'blog.posts');
    }

    public function restoreRevision(string $id, string $revId): void
    {
        $this->requirePermission('posts.edit');
        $this->validateCsrf();

        $post = $this->db('posts')->where('id', $id)->whereNull('deleted_at')->get(1);
        if (!$post) {
            $this->json(['success' => false, 'message' => 'Post not found.'], 404);
        }

        $rev = $this->db('content_revisions')
            ->where('id', $revId)->where('content_type', 'post')->where('content_id', $id)->get(1);
        if (!$rev) {
            $this->json(['success' => false, 'message' => 'Revision not found.'], 404);
        }

        // Snapshot current state before restoring
        $this->snapshotRevision('post', $id, $post);

        $this->db('posts')->where('id', $id)->update([
            'title'            => $rev['title'],
            'body'             => $rev['body'],
            'status'           => $rev['status'],
            'excerpt'          => $rev['excerpt'],
            'meta_title'       => $rev['meta_title'],
            'meta_description' => $rev['meta_description'],
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);

        Auth::audit('post.revision.restore', 'posts', $id, ['revision' => $revId]);
        $this->json(['success' => true, 'message' => 'Revision #' . $rev['revision_number'] . ' restored.']);
    }

    public function viewRevision(string $id, string $revId): void
    {
        $this->requirePermission('posts.edit');
        $this->ensureRevisionsTable();

        $post = $this->db('posts')->where('id', $id)->whereNull('deleted_at')->get(1);
        if (!$post) {
            $this->json(['success' => false, 'message' => 'Post not found.'], 404);
        }

        $rev = $this->db('content_revisions')
            ->select('content_revisions.*, users.name AS created_by_name')
            ->join('users', 'users.id = content_revisions.created_by', 'LEFT')
            ->where('content_revisions.id', $revId)
            ->where('content_type', 'post')
            ->where('content_id', $id)
            ->get(1);
        if (!$rev) {
            $this->json(['success' => false, 'message' => 'Revision not found.'], 404);
        }

        $this->renderPartial('modules/blog/admin/posts/_revision_diff', [
            'post'          => $post,
            'rev'           => $rev,
            'restoreAction' => $this->baseUrl . "/admin/blog/posts/{$id}/revisions/{$revId}/restore",
        ]);
    }

    private function snapshotRevision(string $type, string $contentId, array $current): ?string
    {
        $this->ensureRevisionsTable();
        try {
            $lastRev = $this->db('content_revisions')
                ->select('revision_number')
                ->where('content_type', $type)
                ->where('content_id', $contentId)
                ->orderBy('revision_number', 'DESC')
                ->get(1);
            $nextNum = (int) ($lastRev['revision_number'] ?? 0) + 1;

            $bodyField = $type === 'post' ? 'body' : 'content';
            $this->db('content_revisions')->save([
                'content_type'    => $type,
                'content_id'      => $contentId,
                'revision_number' => $nextNum,
                'title'           => $current['title'] ?? null,
                'body'            => $current[$bodyField] ?? null,
                'status'          => $current['status'] ?? null,
                'slug'            => $current['slug'] ?? null,
                'excerpt'         => $current['excerpt'] ?? null,
                'meta_title'      => $current['meta_title'] ?? null,
                'meta_description' => $current['meta_description'] ?? null,
                'created_by'      => $this->currentUser['id'],
            ]);
            return null;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
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

    /** Validated content language from the post form ('en' fallback) */
    private function postLang(): string
    {
        $lang = strtolower(trim($this->input->post('lang') ?? ''));
        return in_array($lang, \App\CMS\I18n::getSupportedLocales(), true) ? $lang : 'en';
    }

    private function makeSlug(string $text): string
    {
        return \Core\Utilities\Text\Str::slug($text);
    }

    private function uniqueSlug(string $table, string $base, string $excludeId = ''): string
    {
        $slug   = $base;
        $suffix = 2;
        $q      = $this->db($table)->select('id')->where('slug', $slug);
        if ($excludeId) {
            $q->whereRaw('id != :excl_id', [':excl_id' => $excludeId]);
        }
        while ($q->get(1)) {
            $slug = $base . '-' . $suffix++;
            $q    = $this->db($table)->select('id')->where('slug', $slug);
            if ($excludeId) {
                $q->whereRaw('id != :excl_id', [':excl_id' => $excludeId]);
            }
        }
        return $slug;
    }
}
