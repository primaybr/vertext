<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\CMS\Auth;

class AuditController extends BaseController
{
    protected string $module = '';

    public function index(): void
    {
        $this->requirePermission('dashboard.view');

        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 50;
        $offset  = ($page - 1) * $perPage;

        $search   = trim($_GET['q'] ?? '');
        $action   = trim($_GET['action'] ?? '');
        $dateFrom = trim($_GET['from'] ?? '');
        $dateTo   = trim($_GET['to'] ?? '');

        // Build filters as a closure applied to each model separately.
        // Cloning a Phuse ORM model and calling get() on the clone resets shared
        // internal state on the original, stripping joins/wheres from the next call.
        $applyFilters = function (\Core\Model $m) use ($search, $action, $dateFrom, $dateTo): \Core\Model {
            if ($search !== '') {
                $m->whereRaw(
                    "(audit_logs.action ILIKE :sq OR audit_logs.resource_type ILIKE :sq OR audit_logs.resource_id ILIKE :sq OR users.name ILIKE :sq)",
                    [':sq' => '%' . $search . '%']
                );
            }
            if ($action !== '') {
                $m->where('audit_logs.action', $action);
            }
            if ($dateFrom !== '') {
                $m->whereRaw('audit_logs.created_at >= :df', [':df' => $dateFrom . ' 00:00:00']);
            }
            if ($dateTo !== '') {
                $m->whereRaw('audit_logs.created_at <= :dt', [':dt' => $dateTo . ' 23:59:59']);
            }
            return $m;
        };

        $countModel = $applyFilters(
            $this->db('audit_logs')->join('users', 'audit_logs.user_id = users.id', 'LEFT')
        );
        $count = (int) ($countModel->totalRows() ?: 0);

        $dataModel = $applyFilters(
            $this->db('audit_logs')
                ->select('audit_logs.*, users.name AS user_name')
                ->join('users', 'audit_logs.user_id = users.id', 'LEFT')
                ->orderBy('audit_logs.created_at', 'DESC')
        );
        $logs = $dataModel->limitOffset($perPage, $offset)->get() ?: [];

        $distinctActions = array_column(
            $this->db('audit_logs')->select('DISTINCT action')->orderBy('action', 'ASC')->get() ?: [],
            'action'
        );

        $totalPages = (int) ceil($count / $perPage);

        $this->adminRender('admin/audit/index', [
            'logs'            => $logs,
            'total'           => $count,
            'page'            => $page,
            'totalPages'      => $totalPages,
            'perPage'         => $perPage,
            'search'          => $search,
            'actionFilter'    => $action,
            'dateFrom'        => $dateFrom,
            'dateTo'          => $dateTo,
            'distinctActions' => $distinctActions,
        ], 'Audit Log', 'audit-log');
    }
}
