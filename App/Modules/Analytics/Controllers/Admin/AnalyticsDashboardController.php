<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Controllers\Admin;

use App\Controllers\Admin\BaseController;

/**
 * Analytics dashboard.
 *
 * GET /admin/analytics        index()
 * GET /admin/analytics/data   data()  (JSON for charts)
 */
class AnalyticsDashboardController extends BaseController
{
    protected string $module = 'analytics';

    public function __construct()
    {
        parent::__construct();
    }

    public function index(): void
    {
        $this->requirePermission('analytics.view');

        $today = date('Y-m-d');

        $viewsToday = (int) ($this->db('analytics_pageviews')
            ->whereRaw('viewed_at >= :d::date', [':d' => $today])
            ->totalRows() ?: 0);

        $viewsWeek = (int) ($this->db('analytics_pageviews')
            ->whereRaw("viewed_at >= NOW() - INTERVAL '7 days'", [])
            ->totalRows() ?: 0);

        $viewsMonth = (int) ($this->db('analytics_pageviews')
            ->whereRaw("viewed_at >= NOW() - INTERVAL '30 days'", [])
            ->totalRows() ?: 0);

        // Top pages (last 30 days)
        $topPages = $this->db('analytics_pageviews')
            ->select('url_path, MAX(page_title) AS page_title, COUNT(*) AS views')
            ->whereRaw("viewed_at >= NOW() - INTERVAL '30 days'", [])
            ->groupBy('url_path')
            ->orderBy('views', 'DESC')
            ->limitOffset(10, 0)
            ->get() ?: [];

        // Top referrers (last 30 days)
        $topReferrers = $this->db('analytics_pageviews')
            ->select('referrer_host, COUNT(*) AS views')
            ->whereRaw("viewed_at >= NOW() - INTERVAL '30 days'", [])
            ->whereRaw('referrer_host IS NOT NULL AND referrer_host != :empty', [':empty' => ''])
            ->groupBy('referrer_host')
            ->orderBy('views', 'DESC')
            ->limitOffset(10, 0)
            ->get() ?: [];

        // Daily views for last 30 days (for chart)
        $chartRows = $this->db('analytics_pageviews')
            ->select('DATE(viewed_at) AS day, COUNT(*) AS views')
            ->whereRaw("viewed_at >= NOW() - INTERVAL '30 days'", [])
            ->groupBy('DATE(viewed_at)')
            ->orderBy('day', 'ASC')
            ->get() ?: [];

        // Fill gaps in the 30-day range with 0
        $chartMap = array_column($chartRows, 'views', 'day');
        $chartLabels = [];
        $chartValues = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime("-{$i} days"));
            $chartLabels[] = date('M j', strtotime($day));
            $chartValues[] = (int) ($chartMap[$day] ?? 0);
        }

        $this->adminRender('modules/analytics/admin/analytics/index', [
            'viewsToday'   => $viewsToday,
            'viewsWeek'    => $viewsWeek,
            'viewsMonth'   => $viewsMonth,
            'topPages'     => $topPages,
            'topReferrers' => $topReferrers,
            'chartLabels'  => $chartLabels,
            'chartValues'  => $chartValues,
        ], 'Analytics', 'analytics');
    }

    public function data(): void
    {
        $this->requirePermission('analytics.view');

        $days = max(7, min(90, (int) ($this->input->get('days') ?? 30)));

        $rows = $this->db('analytics_pageviews')
            ->select('DATE(viewed_at) AS day, COUNT(*) AS views')
            ->whereRaw("viewed_at >= NOW() - INTERVAL '{$days} days'", [])
            ->groupBy('DATE(viewed_at)')
            ->orderBy('day', 'ASC')
            ->get() ?: [];

        $map = array_column($rows, 'views', 'day');
        $labels = [];
        $values = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime("-{$i} days"));
            $labels[] = date('M j', strtotime($day));
            $values[] = (int) ($map[$day] ?? 0);
        }

        $this->json(['success' => true, 'labels' => $labels, 'values' => $values]);
    }
}
