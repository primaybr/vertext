<?php

declare(strict_types=1);

namespace App\Modules\Blog\Controllers\Front;

use Core\Controller;
use App\Theme\ThemeEngine;

/**
 * Public-facing blog frontend.
 *
 * GET /blog                            → index()
 * GET /blog/category/{slug}            → category($slug)
 * GET /blog/{slug}                     → post($slug)
 *
 * No authentication required.
 */
class BlogController extends Controller
{
    private int   $perPage;
    private array $settings = [];

    public function __construct()
    {
        parent::__construct();

        $this->ensurePostsSchema();

        // Load all blog settings
        $rows = (new \Core\Model('settings'))->where('grp', 'blog')->get() ?: [];
        foreach ($rows as $row) {
            $this->settings[$row['key']] = $row['value'];
        }

        $this->perPage = max(1, (int) ($this->settings['posts_per_page'] ?? 10));
    }

    private function ensurePostsSchema(): void
    {
        static $checked = false;
        if ($checked) return;
        $checked = true;
        try {
            $db = (new \Core\Model('posts'))->db;
            foreach ([
                "ALTER TABLE posts ADD COLUMN IF NOT EXISTS published_at       TIMESTAMP",
                "ALTER TABLE posts ADD COLUMN IF NOT EXISTS expire_at          TIMESTAMP",
                "ALTER TABLE posts ADD COLUMN IF NOT EXISTS featured_image_url VARCHAR(500)",
                "ALTER TABLE posts ADD COLUMN IF NOT EXISTS featured_image_id  UUID",
                "ALTER TABLE posts ADD COLUMN IF NOT EXISTS reading_time       SMALLINT DEFAULT 0",
                "ALTER TABLE posts ADD COLUMN IF NOT EXISTS deleted_at         TIMESTAMP",
                "ALTER TABLE blog_comments ADD COLUMN IF NOT EXISTS parent_comment_id UUID",
            ] as $ddl) {
                $db->query($ddl);
                $db->execute();
            }
        } catch (\Throwable) {}
    }

