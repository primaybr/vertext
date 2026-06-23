<?php

declare(strict_types=1);

namespace App\Modules\Gallery\Controllers\Front;

use Core\Controller;
use App\Theme\ThemeEngine;

/**
 * Public gallery front-end.
 *
 * GET /gallery          → index()  — album grid
 * GET /gallery/{slug}   → album($slug) — lightbox view
 */
class GalleryController extends Controller
{
    public function index(): void
    {
        $galleries = (new \Core\Model('galleries'))
            ->select('galleries.id, galleries.title, galleries.slug, galleries.description,
                      media_files.filename AS cover_filename, media_files.thumbnail_path AS cover_thumb')
            ->join('media_files', 'media_files.id = galleries.cover_image_id', 'LEFT')
            ->where('galleries.status', 'published')
            ->orderBy('galleries.created_at', 'DESC')
            ->get() ?: [];

        $baseUrl = $this->baseUrl;
        foreach ($galleries as &$g) {
            $src           = $g['cover_thumb'] ?: $g['cover_filename'];
            $g['cover_url'] = $src ? ($baseUrl . '/uploads/media/' . $src) : '';
            $g['item_count'] = (int) ((new \Core\Model('gallery_items'))
                ->where('gallery_id', $g['id'])->totalRows() ?: 0);
        }
        unset($g);

        ThemeEngine::render('modules/gallery/front/index', [
            'galleries'  => $galleries,
            'baseUrl'    => $baseUrl,
            'page_title' => 'Gallery',
        ]);
    }

    public function album(string $slug): void
    {
        $gallery = (new \Core\Model('galleries'))
            ->where('slug', $slug)
            ->where('status', 'published')
            ->get(1);

        if (!$gallery) {
            http_response_code(404);
            $this->render('errors/404', ['baseUrl' => $this->baseUrl]);
            return;
        }

        $items = (new \Core\Model('gallery_items'))
            ->select('gallery_items.id, gallery_items.caption, gallery_items.sort_order,
                      media_files.filename, media_files.original_name,
                      media_files.thumbnail_path, media_files.alt_text,
                      media_files.width, media_files.height')
            ->join('media_files', 'media_files.id = gallery_items.media_file_id', 'INNER')
            ->where('gallery_items.gallery_id', $gallery['id'])
            ->orderBy('gallery_items.sort_order', 'ASC')
            ->get() ?: [];

        $baseUrl = $this->baseUrl;
        foreach ($items as &$item) {
            $item['url']           = $baseUrl . '/uploads/media/' . $item['filename'];
            $item['thumbnail_url'] = $baseUrl . '/uploads/media/' . ($item['thumbnail_path'] ?: $item['filename']);
        }
        unset($item);

        ThemeEngine::render('modules/gallery/front/album', [
            'gallery'    => $gallery,
            'items'      => $items,
            'baseUrl'    => $baseUrl,
            'page_title' => $gallery['title'],
        ]);
    }
}
