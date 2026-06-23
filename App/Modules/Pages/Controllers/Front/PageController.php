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
    public function show(string $slug): void
    {
        $page = (new \Core\Model('pages'))
            ->where('slug', $slug)
            ->where('status', 'published')
            ->get(1);

        if (!$page) {
            http_response_code(404);
            $this->render('errors/404', ['baseUrl' => $this->baseUrl]);
            return;
        }

        ThemeEngine::render('modules/pages/front/page', [
            'page'             => $page,
            'baseUrl'          => $this->baseUrl,
            'page_title'       => !empty($page['meta_title']) ? $page['meta_title'] : $page['title'],
            'page_description' => $page['meta_description'] ?? $page['excerpt'] ?? '',
        ]);
    }
}
