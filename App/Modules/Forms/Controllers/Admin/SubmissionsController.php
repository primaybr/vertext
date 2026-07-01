<?php

declare(strict_types=1);

namespace App\Modules\Forms\Controllers\Admin;

use App\Controllers\Admin\BaseController;
use App\CMS\Auth;

/**
 * Submissions inbox for forms.
 *
 * GET  /admin/forms/all-submissions                    → allSubmissions()
 * GET  /admin/forms/{id}/submissions                   → index($id)
 * GET  /admin/forms/{id}/submissions/{sid}             → detail($id, $sid)
 * POST /admin/forms/{id}/submissions/{sid}/delete      → delete($id, $sid)
 * GET  /admin/forms/{id}/submissions/export            → export($id)
 */
class SubmissionsController extends BaseController
{
    protected string $module = 'forms';

    public function __construct()
    {
        parent::__construct();
    }

    public function allSubmissions(): void
    {
        $this->requirePermission('forms.view');

        $page    = max(1, (int) ($this->input->get('page') ?? 1));
        $perPage = 30;
        $offset  = ($page - 1) * $perPage;

        $subs = $this->db('form_submissions')
            ->select('id, form_id, status, submitted_at')
            ->whereNull('deleted_at')
            ->orderBy('submitted_at', 'DESC')
            ->limitOffset($perPage, $offset)
            ->get() ?: [];

        $formNames = [];
        foreach ($subs as &$s) {
            $fid = $s['form_id'];
            if (!isset($formNames[$fid])) {
                $fd = $this->db('form_definitions')->select('name')->where('id', $fid)->get(1);
                $formNames[$fid] = $fd['name'] ?? '(deleted)';
            }
            $s['form_name'] = $formNames[$fid];
        }
        unset($s);

        $total = (int) ($this->db('form_submissions')->whereNull('deleted_at')->totalRows() ?: 0);

        $this->adminRender('modules/forms/admin/all_submissions', [
            'subs'   => $subs,
            'total'  => $total,
            'page'   => $page,
            'pages'  => max(1, (int) ceil($total / $perPage)),
        ], 'All Submissions', 'forms.all-submissions');
    }

    public function index(string $id): void
    {
        $this->requirePermission('forms.view');

        $form = $this->db('form_definitions')->where('id', $id)->whereNull('deleted_at')->get(1);
        if (!$form) {
            $this->flash('error', 'Form not found.');
            $this->redirect($this->baseUrl . '/admin/forms');
        }

        $page    = max(1, (int) ($this->input->get('page') ?? 1));
        $status  = $this->input->get('status') ?? '';
        $perPage = 30;
        $offset  = ($page - 1) * $perPage;

        $q  = $this->db('form_submissions')
            ->select('id, status, submitted_at, ip_hash')
            ->where('form_id', $id)
            ->whereNull('deleted_at')
            ->orderBy('submitted_at', 'DESC')
            ->limitOffset($perPage, $offset);
        $qc = $this->db('form_submissions')->where('form_id', $id)->whereNull('deleted_at');

        if ($status === 'unread') {
            $q->where('status', 'unread');
            $qc->where('status', 'unread');
        }

        $total = (int) ($qc->totalRows() ?: 0);
        $subs  = $q->get() ?: [];

        // Decode data for preview column (first 2 values)
        $fields = json_decode($form['fields'] ?: '[]', true) ?: [];
        foreach ($subs as &$s) {
            $data    = json_decode($s['data'] ?? '{}', true) ?: [];
            $preview = [];
            foreach (array_slice($fields, 0, 2) as $field) {
                $val = $data[$field['id']] ?? '';
                if ($val !== '') {
                    $preview[] = substr((string) $val, 0, 60);
                }
            }
            $s['preview'] = implode(' - ', $preview);
        }
        unset($s);

        $this->adminRender('modules/forms/admin/submissions', [
            'form'   => $form,
            'subs'   => $subs,
            'fields' => $fields,
            'total'  => $total,
            'page'   => $page,
            'pages'  => max(1, (int) ceil($total / $perPage)),
            'status' => $status,
        ], 'Submissions - ' . $form['name'], 'forms');
    }

