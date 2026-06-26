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

        // Load all blog settings
        $rows = (new \Core\Model('settings'))->where('grp', 'blog')->get() ?: [];
        foreach ($rows as $row) {
            $this->settings[$row['key']] = $row['value'];
        }

        $this->perPage = max(1, (int) ($this->settings['posts_per_page'] ?? 10));
    }

    public function index(): void
    {
        $page   = max(1, (int) ($this->input->get('page') ?? 1));
        $offset = ($page - 1) * $this->perPage;

        $posts = (new \Core\Model('posts'))
            ->select('posts.id, posts.title, posts.slug, posts.excerpt, posts.published_at,
                      posts.reading_time, posts.featured_image_url,
                      users.name AS author_name')
            ->join('users', 'users.id = posts.author_id', 'LEFT')
            ->where('posts.status', 'published')
            ->whereNull('posts.deleted_at')
            ->orderBy('posts.published_at', 'DESC')
            ->limitOffset($this->perPage, $offset)
            ->get() ?: [];

        $total = (int) ((new \Core\Model('posts'))
            ->where('status', 'published')
            ->whereNull('deleted_at')
            ->totalRows() ?: 0);

        // Attach categories to each post
        foreach ($posts as &$p) {
            $p['categories'] = $this->postCategories((int) $p['id']);
        }
        unset($p);

        ThemeEngine::render('modules/blog/front/index', [
            'posts'            => $posts,
            'total'            => $total,
            'page'             => $page,
            'pages'            => max(1, (int) ceil($total / $this->perPage)),
            'settings'         => $this->settings,
            'baseUrl'          => $this->baseUrl,
            'page_title'       => $this->settings['blog_title'] ?? 'Blog',
            'page_description' => $this->settings['blog_description'] ?? '',
        ]);
    }

    public function post(string $slug): void
    {
        $post = (new \Core\Model('posts'))
            ->select('posts.*, users.name AS author_name')
            ->join('users', 'users.id = posts.author_id', 'LEFT')
            ->where('posts.slug', $slug)
            ->where('posts.status', 'published')
            ->whereNull('posts.deleted_at')
            ->get(1);

        if (!$post) {
            http_response_code(404);
            $this->render('errors/404', ['baseUrl' => $this->baseUrl]);
            return;
        }

        $post['categories'] = $this->postCategories((int) $post['id']);
        $post['tags']       = $this->postTags((int) $post['id']);

        // Comments enabled?
        $commentsEnabled = $this->settingBool('comments_enabled', true);
        $comments        = [];
        if ($commentsEnabled) {
            $comments = (new \Core\Model('blog_comments'))
                ->where('post_id', $post['id'])
                ->where('status', 'approved')
                ->orderBy('created_at', 'ASC')
                ->get() ?: [];
        }

        $commentFlash = $this->session->flash('blog_comment_flash') ?: [];

        $pageTitle = !empty($post['meta_title']) ? $post['meta_title'] : $post['title'];
        $pageDesc  = $post['meta_description'] ?? $post['excerpt'] ?? '';

        ThemeEngine::render('modules/blog/front/post', [
            'post'             => $post,
            'comments'         => $comments,
            'commentsEnabled'  => $commentsEnabled,
            'commentFlash'     => is_array($commentFlash) ? $commentFlash : [],
            'settings'         => $this->settings,
            'csrf_token'       => $this->csrf->getToken(),
            'baseUrl'          => $this->baseUrl,
            'page_title'       => $pageTitle,
            'page_description' => $pageDesc,
            'page_image'       => $post['featured_image_url'] ?? $this->settings['og_default_image'] ?? '',
        ]);
    }

    public function category(string $slug): void
    {
        $category = (new \Core\Model('post_categories'))
            ->where('slug', $slug)
            ->get(1);

        if (!$category) {
            http_response_code(404);
            $this->render('errors/404', ['baseUrl' => $this->baseUrl]);
            return;
        }

        $page   = max(1, (int) ($this->input->get('page') ?? 1));
        $offset = ($page - 1) * $this->perPage;

        $posts = (new \Core\Model('posts'))
            ->select('posts.id, posts.title, posts.slug, posts.excerpt, posts.published_at,
                      posts.reading_time, posts.featured_image_url, users.name AS author_name')
            ->join('users', 'users.id = posts.author_id', 'LEFT')
            ->join('post_category_pivot', 'post_category_pivot.post_id = posts.id', 'INNER')
            ->where('post_category_pivot.category_id', $category['id'])
            ->where('posts.status', 'published')
            ->whereNull('posts.deleted_at')
            ->orderBy('posts.published_at', 'DESC')
            ->limitOffset($this->perPage, $offset)
            ->get() ?: [];

        $total = (int) ((new \Core\Model('posts'))
            ->join('post_category_pivot', 'post_category_pivot.post_id = posts.id', 'INNER')
            ->where('post_category_pivot.category_id', $category['id'])
            ->where('posts.status', 'published')
            ->whereNull('posts.deleted_at')
            ->totalRows() ?: 0);

        foreach ($posts as &$p) {
            $p['categories'] = $this->postCategories((int) $p['id']);
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

        if (!$authorName || !$body) {
            $this->session->set('blog_comment_flash', ['type' => 'error', 'message' => 'Name and comment are required.']);
            $this->redirect($this->baseUrl . $blogBase . '/' . $slug);
        }

        $requireApproval = $this->settingBool('comments_require_approval', true);
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP']
            ?? $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '';

        (new \Core\Model('blog_comments'))->save([
            'post_id'      => (int) $post['id'],
            'author_name'  => $authorName,
            'author_email' => $authorEmail ?: null,
            'body'         => $body,
            'status'       => $requireApproval ? 'pending' : 'approved',
            'ip_address'   => substr($ip, 0, 45),
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
            ->join('users', 'users.id = posts.author_id', 'LEFT')
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
                ->where('id', $post['author_id'])
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

    private function postCategories(int $postId): array
    {
        return (new \Core\Model('post_categories'))
            ->select('post_categories.id, post_categories.name, post_categories.slug')
            ->join('post_category_pivot', 'post_category_pivot.category_id = post_categories.id', 'INNER')
            ->where('post_category_pivot.post_id', $postId)
            ->get() ?: [];
    }

    private function postTags(int $postId): array
    {
        return (new \Core\Model('post_tags'))
            ->select('post_tags.id, post_tags.name, post_tags.slug')
            ->join('post_tag_pivot', 'post_tag_pivot.tag_id = post_tags.id', 'INNER')
            ->where('post_tag_pivot.post_id', $postId)
            ->get() ?: [];
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
