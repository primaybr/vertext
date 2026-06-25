<?php

declare(strict_types=1);

namespace App\Modules\Navigation\Controllers\Admin;

use App\Controllers\Admin\BaseController;
use App\CMS\Auth;
use App\CMS\ModuleLoader;

/**
 * Navigation menu management.
 *
 * GET  /admin/navigation                                    index()
 * POST /admin/navigation/store                              store()
 * GET  /admin/navigation/{id}                               builder($id)
 * POST /admin/navigation/{id}/delete                        delete($id)
 * POST /admin/navigation/{id}/items/store                   storeItem($id)
 * POST /admin/navigation/{id}/items/reorder                 reorderItems($id)
 * POST /admin/navigation/{menuId}/items/{itemId}/update     updateItem($menuId, $itemId)
 * POST /admin/navigation/{menuId}/items/{itemId}/delete     deleteItem($menuId, $itemId)
 */
class NavigationController extends BaseController
{
    protected string $module = 'navigation';

    public function __construct()
    {
        parent::__construct();
    }

    public function index(): void
    {
        $this->requirePermission('navigation.view');

        $menus = $this->db('nav_menus')->orderBy('created_at', 'ASC')->get() ?: [];

        // Attach item counts
        foreach ($menus as &$menu) {
            $menu['item_count'] = (int) ($this->db('nav_items')->where('menu_id', $menu['id'])->totalRows() ?: 0);
        }
        unset($menu);

        $this->adminRender('modules/navigation/admin/navigation/index', [
            'menus' => $menus,
        ], 'Navigation', 'navigation');
    }

    public function store(): void
    {
        $this->requirePermission('navigation.manage');
        $this->validateCsrf();

        $name = substr(trim($this->input->post('name', false) ?? ''), 0, 120);
        if (!$name) {
            $this->json(['success' => false, 'message' => 'Menu name is required.']);
        }

        $slug = $this->makeSlug($name);
        $slug = $this->uniqueMenuSlug($slug);

        $this->db('nav_menus')->save(['name' => $name, 'slug' => $slug]);
        Auth::audit('navigation.menu.create', 'nav_menus', '', ['name' => $name]);

        $this->json(['success' => true, 'message' => "Menu \"{$name}\" created."]);
    }

    public function builder(string $id): void
    {
        $this->requirePermission('navigation.view');

        $menu = $this->db('nav_menus')->where('id', $id)->get(1);
        if (!$menu) {
            $this->flash('error', 'Menu not found.');
            $this->redirect($this->baseUrl . '/admin/navigation');
        }

        $items = $this->db('nav_items')
            ->where('menu_id', $id)
            ->orderBy('sort_order', 'ASC')
            ->get() ?: [];

        // Build nested structure: parent items with children
        $parents  = [];
        $children = [];
        foreach ($items as $item) {
            if ($item['parent_id']) {
                $children[$item['parent_id']][] = $item;
            } else {
                $parents[] = $item;
            }
        }

        // Available pages (if Pages module installed)
        $availablePages = [];
        if (ModuleLoader::isEnabled('pages')) {
            $availablePages = $this->db('pages')
                ->select('id, title, slug')
                ->where('status', 'published')
                ->orderBy('sort_order', 'ASC')
                ->get() ?: [];
        }

        $this->adminRender('modules/navigation/admin/navigation/builder', [
            'menu'           => $menu,
            'parents'        => $parents,
            'children'       => $children,
            'availablePages' => $availablePages,
            'pagesEnabled'   => ModuleLoader::isEnabled('pages'),
        ], 'Edit: ' . $menu['name'], 'navigation');
    }

    public function delete(string $id): void
    {
        $this->requirePermission('navigation.manage');
        $this->validateCsrf();

        $menu = $this->db('nav_menus')->where('id', $id)->get(1);
        if (!$menu) {
            $this->json(['success' => false, 'message' => 'Menu not found.']);
        }

        if ($menu['slug'] === 'primary') {
            $this->json(['success' => false, 'message' => 'The primary navigation menu cannot be deleted.']);
        }

        $this->db('nav_menus')->where('id', $id)->delete();
        Auth::audit('navigation.menu.delete', 'nav_menus', $id);

        $this->json(['success' => true, 'message' => 'Menu deleted.']);
    }

