<?php

declare(strict_types=1);

namespace App\Modules\Search\Controllers\Admin;

use App\Controllers\Admin\BaseController;
use App\CMS\Auth;

/**
 * Search admin - index stats + reindex trigger.
 *
 * GET  /admin/search          index()
 * POST /admin/search/reindex  reindex()
 */
class SearchAdminController extends BaseController
{
    protected string $module = 'search';

    public function __construct()
    {
        parent::__construct();
    }

    public function index(): void
    {
        $this->requirePermission('search.manage');

        $total = (int) ($this->db('search_index')->totalRows() ?: 0);

        $byType = $this->db('search_index')
            ->select('content_type, COUNT(*) AS cnt')
            ->groupBy('content_type')
            ->get() ?: [];
        $counts = array_column($byType, 'cnt', 'content_type');

        $lastIndexed = $this->db('search_index')
            ->select('MAX(indexed_at) AS last_at')
            ->get(1);

        $this->adminRender('modules/search/admin/search/index', [
            'total'       => $total,
            'counts'      => $counts,
            'lastIndexed' => $lastIndexed['last_at'] ?? null,
        ], 'Search', 'search');
    }

    public function reindex(): void
    {
        $this->requirePermission('search.manage');

        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->json(['success' => false, 'message' => 'Security token invalid.'], 403);
        }

        $module = new \App\Modules\Search\Module();
        $module->reindex($this->dbConnection());

        Auth::audit('search.reindex', 'search_index', '');
        $this->json(['success' => true, 'message' => 'Search index rebuilt successfully.']);
    }

    private function dbConnection(): \Core\Database\Connection
    {
        return (new \Core\Model('search_index'))->db;
    }
}
