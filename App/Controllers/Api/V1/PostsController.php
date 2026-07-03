<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use Core\Model;

/**
 * GET /api/v1/posts          - published posts (paginated)
 * GET /api/v1/posts/{slug}   - single published post
 */
class PostsController extends ApiController
{
    private const LIVE = "(posts.status = 'published' OR (posts.status = 'scheduled' AND posts.published_at <= NOW()))
                          AND (posts.expire_at IS NULL OR posts.expire_at > NOW())";

    public function index(): void
    {
        if (!\App\CMS\ModuleLoader::isEnabled('blog')) {
            $this->fail(404, 'The blog module is not enabled.');
        }

        [$page, $perPage] = $this->pageParams();

        $q = (new Model('posts p'))
            ->select('p.id, p.title, p.slug, p.excerpt, p.featured_image_url, p.lang, p.published_at, p.created_at, u.name AS author')
            ->join('users u', 'u.id = p.created_by', 'LEFT')
            ->whereRaw(str_replace('posts.', 'p.', self::LIVE), [])
            ->whereNull('p.deleted_at')
            ->orderBy('p.created_at', 'DESC');

        $qc = (new Model('posts'))
            ->whereRaw(self::LIVE, [])
            ->whereNull('posts.deleted_at');

        $lang = trim((string) ($this->input->get('lang') ?? ''));
        if ($lang !== '' && preg_match('/^[a-z]{2}(-[a-z0-9]+)?$/i', $lang)) {
            $q->where('p.lang', strtolower($lang));
            $qc->where('lang', strtolower($lang));
        }

        $total = (int) ($qc->totalRows() ?: 0);
        $items = $q->limitOffset($perPage, ($page - 1) * $perPage)->get() ?: [];

        $this->paginated($items, $page, $perPage, $total);
    }

    public function show(string $slug): void
    {
        if (!\App\CMS\ModuleLoader::isEnabled('blog')) {
            $this->fail(404, 'The blog module is not enabled.');
        }

        $post = (new Model('posts p'))
            ->select('p.id, p.title, p.slug, p.excerpt, p.body, p.featured_image_url, p.lang,
                      p.meta_title, p.meta_description, p.published_at, p.created_at, p.updated_at, u.name AS author')
            ->join('users u', 'u.id = p.created_by', 'LEFT')
            ->where('p.slug', $slug)
            ->whereRaw(str_replace('posts.', 'p.', self::LIVE), [])
            ->whereNull('p.deleted_at')
            ->get(1);

        if (!$post) {
            $this->fail(404, 'Post not found.');
        }

        $this->respond($post);
    }
}
