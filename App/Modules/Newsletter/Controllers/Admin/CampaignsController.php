<?php

declare(strict_types=1);

namespace App\Modules\Newsletter\Controllers\Admin;

use App\Controllers\Admin\BaseController;
use App\CMS\Auth;
use App\Mail\Mailer;
use App\Mail\MailMessage;

/**
 * Newsletter campaign management and sending.
 *
 * GET  /admin/newsletter/campaigns                → index()
 * GET  /admin/newsletter/campaigns/create         → createForm()
 * POST /admin/newsletter/campaigns/store          → store()
 * GET  /admin/newsletter/campaigns/{id}/edit      → editForm($id)
 * POST /admin/newsletter/campaigns/{id}/update    → update($id)
 * POST /admin/newsletter/campaigns/{id}/delete    → delete($id)
 * POST /admin/newsletter/campaigns/{id}/send      → send($id)
 * POST /admin/newsletter/campaigns/{id}/test-send → testSend($id)
 */
class CampaignsController extends BaseController
{
    protected string $module = 'newsletter';

    public function __construct()
    {
        parent::__construct();
    }

    public function index(): void
    {
        $this->requirePermission('newsletter.view');

        $page    = max(1, (int) ($this->input->get('page') ?? 1));
        $perPage = 20;
        $offset  = ($page - 1) * $perPage;

        $campaigns = $this->db('newsletter_campaigns')
            ->select('id, subject, status, sent_count, sent_at, created_at')
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'DESC')
            ->limitOffset($perPage, $offset)
            ->get() ?: [];

        $total = (int) ($this->db('newsletter_campaigns')->whereNull('deleted_at')->totalRows() ?: 0);

