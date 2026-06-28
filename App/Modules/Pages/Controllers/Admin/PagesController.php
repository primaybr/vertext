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
            ->whereNull('deleted_at')
            ->orderBy('sort_order', 'ASC')
            ->orderBy('created_at', 'DESC')
            ->limitOffset($perPage, $offset);
        $qc = $this->db('pages')->whereNull('deleted_at');

        if ($search) {
            $binds = [':s' => "%{$search}%"];
            $q->whereRaw('title ILIKE :s', $binds);
            $qc->whereRaw('title ILIKE :s', $binds);
        }

        $total = (int) ($qc->totalRows() ?: 0);
        $pages = $q->get() ?: [];

        $this->adminRender('modules/pages/admin/pages/index', [
            'pages'      => $pages,
            'total'      => $total,
            'page'       => $page,
            'totalPages' => max(1, (int) ceil($total / $perPage)),
            'search'     => $search,
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

        $title       = trim($this->input->post('title', false) ?? '');
        $status      = $this->input->post('status') ?? 'draft';
        $scheduledAt = trim($this->input->post('published_at', false) ?? '');
        $expireAt    = trim($this->input->post('expire_at', false) ?? '') ?: null;

        if (!$title) {
            $this->json(['success' => false, 'message' => 'Title is required.']);
        }

        $rawSlug = trim($this->input->post('slug', false) ?? '');
        $slug    = $rawSlug ? $this->makeSlug($rawSlug) : $this->makeSlug($title);
        $slug    = $this->uniqueSlug($slug);

        if (!in_array($status, ['draft', 'published', 'scheduled', 'archived'])) {
            $status = 'draft';
        }

        $publishedAt = null;
        if ($status === 'published') {
            $publishedAt = $scheduledAt ? date('Y-m-d H:i:s', strtotime($scheduledAt)) : date('Y-m-d H:i:s');
        } elseif ($status === 'scheduled' && $scheduledAt) {
            $publishedAt = date('Y-m-d H:i:s', strtotime($scheduledAt));
        }

        $id = (string) $this->db('pages')->save([
            'title'            => $title,
            'slug'             => $slug,
            'content'          => trim($this->input->post('content', false) ?? ''),
            'excerpt'          => trim($this->input->post('excerpt', false) ?? ''),
            'status'           => $status,
            'published_at'     => $publishedAt,
            'expire_at'        => $expireAt ? date('Y-m-d H:i:s', strtotime($expireAt)) : null,
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
        $page = $this->db('pages')->where('id', $id)->whereNull('deleted_at')->get(1);
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

        $title       = trim($this->input->post('title', false) ?? '');
        $status      = $this->input->post('status') ?? 'draft';
        $scheduledAt = trim($this->input->post('published_at', false) ?? '');
        $expireAt    = trim($this->input->post('expire_at', false) ?? '') ?: null;

        if (!$title) {
            $this->json(['success' => false, 'message' => 'Title is required.']);
        }

        $existing = $this->db('pages')->where('id', $id)->whereNull('deleted_at')->get(1);
        $rawSlug  = trim($this->input->post('slug', false) ?? '');
        $newSlug  = $rawSlug ? $this->makeSlug($rawSlug) : null;
        if ($newSlug && $newSlug !== ($existing['slug'] ?? '')) {
            $newSlug = $this->uniqueSlug($newSlug, $id);
        }

        if (!in_array($status, ['draft', 'published', 'scheduled', 'archived'])) {
            $status = 'draft';
        }

        $data = [
            'title'            => $title,
            'content'          => trim($this->input->post('content', false) ?? ''),
            'excerpt'          => trim($this->input->post('excerpt', false) ?? ''),
            'status'           => $status,
            'expire_at'        => $expireAt ? date('Y-m-d H:i:s', strtotime($expireAt)) : null,
            'meta_title'       => trim($this->input->post('meta_title', false) ?? ''),
            'meta_description' => trim($this->input->post('meta_description', false) ?? ''),
            'sort_order'       => max(0, (int) ($this->input->post('sort_order') ?? 0)),
            'updated_by'       => $this->currentUser['id'],
            'updated_at'       => date('Y-m-d H:i:s'),
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
        $revisionError = $this->snapshotRevision($id, $existing);

        $this->db('pages')->where('id', $id)->update($data);

        Auth::audit('page.update', 'pages', $id, ['title' => $title]);
        $response = ['success' => true, 'message' => 'Page updated.'];
        if ($revisionError) {
            $response['_revision_error'] = $revisionError;
        }
        $this->json($response);
    }

    public function delete(string $id): void
    {
        $this->requirePermission('pages.delete');
        $this->validateCsrf();

        $this->db('pages')->where('id', $id)->whereNull('deleted_at')->update([
            'deleted_at' => date('Y-m-d H:i:s'),
            'deleted_by' => $this->currentUser['id'],
        ]);

        Auth::audit('page.delete', 'pages', $id);
        $this->json(['success' => true, 'message' => 'Page deleted.']);
    }

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
        $this->requirePermission('pages.edit');

        $page = $this->db('pages')->select('id, title, slug')->where('id', $id)->get(1);
        if (!$page) {
            $this->flash('error', 'Page not found.');
            $this->redirect($this->baseUrl . '/admin/pages');
        }

        $revisions = $this->db('content_revisions')
            ->select('content_revisions.id, content_revisions.revision_number, content_revisions.title,
                      content_revisions.status, content_revisions.created_at, users.name AS created_by_name')
            ->join('users', 'users.id = content_revisions.created_by', 'LEFT')
            ->where('content_type', 'page')
            ->where('content_id', $id)
            ->orderBy('revision_number', 'DESC')
            ->get() ?: [];

        $this->adminRender('modules/pages/admin/pages/revisions', [
            'page'      => $page,
            'revisions' => $revisions,
        ], 'Revisions: ' . $page['title'], 'pages.pages');
    }

    public function restoreRevision(string $id, string $revId): void
    {
        $this->requirePermission('pages.edit');
        $this->validateCsrf();

        $page = $this->db('pages')->where('id', $id)->get(1);
        if (!$page) {
            $this->json(['success' => false, 'message' => 'Page not found.'], 404);
        }

        $rev = $this->db('content_revisions')
            ->where('id', $revId)->where('content_type', 'page')->where('content_id', $id)->get(1);
        if (!$rev) {
            $this->json(['success' => false, 'message' => 'Revision not found.'], 404);
        }

        $this->snapshotRevision($id, $page);

        $this->db('pages')->where('id', $id)->update([
            'title'            => $rev['title'],
            'content'          => $rev['body'],
            'status'           => $rev['status'],
            'excerpt'          => $rev['excerpt'],
            'meta_title'       => $rev['meta_title'],
            'meta_description' => $rev['meta_description'],
            'updated_at'       => date('Y-m-d H:i:s'),
            'updated_by'       => $this->currentUser['id'],
        ]);

        Auth::audit('page.revision.restore', 'pages', $id, ['revision' => $revId]);
        $this->json(['success' => true, 'message' => 'Revision #' . $rev['revision_number'] . ' restored.']);
    }

    public function viewRevision(string $id, string $revId): void
    {
        $this->requirePermission('pages.edit');
        $this->ensureRevisionsTable();

        $page = $this->db('pages')->where('id', $id)->get(1);
        if (!$page) {
            $this->json(['success' => false, 'message' => 'Page not found.'], 404);
        }

        $rev = $this->db('content_revisions')
            ->select('content_revisions.*, users.name AS created_by_name')
            ->join('users', 'users.id = content_revisions.created_by', 'LEFT')
            ->where('content_revisions.id', $revId)
            ->where('content_type', 'page')
            ->where('content_id', $id)
            ->get(1);
        if (!$rev) {
            $this->json(['success' => false, 'message' => 'Revision not found.'], 404);
        }

        $this->renderPartial('modules/pages/admin/pages/_revision_diff', [
            'page'          => $page,
            'rev'           => $rev,
            'restoreAction' => $this->baseUrl . "/admin/pages/{$id}/revisions/{$revId}/restore",
        ]);
    }

    private function snapshotRevision(string $contentId, array $current): ?string
    {
        $this->ensureRevisionsTable();
        try {
            $lastRev = $this->db('content_revisions')
                ->select('revision_number')
                ->where('content_type', 'page')
                ->where('content_id', $contentId)
                ->orderBy('revision_number', 'DESC')
                ->get(1);
            $nextNum = (int) ($lastRev['revision_number'] ?? 0) + 1;

            $this->db('content_revisions')->save([
                'content_type'    => 'page',
                'content_id'      => $contentId,
                'revision_number' => $nextNum,
                'title'           => $current['title'] ?? null,
                'body'            => $current['content'] ?? null,
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
            $q->whereRaw('id != :excl_id', [':excl_id' => $excludeId]);
        }
        while ($q->get(1)) {
            $slug = $base . '-' . $suffix++;
            $q    = $this->db('pages')->select('id')->where('slug', $slug);
            if ($excludeId) {
                $q->whereRaw('id != :excl_id', [':excl_id' => $excludeId]);
            }
        }
        return $slug;
    }
}
