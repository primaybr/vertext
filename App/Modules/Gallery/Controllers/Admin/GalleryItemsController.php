<?php

declare(strict_types=1);

namespace App\Modules\Gallery\Controllers\Admin;

use App\Controllers\Admin\BaseController;
use App\CMS\Auth;

/**
 * Manage images within a gallery album.
 *
 * GET  /admin/gallery/{id}/items                              → index($id)
 * POST /admin/gallery/{id}/items/add                         → add($id)
 * POST /admin/gallery/{id}/items/reorder                     → reorder($id)
 * POST /admin/gallery/{id}/items/{itemId}/remove             → remove($id, $itemId)
 */
class GalleryItemsController extends BaseController
{
    protected string $module = 'gallery';

    public function __construct()
    {
        parent::__construct();
    }

    public function index(string $galleryId): void
    {
        $this->requirePermission('gallery.edit');

        $gallery = $this->db('galleries')->where('id', $galleryId)->get(1);
        if (!$gallery) {
            $this->flash('error', 'Album not found.');
            $this->redirect($this->baseUrl . '/admin/gallery');
        }

        $items = $this->db('gallery_items')
            ->select('gallery_items.id, gallery_items.caption, gallery_items.sort_order,
                      media_files.filename, media_files.original_name,
                      media_files.thumbnail_path, media_files.alt_text')
            ->join('media_files', 'media_files.id = gallery_items.media_file_id', 'INNER')
            ->where('gallery_items.gallery_id', $galleryId)
            ->orderBy('gallery_items.sort_order', 'ASC')
            ->get() ?: [];

        foreach ($items as &$item) {
            $item['url']           = $this->baseUrl . '/uploads/media/' . $item['filename'];
            $item['thumbnail_url'] = $this->baseUrl . '/uploads/media/' . ($item['thumbnail_path'] ?: $item['filename']);
        }
        unset($item);

        $this->adminRender('modules/gallery/admin/galleries/items', [
            'gallery'   => $gallery,
            'items'     => $items,
        ], 'Album: ' . htmlspecialchars($gallery['title']), 'gallery');
    }

    /** Add a single media file to the album. */
    public function add(string $galleryId): void
    {
        $this->requirePermission('gallery.edit');
        $this->validateCsrf();

        $gallery = $this->db('galleries')->where('id', $galleryId)->get(1);
        if (!$gallery) {
            $this->json(['success' => false, 'message' => 'Album not found.'], 404);
        }

        $mediaId = trim($this->input->post('media_file_id', false) ?? '');
        if (!$mediaId) {
            $this->json(['success' => false, 'message' => 'No media file selected.']);
        }

        $media = $this->db('media_files')
            ->select('id, filename, original_name, thumbnail_path, alt_text')
            ->where('id', $mediaId)
            ->get(1);
        if (!$media) {
            $this->json(['success' => false, 'message' => 'Media file not found.'], 404);
        }

        // Check not already in album
        $exists = $this->db('gallery_items')
            ->where('gallery_id', $galleryId)
            ->where('media_file_id', $mediaId)
            ->get(1);
        if ($exists) {
            $this->json(['success' => false, 'message' => 'Image already in this album.']);
        }

        $maxOrder = (int) ($this->db('gallery_items')
            ->select('COALESCE(MAX(sort_order), -1) AS mx')
            ->where('gallery_id', $galleryId)
            ->get(1)['mx'] ?? -1);

        $this->db('gallery_items')->withoutTimestamps()->save([
            'gallery_id'    => $galleryId,
            'media_file_id' => $mediaId,
            'sort_order'    => $maxOrder + 1,
        ]);

        $inserted = $this->db('gallery_items')
            ->where('gallery_id', $galleryId)
            ->where('media_file_id', $mediaId)
            ->get(1);

        Auth::audit('gallery.item.add', 'gallery_items', $mediaId, ['gallery_id' => $galleryId]);
        \App\CMS\PageCache::flushPages();

        $this->json([
            'success'       => true,
            'message'       => 'Image added.',
            'item_id'       => $inserted['id'] ?? '',
            'thumbnail_url' => $this->baseUrl . '/uploads/media/' . ($media['thumbnail_path'] ?: $media['filename']),
            'url'           => $this->baseUrl . '/uploads/media/' . $media['filename'],
            'name'          => $media['original_name'],
        ]);
    }

    /** Reorder items - accepts JSON body [{ id, sort_order }, ...] */
    public function reorder(string $galleryId): void
    {
        $this->requirePermission('gallery.edit');
        $this->validateCsrf();

        $body  = file_get_contents('php://input');
        $items = json_decode($body, true);

        if (!is_array($items)) {
            $this->json(['success' => false, 'message' => 'Invalid data.'], 400);
        }

        foreach ($items as $item) {
            $itemId    = $item['id']         ?? '';
            $sortOrder = (int) ($item['sort_order'] ?? 0);
            if (!$itemId) {
                continue;
            }
            $this->db('gallery_items')
                ->where('id', $itemId)
                ->where('gallery_id', $galleryId)
                ->update(['sort_order' => $sortOrder]);
        }

        \App\CMS\PageCache::flushPages();
        $this->json(['success' => true]);
    }

    /** Remove a single item from the album. */
    public function remove(string $galleryId, string $itemId): void
    {
        $this->requirePermission('gallery.edit');
        $this->validateCsrf();

        $this->db('gallery_items')
            ->where('id', $itemId)
            ->where('gallery_id', $galleryId)
            ->delete();

        Auth::audit('gallery.item.remove', 'gallery_items', $itemId);
        \App\CMS\PageCache::flushPages();
        $this->json(['success' => true, 'message' => 'Image removed.']);
    }

    private function validateCsrf(): void
    {
        $token = $this->input->post('csrf_token') ?? '';
        // Also accept from X-CSRF-Token header (for JSON reorder)
        if (!$token) {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        }
        if (!$this->csrf->validateToken($token)) {
            $this->json(['success' => false, 'message' => 'Security token invalid.'], 403);
        }
    }
}
