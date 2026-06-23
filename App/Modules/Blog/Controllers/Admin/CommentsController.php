<?php

declare(strict_types=1);

namespace App\Modules\Blog\Controllers\Admin;

use App\Controllers\Admin\BaseController;
use App\CMS\Auth;
use App\Mail\Mailer;
use App\Mail\MailMessage;
use App\Mail\MailTemplate;

/**
 * Blog comment moderation.
 *
 * GET  /admin/blog/comments
 * POST /admin/blog/comments/{id}/approve
 * POST /admin/blog/comments/{id}/spam
 * POST /admin/blog/comments/{id}/delete
 * POST /admin/blog/comments/bulk
 */
class CommentsController extends BaseController
{
    protected string $module = 'blog';

    public function __construct()
    {
        parent::__construct();
    }

    public function index(): void
    {
        $this->requirePermission('comments.view');

        $status  = $this->input->get('status') ?? 'pending';
        $search  = trim($this->input->get('search') ?? '');
        $page    = max(1, (int) ($this->input->get('page') ?? 1));
        $perPage = 20;
        $offset  = ($page - 1) * $perPage;

        $allowed = ['pending', 'approved', 'spam', 'trash', 'all'];
        if (!in_array($status, $allowed, true)) {
            $status = 'pending';
        }

        $q  = $this->db('blog_comments')
            ->select('blog_comments.id, blog_comments.author_name, blog_comments.author_email,
                      blog_comments.body, blog_comments.status, blog_comments.ip_address,
                      blog_comments.created_at, posts.title AS post_title, posts.slug AS post_slug')
            ->join('posts', 'posts.id = blog_comments.post_id', 'LEFT')
            ->orderBy('blog_comments.created_at', 'DESC')
            ->limitOffset($perPage, $offset);

        $qc = $this->db('blog_comments');

        if ($status !== 'all') {
            $q->where('blog_comments.status', $status);
            $qc->where('blog_comments.status', $status);
        }

        if ($search) {
            $binds = [':s' => "%{$search}%"];
            $q->whereRaw('(blog_comments.author_name ILIKE :s OR blog_comments.body ILIKE :s)', $binds);
            $qc->whereRaw('(blog_comments.author_name ILIKE :s OR blog_comments.body ILIKE :s)', $binds);
        }

        // Status counts for filter tabs
        $counts = [];
        foreach (['pending', 'approved', 'spam'] as $s) {
            $counts[$s] = (int) ($this->db('blog_comments')->where('status', $s)->totalRows() ?: 0);
        }

        $total    = (int) ($qc->totalRows() ?: 0);
        $comments = $q->get() ?: [];

        $this->adminRender('modules/blog/admin/comments/index', [
            'comments' => $comments,
            'total'    => $total,
            'page'     => $page,
            'pages'    => max(1, (int) ceil($total / $perPage)),
            'search'   => $search,
            'status'   => $status,
            'counts'   => $counts,
        ], 'Comments', 'blog.comments');
    }

    public function approve(string $id): void
    {
        $this->requirePermission('comments.moderate');
        $this->validateCsrf();

        $comment = $this->db('blog_comments')
            ->select('blog_comments.id, blog_comments.author_name, blog_comments.author_email,
                      blog_comments.body, posts.title AS post_title, posts.slug AS post_slug')
            ->join('posts', 'posts.id = blog_comments.post_id', 'LEFT')
            ->where('blog_comments.id', $id)
            ->get(1);

        $this->db('blog_comments')->where('id', $id)->update(['status' => 'approved']);
        Auth::audit('comment.approve', 'blog_comments', $id);

        if ($comment && !empty($comment['author_email'])) {
            $this->sendCommentApprovedEmail($comment);
        }

        $this->json(['success' => true, 'message' => 'Comment approved.']);
    }

    private function sendCommentApprovedEmail(array $comment): void
    {
        try {
            $settings = array_column($this->db('settings')->get() ?: [], 'value', 'key');
            $baseUrl  = $settings['site_url'] ?? $this->baseUrl;
            $blogBase = $settings['blog_base_path'] ?? 'blog';
            $postUrl  = rtrim($baseUrl, '/') . '/' . ltrim($blogBase, '/') . '/' . ($comment['post_slug'] ?? '');

            $html = MailTemplate::render('comment_approved', [
                'authorName'  => $comment['author_name'] ?? 'there',
                'postTitle'   => $comment['post_title'] ?? 'the post',
                'postUrl'     => $postUrl,
                'commentBody' => $comment['body'] ?? '',
                'siteName'    => $settings['site_name'] ?? 'Vertext CMS',
                'siteUrl'     => $baseUrl,
            ]);

            $mailer  = Mailer::make();
            $message = (new MailMessage())
                ->to($comment['author_email'], $comment['author_name'] ?? '')
                ->subject('Your comment was approved — ' . ($settings['site_name'] ?? 'Vertext CMS'))
                ->htmlBody($html);

            $mailer->send($message);
        } catch (\Throwable) {
            // Email failure must not break the approval flow
        }
    }

    public function spam(string $id): void
    {
        $this->requirePermission('comments.moderate');
        $this->validateCsrf();
        $this->db('blog_comments')->where('id', $id)->update(['status' => 'spam']);
        Auth::audit('comment.spam', 'blog_comments', $id);
        $this->json(['success' => true, 'message' => 'Comment marked as spam.']);
    }

    public function delete(string $id): void
    {
        $this->requirePermission('comments.delete');
        $this->validateCsrf();
        $this->db('blog_comments')->where('id', $id)->delete();
        Auth::audit('comment.delete', 'blog_comments', $id);
        $this->json(['success' => true, 'message' => 'Comment deleted.']);
    }

    public function bulk(): void
    {
        $this->requirePermission('comments.moderate');
        $this->validateCsrf();

        $action = $this->input->post('bulk_action') ?? '';
        $ids    = array_filter((array) ($this->input->post('ids', false) ?? []));

        if (empty($ids)) {
            $this->json(['success' => false, 'message' => 'No comments selected.']);
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        match ($action) {
            'approve' => $this->db('blog_comments')->whereRaw("id IN ({$placeholders})", array_values($ids))->update(['status' => 'approved']),
            'spam'    => $this->db('blog_comments')->whereRaw("id IN ({$placeholders})", array_values($ids))->update(['status' => 'spam']),
            'delete'  => $this->db('blog_comments')->whereRaw("id IN ({$placeholders})", array_values($ids))->delete(),
            default   => null,
        };

        $this->json(['success' => true, 'message' => 'Bulk action applied.']);
    }

    private function validateCsrf(): void
    {
        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->json(['success' => false, 'message' => 'Security token invalid.'], 403);
        }
    }
}
