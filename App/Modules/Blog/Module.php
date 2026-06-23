<?php

declare(strict_types=1);

namespace App\Modules\Blog;

use App\CMS\ModuleInterface;

/**
 * Blog Module v2 — lifecycle class
 *
 * Manages tables, permissions, and route registration for the full-featured
 * blog: posts, categories, tags, comments, analytics dashboard, settings,
 * and a public-facing frontend.
 */
class Module implements ModuleInterface
{
    public function install(\Core\Database\Connection $db): void
    {
        // ── Core post table ───────────────────────────────────────────────────
        $db->query("CREATE TABLE IF NOT EXISTS posts (
            id                UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
            title             VARCHAR(255) NOT NULL,
            slug              VARCHAR(255) UNIQUE NOT NULL,
            body              TEXT,
            excerpt           TEXT,
            status            VARCHAR(20)  DEFAULT 'draft',
            author_id         UUID,
            published_at      TIMESTAMP,
            featured_image_id UUID,
            featured_image_url VARCHAR(500),
            meta_title        VARCHAR(160),
            meta_description  VARCHAR(320),
            reading_time      SMALLINT     DEFAULT 0,
            created_at        TIMESTAMP    DEFAULT NOW(),
            updated_at        TIMESTAMP    DEFAULT NOW(),
            deleted_at        TIMESTAMP,
            FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
        )");
        $db->execute();

        // Safely add new columns to an existing posts table (re-install safe)
        foreach ([
            "ALTER TABLE posts ADD COLUMN IF NOT EXISTS featured_image_id  INT",
            "ALTER TABLE posts ADD COLUMN IF NOT EXISTS featured_image_url VARCHAR(500)",
            "ALTER TABLE posts ADD COLUMN IF NOT EXISTS meta_title         VARCHAR(160)",
            "ALTER TABLE posts ADD COLUMN IF NOT EXISTS meta_description   VARCHAR(320)",
            "ALTER TABLE posts ADD COLUMN IF NOT EXISTS reading_time       SMALLINT DEFAULT 0",
        ] as $ddl) {
            $db->query($ddl);
            $db->execute();
        }

        // ── Categories ────────────────────────────────────────────────────────
        $db->query("CREATE TABLE IF NOT EXISTS post_categories (
            id          UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
            name        VARCHAR(120) UNIQUE NOT NULL,
            slug        VARCHAR(120) UNIQUE NOT NULL,
            description TEXT,
            created_at  TIMESTAMP DEFAULT NOW(),
            updated_at  TIMESTAMP DEFAULT NOW()
        )");
        $db->execute();

        $db->query("CREATE TABLE IF NOT EXISTS post_category_pivot (
            post_id     UUID NOT NULL,
            category_id UUID NOT NULL,
            PRIMARY KEY (post_id, category_id),
            FOREIGN KEY (post_id)     REFERENCES posts(id)           ON DELETE CASCADE,
            FOREIGN KEY (category_id) REFERENCES post_categories(id) ON DELETE CASCADE
        )");
        $db->execute();

        // ── Tags ──────────────────────────────────────────────────────────────
        $db->query("CREATE TABLE IF NOT EXISTS post_tags (
            id         UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
            name       VARCHAR(80) UNIQUE NOT NULL,
            slug       VARCHAR(80) UNIQUE NOT NULL,
            created_at TIMESTAMP DEFAULT NOW(),
            updated_at TIMESTAMP DEFAULT NOW()
        )");
        $db->execute();

        $db->query("ALTER TABLE post_tags ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT NOW()");
        $db->execute();

        $db->query("CREATE TABLE IF NOT EXISTS post_tag_pivot (
            post_id UUID NOT NULL,
            tag_id  UUID NOT NULL,
            PRIMARY KEY (post_id, tag_id),
            FOREIGN KEY (post_id) REFERENCES posts(id)     ON DELETE CASCADE,
            FOREIGN KEY (tag_id)  REFERENCES post_tags(id) ON DELETE CASCADE
        )");
        $db->execute();

        // ── Comments ─────────────────────────────────────────────────────────
        $db->query("CREATE TABLE IF NOT EXISTS blog_comments (
            id           UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
            post_id      UUID NOT NULL,
            author_name  VARCHAR(120) NOT NULL,
            author_email VARCHAR(180),
            body         TEXT NOT NULL,
            status       VARCHAR(20) DEFAULT 'pending',
            ip_address   VARCHAR(45),
            created_at   TIMESTAMP DEFAULT NOW(),
            updated_at   TIMESTAMP DEFAULT NOW(),
            FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
        )");
        $db->execute();

        $db->query("ALTER TABLE blog_comments ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT NOW()");
        $db->execute();

        // ── Permissions ───────────────────────────────────────────────────────
        $permSql = "INSERT INTO permissions (name, slug, description, module)
                    VALUES (:name, :slug, :desc, 'blog')
                    ON CONFLICT (slug) DO NOTHING";
        foreach ([
            ['View Posts',          'posts.view',         'View blog posts'],
            ['Create Posts',        'posts.create',       'Create new blog posts'],
            ['Edit Posts',          'posts.edit',         'Edit existing blog posts'],
            ['Publish Posts',       'posts.publish',      'Publish / unpublish blog posts'],
            ['Delete Posts',        'posts.delete',       'Delete blog posts'],
            ['View Categories',     'categories.view',    'View post categories'],
            ['Create Categories',   'categories.create',  'Create post categories'],
            ['Edit Categories',     'categories.edit',    'Edit post categories'],
            ['Delete Categories',   'categories.delete',  'Delete post categories'],
            ['View Tags',           'tags.view',          'View post tags'],
            ['Create Tags',         'tags.create',        'Create post tags'],
            ['Edit Tags',           'tags.edit',          'Edit post tags'],
            ['Delete Tags',         'tags.delete',        'Delete post tags'],
            ['View Comments',       'comments.view',      'View blog comments'],
            ['Moderate Comments',   'comments.moderate',  'Approve or mark comments as spam'],
            ['Delete Comments',     'comments.delete',    'Delete blog comments'],
            ['Blog Settings',       'blog.settings',      'Manage blog-wide settings'],
        ] as [$name, $slug, $desc]) {
            $db->query($permSql);
            $db->arrayBind([':name' => $name, ':slug' => $slug, ':desc' => $desc]);
            $db->execute();
        }

        // Grant all blog permissions to the Administrator role
        $db->query(
            "INSERT INTO role_permissions (role_id, permission_id)
             SELECT r.id, p.id
             FROM   roles r, permissions p
             WHERE  r.slug = 'administrator' AND p.module = 'blog'
             ON CONFLICT DO NOTHING"
        );
        $db->execute();

        // ── Default settings (only seed if not already present) ──────────────
        // Settings survive uninstall intentionally so that reinstalling
        // pre-populates the setup wizard with the user's previous values.
        foreach ([
            ['blog_base_path',      'blog', 'string', 'Blog Base Path'],
            ['blog_redirect_paths', '[]',   'json',   'Blog Redirect Paths'],
        ] as [$key, $val, $type, $label]) {
            $exists = \Core\Model::on($db, 'settings')
                ->select('id')
                ->where('key', $key)
                ->where('grp', 'blog')
                ->get(1);

            if (!$exists) {
                \Core\Model::on($db, 'settings')->save([
                    'key'   => $key,
                    'value' => $val,
                    'grp'   => 'blog',
                    'type'  => $type,
                    'label' => $label,
                ]);
            }
        }
    }