    public function storeItem(string $menuId): void
    {
        $this->requirePermission('navigation.manage');
        $this->validateCsrf();

        $menu = $this->db('nav_menus')->where('id', $menuId)->get(1);
        if (!$menu) {
            $this->json(['success' => false, 'message' => 'Menu not found.']);
        }

        $type      = $this->input->post('type') ?? 'custom';
        $label     = substr(trim($this->input->post('label', false) ?? ''), 0, 120);
        $url       = substr(trim($this->input->post('url',   false) ?? ''), 0, 500);
        $pageSlug  = substr(trim($this->input->post('page_slug', false) ?? ''), 0, 255);
        $parentId  = $this->input->post('parent_id') ?: null;
        $openInNew = $this->input->post('open_in_new') ? 1 : 0;

        if (!in_array($type, ['custom', 'page'], true)) {
            $type = 'custom';
        }
        if (!$label) {
            $this->json(['success' => false, 'message' => 'Label is required.']);
        }
        if ($type === 'custom' && !$url) {
            $this->json(['success' => false, 'message' => 'URL is required for custom links.']);
        }
        if ($type === 'page' && !$pageSlug) {
            $this->json(['success' => false, 'message' => 'Page is required.']);
        }

        // Validate parent belongs to this menu (one level only)
        if ($parentId) {
            $parent = $this->db('nav_items')->where('id', $parentId)->where('menu_id', $menuId)->get(1);
            if (!$parent || $parent['parent_id']) {
                $parentId = null; // Ignore invalid or nested parent
            }
        }

        $maxOrder = (int) ($this->db('nav_items')->where('menu_id', $menuId)->whereRaw('parent_id IS NULL', [])->totalRows() ?: 0);

        $this->db('nav_items')->save([
            'menu_id'     => $menuId,
            'parent_id'   => $parentId,
            'type'        => $type,
            'label'       => $label,
            'url'         => $type === 'custom' ? $url : null,
            'page_slug'   => $type === 'page' ? $pageSlug : null,
            'sort_order'  => $maxOrder,
            'open_in_new' => $openInNew,
        ]);

        $this->json(['success' => true, 'message' => 'Item added.']);
    }

    public function updateItem(string $menuId, string $itemId): void
    {
        $this->requirePermission('navigation.manage');
        $this->validateCsrf();

        $item = $this->db('nav_items')->where('id', $itemId)->where('menu_id', $menuId)->get(1);
        if (!$item) {
            $this->json(['success' => false, 'message' => 'Item not found.']);
        }

        $label     = substr(trim($this->input->post('label', false) ?? ''), 0, 120);
        $url       = substr(trim($this->input->post('url',   false) ?? ''), 0, 500);
        $pageSlug  = substr(trim($this->input->post('page_slug', false) ?? ''), 0, 255);
        $openInNew = $this->input->post('open_in_new') ? 1 : 0;

        if (!$label) {
            $this->json(['success' => false, 'message' => 'Label is required.']);
        }

        $updates = [
            'label'       => $label,
            'open_in_new' => $openInNew,
            'updated_at'  => date('Y-m-d H:i:s'),
        ];

        if ($item['type'] === 'custom') {
            $updates['url'] = $url;
        } elseif ($item['type'] === 'page') {
            $updates['page_slug'] = $pageSlug ?: $item['page_slug'];
        }

        $this->db('nav_items')->where('id', $itemId)->update($updates);

        $this->json(['success' => true, 'message' => 'Item updated.']);
    }

    public function deleteItem(string $menuId, string $itemId): void
    {
        $this->requirePermission('navigation.manage');
        $this->validateCsrf();

        $item = $this->db('nav_items')->where('id', $itemId)->where('menu_id', $menuId)->get(1);
        if (!$item) {
            $this->json(['success' => false, 'message' => 'Item not found.']);
        }

        $this->db('nav_items')->where('id', $itemId)->delete();

        $this->json(['success' => true, 'message' => 'Item removed.']);
    }

    public function reorderItems(string $menuId): void
    {
        $this->requirePermission('navigation.manage');
        $this->validateCsrf();

        $menu = $this->db('nav_menus')->where('id', $menuId)->get(1);
        if (!$menu) {
            $this->json(['success' => false, 'message' => 'Menu not found.']);
        }

        // Expect JSON body: [{id: "...", sort_order: 0, parent_id: "..."|null}, ...]
        $raw = file_get_contents('php://input');
        $body = json_decode($raw ?: '{}', true);
        $items = $body['items'] ?? [];

        if (!is_array($items)) {
            $this->json(['success' => false, 'message' => 'Invalid payload.']);
        }

        foreach ($items as $entry) {
            $id         = $entry['id'] ?? null;
            $sortOrder  = (int) ($entry['sort_order'] ?? 0);
            $parentId   = $entry['parent_id'] ?? null;

            if (!$id) {
                continue;
            }

            $this->db('nav_items')->where('id', $id)->where('menu_id', $menuId)->update([
                'sort_order' => $sortOrder,
                'parent_id'  => $parentId ?: null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $this->json(['success' => true]);
    }

    private function makeSlug(string $text): string
    {
        $slug = strtolower(trim($text));
        $slug = preg_replace('/[^a-z0-9\-_]+/', '-', $slug) ?? $slug;
        return trim($slug, '-');
    }

    private function uniqueMenuSlug(string $base): string
    {
        $slug    = $base;
        $counter = 1;
        while ($this->db('nav_menus')->select('id')->where('slug', $slug)->get(1)) {
            $slug = $base . '-' . $counter++;
        }
        return $slug;
    }

    private function validateCsrf(): void
    {
        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            if ($this->isAjax()) {
                $this->json(['success' => false, 'message' => 'Security token invalid.'], 403);
            }
            $this->flash('error', 'Security token invalid. Please try again.');
            $this->redirect($this->baseUrl . '/admin/navigation');
        }
    }
}