    public function index(): void
    {
        \App\CMS\PageCache::serve();

        $page   = max(1, (int) ($this->input->get('page') ?? 1));
        $offset = ($page - 1) * $this->perPage;

        $visibleFilter = "(posts.status = 'published' OR (posts.status = 'scheduled' AND posts.published_at <= NOW())) AND (posts.expire_at IS NULL OR posts.expire_at > NOW())";
        $totalFilter   = "(status = 'published' OR (status = 'scheduled' AND published_at <= NOW())) AND (expire_at IS NULL OR expire_at > NOW())";

        // i18n: filter the listing by the visitor's locale, but only when that
        // locale actually has posts - otherwise fall back to everything.
        $locale     = \App\CMS\I18n::getLocale();
        $langFilter = false;
        try {
            $langCount = (int) ((new \Core\Model('posts'))
                ->whereRaw($totalFilter, [])
                ->where('lang', $locale)
                ->whereNull('deleted_at')
                ->totalRows() ?: 0);
            $langFilter = $langCount > 0;
        } catch (\Throwable) {
        }

        $q = (new \Core\Model('posts'))
            ->select('posts.id, posts.title, posts.slug, posts.excerpt, posts.published_at,
                      posts.reading_time, posts.featured_image_url,
                      users.name AS author_name')
            ->join('users', 'users.id = posts.created_by', 'LEFT')
            ->whereRaw($visibleFilter, [])
            ->whereNull('posts.deleted_at')
            ->orderBy('posts.published_at', 'DESC')
            ->limitOffset($this->perPage, $offset);
        if ($langFilter) {
            $q->where('posts.lang', $locale);
        }
        $posts = $q->get() ?: [];

        $qc = (new \Core\Model('posts'))
            ->whereRaw($totalFilter, [])
            ->whereNull('deleted_at');
        if ($langFilter) {
            $qc->where('lang', $locale);
        }
        $total = (int) ($qc->totalRows() ?: 0);

        // Attach categories to each post
        foreach ($posts as &$p) {
            $p['categories'] = $this->postCategories($p['id']);
        }
        unset($p);

        $vars = [
            'posts'            => $posts,
            'total'            => $total,
            'page'             => $page,
            'pages'            => max(1, (int) ceil($total / $this->perPage)),
            'settings'         => $this->settings,
            'baseUrl'          => $this->baseUrl,
            'page_title'       => $this->settings['blog_title'] ?? 'Blog',
            'page_description' => $this->settings['blog_description'] ?? '',
        ];
        \App\CMS\PageCache::capture(static function () use ($vars) {
            ThemeEngine::render('modules/blog/front/index', $vars);
        });
    }

    public function post(string $slug): void
    {
        \App\CMS\PageCache::serve();

        $post = (new \Core\Model('posts'))
            ->select('posts.*, users.name AS author_name')
            ->join('users', 'users.id = posts.created_by', 'LEFT')
            ->where('posts.slug', $slug)
            ->whereRaw("(posts.status = 'published' OR (posts.status = 'scheduled' AND posts.published_at <= NOW())) AND (posts.expire_at IS NULL OR posts.expire_at > NOW())", [])
            ->whereNull('posts.deleted_at')
            ->get(1);

        if (!$post) {
            // With Blog mounted at the root path its slug catch-all shadows the
            // Pages catch-all - fall through so /some-page still renders.
            if (\App\CMS\ModuleLoader::isEnabled('pages')) {
                (new \App\Modules\Pages\Controllers\Front\PageController())->show($slug);
                return;
            }
            http_response_code(404);
            $this->render('error/404', ['baseUrl' => $this->baseUrl]);
            return;
        }

        $post['categories'] = $this->postCategories($post['id']);
        $post['tags']       = $this->postTags($post['id']);

        // Series navigation
        $series = $this->postSeries($post['id']);

        // Related posts (shared tags/categories, max 4)
        $relatedPosts = $this->relatedPosts(
            $post['id'],
            array_column($post['tags'], 'id'),
            array_column($post['categories'], 'id')
        );

        // Comments enabled?
        $commentsEnabled = $this->settingBool('comments_enabled', true);
        $threadedComments = [];
        if ($commentsEnabled) {
            $flat = (new \Core\Model('blog_comments'))
                ->where('post_id', $post['id'])
                ->where('status', 'approved')
                ->orderBy('created_at', 'ASC')
                ->get() ?: [];
            $threadedComments = $this->threadComments($flat);
        }

        $commentFlash = $this->session->flash('blog_comment_flash') ?: [];

        $pageTitle = !empty($post['meta_title']) ? $post['meta_title'] : $post['title'];
        $pageDesc  = $post['meta_description'] ?? $post['excerpt'] ?? '';

        // Resolve [form slug="..."] and future shortcodes in the trusted body
        $post['body'] = \App\CMS\Shortcodes::render((string) ($post['body'] ?? ''), $this->baseUrl);

        $vars = [
            'post'             => $post,
            'threadedComments' => $threadedComments,
            'commentsEnabled'  => $commentsEnabled,
            'commentFlash'     => is_array($commentFlash) ? $commentFlash : [],
            'series'           => $series,
            'relatedPosts'     => $relatedPosts,
            'settings'         => $this->settings,
            'csrf_token'       => $this->csrf->getToken(),
            'baseUrl'          => $this->baseUrl,
            'page_title'       => $pageTitle,
            'page_description' => $pageDesc,
            'page_image'       => $post['featured_image_url'] ?? $this->settings['og_default_image'] ?? '',
        ];
        // Posts with comments enabled embed a CSRF token, so capture() will
        // detect that and skip storing - only comment-free pages get cached.
        \App\CMS\PageCache::capture(static function () use ($vars) {
            ThemeEngine::render('modules/blog/front/post', $vars);
        });
    }

    public function category(string $slug): void
    {
        $category = (new \Core\Model('post_categories'))
            ->where('slug', $slug)
            ->get(1);

        if (!$category) {
            http_response_code(404);
            $this->render('error/404', ['baseUrl' => $this->baseUrl]);
            return;
        }

        $page   = max(1, (int) ($this->input->get('page') ?? 1));
        $offset = ($page - 1) * $this->perPage;

        $catFilter = "(posts.status = 'published' OR (posts.status = 'scheduled' AND posts.published_at <= NOW())) AND (posts.expire_at IS NULL OR posts.expire_at > NOW())";

        $posts = (new \Core\Model('posts'))
            ->select('posts.id, posts.title, posts.slug, posts.excerpt, posts.published_at,
                      posts.reading_time, posts.featured_image_url, users.name AS author_name')
            ->join('users', 'users.id = posts.created_by', 'LEFT')
            ->join('post_category_pivot', 'post_category_pivot.post_id = posts.id', 'INNER')
            ->where('post_category_pivot.category_id', $category['id'])
            ->whereRaw($catFilter, [])
            ->whereNull('posts.deleted_at')
            ->orderBy('posts.published_at', 'DESC')
            ->limitOffset($this->perPage, $offset)
            ->get() ?: [];

        $total = (int) ((new \Core\Model('posts'))
            ->join('post_category_pivot', 'post_category_pivot.post_id = posts.id', 'INNER')
            ->where('post_category_pivot.category_id', $category['id'])
            ->whereRaw($catFilter, [])
            ->whereNull('posts.deleted_at')
            ->totalRows() ?: 0);

        foreach ($posts as &$p) {
            $p['categories'] = $this->postCategories($p['id']);
        }
        unset($p);

        ThemeEngine::render('modules/blog/front/category', [
            'category'   => $category,
            'posts'      => $posts,
            'total'      => $total,
            'page'       => $page,
            'pages'      => max(1, (int) ceil($total / $this->perPage)),
            'settings'   => $this->settings,
            'baseUrl'    => $this->baseUrl,
            'page_title' => ($category['name'] ?? '') . ' - ' . ($this->settings['blog_title'] ?? 'Blog'),
        ]);
    }

    public function submitComment(string $slug): void
    {
        $rawBase   = trim($this->settings['blog_base_path'] ?? 'blog', '/');
        $blogBase  = $rawBase === '' ? '' : '/' . $rawBase;

        $post = (new \Core\Model('posts'))
            ->where('slug', $slug)
            ->where('status', 'published')
            ->whereNull('deleted_at')
            ->get(1);

        if (!$post) {
            $this->redirect($this->baseUrl . ($blogBase ?: '/'));
        }

        // CSRF
        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->session->set('blog_comment_flash', ['type' => 'error', 'message' => 'Security token invalid. Please try again.']);
            $this->redirect($this->baseUrl . $blogBase . '/' . $slug);
        }

        // Comments enabled?
        if (!$this->settingBool('comments_enabled', true)) {
            $this->redirect($this->baseUrl . $blogBase . '/' . $slug);
        }

        $authorName  = substr(trim($this->input->post('author_name',  false) ?? ''), 0, 120);
        $authorEmail = substr(trim($this->input->post('author_email', false) ?? ''), 0, 180);
        $body        = substr(trim($this->input->post('body',         false) ?? ''), 0, 2000);
        $rawParentId = trim($this->input->post('parent_comment_id', false) ?? '');

        if (!$authorName || !$body) {
            $this->session->set('blog_comment_flash', ['type' => 'error', 'message' => 'Name and comment are required.']);
            $this->redirect($this->baseUrl . $blogBase . '/' . $slug);
        }

        // Validate parent comment belongs to same post and is approved
        $parentId = null;
        if ($rawParentId !== '') {
            $parentRow = (new \Core\Model('blog_comments'))
                ->where('id', $rawParentId)
                ->where('post_id', (string) $post['id'])
                ->where('status', 'approved')
                ->get(1);
            if ($parentRow) $parentId = $rawParentId;
        }

        $requireApproval = $this->settingBool('comments_require_approval', true);
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP']
            ?? $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '';

        (new \Core\Model('blog_comments'))->save([
            'post_id'           => (string) $post['id'],
            'parent_comment_id' => $parentId,
            'author_name'       => $authorName,
            'author_email'      => $authorEmail ?: null,
            'body'              => $body,
            'status'            => $requireApproval ? 'pending' : 'approved',
            'ip_address'        => substr($ip, 0, 45),
        ]);

        $msg = $requireApproval
            ? 'Thanks! Your comment is awaiting moderation.'
            : 'Comment posted successfully.';

        $this->session->set('blog_comment_flash', ['type' => 'success', 'message' => $msg]);

        // Notify post author about the new pending comment
        if ($requireApproval) {
            $rawBase  = trim($this->settings['blog_base_path'] ?? 'blog', '/');
            $this->sendNewCommentNotification($post, $authorName, $authorEmail, $body, $rawBase);
        }

        $this->redirect($this->baseUrl . $blogBase . '/' . $slug);
    }

    public function feed(): void
    {
        $rawBase  = trim($this->settings['blog_base_path'] ?? 'blog', '/');
        $blogBase = $rawBase === '' ? '' : '/' . $rawBase;

        $siteSettings = array_column((new \Core\Model('settings'))->get() ?: [], 'value', 'key');
        $siteUrl   = rtrim($siteSettings['site_url'] ?? $this->baseUrl, '/');
        $siteName  = $siteSettings['site_name'] ?? 'Vertext CMS';
        $blogTitle = $this->settings['blog_title'] ?? $siteName;
        $blogDesc  = $this->settings['blog_description'] ?? '';
        $feedUrl   = $siteUrl . $blogBase . '/feed.rss';
        $blogUrl   = $siteUrl . ($blogBase ?: '/');

        $posts = (new \Core\Model('posts'))
            ->select('posts.id, posts.title, posts.slug, posts.excerpt, posts.body,
                      posts.published_at, posts.featured_image_url,
                      users.name AS author_name')
            ->join('users', 'users.id = posts.created_by', 'LEFT')
            ->where('posts.status', 'published')
            ->whereNull('posts.deleted_at')
            ->orderBy('posts.published_at', 'DESC')
            ->limitOffset(20, 0)
            ->get() ?: [];

        header('Content-Type: application/rss+xml; charset=utf-8');
        header('X-Robots-Tag: noindex');

        $lastBuild = !empty($posts)
            ? date(DATE_RSS, strtotime($posts[0]['published_at']))
            : date(DATE_RSS);

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/">' . "\n";
        echo '<channel>' . "\n";
        echo '  <title>' . htmlspecialchars($blogTitle) . '</title>' . "\n";
        echo '  <link>' . htmlspecialchars($blogUrl) . '</link>' . "\n";
        echo '  <description>' . htmlspecialchars($blogDesc ?: $blogTitle) . '</description>' . "\n";
        echo '  <language>en</language>' . "\n";
        echo '  <lastBuildDate>' . $lastBuild . '</lastBuildDate>' . "\n";
        echo '  <atom:link href="' . htmlspecialchars($feedUrl) . '" rel="self" type="application/rss+xml"/>' . "\n";

        foreach ($posts as $post) {
            $postUrl = $siteUrl . $blogBase . '/' . $post['slug'];
            $pubDate = !empty($post['published_at'])
                ? date(DATE_RSS, strtotime($post['published_at']))
                : date(DATE_RSS);
            $excerpt = strip_tags($post['excerpt'] ?? '');
            $body    = $post['body'] ?? '';

            echo '  <item>' . "\n";
            echo '    <title>' . htmlspecialchars($post['title'] ?? '') . '</title>' . "\n";
            echo '    <link>' . htmlspecialchars($postUrl) . '</link>' . "\n";
            echo '    <guid isPermaLink="true">' . htmlspecialchars($postUrl) . '</guid>' . "\n";
            echo '    <pubDate>' . $pubDate . '</pubDate>' . "\n";
            if (!empty($post['author_name'])) {
                echo '    <author>' . htmlspecialchars($post['author_name']) . '</author>' . "\n";
            }
            if ($excerpt) {
                echo '    <description>' . htmlspecialchars($excerpt) . '</description>' . "\n";
            }
            if ($body) {
                echo '    <content:encoded><![CDATA[' . $body . ']]></content:encoded>' . "\n";
            }
            if (!empty($post['featured_image_url'])) {
                $imgUrl = str_starts_with($post['featured_image_url'], 'http')
                    ? $post['featured_image_url']
                    : $siteUrl . $post['featured_image_url'];
                echo '    <enclosure url="' . htmlspecialchars($imgUrl) . '" type="image/jpeg" length="0"/>' . "\n";
            }
            echo '  </item>' . "\n";
        }

        echo '</channel>' . "\n";
        echo '</rss>' . "\n";
        exit;
    }

    private function sendNewCommentNotification(array $post, string $authorName, string $authorEmail, string $body, string $blogBase): void
    {
        try {
            $author = (new \Core\Model('users'))
                ->select('email, name')
                ->where('id', $post['created_by'])
                ->get(1);

            if (!$author || empty($author['email'])) {
                return;
            }

            $settings    = array_column((new \Core\Model('settings'))->get() ?: [], 'value', 'key');
            $baseUrl     = $settings['site_url'] ?? $this->baseUrl;
            $postUrl     = rtrim($baseUrl, '/') . '/' . ltrim($blogBase, '/') . '/' . $post['slug'];
            $moderateUrl = rtrim($baseUrl, '/') . '/admin/blog/comments';

            $html = \App\Mail\MailTemplate::render('comment_pending', [
                'authorName'  => $authorName,
                'authorEmail' => $authorEmail,
                'postTitle'   => $post['title'],
                'postUrl'     => $postUrl,
                'moderateUrl' => $moderateUrl,
                'commentBody' => $body,
                'siteName'    => $settings['site_name'] ?? 'Vertext CMS',
                'siteUrl'     => $baseUrl,
            ]);

            $message = (new \App\Mail\MailMessage())
                ->to($author['email'], $author['name'] ?? '')
                ->subject('New comment pending review - ' . ($post['title'] ?? ''))
                ->htmlBody($html);

            \App\Mail\Mailer::make()->send($message);
        } catch (\Throwable) {
            // Email failure must not break comment submission
        }
    }

    private function threadComments(array $flat): array
    {
        $byId = [];
        foreach ($flat as &$c) {
            $c['replies'] = [];
            $byId[$c['id']] = &$c;
        }
        unset($c);
        $tree = [];
        foreach ($byId as &$c) {
            $pid = $c['parent_comment_id'] ?? null;
            if ($pid && isset($byId[$pid])) {
                $byId[$pid]['replies'][] = &$c;
            } else {
                $tree[] = &$c;
            }
        }
        return $tree;
    }

    private function postSeries(string $postId): array
    {
        try {
            $row = (new \Core\Model('post_series_posts'))
                ->select('post_series_posts.series_id, post_series_posts.sort_order, post_series.title, post_series.slug AS series_slug, post_series.description')
                ->join('post_series', 'post_series.id = post_series_posts.series_id', 'INNER')
                ->where('post_series_posts.post_id', $postId)
                ->whereNull('post_series.deleted_at')
                ->get(1);

            if (!$row) return [];

            $all = (new \Core\Model('post_series_posts'))
                ->select('post_series_posts.post_id, post_series_posts.sort_order, posts.title, posts.slug')
                ->join('posts', 'posts.id = post_series_posts.post_id', 'INNER')
                ->where('post_series_posts.series_id', $row['series_id'])
                ->whereNull('posts.deleted_at')
                ->whereRaw("(posts.status = 'published' OR (posts.status = 'scheduled' AND posts.published_at <= NOW()))", [])
                ->orderBy('post_series_posts.sort_order', 'ASC')
                ->get() ?: [];

            $cur = (int) $row['sort_order'];
            $prev = $next = null;
            foreach ($all as $sp) {
                $o = (int) $sp['sort_order'];
                if ($o < $cur && (!$prev || $o > (int) $prev['sort_order'])) $prev = $sp;
                if ($o > $cur && (!$next || $o < (int) $next['sort_order'])) $next = $sp;
            }

            return [
                'title'         => $row['title'],
                'slug'          => $row['series_slug'],
                'posts'         => $all,
                'current_order' => $cur,
                'prev'          => $prev,
                'next'          => $next,
            ];
        } catch (\Throwable) {
            return [];
        }
    }

    private function relatedPosts(string $postId, array $tagIds, array $catIds, int $limit = 4): array
    {
        if (empty($tagIds) && empty($catIds)) return [];
        try {
            $binds      = [':postId' => $postId];
            $conditions = [];

            if (!empty($tagIds)) {
                $ph = implode(',', array_map(fn($i) => ":tid{$i}", array_keys($tagIds)));
                $conditions[] = "EXISTS (SELECT 1 FROM post_tag_pivot tp WHERE tp.post_id = p.id AND tp.tag_id IN ({$ph}))";
                foreach ($tagIds as $i => $id) { $binds[":tid{$i}"] = (string) $id; }
            }
            if (!empty($catIds)) {
                $ph = implode(',', array_map(fn($i) => ":cid{$i}", array_keys($catIds)));
                $conditions[] = "EXISTS (SELECT 1 FROM post_category_pivot cp WHERE cp.post_id = p.id AND cp.category_id IN ({$ph}))";
                foreach ($catIds as $i => $id) { $binds[":cid{$i}"] = (string) $id; }
            }

            $whereOr = implode(' OR ', $conditions);
            $visible = "(p.status = 'published' OR (p.status = 'scheduled' AND p.published_at <= NOW())) AND (p.expire_at IS NULL OR p.expire_at > NOW())";

            $sql = "SELECT DISTINCT p.id, p.title, p.slug, p.excerpt, p.published_at, p.featured_image_url, u.name AS author_name
                    FROM posts p
                    LEFT JOIN users u ON u.id = p.created_by
                    WHERE p.id != :postId AND p.deleted_at IS NULL AND {$visible} AND ({$whereOr})
                    LIMIT {$limit}";

            $db = (new \Core\Model('posts'))->db;
            $db->query($sql);
            $db->arrayBind($binds);
            $db->execute();
            return $db->resultset() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function postCategories(string|int $postId): array
    {
        try {
            return (new \Core\Model('post_categories'))
                ->select('post_categories.id, post_categories.name, post_categories.slug')
                ->join('post_category_pivot', 'post_category_pivot.category_id = post_categories.id', 'INNER')
                ->where('post_category_pivot.post_id', (string) $postId)
                ->get() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function postTags(string|int $postId): array
    {
        try {
            return (new \Core\Model('post_tags'))
                ->select('post_tags.id, post_tags.name, post_tags.slug')
                ->join('post_tag_pivot', 'post_tag_pivot.tag_id = post_tags.id', 'INNER')
                ->where('post_tag_pivot.post_id', (string) $postId)
                ->get() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function settingBool(string $key, bool $default = false): bool
    {
        $row = (new \Core\Model('settings'))
            ->select('value')
            ->where('key', $key)
            ->where('grp', 'blog')
            ->get(1);
        return $row ? (bool) $row['value'] : $default;
    }
}
