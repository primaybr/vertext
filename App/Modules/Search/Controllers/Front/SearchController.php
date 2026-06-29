<?php

declare(strict_types=1);

namespace App\Modules\Search\Controllers\Front;

use Core\Controller;
use App\Theme\ThemeEngine;

/**
 * Public search endpoint.
 *
 * GET /search?q=...   index()
 */
class SearchController extends Controller
{
    public function index(): void
    {
        $q       = trim($this->input->get('q') ?? '');
        $results = [];
        $total   = 0;

        if (strlen($q) >= 2) {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';

            $rows = (new \Core\Model('search_index'))
                ->select('content_type, title, url, body')
                ->whereRaw('(title ILIKE :q1 OR body ILIKE :q2)', [':q1' => $like, ':q2' => $like])
                ->orderBy('content_type', 'ASC')
                ->limitOffset(30, 0)
                ->get() ?: [];

            foreach ($rows as $row) {
                $results[] = [
                    'type'    => $row['content_type'],
                    'title'   => $row['title'],
                    'url'     => $row['url'],
                    'excerpt' => $this->highlight(strip_tags($row['body'] ?? ''), $q, 160),
                ];
            }

            $total = (int) ((new \Core\Model('search_index'))
                ->whereRaw('(title ILIKE :q1 OR body ILIKE :q2)', [':q1' => $like, ':q2' => $like])
                ->totalRows() ?: 0);

            // Track search query in Analytics if module is installed
            if (class_exists('\App\Modules\Analytics\Tracker')) {
                \App\Modules\Analytics\Tracker::recordSearch($q, $total);
            }
        }

        ThemeEngine::render('modules/search/front/results', [
            'q'          => $q,
            'results'    => $results,
            'total'      => $total,
            'baseUrl'    => $this->baseUrl,
            'page_title' => $q ? 'Search: ' . $q : 'Search',
        ]);
    }

    private function highlight(string $text, string $query, int $length): string
    {
        $pos = mb_stripos($text, $query);
        if ($pos === false) {
            return mb_substr($text, 0, $length) . (mb_strlen($text) > $length ? '...' : '');
        }
        $start  = max(0, $pos - 60);
        $excerpt = mb_substr($text, $start, $length);
        if ($start > 0) {
            $excerpt = '...' . $excerpt;
        }
        if ($start + $length < mb_strlen($text)) {
            $excerpt .= '...';
        }
        return $excerpt;
    }
}
