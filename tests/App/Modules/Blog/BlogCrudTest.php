<?php

declare(strict_types=1);

namespace Tests\App\Modules\Blog;

use Tests\App\Support\DatabaseTestCase;
use App\Modules\Blog\Controllers\Front\BlogController;
use Core\Model;

/**
 * Covers Blog post CRUD (via direct Model writes, mirroring what the admin
 * PostsController's store/update actions do) and the public-routing visibility
 * rule - draft posts must never be publicly reachable, published ones must be.
 *
 * Doesn't drive PostsController (admin) directly: its constructor redirects to
 * /admin/login when Auth::check() is false, which - like every redirect() in
 * this codebase - terminates the process rather than returning, so it can't
 * run inside PHPUnit without a real HTTP test client.
 */
final class BlogCrudTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->truncate(['posts', 'blog_comments', 'post_categories', 'post_tags']);
    }

    public function testDraftPostIsNotPubliclyVisible(): void
    {
        $slug = 'draft-post-' . bin2hex(random_bytes(4));
        (new Model('posts'))->save([
            'title'  => 'A Draft Post',
            'slug'   => $slug,
            'body'   => 'Draft body',
            'status' => 'draft',
        ]);

        $visible = (new Model('posts'))
            ->where('slug', $slug)
            ->whereRaw("(status = 'published' OR (status = 'scheduled' AND published_at <= NOW())) AND (expire_at IS NULL OR expire_at > NOW())", [])
            ->whereNull('deleted_at')
            ->get(1);

        $this->assertFalse($visible, 'A draft post must not match the public visibility filter.');

        // BlogController::post() takes the "not found" branch (404, no redirect)
        // for anything the visibility filter excludes - safe to call directly.
        ob_start();
        (new BlogController())->post($slug);
        $output = ob_get_clean();

        $this->assertSame(404, http_response_code());
        $this->assertStringNotContainsString('A Draft Post', $output);
    }

    public function testPublishedPostIsPubliclyVisible(): void
    {
        $slug = 'published-post-' . bin2hex(random_bytes(4));
        (new Model('posts'))->save([
            'title'        => 'A Published Post',
            'slug'         => $slug,
            'body'         => 'Published body',
            'status'       => 'published',
            'published_at' => date('Y-m-d H:i:s', time() - 60),
        ]);

        $visible = (new Model('posts'))
            ->where('slug', $slug)
            ->whereRaw("(status = 'published' OR (status = 'scheduled' AND published_at <= NOW())) AND (expire_at IS NULL OR expire_at > NOW())", [])
            ->whereNull('deleted_at')
            ->get(1);

        $this->assertIsArray($visible, 'A published post must match the public visibility filter.');
        $this->assertSame('A Published Post', $visible['title']);
    }

    public function testExpiredPostIsNotPubliclyVisible(): void
    {
        $slug = 'expired-post-' . bin2hex(random_bytes(4));
        (new Model('posts'))->save([
            'title'        => 'An Expired Post',
            'slug'         => $slug,
            'body'         => 'Expired body',
            'status'       => 'published',
            'published_at' => date('Y-m-d H:i:s', time() - 3600),
            'expire_at'    => date('Y-m-d H:i:s', time() - 60),
        ]);

        $visible = (new Model('posts'))
            ->where('slug', $slug)
            ->whereRaw("(status = 'published' OR (status = 'scheduled' AND published_at <= NOW())) AND (expire_at IS NULL OR expire_at > NOW())", [])
            ->whereNull('deleted_at')
            ->get(1);

        $this->assertFalse($visible, 'A post past its expire_at must not match the public visibility filter.');
    }

    public function testSlugMustBeUnique(): void
    {
        $slug = 'duplicate-slug-' . bin2hex(random_bytes(4));
        (new Model('posts'))->save(['title' => 'First', 'slug' => $slug, 'body' => 'x', 'status' => 'draft']);

        $this->expectException(\Throwable::class);
        (new Model('posts'))->save(['title' => 'Second', 'slug' => $slug, 'body' => 'y', 'status' => 'draft']);
    }
}