    public function uninstall(\Core\Database\Connection $db): void
    {
        foreach ([
            'post_tag_pivot',
            'post_tags',
            'post_category_pivot',
            'post_categories',
            'blog_comments',
            'posts',
        ] as $table) {
            $db->query("DROP TABLE IF EXISTS {$table} CASCADE");
            $db->execute();
        }

        $db->query("DELETE FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE module = 'blog')");
        $db->execute();

        $db->query("DELETE FROM permissions WHERE module = 'blog'");
        $db->execute();

        // Settings are intentionally kept so that reinstalling pre-populates
        // the setup wizard with the previously configured values.
    }

    public function registerRoutes(\Core\Router $router): void
    {
        $ns   = 'App\Modules\Blog\Controllers\Admin\\';
        $nsF  = 'App\Modules\Blog\Controllers\Front\\';

        // Dashboard
        $router->get('/admin/blog', $ns . 'BlogDashboardController', 'index');

        // Posts
        $c = $ns . 'PostsController';
        $router->get('/admin/blog/posts',                   $c, 'index');
        $router->get('/admin/blog/posts/form',              $c, 'createForm');
        $router->post('/admin/blog/posts/store',            $c, 'store');
        $router->get('/admin/blog/posts/([a-zA-Z0-9\-]+)/form',        $c, 'editForm');
        $router->post('/admin/blog/posts/([a-zA-Z0-9\-]+)/update',     $c, 'update');
        $router->post('/admin/blog/posts/([a-zA-Z0-9\-]+)/delete',     $c, 'delete');
        $router->post('/admin/blog/posts/bulk',             $c, 'bulk');

        // Categories
        $cat = $ns . 'CategoriesController';
        $router->get('/admin/blog/categories',              $cat, 'index');
        $router->get('/admin/blog/categories/form',         $cat, 'createForm');
        $router->post('/admin/blog/categories/store',       $cat, 'store');
        $router->get('/admin/blog/categories/([a-zA-Z0-9\-]+)/form',   $cat, 'editForm');
        $router->post('/admin/blog/categories/([a-zA-Z0-9\-]+)/update',$cat, 'update');
        $router->post('/admin/blog/categories/([a-zA-Z0-9\-]+)/delete',$cat, 'delete');

        // Tags
        $tag = $ns . 'TagsController';
        $router->get('/admin/blog/tags',                    $tag, 'index');
        $router->get('/admin/blog/tags/form',               $tag, 'createForm');
        $router->post('/admin/blog/tags/store',             $tag, 'store');
        $router->get('/admin/blog/tags/([a-zA-Z0-9\-]+)/form',         $tag, 'editForm');
        $router->post('/admin/blog/tags/([a-zA-Z0-9\-]+)/update',      $tag, 'update');
        $router->post('/admin/blog/tags/([a-zA-Z0-9\-]+)/delete',      $tag, 'delete');
        $router->get('/admin/blog/tags/search',             $tag, 'search');

        // Comments
        $cmt = $ns . 'CommentsController';
        $router->get('/admin/blog/comments',                        $cmt, 'index');
        $router->post('/admin/blog/comments/([a-zA-Z0-9\-]+)/approve',         $cmt, 'approve');
        $router->post('/admin/blog/comments/([a-zA-Z0-9\-]+)/spam',            $cmt, 'spam');
        $router->post('/admin/blog/comments/([a-zA-Z0-9\-]+)/delete',          $cmt, 'delete');
        $router->post('/admin/blog/comments/bulk',                  $cmt, 'bulk');

        // Settings
        $set = $ns . 'BlogSettingsController';
        $router->get('/admin/blog/settings',                $set, 'index');
        $router->post('/admin/blog/settings/save',          $set, 'save');

        // Setup wizard
        $setup = $ns . 'BlogSetupController';
        $router->get('/admin/blog/setup',          $setup, 'index');
        $router->post('/admin/blog/setup/complete', $setup, 'complete');

        // ── Public frontend (dynamic base path) ───────────────────────────────
        $front    = $nsF . 'BlogController';
        $redirect = $nsF . 'BlogRedirectController';

        $pathRow = (new \Core\Model('settings'))
            ->select('value')
            ->where('key', 'blog_base_path')
            ->where('grp', 'blog')
            ->get(1);
        $rawBase = trim($pathRow['value'] ?? 'blog', '/');
        $base    = $rawBase === '' ? '' : '/' . $rawBase;

        $router->get($base === '' ? '/' : $base,                          $front, 'index');
        $router->get($base . '/category/([a-z0-9\-]+)',                   $front, 'category');
        $router->post($base . '/([a-z0-9\-]+)/comment',                   $front, 'submitComment');
        $router->get($base . '/([a-z0-9\-]+)',                            $front, 'post');

        // 301 redirects for previously used base paths
        $redirectRow = (new \Core\Model('settings'))
            ->select('value')
            ->where('key', 'blog_redirect_paths')
            ->where('grp', 'blog')
            ->get(1);
        $oldPaths = json_decode($redirectRow['value'] ?? '[]', true) ?: [];

        foreach ($oldPaths as $old) {
            $old = trim((string) $old, '/');
            if ($old === '' || $old === $rawBase) {
                continue;
            }
            $oldBase = '/' . $old;
            $router->get($oldBase,                              $redirect, 'index');
            $router->get($oldBase . '/category/([a-z0-9\-]+)', $redirect, 'category');
            $router->get($oldBase . '/([a-z0-9\-]+)',           $redirect, 'post');
        }
    }
}
