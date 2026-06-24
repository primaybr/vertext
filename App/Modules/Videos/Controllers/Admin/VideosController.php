<?php

declare(strict_types=1);

namespace App\Modules\Videos\Controllers\Admin;

use App\Controllers\Admin\BaseController;
use App\CMS\Auth;
use Core\Utilities\Str;

/**
 * Admin video management.
 *
 * GET  /admin/videos                          → index()
 * GET  /admin/videos/form                     → form()          [modal - create]
 * POST /admin/videos/store                    → store()
 * GET  /admin/videos/{id}/form                → editForm($id)   [modal - edit]
 * POST /admin/videos/{id}/update              → update($id)
 * POST /admin/videos/{id}/delete              → delete($id)
 */
class VideosController extends BaseController
{
    protected string $module = 'videos';

    private const THUMB_DIR = 'video-thumbs';

    public function __construct()
    {
        parent::__construct();
    }

    public function index(): void
    {
        $this->requirePermission('videos.view');

        $page    = max(1, (int) ($this->input->get('page') ?? 1));
        $perPage = 20;
        $offset  = ($page - 1) * $perPage;

        $total   = (int) ($this->db('videos')->totalRows() ?: 0);
        $videos  = $this->db('videos')
            ->select('id, title, slug, provider, status, thumbnail_path, sort_order, created_at')
            ->orderBy('sort_order', 'ASC')
            ->orderBy('created_at', 'DESC')
            ->limitOffset($perPage, $offset)
            ->get() ?: [];

        foreach ($videos as &$v) {
            $v['thumbnail_url'] = $v['thumbnail_path']
                ? $this->baseUrl . '/Public/' . ltrim(str_replace(ROOT, '', $v['thumbnail_path']), '/\\')
                : '';
        }
        unset($v);

        $this->adminRender('modules/videos/admin/videos/index', [
            'videos' => $videos,
            'total'  => $total,
            'page'   => $page,
            'pages'  => max(1, (int) ceil($total / $perPage)),
        ], 'Videos', 'videos');
    }

    public function form(): void
    {
        $this->requirePermission('videos.create');
        $this->renderPartial('modules/videos/admin/videos/_form', ['video' => null]);
    }

    public function store(): void
    {
        $this->requirePermission('videos.create');
        $this->validateCsrf();

        $data = $this->collectInput();
        if ($error = $this->validate($data)) {
            $this->json(['success' => false, 'message' => $error]);
        }

        $data['slug']       = $this->uniqueSlug($data['slug'] ?: Str::slug($data['title']));
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();

        $this->fetchThumbnail($data);

        $this->db('videos')->save($data);
        Auth::audit('videos.create', 'videos', $data['slug']);
        $this->json(['success' => true, 'message' => 'Video added.']);
    }

    public function editForm(string $id): void
    {
        $this->requirePermission('videos.edit');
        $video = $this->db('videos')->where('id', $id)->get(1);
        if (!$video) {
            $this->json(['success' => false, 'message' => 'Not found.'], 404);
        }
        $this->renderPartial('modules/videos/admin/videos/_form', ['video' => $video]);
    }

    public function update(string $id): void
    {
        $this->requirePermission('videos.edit');
        $this->validateCsrf();

        $existing = $this->db('videos')->where('id', $id)->get(1);
        if (!$existing) {
            $this->json(['success' => false, 'message' => 'Not found.'], 404);
        }

        $data = $this->collectInput();
        if ($error = $this->validate($data)) {
            $this->json(['success' => false, 'message' => $error]);
        }

        $slug = $data['slug'] ?: Str::slug($data['title']);
        if ($slug !== $existing['slug']) {
            $slug = $this->uniqueSlug($slug, $id);
        }
        $data['slug']       = $slug;
        $data['updated_by'] = Auth::id();

        // Re-fetch thumbnail only if embed URL changed
        if ($data['embed_url'] !== $existing['embed_url']) {
            $data['thumbnail_path'] = null;
            $data['video_id']       = null;
            $this->fetchThumbnail($data);
        } else {
            $data['thumbnail_path'] = $existing['thumbnail_path'];
            $data['video_id']       = $existing['video_id'];
        }

        $this->db('videos')->where('id', $id)->update($data);
        Auth::audit('videos.update', 'videos', $id);
        $this->json(['success' => true, 'message' => 'Video updated.']);
    }

