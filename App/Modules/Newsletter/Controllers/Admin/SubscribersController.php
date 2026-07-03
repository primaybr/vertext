<?php

declare(strict_types=1);

namespace App\Modules\Newsletter\Controllers\Admin;

use App\Controllers\Admin\BaseController;
use App\CMS\Auth;

/**
 * Newsletter subscriber management.
 *
 * GET  /admin/newsletter/subscribers            → index()
 * POST /admin/newsletter/subscribers/store      → store()
 * POST /admin/newsletter/subscribers/{id}/delete → delete($id)
 * POST /admin/newsletter/subscribers/import     → import()
 * GET  /admin/newsletter/subscribers/export     → export()
 */
class SubscribersController extends BaseController
{
    protected string $module = 'newsletter';

    public function __construct()
    {
        parent::__construct();
    }

    public function index(): void
    {
        $this->requirePermission('newsletter.view');

        $search  = trim($this->input->get('search') ?? '');
        $status  = $this->input->get('status') ?? '';
        $page    = max(1, (int) ($this->input->get('page') ?? 1));
        $perPage = 30;
        $offset  = ($page - 1) * $perPage;

        $q  = $this->db('newsletter_subscribers')
            ->select('id, email, name, status, source, created_at')
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'DESC')
            ->limitOffset($perPage, $offset);
        $qc = $this->db('newsletter_subscribers')->whereNull('deleted_at');

        if ($search) {
            $q->whereRaw('email ILIKE :s OR name ILIKE :s', [':s' => "%{$search}%"]);
            $qc->whereRaw('email ILIKE :s OR name ILIKE :s', [':s' => "%{$search}%"]);
        }
        if (in_array($status, ['active', 'pending', 'unsubscribed'], true)) {
            $q->where('status', $status);
            $qc->where('status', $status);
        }

        $total = (int) ($qc->totalRows() ?: 0);
        $subs  = $q->get() ?: [];

        // Counts by status for tabs
        $counts = [];
        foreach (['active', 'pending', 'unsubscribed'] as $st) {
            $counts[$st] = (int) ($this->db('newsletter_subscribers')
                ->where('status', $st)->whereNull('deleted_at')->totalRows() ?: 0);
        }

        $this->adminRender('modules/newsletter/admin/subscribers', [
            'subs'    => $subs,
            'total'   => $total,
            'page'    => $page,
            'pages'   => max(1, (int) ceil($total / $perPage)),
            'search'  => $search,
            'status'  => $status,
            'counts'  => $counts,
        ], 'Subscribers', 'newsletter');
    }

    public function store(): void
    {
        $this->requirePermission('newsletter.manage');
        $this->validateCsrf();

        $email = strtolower(trim($this->input->post('email', false) ?? ''));
        $name  = substr(trim($this->input->post('name', false) ?? ''), 0, 120);

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json(['success' => false, 'message' => 'A valid email address is required.']);
        }

        $existing = $this->db('newsletter_subscribers')->where('email', $email)->get(1);
        if ($existing) {
            if ($existing['deleted_at']) {
                $this->db('newsletter_subscribers')->where('id', $existing['id'])->update([
                    'deleted_at' => null, 'status' => 'active', 'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $this->json(['success' => true, 'message' => "Subscriber {$email} reactivated."]);
            }
            $this->json(['success' => false, 'message' => 'This email is already subscribed.']);
        }

        $this->db('newsletter_subscribers')->save([
            'email'      => $email,
            'name'       => $name ?: null,
            'status'     => 'active',
            'source'     => 'admin',
            'created_by' => $this->currentUser['id'] ?? null,
        ]);

        Auth::audit('newsletter.subscriber_added', 'newsletter_subscribers', '', ['email' => $email]);
        $this->json(['success' => true, 'message' => "{$email} added."]);
    }

    public function delete(string $id): void
    {
        $this->requirePermission('newsletter.manage');
        $this->validateCsrf();

        $sub = $this->db('newsletter_subscribers')->where('id', $id)->whereNull('deleted_at')->get(1);
        if (!$sub) {
            $this->json(['success' => false, 'message' => 'Subscriber not found.'], 404);
        }

        $this->db('newsletter_subscribers')->where('id', $id)->update([
            'deleted_at' => date('Y-m-d H:i:s'),
            'deleted_by' => $this->currentUser['id'] ?? null,
        ]);

        Auth::audit('newsletter.subscriber_deleted', 'newsletter_subscribers', $id, ['email' => $sub['email']]);
        $this->json(['success' => true, 'message' => 'Subscriber removed.']);
    }

    public function import(): void
    {
        $this->requirePermission('newsletter.manage');
        $this->validateCsrf();

        $csvData = trim($this->input->post('csv_data', false) ?? '');

        // v0.0.2: a .csv file upload takes precedence over the paste box
        if (!empty($_FILES['csv_file']['name']) && ($_FILES['csv_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            if (($_FILES['csv_file']['size'] ?? 0) > 5 * 1024 * 1024) {
                $this->json(['success' => false, 'message' => 'CSV file must be 5 MB or smaller.']);
            }
            $ext = strtolower(pathinfo((string) $_FILES['csv_file']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['csv', 'txt'], true)) {
                $this->json(['success' => false, 'message' => 'Only .csv or .txt files can be imported.']);
            }
            $contents = @file_get_contents((string) $_FILES['csv_file']['tmp_name']);
            if ($contents === false) {
                $this->json(['success' => false, 'message' => 'Could not read the uploaded file.']);
            }
            // Strip a UTF-8 BOM so the first email parses cleanly
            $csvData = trim(preg_replace('/^\xEF\xBB\xBF/', '', $contents));
        }

        if (!$csvData) {
            $this->json(['success' => false, 'message' => 'No CSV data provided.']);
        }

        $lines  = preg_split('/\r?\n/', $csvData);
        $added  = 0;
        $skipped = 0;

        foreach ($lines as $line) {
            $line = trim($line);
            if (!$line) continue;

            $parts = str_getcsv($line);
            $email = strtolower(trim($parts[0] ?? ''));
            $name  = substr(trim($parts[1] ?? ''), 0, 120);

            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped++;
                continue;
            }

            $existing = $this->db('newsletter_subscribers')->where('email', $email)->get(1);
            if ($existing) {
                $skipped++;
                continue;
            }

            $this->db('newsletter_subscribers')->save([
                'email'      => $email,
                'name'       => $name ?: null,
                'status'     => 'active',
                'source'     => 'import',
                'created_by' => $this->currentUser['id'] ?? null,
            ]);
            $added++;
        }

        Auth::audit('newsletter.import', 'newsletter_subscribers', '', ['added' => $added, 'skipped' => $skipped]);
        $this->json(['success' => true, 'message' => "{$added} imported, {$skipped} skipped."]);
    }

    public function export(): void
    {
        $this->requirePermission('newsletter.export');

        $subs = $this->db('newsletter_subscribers')
            ->select('email, name, status, source, created_at')
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'DESC')
            ->get() ?: [];

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="subscribers-' . date('Ymd') . '.csv"');
        header('Cache-Control: no-cache');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Email', 'Name', 'Status', 'Source', 'Subscribed At']);
        foreach ($subs as $s) {
            fputcsv($out, [$s['email'], $s['name'] ?? '', $s['status'], $s['source'] ?? '', $s['created_at']]);
        }
        fclose($out);
        exit;
    }

    private function validateCsrf(): void
    {
        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->json(['success' => false, 'message' => 'Security token invalid.'], 403);
        }
    }
}