    public function detail(string $id, string $sid): void
    {
        $this->requirePermission('forms.view');

        $form = $this->db('form_definitions')->where('id', $id)->whereNull('deleted_at')->get(1);
        if (!$form) {
            $this->flash('error', 'Form not found.');
            $this->redirect($this->baseUrl . '/admin/forms');
        }

        $sub = $this->db('form_submissions')
            ->where('id', $sid)
            ->where('form_id', $id)
            ->whereNull('deleted_at')
            ->get(1);
        if (!$sub) {
            $this->flash('error', 'Submission not found.');
            $this->redirect($this->baseUrl . "/admin/forms/{$id}/submissions");
        }

        // Mark as read
        if ($sub['status'] === 'unread') {
            $this->db('form_submissions')->where('id', $sid)->update([
                'status'     => 'read',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $fields = json_decode($form['fields'] ?: '[]', true) ?: [];
        $data   = json_decode($sub['data'] ?: '{}', true) ?: [];

        if ($this->input->isAjax()) {
            $this->renderPartial('modules/forms/admin/submission_detail', [
                'form'   => $form,
                'sub'    => $sub,
                'fields' => $fields,
                'data'   => $data,
                'baseUrl'=> $this->baseUrl,
            ]);
            return;
        }

        $this->adminRender('modules/forms/admin/submission_detail', [
            'form'   => $form,
            'sub'    => $sub,
            'fields' => $fields,
            'data'   => $data,
        ], 'Submission Detail', 'forms');
    }

    public function delete(string $id, string $sid): void
    {
        $this->requirePermission('forms.delete_submission');
        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->json(['success' => false, 'message' => 'Security token invalid.'], 403);
        }

        $sub = $this->db('form_submissions')
            ->where('id', $sid)
            ->where('form_id', $id)
            ->whereNull('deleted_at')
            ->get(1);
        if (!$sub) {
            $this->json(['success' => false, 'message' => 'Submission not found.'], 404);
        }

        $this->db('form_submissions')->where('id', $sid)->update([
            'deleted_at' => date('Y-m-d H:i:s'),
            'deleted_by' => $this->currentUser['id'] ?? null,
        ]);

        Auth::audit('form_submission.delete', 'form_submissions', $sid, ['form_id' => $id]);
        $this->json(['success' => true, 'message' => 'Submission deleted.']);
    }

    public function export(string $id): void
    {
        $this->requirePermission('forms.export');

        $form = $this->db('form_definitions')->where('id', $id)->whereNull('deleted_at')->get(1);
        if (!$form) {
            $this->flash('error', 'Form not found.');
            $this->redirect($this->baseUrl . '/admin/forms');
        }

        $fields = json_decode($form['fields'] ?: '[]', true) ?: [];
        $subs   = $this->db('form_submissions')
            ->where('form_id', $id)
            ->whereNull('deleted_at')
            ->orderBy('submitted_at', 'DESC')
            ->get() ?: [];

        $filename = 'form-' . $form['slug'] . '-' . date('Ymd') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');

        $out = fopen('php://output', 'w');
        // BOM for Excel UTF-8 compatibility
        fwrite($out, "\xEF\xBB\xBF");

        // Header row
        $headers = ['Submitted At', 'Status'];
        foreach ($fields as $field) {
            $headers[] = $field['label'] ?? $field['id'];
        }
        fputcsv($out, $headers);

        // Data rows
        foreach ($subs as $sub) {
            $data = json_decode($sub['data'] ?: '{}', true) ?: [];
            $row  = [
                $sub['submitted_at'],
                $sub['status'],
            ];
            foreach ($fields as $field) {
                $val = $data[$field['id']] ?? '';
                if (is_array($val)) {
                    $val = implode(', ', $val);
                }
                $row[] = (string) $val;
            }
            fputcsv($out, $row);
        }

        fclose($out);
        exit;
    }
}
