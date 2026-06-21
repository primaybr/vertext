<?php

declare(strict_types=1);

namespace App\Modules\Blog\Controllers\Front;

use Core\Controller;

/**
 * Issues 301 redirects from old blog base paths to the current one.
 *
 * Registered automatically by Module::registerRoutes() for each slug
 * stored in the blog_redirect_paths setting.
 *
 * No authentication required.
 */
class BlogRedirectController extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    /** Old /prefix → /new-prefix */
    public function index(): never
    {
        $this->toNewBase();
    }

    /** Old /prefix/{slug} → /new-prefix/{slug} */
    public function post(string $slug): never
    {
        $this->toNewBase($slug);
    }

    /** Old /prefix/category/{slug} → /new-prefix/category/{slug} */
    public function category(string $slug): never
    {
        $this->toNewBase('category/' . $slug);
    }

    private function toNewBase(string $tail = ''): never
    {
        $row     = (new \Core\Model('settings'))
            ->select('value')
            ->where('key', 'blog_base_path')
            ->where('grp', 'blog')
            ->get(1);
        $rawBase = trim($row['value'] ?? 'blog', '/');
        $newBase = $rawBase === '' ? '' : '/' . $rawBase;

        $to = $this->baseUrl . $newBase . ($tail !== '' ? '/' . $tail : '');

        http_response_code(301);
        header('Location: ' . $to);
        exit;
    }
}
