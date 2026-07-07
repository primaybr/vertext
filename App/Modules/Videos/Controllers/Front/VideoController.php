<?php

declare(strict_types=1);

namespace App\Modules\Videos\Controllers\Front;

use Core\Controller;
use App\Theme\ThemeEngine;

/**
 * Public video listing and single video view.
 *
 * GET /videos          → index()
 * GET /videos/{slug}   → single($slug)
 */
class VideoController extends Controller
{
    public function index(): void
    {
        $videos = (new \Core\Model('videos'))
            ->select('id, title, slug, provider, video_id, thumbnail_path, description')
            ->where('status', 'published')
            ->orderBy('sort_order', 'ASC')
            ->orderBy('created_at', 'DESC')
            ->get() ?: [];

        foreach ($videos as &$v) {
            $v['thumbnail_url'] = $this->thumbnailUrl($v);
        }
        unset($v);

        ThemeEngine::render('modules/videos/front/index', [
            'videos'           => $videos,
            'baseUrl'          => $this->baseUrl,
            'page_title'       => 'Videos',
            'page_description' => 'Watch our video collection.',
        ]);
    }

    public function single(string $slug): void
    {
        $video = (new \Core\Model('videos'))
            ->where('slug', $slug)
            ->where('status', 'published')
            ->get(1);

        if (!$video) {
            http_response_code(404);
            ThemeEngine::render('modules/videos/front/index', [
                'videos'     => [],
                'baseUrl'    => $this->baseUrl,
                'page_title' => 'Not Found',
            ]);
            return;
        }

        $video['thumbnail_url'] = $this->thumbnailUrl($video);
        $video['embed_iframe']  = $this->buildEmbed($video);

        ThemeEngine::render('modules/videos/front/single', [
            'video'            => $video,
            'baseUrl'          => $this->baseUrl,
            'page_title'       => $video['meta_title'] ?: $video['title'],
            'page_description' => $video['meta_description'] ?: mb_substr(strip_tags($video['description'] ?? ''), 0, 160),
            'page_image'       => $video['thumbnail_url'],
        ]);
    }

    private function thumbnailUrl(array $v): string
    {
        if ($v['thumbnail_path'] && is_file($v['thumbnail_path'])) {
            $rel = str_replace(ROOT . 'Public', '', $v['thumbnail_path']);
            return $this->baseUrl . '/' . ltrim(str_replace('\\', '/', $rel), '/');
        }
        // Fallback: YouTube thumbnail served directly (no local cache yet)
        if ($v['provider'] === 'youtube' && $v['video_id']) {
            return "https://img.youtube.com/vi/{$v['video_id']}/hqdefault.jpg";
        }
        return '';
    }

    private function buildEmbed(array $v): string
    {
        $id = htmlspecialchars($v['video_id'] ?? '', ENT_QUOTES);
        if (!$id) {
            return '';
        }
        $src = match ($v['provider']) {
            'youtube' => "https://www.youtube.com/embed/{$id}?autoplay=1&rel=0",
            'vimeo'   => "https://player.vimeo.com/video/{$id}?autoplay=1",
            default   => htmlspecialchars($v['embed_url'], ENT_QUOTES),
        };
        return '<iframe src="' . $src . '" frameborder="0" allowfullscreen allow="autoplay; encrypted-media" style="width:100%;height:100%;position:absolute;top:0;left:0"></iframe>';
    }
}
