<?php

declare(strict_types=1);

namespace App\Modules\Search;

use App\CMS\ModuleInterface;

class Module implements ModuleInterface
{
    public function install(\Core\Database\Connection $db): void
    {
        // Full-text search index table (stores pre-indexed searchable content)
        $db->query("CREATE TABLE IF NOT EXISTS search_index (
            id           UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
            content_type VARCHAR(20)  NOT NULL,
            content_id   UUID         NOT NULL,
            title        VARCHAR(255) NOT NULL,
            body         TEXT,
            url          VARCHAR(500) NOT NULL,
            indexed_at   TIMESTAMP    DEFAULT NOW(),
            UNIQUE (content_type, content_id)
        )");
        $db->execute();

        $db->query("CREATE INDEX IF NOT EXISTS idx_search_index_type ON search_index (content_type)");
        $db->execute();

        $permSql = "INSERT INTO permissions (name, slug, description, module)
                    VALUES (:name, :slug, :desc, 'search')
                    ON CONFLICT (slug) DO NOTHING";
        foreach ([
            ['Manage Search', 'search.manage', 'Access search admin and reindex'],
        ] as [$name, $slug, $desc]) {
            $db->query($permSql);
            $db->arrayBind([':name' => $name, ':slug' => $slug, ':desc' => $desc]);
            $db->execute();
        }

        $db->query(
            "INSERT INTO role_permissions (role_id, permission_id)
             SELECT r.id, p.id
             FROM   roles r, permissions p
             WHERE  r.slug = 'administrator' AND p.module = 'search'
             ON CONFLICT DO NOTHING"
        );
        $db->execute();

        // Auto-insert into primary navigation if Navigation module is installed
        try {
            $db->query("SAVEPOINT sp_search_nav"); $db->execute();
            $pm = \Core\Model::on($db, 'nav_menus')->select('id')->where('slug', 'primary')->get(1);
            if ($pm) {
                $exists = \Core\Model::on($db, 'nav_items')
                    ->where('menu_id', $pm['id'])->where('url', '/search')->get(1);
                if (!$exists) {
                    $order = (int) (\Core\Model::on($db, 'nav_items')
                        ->where('menu_id', $pm['id'])->whereRaw('parent_id IS NULL', [])->totalRows() ?: 0);
                    \Core\Model::on($db, 'nav_items')->save([
                        'menu_id'     => $pm['id'],
                        'type'        => 'module',
                        'label'       => 'Search',
                        'url'         => '/search',
                        'sort_order'  => $order,
                        'open_in_new' => false,
                    ]);
                }
            }
            $db->query("RELEASE SAVEPOINT sp_search_nav"); $db->execute();
        } catch (\Exception) {
            try { $db->query("ROLLBACK TO SAVEPOINT sp_search_nav"); $db->execute(); } catch (\Exception) {}
        }

        // Seed initial index if Pages/Blog are installed
        $this->reindex($db);
    }

    public function uninstall(\Core\Database\Connection $db): void
    {
        $db->query("DROP TABLE IF EXISTS search_index CASCADE");
        $db->execute();

        $db->query("DELETE FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE module = 'search')");
        $db->execute();

        $db->query("DELETE FROM permissions WHERE module = 'search'");
        $db->execute();
    }

    public function registerRoutes(\Core\Router $router): void
    {
        $adm   = 'App\Modules\Search\Controllers\Admin\SearchAdminController';
        $front = 'App\Modules\Search\Controllers\Front\SearchController';

        $router->get('/admin/search',          $adm, 'index');
        $router->post('/admin/search/reindex', $adm, 'reindex');

        $router->get('/search', $front, 'index');
    }

    public function reindex(\Core\Database\Connection $db): void
    {
        try {
            // Index published pages
            $pages = \Core\Model::on($db, 'pages')
                ->select('id, title, content, slug, status')
                ->get() ?: [];
            foreach ($pages as $page) {
                if (!in_array($page['status'] ?? '', ['published', 'scheduled'])) {
                    continue;
                }
                $db->query(
                    "INSERT INTO search_index (content_type, content_id, title, body, url, indexed_at)
                     VALUES ('page', :cid, :title, :body, :url, NOW())
                     ON CONFLICT (content_type, content_id) DO UPDATE
                     SET title = EXCLUDED.title, body = EXCLUDED.body, url = EXCLUDED.url, indexed_at = NOW()"
                );
                $db->arrayBind([
                    ':cid'   => $page['id'],
                    ':title' => $page['title'],
                    ':body'  => strip_tags($page['content'] ?? ''),
                    ':url'   => '/' . ltrim($page['slug'], '/'),
                ]);
                $db->execute();
            }

            // Index published posts (detect blog base path)
            $pathRow = \Core\Model::on($db, 'settings')
                ->select('value')->where('key', 'blog_base_path')->where('grp', 'blog')->get(1);
            $blogBase = '/' . trim($pathRow['value'] ?? 'blog', '/');

            $posts = \Core\Model::on($db, 'posts')
                ->select('id, title, body, excerpt, slug, status, deleted_at')
                ->get() ?: [];
            foreach ($posts as $post) {
                if (!in_array($post['status'] ?? '', ['published', 'scheduled'])) {
                    continue;
                }
                if (!empty($post['deleted_at'])) {
                    continue;
                }
                $db->query(
                    "INSERT INTO search_index (content_type, content_id, title, body, url, indexed_at)
                     VALUES ('post', :cid, :title, :body, :url, NOW())
                     ON CONFLICT (content_type, content_id) DO UPDATE
                     SET title = EXCLUDED.title, body = EXCLUDED.body, url = EXCLUDED.url, indexed_at = NOW()"
                );
                $db->arrayBind([
                    ':cid'   => $post['id'],
                    ':title' => $post['title'],
                    ':body'  => strip_tags($post['body'] ?? '') . ' ' . ($post['excerpt'] ?? ''),
                    ':url'   => $blogBase . '/' . ltrim($post['slug'], '/'),
                ]);
                $db->execute();
            }
        } catch (\Exception) {}
    }
}
