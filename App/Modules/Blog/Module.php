<?php

declare(strict_types=1);

namespace App\Modules\Blog;

use App\CMS\ModuleInterface;

/**
 * Blog Module v2 - lifecycle class
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
        // Detect users.id type so created_by/updated_by/deleted_by use a compatible type for JOINs
        $userIdType = 'UUID';
        try {
            $r = \Core\Model::on($db, 'information_schema.columns')
                ->select('data_type')->where('table_name', 'users')
                ->where('column_name', 'id')->where('table_schema', 'public')->get(1);
            if ($r && stripos($r['data_type'] ?? '', 'int') !== false) {
                $userIdType = 'BIGINT';
            }
        } catch (\Exception) {}

        $db->query("CREATE TABLE IF NOT EXISTS posts (
            id                UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
            title             VARCHAR(255) NOT NULL,
            slug              VARCHAR(255) UNIQUE NOT NULL,
            body              TEXT,
            excerpt           TEXT,
            status            VARCHAR(20)  DEFAULT 'draft',
            created_by        {$userIdType},
            published_at      TIMESTAMP,
            featured_image_id UUID,
            featured_image_url VARCHAR(500),
            meta_title        VARCHAR(160),
            meta_description  VARCHAR(320),
            reading_time      SMALLINT     DEFAULT 0,
            lang              VARCHAR(10)  NOT NULL DEFAULT 'en',
            created_at        TIMESTAMP    DEFAULT NOW(),
            updated_at        TIMESTAMP    DEFAULT NOW(),
            updated_by        {$userIdType},
            deleted_at        TIMESTAMP,
            deleted_by        {$userIdType}
        )");
        $db->execute();

        // FKs added separately - survives when users.id type doesn't match UUID yet.
        // SAVEPOINT/ROLLBACK TO clears the aborted-transaction state on failure.
        try {
            $db->query("SAVEPOINT sp_posts_users_fk"); $db->execute();
            $db->query("ALTER TABLE posts DROP CONSTRAINT IF EXISTS posts_created_by_fkey"); $db->execute();
            $db->query("ALTER TABLE posts ADD CONSTRAINT posts_created_by_fkey FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL"); $db->execute();
            $db->query("ALTER TABLE posts DROP CONSTRAINT IF EXISTS posts_updated_by_fkey"); $db->execute();
            $db->query("ALTER TABLE posts ADD CONSTRAINT posts_updated_by_fkey FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL"); $db->execute();
            $db->query("ALTER TABLE posts DROP CONSTRAINT IF EXISTS posts_deleted_by_fkey"); $db->execute();
            $db->query("ALTER TABLE posts ADD CONSTRAINT posts_deleted_by_fkey FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL"); $db->execute();
            $db->query("RELEASE SAVEPOINT sp_posts_users_fk"); $db->execute();
        } catch (\Exception) {
            try { $db->query("ROLLBACK TO SAVEPOINT sp_posts_users_fk"); $db->execute(); } catch (\Exception) {}
        }

        // Correct created_by type if the table was created before this detection was added
        // (safe: only runs when there is a type mismatch, which implies no valid data exists)
        if ($userIdType === 'BIGINT') {
            try {
                $db->query("SAVEPOINT sp_fix_created_by_type"); $db->execute();
                $cr = \Core\Model::on($db, 'information_schema.columns')
                    ->select('data_type')->where('table_name', 'posts')
                    ->where('column_name', 'created_by')->where('table_schema', 'public')->get(1);
                if ($cr && strtolower($cr['data_type'] ?? '') === 'uuid') {
                    $db->query("ALTER TABLE posts DROP COLUMN IF EXISTS created_by"); $db->execute();
                    $db->query("ALTER TABLE posts ADD COLUMN created_by BIGINT"); $db->execute();
                }
                $db->query("RELEASE SAVEPOINT sp_fix_created_by_type"); $db->execute();
            } catch (\Exception) {
                try { $db->query("ROLLBACK TO SAVEPOINT sp_fix_created_by_type"); $db->execute(); } catch (\Exception) {}
            }
        }

        // Safely add new columns to an existing posts table (re-install safe)
        foreach ([
            "ALTER TABLE posts ADD COLUMN IF NOT EXISTS featured_image_id  UUID",
            "ALTER TABLE posts ADD COLUMN IF NOT EXISTS featured_image_url VARCHAR(500)",
            "ALTER TABLE posts ADD COLUMN IF NOT EXISTS meta_title         VARCHAR(160)",
            "ALTER TABLE posts ADD COLUMN IF NOT EXISTS meta_description   VARCHAR(320)",
            "ALTER TABLE posts ADD COLUMN IF NOT EXISTS reading_time       SMALLINT DEFAULT 0",
            "ALTER TABLE posts ADD COLUMN IF NOT EXISTS expire_at          TIMESTAMP",
        ] as $ddl) {
            $db->query($ddl);
            $db->execute();
        }

        // ── Content revisions (shared with Pages module) ─────────────────────
        $db->query("CREATE TABLE IF NOT EXISTS content_revisions (
            id               UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
            content_type     VARCHAR(20)  NOT NULL,
            content_id       UUID         NOT NULL,
            revision_number  INT          NOT NULL DEFAULT 1,
            title            VARCHAR(255),
            body             TEXT,
            status           VARCHAR(20),
            slug             VARCHAR(255),
            excerpt          TEXT,
            meta_title       VARCHAR(255),
            meta_description TEXT,
            created_at       TIMESTAMP    DEFAULT NOW(),
            updated_at       TIMESTAMP    DEFAULT NOW(),
            created_by       UUID,
            updated_by       UUID
        )");
        $db->execute();

        $db->query("CREATE INDEX IF NOT EXISTS idx_content_revisions_content ON content_revisions (content_type, content_id)");
        $db->execute();

        // Add columns to tables created before this version
        foreach ([
            "ALTER TABLE content_revisions ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT NOW()",
            "ALTER TABLE content_revisions ADD COLUMN IF NOT EXISTS updated_by UUID",
            "ALTER TABLE content_revisions ADD COLUMN IF NOT EXISTS slug VARCHAR(255)",
            "ALTER TABLE content_revisions ADD COLUMN IF NOT EXISTS excerpt TEXT",
            "ALTER TABLE content_revisions ADD COLUMN IF NOT EXISTS meta_title VARCHAR(255)",
            "ALTER TABLE content_revisions ADD COLUMN IF NOT EXISTS meta_description TEXT",
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
            created_at  TIMESTAMP    DEFAULT NOW(),
            updated_at  TIMESTAMP    DEFAULT NOW(),
            deleted_at  TIMESTAMP,
            created_by  {$userIdType},
            updated_by  {$userIdType},
            deleted_by  {$userIdType}
        )");
        $db->execute();

        try {
            $db->query("SAVEPOINT sp_post_cat_users_fk"); $db->execute();
            $db->query("ALTER TABLE post_categories DROP CONSTRAINT IF EXISTS post_categories_created_by_fkey"); $db->execute();
            $db->query("ALTER TABLE post_categories ADD CONSTRAINT post_categories_created_by_fkey FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL"); $db->execute();
            $db->query("ALTER TABLE post_categories DROP CONSTRAINT IF EXISTS post_categories_updated_by_fkey"); $db->execute();
            $db->query("ALTER TABLE post_categories ADD CONSTRAINT post_categories_updated_by_fkey FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL"); $db->execute();
            $db->query("ALTER TABLE post_categories DROP CONSTRAINT IF EXISTS post_categories_deleted_by_fkey"); $db->execute();
            $db->query("ALTER TABLE post_categories ADD CONSTRAINT post_categories_deleted_by_fkey FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL"); $db->execute();
            $db->query("RELEASE SAVEPOINT sp_post_cat_users_fk"); $db->execute();
        } catch (\Exception) {
            try { $db->query("ROLLBACK TO SAVEPOINT sp_post_cat_users_fk"); $db->execute(); } catch (\Exception) {}
        }

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
            name       VARCHAR(80)  UNIQUE NOT NULL,
            slug       VARCHAR(80)  UNIQUE NOT NULL,
            created_at TIMESTAMP    DEFAULT NOW(),
            updated_at TIMESTAMP    DEFAULT NOW(),
            deleted_at TIMESTAMP,
            created_by {$userIdType},
            updated_by {$userIdType},
            deleted_by {$userIdType}
        )");
        $db->execute();

        try {
            $db->query("SAVEPOINT sp_post_tags_users_fk"); $db->execute();
            $db->query("ALTER TABLE post_tags DROP CONSTRAINT IF EXISTS post_tags_created_by_fkey"); $db->execute();
            $db->query("ALTER TABLE post_tags ADD CONSTRAINT post_tags_created_by_fkey FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL"); $db->execute();
            $db->query("ALTER TABLE post_tags DROP CONSTRAINT IF EXISTS post_tags_updated_by_fkey"); $db->execute();
            $db->query("ALTER TABLE post_tags ADD CONSTRAINT post_tags_updated_by_fkey FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL"); $db->execute();
            $db->query("ALTER TABLE post_tags DROP CONSTRAINT IF EXISTS post_tags_deleted_by_fkey"); $db->execute();
            $db->query("ALTER TABLE post_tags ADD CONSTRAINT post_tags_deleted_by_fkey FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL"); $db->execute();
            $db->query("RELEASE SAVEPOINT sp_post_tags_users_fk"); $db->execute();
        } catch (\Exception) {
            try { $db->query("ROLLBACK TO SAVEPOINT sp_post_tags_users_fk"); $db->execute(); } catch (\Exception) {}
        }

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

        // ── Threaded comments ─────────────────────────────────────────────────
        $db->query("ALTER TABLE blog_comments ADD COLUMN IF NOT EXISTS parent_comment_id UUID");
        $db->execute();

        try {
            $db->query("SAVEPOINT sp_blog_comments_parent_fk"); $db->execute();
            $db->query("ALTER TABLE blog_comments DROP CONSTRAINT IF EXISTS blog_comments_parent_comment_id_fkey"); $db->execute();
            $db->query("ALTER TABLE blog_comments ADD CONSTRAINT blog_comments_parent_comment_id_fkey FOREIGN KEY (parent_comment_id) REFERENCES blog_comments(id) ON DELETE CASCADE"); $db->execute();
            $db->query("RELEASE SAVEPOINT sp_blog_comments_parent_fk"); $db->execute();
        } catch (\Exception) {
            try { $db->query("ROLLBACK TO SAVEPOINT sp_blog_comments_parent_fk"); $db->execute(); } catch (\Exception) {}
        }

        // ── Post Series ───────────────────────────────────────────────────────
        $db->query("CREATE TABLE IF NOT EXISTS post_series (
            id          UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
            title       VARCHAR(255) NOT NULL,
            slug        VARCHAR(255) UNIQUE NOT NULL,
            description TEXT,
            created_at  TIMESTAMP    DEFAULT NOW(),
            updated_at  TIMESTAMP    DEFAULT NOW(),
            deleted_at  TIMESTAMP,
            created_by  {$userIdType},
            updated_by  {$userIdType},
            deleted_by  {$userIdType}
        )");
        $db->execute();

        $db->query("CREATE TABLE IF NOT EXISTS post_series_posts (
            series_id   UUID     NOT NULL,
            post_id     UUID     NOT NULL,
            sort_order  SMALLINT DEFAULT 0,
            PRIMARY KEY (series_id, post_id),
            FOREIGN KEY (series_id) REFERENCES post_series(id)  ON DELETE CASCADE,
            FOREIGN KEY (post_id)   REFERENCES posts(id)        ON DELETE CASCADE
        )");
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

        // Auto-insert into primary navigation if Navigation module is installed
        // Uses the configured base path (defaults to /blog). Skipped when the
        // base path is root ("/") since Blog is then the homepage and a
        // separate "Blog" nav link would be redundant.
        $pathRow = \Core\Model::on($db, 'settings')
            ->select('value')->where('key', 'blog_base_path')->where('grp', 'blog')->get(1);
        $rawInstallPath = trim($pathRow['value'] ?? 'blog', '/');
        if ($rawInstallPath === '') {
            return;
        }
        $blogPath = '/' . $rawInstallPath;
        try {
            $db->query("SAVEPOINT sp_blog_nav"); $db->execute();
            $pm = \Core\Model::on($db, 'nav_menus')->select('id')->where('slug', 'primary')->get(1);
            if ($pm) {
                $exists = \Core\Model::on($db, 'nav_items')
                    ->where('menu_id', $pm['id'])->where('url', $blogPath)->get(1);
                if (!$exists) {
                    $order = (int) (\Core\Model::on($db, 'nav_items')
                        ->where('menu_id', $pm['id'])->whereRaw('parent_id IS NULL', [])->totalRows() ?: 0);
                    \Core\Model::on($db, 'nav_items')->save([
                        'menu_id'     => $pm['id'],
                        'type'        => 'module',
                        'label'       => 'Blog',
                        'url'         => $blogPath,
                        'sort_order'  => $order,
                        'open_in_new' => false,
                    ]);
                }
            }
            $db->query("RELEASE SAVEPOINT sp_blog_nav"); $db->execute();
        } catch (\Exception) {
            try { $db->query("ROLLBACK TO SAVEPOINT sp_blog_nav"); $db->execute(); } catch (\Exception) {}
        }
    }

    public function uninstall(\Core\Database\Connection $db): void
    {
        foreach ([
            'post_series_posts',
            'post_series',
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

    /** Normalized blog base path segment (e.g. "blog", "docs/news", or "" for root). */
    public static function basePath(): string
    {
        $row = (new \Core\Model('settings'))
            ->select('value')
            ->where('key', 'blog_base_path')
            ->where('grp', 'blog')
            ->get(1);

        return trim($row['value'] ?? 'blog', '/');
    }

    /**
     * Keep Blog's auto-registered primary-nav item in sync with the configured
     * base path: update its URL when the path changes, remove it when Blog
     * becomes the homepage (newPath === ''), or re-create it if it was
     * previously removed and Blog has since moved off the homepage.
     *
     * Matched by (type, label) rather than URL: the URL is exactly what's
     * changing, and a bare "/" value would otherwise be misread as the SQL
     * division operator by BuildersTrait::where()'s operator/value detection.
     */
    public static function syncNavItem(string $newPath): void
    {
        if (!\App\CMS\ModuleLoader::isEnabled('navigation')) {
            return;
        }

        try {
            $menu = (new \Core\Model('nav_menus'))->select('id')->where('slug', 'primary')->get(1);
            if (!$menu) {
                return;
            }

            $item = (new \Core\Model('nav_items'))
                ->where('menu_id', $menu['id'])
                ->where('type', 'module')
                ->where('label', 'Blog')
                ->get(1);

            if ($newPath === '') {
                // Blog is now the homepage - its own nav link is redundant.
                if ($item) {
                    (new \Core\Model('nav_items'))->where('id', $item['id'])->delete();
                }
                return;
            }

            $newUrl = '/' . trim($newPath, '/');

            if ($item) {
                (new \Core\Model('nav_items'))
                    ->where('id', $item['id'])
                    ->update(['url' => $newUrl, 'updated_at' => date('Y-m-d H:i:s')]);
                return;
            }

            // Blog moved off the homepage but has no nav item (e.g. it was
            // removed on a previous change to root) - re-create it like
            // install() does.
            $order = (int) ((new \Core\Model('nav_items'))
                ->where('menu_id', $menu['id'])->whereRaw('parent_id IS NULL', [])->totalRows() ?: 0);
            (new \Core\Model('nav_items'))->save([
                'menu_id'     => $menu['id'],
                'type'        => 'module',
                'label'       => 'Blog',
                'url'         => $newUrl,
                'sort_order'  => $order,
                'open_in_new' => false,
            ]);
        } catch (\Throwable) {
            // Navigation tables may not exist - nav sync is best-effort.
        }
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
        $router->get('/admin/blog/posts/([a-zA-Z0-9\-]+)/revisions',  $c, 'revisions');
        $router->post('/admin/blog/posts/([a-zA-Z0-9\-]+)/revisions/([a-zA-Z0-9\-]+)/restore', $c, 'restoreRevision');
        $router->get('/admin/blog/posts/([a-zA-Z0-9\-]+)/revisions/([a-zA-Z0-9\-]+)/diff',    $c, 'viewRevision');
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

        // Series
        $ser = $ns . 'SeriesController';
        $router->get('/admin/blog/series',                             $ser, 'index');
        $router->get('/admin/blog/series/form',                        $ser, 'createForm');
        $router->post('/admin/blog/series/store',                      $ser, 'store');
        $router->get('/admin/blog/series/([a-zA-Z0-9\-]+)/form',      $ser, 'editForm');
        $router->post('/admin/blog/series/([a-zA-Z0-9\-]+)/update',   $ser, 'update');
        $router->post('/admin/blog/series/([a-zA-Z0-9\-]+)/delete',   $ser, 'delete');

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

        $rawBase = self::basePath();
        $base    = $rawBase === '' ? '' : '/' . $rawBase;

        $router->get($base . '/feed.rss',                                 $front, 'feed');
        $router->get($base === '' ? '/' : $base,                          $front, 'index');
        $router->get($base . '/category/([a-z0-9\-]+)',                   $front, 'category');
        $router->post($base . '/([a-z0-9\-]+)/comment',                   $front, 'submitComment');

        if ($base !== '') {
            // Scoped under a path prefix, so this is safe to register here.
            // When Blog is at the site root ($base === ''), this pattern would
            // become a global single-segment catch-all and — because modules
            // load alphabetically in ModuleManager::loadRoutes() — could shadow
            // other modules' front-end routes (e.g. /contact, /events, /search).
            // In that case it is registered centrally in Config/Routes.php,
            // after all other module routes, instead. See Pages/Module.php for
            // the same convention.
            $router->get($base . '/([a-z0-9\-]+)', $front, 'post');
        }

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
