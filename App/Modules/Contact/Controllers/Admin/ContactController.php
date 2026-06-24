<?php

declare(strict_types=1);

namespace App\Modules\Contact\Controllers\Admin;

use App\Controllers\Admin\BaseController;
use App\CMS\Auth;

/**
 * Contact inbox - view and manage form submissions.
 *
 * GET  /admin/contact                  → index()
 * GET  /admin/contact/{id}             → view($id)
 * POST /admin/contact/{id}/mark-read   → markRead($id)
 * POST /admin/contact/{id}/mark-spam   → markSpam($id)
 * POST /admin/contact/{id}/delete      → delete($id)
 */
class ContactController extends BaseController
{
    protected string $module = 'contact';

    public function __construct()
    {
        parent::__construct();
    }

    public function index(): void
    {
        $this->requirePermission('contact.view');

        $filter  = $this->input->get('status') ?? 'all';
        $page    = max(1, (int) ($this->input->get('page') ?? 1));
        $perPage = 25;
        $offset  = ($page - 1) * $perPage;

        $q  = $this->db('contact_submissions')
            ->select('id, name, email, subject, status, submitted_at')
            ->orderBy('submitted_at', 'DESC')
            ->limitOffset($perPage, $offset);
        $qc = $this->db('contact_submissions');

        if ($filter !== 'all') {
            $q->where('status', $filter);
            $qc->where('status', $filter);
        }

        $total     = (int) ($qc->totalRows() ?: 0);
        $items     = $q->get() ?: [];
        $unreadCnt = (int) ($this->db('contact_submissions')->where('status', 'unread')->totalRows() ?: 0);

        $this->adminRender('modules/contact/admin/contact/index', [
            'items'     => $items,
            'total'     => $total,
            'page'      => $page,
            'pages'     => max(1, (int) ceil($total / $perPage)),
            'filter'    => $filter,
            'unreadCnt' => $unreadCnt,
        ], 'Contact Inbox', 'contact');
    }

    public function view(string $id): void
    {
        $this->requirePermission('contact.view');

        $item = $this->db('contact_submissions')->where('id', $id)->get(1);
        if (!$item) {
            $this->flash('error', 'Submission not found.');
            $this->redirect($this->baseUrl . '/admin/contact');
        }

        if ($item['status'] === 'unread') {
            $this->db('contact_submissions')->where('id', $id)->update([
                'status'  => 'read',
                'read_at' => date('Y-m-d H:i:s'),
            ]);
            $item['status'] = 'read';
        }

        $this->adminRender('modules/contact/admin/contact/view', [
            'item' => $item,
        ], 'Contact: ' . htmlspecialchars($item['name']), 'contact');
    }

    public function markRead(string $id): void
    {
        $this->requirePermission('contact.view');
        $this->validateCsrf();

        $this->db('contact_submissions')->where('id', $id)->update([
            'status'  => 'read',
            'read_at' => date('Y-m-d H:i:s'),
        ]);

        $this->json(['success' => true, 'message' => 'Marked as read.']);
    }

    public function markSpam(string $id): void
    {
        $this->requirePermission('contact.view');
        $this->validateCsrf();

        $this->db('contact_submissions')->where('id', $id)->update(['status' => 'spam']);
        $this->json(['success' => true, 'message' => 'Marked as spam.']);
    }

    public function delete(string $id): void
    {
        $this->requirePermission('contact.delete');
        $this->validateCsrf();

        $this->db('contact_submissions')->where('id', $id)->delete();
        Auth::audit('contact.delete', 'contact_submissions', $id);
        $this->json(['success' => true, 'message' => 'Submission deleted.']);
    }

    private function validateCsrf(): void
    {
        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->json(['success' => false, 'message' => 'Security token invalid.'], 403);
        }
    }
}
