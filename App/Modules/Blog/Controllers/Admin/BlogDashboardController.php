<?php

declare(strict_types=1);

namespace App\Modules\Blog\Controllers\Admin;

use App\Controllers\Admin\BaseController;

/**
 * Blog analytics dashboard.
 * GET /admin/blog
 */
class BlogDashboardController extends BaseController
{
    protected string $module = 'blog';

    public function __construct()
    {
        parent::__construct();
    }

    public function index(): void
    {
        $this->requirePermission('posts.view');

        // Summary counts
        $totalPosts     = (int) ($this->db('posts')->whereNull('deleted_at')->totalRows() ?: 0);
        $published      = (int) ($this->db('posts')->where('status', 'published')->whereNull('deleted_at')->totalRows() ?: 0);
        $drafts         = (int) ($this->db('posts')->where('status', 'draft')->whereNull('deleted_at')->totalRows() ?: 0);
        $pendingComments = 0;
        if (\App\CMS\Auth::can('comments.view')) {
            $pendingComments = (int) ($this->db('blog_comments')->where('status', 'pending')->totalRows() ?: 0);
        }

        // Posts over the last 30 days (day-by-day)
        $chartRows = $this->db('posts')
            ->select("DATE(published_at) AS day, COUNT(*) AS cnt")
            ->where('status', 'published')
            ->whereNull('deleted_at')
            ->whereRaw(
                'published_at >= :since',
                [':since' => date('Y-m-d', strtotime('-29 days'))]
            )
            ->whereRaw('published_at IS NOT NULL', [])
            ->groupBy('DATE(published_at)')
            ->get() ?: [];

        // Build a 30-day label/value array (fill gaps with 0)
        $chartMap = [];
        foreach ($chartRows as $r) {
            $chartMap[$r['day']] = (int) $r['cnt'];
        }
        $chartLabels = [];
        $chartValues = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime("-{$i} days"));
            $chartLabels[] = date('M j', strtotime($day));
            $chartValues[] = $chartMap[$day] ?? 0;
        }

        // Recent posts
        $recentPosts = $this->db('posts')
            ->select('posts.id, posts.title, posts.status, posts.published_at, posts.created_at, users.name AS author_name')
            ->join('users', 'users.id = posts.created_by', 'LEFT')
            ->whereNull('posts.deleted_at')
            ->orderBy('posts.created_at', 'DESC')
            ->limitOffset(8, 0)
            ->get() ?: [];

        // Recent pending comments
        $recentComments = [];
        if (\App\CMS\Auth::can('comments.view')) {
            $recentComments = $this->db('blog_comments')
                ->select('blog_comments.id, blog_comments.author_name, blog_comments.body, blog_comments.created_at, posts.title AS post_title')
                ->join('posts', 'posts.id = blog_comments.post_id', 'LEFT')
                ->where('blog_comments.status', 'pending')
                ->orderBy('blog_comments.created_at', 'DESC')
                ->limitOffset(5, 0)
                ->get() ?: [];
        }

        $this->adminRender('modules/blog/admin/dashboard/index', [
            'totalPosts'      => $totalPosts,
            'published'       => $published,
            'drafts'          => $drafts,
            'pendingComments' => $pendingComments,
            'chartLabels'     => $chartLabels,
            'chartValues'     => $chartValues,
            'recentPosts'     => $recentPosts,
            'recentComments'  => $recentComments,
        ], 'Blog Dashboard', 'blog.dashboard');
    }
}
