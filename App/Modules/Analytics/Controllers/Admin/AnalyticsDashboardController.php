<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Controllers\Admin;

use App\Controllers\Admin\BaseController;

/**
 * Analytics dashboard.
 *
 * GET /admin/analytics          index()
 * GET /admin/analytics/data     data()   (JSON for charts)
 * GET /admin/analytics/export   export() (CSV download)
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

        [$from, $to] = $this->parseDateRange();

        $fromTs = strtotime($from);
        $toTs   = strtotime($to);
        $days   = max(1, (int)(($toTs - $fromTs) / 86400) + 1);

        // Previous equivalent period (same duration, immediately before $from)
        $prevTo   = date('Y-m-d', $fromTs - 86400);
        $prevFrom = date('Y-m-d', $fromTs - $days * 86400);

        // Selected period total
        $viewsPeriod = (int) ($this->db('analytics_pageviews')
            ->whereRaw('DATE(viewed_at) >= :f AND DATE(viewed_at) <= :t', [':f' => $from, ':t' => $to])
            ->totalRows() ?: 0);

        // Previous period total (for comparison)
        $viewsPrevPeriod = (int) ($this->db('analytics_pageviews')
            ->whereRaw('DATE(viewed_at) >= :f AND DATE(viewed_at) <= :t', [':f' => $prevFrom, ':t' => $prevTo])
            ->totalRows() ?: 0);

        // Period delta percentage
        $deltaPeriod = $viewsPrevPeriod > 0
            ? round((($viewsPeriod - $viewsPrevPeriod) / $viewsPrevPeriod) * 100, 1)
            : null;

        // Today vs yesterday (always fixed, independent of filter)
        $today     = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        $viewsToday = (int) ($this->db('analytics_pageviews')
            ->whereRaw('viewed_at >= :d::date', [':d' => $today])
            ->totalRows() ?: 0);

        $viewsYesterday = (int) ($this->db('analytics_pageviews')
            ->whereRaw('DATE(viewed_at) = :d', [':d' => $yesterday])
            ->totalRows() ?: 0);

        $deltaToday = $viewsYesterday > 0
            ? round((($viewsToday - $viewsYesterday) / $viewsYesterday) * 100, 1)
            : null;

        // Daily average for selected period
        $dailyAvg = $days > 0 ? round($viewsPeriod / $days, 1) : 0;

        // Top pages (selected period)
        $topPages = $this->db('analytics_pageviews')
            ->select('url_path, MAX(page_title) AS page_title, COUNT(*) AS views')
            ->whereRaw('DATE(viewed_at) >= :f AND DATE(viewed_at) <= :t', [':f' => $from, ':t' => $to])
            ->groupBy('url_path')
            ->orderBy('views', 'DESC')
            ->limitOffset(10, 0)
            ->get() ?: [];

        // Top referrers (selected period)
        $topReferrers = $this->db('analytics_pageviews')
            ->select('referrer_host, COUNT(*) AS views')
            ->whereRaw('DATE(viewed_at) >= :f AND DATE(viewed_at) <= :t', [':f' => $from, ':t' => $to])
            ->whereRaw('referrer_host IS NOT NULL AND referrer_host != :empty', [':empty' => ''])
            ->groupBy('referrer_host')
            ->orderBy('views', 'DESC')
            ->limitOffset(10, 0)
            ->get() ?: [];

        // Unique visitors (distinct ip_hash) for selected period
        $uniqueRow = $this->db('analytics_pageviews')
            ->select('COUNT(DISTINCT ip_hash) AS unique_count')
            ->whereRaw('DATE(viewed_at) >= :f AND DATE(viewed_at) <= :t', [':f' => $from, ':t' => $to])
            ->whereRaw('ip_hash IS NOT NULL', [])
            ->get(1);
        $uniqueVisitors = (int) ($uniqueRow['unique_count'] ?? 0);

        // Device breakdown (mobile vs desktop) for selected period
        $deviceRows = $this->db('analytics_pageviews')
            ->select('device_type, COUNT(*) AS views')
            ->whereRaw('DATE(viewed_at) >= :f AND DATE(viewed_at) <= :t', [':f' => $from, ':t' => $to])
            ->whereRaw('device_type IS NOT NULL', [])
            ->groupBy('device_type')
            ->orderBy('views', 'DESC')
            ->get() ?: [];
        $deviceBreakdown = array_column($deviceRows, 'views', 'device_type');

        // Daily chart data (selected period)
        $chartRows = $this->db('analytics_pageviews')
            ->select('DATE(viewed_at) AS day, COUNT(*) AS views')
            ->whereRaw('DATE(viewed_at) >= :f AND DATE(viewed_at) <= :t', [':f' => $from, ':t' => $to])
            ->groupBy('DATE(viewed_at)')
            ->orderBy('day', 'ASC')
            ->get() ?: [];

        $chartMap    = array_column($chartRows, 'views', 'day');
        $chartLabels = [];
        $chartValues = [];
        $cursor      = $fromTs;
        while ($cursor <= $toTs) {
            $day           = date('Y-m-d', $cursor);
            $chartLabels[] = date('M j', $cursor);
            $chartValues[] = (int) ($chartMap[$day] ?? 0);
            $cursor       += 86400;
        }

        $this->adminRender('modules/analytics/admin/analytics/index', [
            'from'            => $from,
            'to'              => $to,
            'days'            => $days,
            'prevFrom'        => $prevFrom,
            'prevTo'          => $prevTo,
            'viewsPeriod'     => $viewsPeriod,
            'viewsPrevPeriod' => $viewsPrevPeriod,
            'deltaPeriod'     => $deltaPeriod,
            'viewsToday'      => $viewsToday,
            'viewsYesterday'  => $viewsYesterday,
            'deltaToday'      => $deltaToday,
            'dailyAvg'        => $dailyAvg,
            'uniqueVisitors'  => $uniqueVisitors,
            'deviceBreakdown' => $deviceBreakdown,
            'topPages'        => $topPages,
            'topReferrers'    => $topReferrers,
            'chartLabels'     => $chartLabels,
            'chartValues'     => $chartValues,
        ], 'Analytics', 'analytics');
    }

    public function data(): void
    {
        $this->requirePermission('analytics.view');

        [$from, $to] = $this->parseDateRange();

        $rows = $this->db('analytics_pageviews')
            ->select('DATE(viewed_at) AS day, COUNT(*) AS views')
            ->whereRaw('DATE(viewed_at) >= :f AND DATE(viewed_at) <= :t', [':f' => $from, ':t' => $to])
            ->groupBy('DATE(viewed_at)')
            ->orderBy('day', 'ASC')
            ->get() ?: [];

        $map    = array_column($rows, 'views', 'day');
        $labels = [];
        $values = [];
        $cursor = strtotime($from);
        $toTs   = strtotime($to);
        while ($cursor <= $toTs) {
            $day      = date('Y-m-d', $cursor);
            $labels[] = date('M j', $cursor);
            $values[] = (int) ($map[$day] ?? 0);
            $cursor  += 86400;
        }

        $this->json(['success' => true, 'labels' => $labels, 'values' => $values]);
    }

    public function export(): void
    {
        $this->requirePermission('analytics.view');

        [$from, $to] = $this->parseDateRange();

        $rows = $this->db('analytics_pageviews')
            ->select('url_path, page_title, referrer_host, viewed_at')
            ->whereRaw('DATE(viewed_at) >= :f AND DATE(viewed_at) <= :t', [':f' => $from, ':t' => $to])
            ->orderBy('viewed_at', 'DESC')
            ->get() ?: [];

        $filename = 'analytics_' . $from . '_to_' . $to . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['url_path', 'page_title', 'referrer_host', 'viewed_at']);
        foreach ($rows as $row) {
            fputcsv($out, [
                $row['url_path']      ?? '',
                $row['page_title']    ?? '',
                $row['referrer_host'] ?? '',
                $row['viewed_at']     ?? '',
            ]);
        }
        fclose($out);
        exit;
    }

    private function parseDateRange(): array
    {
        $rawFrom = trim($this->input->get('from') ?? '');
        $rawTo   = trim($this->input->get('to')   ?? '');
        $today   = date('Y-m-d');

        $to   = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawTo)   && $rawTo   <= $today) ? $rawTo   : $today;
        $from = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawFrom) && $rawFrom <= $to)     ? $rawFrom : date('Y-m-d', strtotime($to . ' -29 days'));

        return [$from, $to];
    }
}
