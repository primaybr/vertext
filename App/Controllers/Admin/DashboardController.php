<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

/**
 * Admin Dashboard Controller
 */
class DashboardController extends BaseController
{
    protected string $module = 'dashboard';

    public function __construct()
    {
        parent::__construct();
    }

    /** GET /admin or GET /admin/dashboard */
    public function index(): void
    {
        $stats  = $this->getStats();
        $recent = $this->getRecentActivity();

        $this->adminRender('admin/dashboard/index', [
            'stats'  => $stats,
            'recent' => $recent,
        ], 'Dashboard', 'dashboard');
    }

    private function getStats(): array
    {
        $defaults = ['users' => 0, 'roles' => 0, 'modules' => 0, 'settings' => 0];
        try {
            $defaults['users']    = (int) ($this->db('users')->whereNull('deleted_at')->totalRows() ?: 0);
            $defaults['roles']    = (int) ($this->db('roles')->totalRows() ?: 0);
            $defaults['modules']  = (int) ($this->db('modules')->where('status', 'enabled')->totalRows() ?: 0);
            $defaults['settings'] = (int) ($this->db('settings')->totalRows() ?: 0);
        } catch (\Exception) {
            // Return defaults if DB unavailable
        }
        return $defaults;
    }

    private function getRecentActivity(): array
    {
        try {
            return $this->db('audit_logs')
                ->select('audit_logs.action, audit_logs.resource_type, audit_logs.created_at, users.name AS user_name')
                ->join('users', 'users.id = audit_logs.user_id', 'LEFT')
                ->orderBy('audit_logs.created_at', 'DESC')
                ->get(10) ?: [];
        } catch (\Exception) {
            return [];
        }
    }
}
