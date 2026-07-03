<?php

declare(strict_types=1);

namespace App\Modules\Pages\Controllers\Front;

use Core\Controller;
use App\Theme\ThemeEngine;

/**
 * Public front-end page rendering.
 *
 * GET /{slug} → show($slug)
 */
class PageController extends Controller
{
    private function ensurePagesSchema(): void
    {
        static $checked = false;
        if ($checked) return;
        $checked = true;
        try {
            $db = (new \Core\Model('pages'))->db;
            foreach ([
                "ALTER TABLE pages ADD COLUMN IF NOT EXISTS published_at TIMESTAMP",
                "ALTER TABLE pages ADD COLUMN IF NOT EXISTS expire_at    TIMESTAMP",
            ] as $ddl) {
                $db->query($ddl);
                $db->execute();
            }
        } catch (\Throwable) {}
    }

    public function show(string $slug): void
    {
        \App\CMS\PageCache::serve();

        $this->ensurePagesSchema();

        $page = (new \Core\Model('pages'))
            ->where('slug', $slug)
            ->whereRaw("(status = 'published' OR (status = 'scheduled' AND published_at <= NOW())) AND (expire_at IS NULL OR expire_at > NOW())", [])
            ->get(1);

        if (!$page) {
            http_response_code(404);
            $this->render('error/404', ['baseUrl' => $this->baseUrl]);
            return;
        }

        // Resolve [form slug="..."] and future shortcodes in the trusted body
        $page['content'] = \App\CMS\Shortcodes::render((string) ($page['content'] ?? ''), $this->baseUrl);

        \App\Modules\Pages\PageHelper::ensureSchema();

        \App\CMS\PageCache::capture(function () use ($page) {
            ThemeEngine::render('modules/pages/front/page', [
                'page'             => $page,
                'pageMeta'         => \App\Modules\Pages\PageHelper::getAllMeta((string) $page['id']),
                'baseUrl'          => $this->baseUrl,
                'page_title'       => !empty($page['meta_title']) ? $page['meta_title'] : $page['title'],
                'page_description' => $page['meta_description'] ?? $page['excerpt'] ?? '',
            ]);
        });
    }
}