        $this->adminRender('modules/newsletter/admin/campaigns', [
            'campaigns' => $campaigns,
            'total'     => $total,
            'page'      => $page,
            'pages'     => max(1, (int) ceil($total / $perPage)),
        ], 'Campaigns', 'newsletter');
    }

    public function createForm(): void
    {
        $this->requirePermission('newsletter.manage');
        $vars = [
            'campaign' => null,
            'action'   => $this->baseUrl . '/admin/newsletter/campaigns/store',
            'isModal'  => $this->input->isAjax(),
        ];
        if ($this->input->isAjax()) {
            $this->renderPartial('modules/newsletter/admin/campaign_form', $vars);
            return;
        }
        $this->adminRender('modules/newsletter/admin/campaign_form', $vars, 'New Campaign', 'newsletter');
    }

    public function store(): void
    {
        $this->requirePermission('newsletter.manage');
        $this->validateCsrf();

        $subject = trim($this->input->post('subject', false) ?? '');
        if (!$subject) {
            if ($this->input->isAjax()) {
                $this->json(['success' => false, 'message' => 'Subject is required.']);
            }
            $this->flash('error', 'Subject is required.');
            $this->redirect($this->baseUrl . '/admin/newsletter/campaigns/create');
        }

        $id = (string) $this->db('newsletter_campaigns')->save([
            'subject'      => $subject,
            'preview_text' => substr(trim($this->input->post('preview_text', false) ?? ''), 0, 255),
            'body_html'    => $this->input->post('body_html', false) ?? '',
            'body_text'    => $this->input->post('body_text', false) ?? '',
            'status'       => 'draft',
            'created_by'   => $this->currentUser['id'] ?? null,
        ]);

        Auth::audit('newsletter.campaign_created', 'newsletter_campaigns', $id, ['subject' => $subject]);

        if ($this->input->isAjax()) {
            $this->json([
                'success'  => true,
                'message'  => 'Campaign created.',
                'redirect' => $this->baseUrl . "/admin/newsletter/campaigns/{$id}/edit",
            ]);
        }
        $this->flash('success', 'Campaign saved as draft.');
        $this->redirect($this->baseUrl . "/admin/newsletter/campaigns/{$id}/edit");
    }

    public function editForm(string $id): void
    {
        $this->requirePermission('newsletter.manage');
        $campaign = $this->db('newsletter_campaigns')->where('id', $id)->whereNull('deleted_at')->get(1);
        if (!$campaign) {
            $this->flash('error', 'Campaign not found.');
            $this->redirect($this->baseUrl . '/admin/newsletter/campaigns');
        }

        $activeCount = (int) ($this->db('newsletter_subscribers')
            ->where('status', 'active')->whereNull('deleted_at')->totalRows() ?: 0);

        $this->adminRender('modules/newsletter/admin/campaign_form', [
            'campaign'    => $campaign,
            'action'      => $this->baseUrl . "/admin/newsletter/campaigns/{$id}/update",
            'activeCount' => $activeCount,
        ], 'Edit Campaign', 'newsletter');
    }

    public function update(string $id): void
    {
        $this->requirePermission('newsletter.manage');
        $this->validateCsrf();

        $campaign = $this->db('newsletter_campaigns')->where('id', $id)->whereNull('deleted_at')->get(1);
        if (!$campaign) {
            $this->json(['success' => false, 'message' => 'Campaign not found.'], 404);
        }
        if ($campaign['status'] === 'sent') {
            $this->json(['success' => false, 'message' => 'Sent campaigns cannot be edited.']);
        }

        $subject = trim($this->input->post('subject', false) ?? '');
        if (!$subject) {
            $this->json(['success' => false, 'message' => 'Subject is required.']);
        }

        $this->db('newsletter_campaigns')->where('id', $id)->update([
            'subject'      => $subject,
            'preview_text' => substr(trim($this->input->post('preview_text', false) ?? ''), 0, 255),
            'body_html'    => $this->input->post('body_html', false) ?? '',
            'body_text'    => $this->input->post('body_text', false) ?? '',
            'updated_at'   => date('Y-m-d H:i:s'),
            'updated_by'   => $this->currentUser['id'] ?? null,
        ]);

        Auth::audit('newsletter.campaign_updated', 'newsletter_campaigns', $id, ['subject' => $subject]);
        $this->json(['success' => true, 'message' => 'Campaign saved.']);
    }

    public function delete(string $id): void
    {
        $this->requirePermission('newsletter.manage');
        $this->validateCsrf();

        $campaign = $this->db('newsletter_campaigns')->where('id', $id)->whereNull('deleted_at')->get(1);
        if (!$campaign) {
            $this->json(['success' => false, 'message' => 'Campaign not found.'], 404);
        }

        $this->db('newsletter_campaigns')->where('id', $id)->update([
            'deleted_at' => date('Y-m-d H:i:s'),
            'deleted_by' => $this->currentUser['id'] ?? null,
        ]);

        Auth::audit('newsletter.campaign_deleted', 'newsletter_campaigns', $id, ['subject' => $campaign['subject']]);
        $this->json(['success' => true, 'message' => 'Campaign deleted.']);
    }

    public function send(string $id): void
    {
        $this->requirePermission('newsletter.manage');
        $this->validateCsrf();

        $campaign = $this->db('newsletter_campaigns')->where('id', $id)->whereNull('deleted_at')->get(1);
        if (!$campaign) {
            $this->json(['success' => false, 'message' => 'Campaign not found.'], 404);
        }
        if ($campaign['status'] === 'sent') {
            $this->json(['success' => false, 'message' => 'Campaign already sent.']);
        }
        if (empty(trim($campaign['body_html'] . $campaign['body_text']))) {
            $this->json(['success' => false, 'message' => 'Campaign has no content.']);
        }

        // Mark as sending
        $this->db('newsletter_campaigns')->where('id', $id)->update([
            'status'     => 'sending',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $settings   = $this->loadSettings();
        $fromName   = $settings['newsletter_from_name'] ?: ($this->getSiteName());
        $fromEmail  = $settings['newsletter_from_email'] ?: '';
        $siteUrl    = $this->baseUrl;
        $subject    = $campaign['subject'];
        $bodyHtml   = $campaign['body_html'] ?? '';
        $bodyText   = $campaign['body_text'] ?? '';

        $subscribers = $this->db('newsletter_subscribers')
            ->select('id, email, name, token')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->get() ?: [];

        $sent = 0;
        foreach ($subscribers as $sub) {
            $unsub  = $siteUrl . '/newsletter/unsubscribe?token=' . $sub['token'];
            $footer = "\n\n--\nYou received this because you subscribed at {$siteUrl}.\nUnsubscribe: {$unsub}";
            $htmlFooter = '<div style="margin-top:32px;padding-top:16px;border-top:1px solid #e5e7eb;font-size:12px;color:#9ca3af;">'
                . 'You received this because you subscribed at <a href="' . htmlspecialchars($siteUrl) . '">' . htmlspecialchars($siteUrl) . '</a>.'
                . ' <a href="' . htmlspecialchars($unsub) . '">Unsubscribe</a></div>';

            try {
                $msg = (new MailMessage())
                    ->to($sub['email'], $sub['name'] ?? '')
                    ->subject($subject)
                    ->htmlBody($bodyHtml . $htmlFooter)
                    ->textBody($bodyText . $footer);

                if ($fromEmail) {
                    // MailMessage::from() if it exists; fall back gracefully
                    if (method_exists($msg, 'from')) {
                        $msg->from($fromEmail, $fromName);
                    }
                }

                Mailer::make()->send($msg);
                $sent++;
            } catch (\Throwable) {
                // continue on per-recipient failure
            }
        }

        $this->db('newsletter_campaigns')->where('id', $id)->update([
            'status'     => 'sent',
            'sent_count' => $sent,
            'sent_at'    => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if (\App\CMS\ModuleLoader::isEnabled('webhooks')) {
            try {
                \App\Modules\Webhooks\WebhookDispatcher::dispatch('campaign.sent', [
                    'campaign_id' => $id,
                    'subject'     => $subject,
                    'sent_count'  => $sent,
                    'sent_at'     => date('c'),
                ]);
            } catch (\Throwable) {}
        }

        Auth::audit('newsletter.campaign_sent', 'newsletter_campaigns', $id, ['sent' => $sent]);
        $this->json(['success' => true, 'message' => "Campaign sent to {$sent} subscriber(s)."]);
    }

    public function testSend(string $id): void
    {
        $this->requirePermission('newsletter.manage');
        $this->validateCsrf();

        $campaign = $this->db('newsletter_campaigns')->where('id', $id)->whereNull('deleted_at')->get(1);
        if (!$campaign) {
            $this->json(['success' => false, 'message' => 'Campaign not found.'], 404);
        }

        $to = trim($this->input->post('test_email', false) ?? '');
        if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->json(['success' => false, 'message' => 'A valid test email address is required.']);
        }

        try {
            $testLabel = '<div style="background:#fef3c7;border:1px solid #d97706;padding:8px 12px;border-radius:4px;font-size:13px;margin-bottom:16px;"><strong>TEST SEND</strong> - This is a preview, not sent to subscribers.</div>';
            $msg = (new MailMessage())
                ->to($to)
                ->subject('[TEST] ' . $campaign['subject'])
                ->htmlBody($testLabel . ($campaign['body_html'] ?? ''))
                ->textBody('[TEST SEND]\n\n' . ($campaign['body_text'] ?? ''));
            Mailer::make()->send($msg);
            $this->json(['success' => true, 'message' => "Test email sent to {$to}."]);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => 'Send failed: ' . $e->getMessage()]);
        }
    }

    private function loadSettings(): array
    {
        $rows = (new \Core\Model('settings'))->where('grp', 'newsletter')->get() ?: [];
        $out  = [];
        foreach ($rows as $r) {
            $out[$r['key']] = $r['value'];
        }
        return $out;
    }

    private function getSiteName(): string
    {
        $row = (new \Core\Model('settings'))->select('value')->where('key', 'site_name')->where('grp', 'general')->get(1);
        return $row ? ($row['value'] ?? 'Vertext') : 'Vertext';
    }

    private function validateCsrf(): void
    {
        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->json(['success' => false, 'message' => 'Security token invalid.'], 403);
        }
    }
}
