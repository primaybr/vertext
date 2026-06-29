<?php

declare(strict_types=1);

namespace App\Modules\Blog\Controllers\Admin;

use App\Controllers\Admin\BaseController;
use App\CMS\Auth;

/**
 * Admin controller for post series.
 * Series group ordered blog posts with prev/next navigation on the front-end.
 */
class SeriesController extends BaseController
{
    protected string $module = 'blog';

    public function __construct()
    {
        parent::__construct();
    }

    /** GET /admin/blog/series */
    public function index(): void
    {
        $this->requirePermission('posts.view');

        $series = $this->db('post_series')
            ->select('post_series.*, COUNT(post_series_posts.post_id) AS post_count')
            ->join('post_series_posts', 'post_series_posts.series_id = post_series.id', 'LEFT')
            ->whereNull('post_series.deleted_at')
            ->groupBy('post_series.id')
            ->orderBy('post_series.title', 'ASC')
            ->get() ?: [];

        $this->adminRender('modules/blog/admin/series/index', [
            'series' => $series,
        ], 'Post Series', 'blog');
    }

    /** GET /admin/blog/series/form */
    public function createForm(): void
    {
        $this->requirePermission('posts.create');
        $this->renderPartial('modules/blog/admin/series/_form', [
            'series' => null,
            'posts'  => $this->allPosts(),
            'seriesPostIds' => [],
            'action' => $this->baseUrl . '/admin/blog/series/store',
        ]);
    }

    /** POST /admin/blog/series/store */
    public function store(): void
    {
        $this->requirePermission('posts.create');
        $this->validateCsrf();

        $title = trim($this->input->post('title', false) ?? '');
        $slug  = trim(strtolower($this->input->post('slug', false) ?? ''));
        $desc  = trim($this->input->post('description', false) ?? '');

        if (!$title) {
            $this->json(['success' => false, 'message' => 'Title is required.']);
        }

        $slug = $slug ?: $this->slugify($title);

        $existing = $this->db('post_series')->where('slug', $slug)->get(1);
        if ($existing) {
            $this->json(['success' => false, 'message' => "Slug \"{$slug}\" already exists."]);
        }

        $seriesId = (string) $this->db('post_series')->save([
            'title'       => $title,
            'slug'        => $slug,
            'description' => $desc ?: null,
            'created_by'  => (string) Auth::id(),
            'updated_by'  => (string) Auth::id(),
        ]);

        $this->syncSeriesPosts($seriesId, $this->input->post('post_ids') ?? [], $this->input->post('sort_orders') ?? []);

        Auth::audit('series.create', 'post_series', $seriesId, ['title' => $title]);
        $this->json(['success' => true, 'message' => "Series \"{$title}\" created."]);
    }

    /** GET /admin/blog/series/([a-zA-Z0-9\-]+)/form */
    public function editForm(string $id): void
    {
        $this->requirePermission('posts.edit');

        $series = $this->db('post_series')->where('id', $id)->get(1);
        if (!$series) {
            $this->json(['success' => false, 'message' => 'Series not found.'], 404);
        }

        $seriesPosts = $this->db('post_series_posts')
            ->where('series_id', $id)
            ->orderBy('sort_order', 'ASC')
            ->get() ?: [];

        $seriesPostIds = array_column($seriesPosts, 'post_id');
        $sortMap = array_column($seriesPosts, 'sort_order', 'post_id');

        $this->renderPartial('modules/blog/admin/series/_form', [
            'series'        => $series,
            'posts'         => $this->allPosts(),
            'seriesPostIds' => $seriesPostIds,
            'sortMap'       => $sortMap,
            'action'        => $this->baseUrl . "/admin/blog/series/{$id}/update",
        ]);
    }

    /** POST /admin/blog/series/([a-zA-Z0-9\-]+)/update */
    public function update(string $id): void
    {
        $this->requirePermission('posts.edit');
        $this->validateCsrf();

        $series = $this->db('post_series')->where('id', $id)->get(1);
        if (!$series) {
            $this->json(['success' => false, 'message' => 'Series not found.'], 404);
        }

        $title = trim($this->input->post('title', false) ?? '');
        $slug  = trim(strtolower($this->input->post('slug', false) ?? ''));
        $desc  = trim($this->input->post('description', false) ?? '');

        if (!$title) {
            $this->json(['success' => false, 'message' => 'Title is required.']);
        }

        $slug = $slug ?: $this->slugify($title);

        $existing = $this->db('post_series')->where('slug', $slug)->whereRaw("id != :id", [':id' => $id])->get(1);
        if ($existing) {
            $this->json(['success' => false, 'message' => "Slug \"{$slug}\" already exists."]);
        }

        $this->db('post_series')->where('id', $id)->update([
            'title'       => $title,
            'slug'        => $slug,
            'description' => $desc ?: null,
            'updated_by'  => (string) Auth::id(),
        ]);

        $this->syncSeriesPosts($id, $this->input->post('post_ids') ?? [], $this->input->post('sort_orders') ?? []);

        Auth::audit('series.update', 'post_series', $id, ['title' => $title]);
        $this->json(['success' => true, 'message' => "Series \"{$title}\" updated."]);
    }

    /** POST /admin/blog/series/([a-zA-Z0-9\-]+)/delete */
    public function delete(string $id): void
    {
        $this->requirePermission('posts.delete');
        $this->validateCsrf();

        $series = $this->db('post_series')->where('id', $id)->get(1);
        if (!$series) {
            $this->json(['success' => false, 'message' => 'Series not found.'], 404);
        }

        $this->db('post_series_posts')->where('series_id', $id)->delete();
        $this->db('post_series')->where('id', $id)->update([
            'deleted_at' => date('Y-m-d H:i:s'),
            'deleted_by' => (string) Auth::id(),
        ]);

        Auth::audit('series.delete', 'post_series', $id, ['title' => $series['title']]);
        $this->json(['success' => true, 'message' => "Series \"{$series['title']}\" deleted."]);
    }

    private function syncSeriesPosts(string $seriesId, mixed $postIds, mixed $sortOrders): void
    {
        $postIds    = is_array($postIds) ? $postIds : [];
        $sortOrders = is_array($sortOrders) ? $sortOrders : [];

        $this->db('post_series_posts')->where('series_id', $seriesId)->delete();

        foreach ($postIds as $i => $pid) {
            $pid = (string) $pid;
            if (!$pid) continue;
            $this->db('post_series_posts')->withoutTimestamps()->ignoreDuplicate()->save([
                'series_id'  => $seriesId,
                'post_id'    => $pid,
                'sort_order' => (int) ($sortOrders[$i] ?? $i),
            ]);
        }
    }

    private function allPosts(): array
    {
        return $this->db('posts')
            ->select('id, title, slug, status')
            ->whereNull('deleted_at')
            ->orderBy('title', 'ASC')
            ->get() ?: [];
    }

    private function slugify(string $text): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($text)), '-');
    }

    private function validateCsrf(): void
    {
        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->json(['success' => false, 'message' => 'Security token invalid.'], 403);
        }
    }
}
