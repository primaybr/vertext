<?php

declare(strict_types=1);

namespace App\Modules\Members\Controllers\Admin;

use App\Controllers\Admin\BaseController;
use App\CMS\Auth;
use App\Mail\Mailer;
use App\Mail\MailMessage;
use App\Mail\MailTemplate;

/**
 * Admin management of front-end site members (site_users table).
 */
class MembersController extends BaseController
{
    protected string $module = 'members';

    /** GET /admin/members */
    public function index(): void
    {
        $this->requirePermission('members.view');

        $search  = trim($this->input->get('search') ?? '');
        $status  = trim($this->input->get('status') ?? '');
        $page    = max(1, (int) ($this->input->get('page') ?? 1));
        $perPage = 20;
        $offset  = ($page - 1) * $perPage;

        $countModel = $this->db('site_users')->whereNull('deleted_at');
        $listModel  = $this->db('site_users')
            ->select('id, name, email, status, verified_at, last_login, created_at')
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'DESC')
            ->limitOffset($perPage, $offset);

        if ($search) {
            $binds = [':s1' => "%{$search}%", ':s2' => "%{$search}%"];
            $countModel->whereRaw('(name ILIKE :s1 OR email ILIKE :s2)', $binds);
            $listModel->whereRaw('(name ILIKE :s1 OR email ILIKE :s2)', $binds);
        }
        if (in_array($status, ['pending', 'active', 'suspended'], true)) {
            $countModel->where('status', $status);
            $listModel->where('status', $status);
        }

        $total   = (int) ($countModel->totalRows() ?: 0);
        $members = $listModel->get() ?: [];

        // Status counts for the filter tabs
        $counts = ['all' => 0, 'pending' => 0, 'active' => 0, 'suspended' => 0];
        try {
            $rows = $this->db('site_users')
                ->select('status, COUNT(*) AS cnt')
                ->whereNull('deleted_at')
                ->groupBy('status')
                ->get() ?: [];
            foreach ($rows as $row) {
                $counts[(string) $row['status']] = (int) $row['cnt'];
                $counts['all'] += (int) $row['cnt'];
            }
        } catch (\Throwable $e) {
        }

        $this->adminRender('modules/members/admin/index', [
            'members' => $members,
            'total'   => $total,
            'page'    => $page,
            'pages'   => max(1, (int) ceil($total / $perPage)),
            'search'  => $search,
            'status'  => $status,
            'counts'  => $counts,
        ], 'Members', 'members');
    }

    /** POST /admin/members/{id}/status - AJAX: activate or suspend */
    public function setStatus(string $id): void
    {
        $this->requirePermission('members.manage');
        $this->validateCsrf();

        $member = $this->db('site_users')->where('id', $id)->whereNull('deleted_at')->get(1);
        if (!$member) {
            $this->json(['success' => false, 'message' => 'Member not found.'], 404);
        }

        $newStatus = $this->input->post('status') ?? '';
        if (!in_array($newStatus, ['active', 'suspended'], true)) {
            $this->json(['success' => false, 'message' => 'Invalid status.'], 422);
        }

        $data = ['status' => $newStatus];
        // Manually activating an unverified account counts as verification
        if ($newStatus === 'active' && empty($member['verified_at'])) {
            $data['verified_at'] = date('Y-m-d H:i:s');
        }

        $this->db('site_users')->where('id', $id)->update($data);

        $this->audit('member.status_changed', 'site_users', $id, [
            'email' => $member['email'],
            'from'  => $member['status'],
            'to'    => $newStatus,
        ]);

        $this->json([
            'success' => true,
            'message' => ucfirst($newStatus === 'active' ? 'activated' : 'suspended') . " \"{$member['name']}\".",
            'status'  => $newStatus,
        ]);
    }

    /** POST /admin/members/{id}/delete - AJAX: soft delete */
    public function delete(string $id): void
    {
        $this->requirePermission('members.manage');
        $this->validateCsrf();

        $member = $this->db('site_users')->where('id', $id)->whereNull('deleted_at')->get(1);
        if (!$member) {
            $this->json(['success' => false, 'message' => 'Member not found.'], 404);
        }

        $this->db('site_users')->where('id', $id)->update([
            'deleted_at' => date('Y-m-d H:i:s'),
            'deleted_by' => $this->currentUser['id'] ?? null,
        ]);

        $this->audit('member.deleted', 'site_users', $id, ['email' => $member['email']]);
        $this->json(['success' => true, 'message' => "Deleted \"{$member['name']}\"."]);
    }

    /** POST /admin/members/{id}/resend-verification - AJAX */
    public function resendVerification(string $id): void
    {
        $this->requirePermission('members.manage');
        $this->validateCsrf();

        $member = $this->db('site_users')->where('id', $id)->whereNull('deleted_at')->get(1);
        if (!$member) {
            $this->json(['success' => false, 'message' => 'Member not found.'], 404);
        }
        if ($member['status'] !== 'pending') {
            $this->json(['success' => false, 'message' => 'This account is not awaiting verification.'], 422);
        }

        try {
            $settings  = array_column($this->db('settings')->get() ?: [], 'value', 'key');
            $siteUrl   = rtrim($settings['site_url'] ?? $this->baseUrl, '/');
            $verifyUrl = $siteUrl . '/account/verify?token=' . urlencode((string) $member['verify_token']);

            $html = MailTemplate::render('member_verify', [
                'userName'  => (string) $member['name'],
                'verifyUrl' => $verifyUrl,
                'siteName'  => $settings['site_name'] ?? 'Our site',
            ]);

            $sent = Mailer::make()->send(
                (new MailMessage())
                    ->to((string) $member['email'], (string) $member['name'])
                    ->subject('Verify your email - ' . ($settings['site_name'] ?? 'New account'))
                    ->htmlBody($html)
            );

            if (!$sent) {
                $this->json(['success' => false, 'message' => 'Mailer error: could not send. Check mail settings.']);
            }
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => 'Could not send the email.']);
        }

        $this->audit('member.verification_resent', 'site_users', $id, ['email' => $member['email']]);
        $this->json(['success' => true, 'message' => "Verification email sent to {$member['email']}."]);
    }

    private function validateCsrf(): void
    {
        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->json(['success' => false, 'message' => 'Security token invalid.'], 403);
        }
    }

    /** Shorthand audit helper matching module controller conventions */
    private function audit(string $action, string $type, string $id, array $details = []): void
    {
        Auth::audit($action, $type, $id, $details);
    }
}
