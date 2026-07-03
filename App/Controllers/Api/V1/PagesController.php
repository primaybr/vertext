<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use Core\Model;

/**
 * GET /api/v1/pages          - published pages (paginated)
 * GET /api/v1/pages/{slug}   - single published page
 */
class PagesController extends ApiController
{
    private const LIVE = "(status = 'published' OR (status = 'scheduled' AND published_at <= NOW()))
                          AND (expire_at IS NULL OR expire_at > NOW())";

    public function index(): void
    {
        if (!\App\CMS\ModuleLoader::isEnabled('pages')) {
            $this->fail(404, 'The pages module is not enabled.');
        }

        [$page, $perPage] = $this->pageParams();

        $q = (new Model('pages'))
            ->select('id, title, slug, excerpt, lang, published_at, created_at, updated_at')
            ->whereRaw(self::LIVE, [])
            ->whereNull('deleted_at')
            ->orderBy('title', 'ASC');

        $qc = (new Model('pages'))->whereRaw(self::LIVE, [])->whereNull('deleted_at');

        $lang = trim((string) ($this->input->get('lang') ?? ''));
        if ($lang !== '' && preg_match('/^[a-z]{2}(-[a-z0-9]+)?$/i', $lang)) {
            $q->where('lang', strtolower($lang));
            $qc->where('lang', strtolower($lang));
        }

        $total = (int) ($qc->totalRows() ?: 0);
        $items = $q->limitOffset($perPage, ($page - 1) * $perPage)->get() ?: [];

        $this->paginated($items, $page, $perPage, $total);
    }

    public function show(string $slug): void
    {
        if (!\App\CMS\ModuleLoader::isEnabled('pages')) {
            $this->fail(404, 'The pages module is not enabled.');
        }

        $pageRow = (new Model('pages'))
            ->select('id, title, slug, excerpt, content, lang, meta_title, meta_description, published_at, created_at, updated_at')
            ->where('slug', $slug)
            ->whereRaw(self::LIVE, [])
            ->whereNull('deleted_at')
            ->get(1);

        if (!$pageRow) {
            $this->fail(404, 'Page not found.');
        }

        $this->respond($pageRow);
    }
}