    public function delete(string $id): void
    {
        $this->requirePermission('videos.delete');
        $this->validateCsrf();

        $video = $this->db('videos')->where('id', $id)->get(1);
        if ($video && $video['thumbnail_path'] && is_file($video['thumbnail_path'])) {
            @unlink($video['thumbnail_path']);
        }

        $this->db('videos')->where('id', $id)->delete();
        Auth::audit('videos.delete', 'videos', $id);
        $this->json(['success' => true, 'message' => 'Video deleted.']);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function collectInput(): array
    {
        return [
            'title'            => trim($this->input->post('title',            false) ?? ''),
            'slug'             => Str::slug(trim($this->input->post('slug',   false) ?? '')),
            'embed_url'        => trim($this->input->post('embed_url',         false) ?? ''),
            'provider'         => $this->input->post('provider')              ?? 'youtube',
            'description'      => trim($this->input->post('description',      false) ?? ''),
            'status'           => $this->input->post('status')                ?? 'draft',
            'sort_order'       => (int) ($this->input->post('sort_order')     ?? 0),
            'meta_title'       => trim($this->input->post('meta_title',       false) ?? ''),
            'meta_description' => trim($this->input->post('meta_description', false) ?? ''),
        ];
    }

    private function validate(array $data): ?string
    {
        if (!$data['title'])     return 'Title is required.';
        if (!$data['embed_url']) return 'Embed URL or video URL is required.';
        return null;
    }

    private function uniqueSlug(string $base, string $excludeId = ''): string
    {
        $slug = $base;
        $i    = 2;
        while (true) {
            $q = $this->db('videos')->where('slug', $slug);
            if ($excludeId) {
                $q->whereRaw('id != :xid', [':xid' => $excludeId]);
            }
            if (!$q->get(1)) {
                break;
            }
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    /**
     * Extract video ID + fetch/cache thumbnail poster from YouTube or Vimeo.
     * Mutates $data in place.
     */
    private function fetchThumbnail(array &$data): void
    {
        $url      = $data['embed_url'];
        $provider = $data['provider'];

        $videoId = $this->extractVideoId($url, $provider);
        if (!$videoId) {
            return;
        }
        $data['video_id'] = $videoId;

        try {
            $thumbUrl = match ($provider) {
                'youtube' => "https://img.youtube.com/vi/{$videoId}/maxresdefault.jpg",
                'vimeo'   => $this->vimeoThumbnailUrl($videoId),
                default   => null,
            };

            if (!$thumbUrl) {
                return;
            }

            $dir = ROOT . 'Public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . self::THUMB_DIR;
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $ext  = 'jpg';
            $dest = $dir . DIRECTORY_SEPARATOR . $provider . '_' . $videoId . '.' . $ext;

            if (!is_file($dest)) {
                $contents = @file_get_contents($thumbUrl);
                if ($contents !== false) {
                    file_put_contents($dest, $contents);
                }
            }

            if (is_file($dest)) {
                $data['thumbnail_path'] = $dest;
            }
        } catch (\Throwable) {
            // Thumbnail fetch is non-critical
        }
    }

    private function extractVideoId(string $url, string $provider): ?string
    {
        return match ($provider) {
            'youtube' => $this->extractYoutubeId($url),
            'vimeo'   => $this->extractVimeoId($url),
            default   => null,
        };
    }

    private function extractYoutubeId(string $url): ?string
    {
        // Handles youtu.be/{id}, youtube.com/watch?v={id}, youtube.com/embed/{id}
        if (preg_match('/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|v\/))([a-zA-Z0-9_\-]{11})/', $url, $m)) {
            return $m[1];
        }
        return null;
    }

    private function extractVimeoId(string $url): ?string
    {
        if (preg_match('/vimeo\.com\/(?:video\/)?(\d+)/', $url, $m)) {
            return $m[1];
        }
        return null;
    }

    private function vimeoThumbnailUrl(string $videoId): ?string
    {
        $api = @file_get_contents("https://vimeo.com/api/v2/video/{$videoId}.json");
        if (!$api) {
            return null;
        }
        $data = json_decode($api, true);
        return $data[0]['thumbnail_large'] ?? $data[0]['thumbnail_medium'] ?? null;
    }

    private function validateCsrf(): void
    {
        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->json(['success' => false, 'message' => 'Security token invalid.'], 403);
        }
    }
}
