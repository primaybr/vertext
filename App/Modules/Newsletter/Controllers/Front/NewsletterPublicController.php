<?php

declare(strict_types=1);

namespace App\Modules\Newsletter\Controllers\Front;

use Core\Controller;
use App\Mail\Mailer;
use App\Mail\MailMessage;

/**
 * Public newsletter endpoints.
 *
 * POST /newsletter/subscribe         → subscribe()
 * GET  /newsletter/unsubscribe?token= → unsubscribe()
 * GET  /newsletter/confirm?token=    → confirm()
 */
class NewsletterPublicController extends Controller
{
    private array $settings = [];

    public function __construct()
    {
        parent::__construct();

        $rows = (new \Core\Model('settings'))->where('grp', 'newsletter')->get() ?: [];
        foreach ($rows as $r) {
            $this->settings[$r['key']] = $r['value'];
        }
    }

    public function subscribe(): void
    {
        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->jsonOrRedirect(['success' => false, 'message' => 'Security token invalid.']);
        }

        $email = strtolower(trim($this->input->post('email', false) ?? ''));
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->jsonOrRedirect(['success' => false, 'message' => 'Please enter a valid email address.']);
        }

        $existing = (new \Core\Model('newsletter_subscribers'))->where('email', $email)->get(1);

        if ($existing && !$existing['deleted_at'] && $existing['status'] !== 'unsubscribed') {
            $this->jsonOrRedirect(['success' => true, 'message' => 'You are already subscribed.']);
        }

        $doubleOptin = (bool) ($this->settings['newsletter_double_optin'] ?? false);

        if ($existing) {
            // Reactivate
            $status = $doubleOptin ? 'pending' : 'active';
            (new \Core\Model('newsletter_subscribers'))->where('id', $existing['id'])->update([
                'status'     => $status,
                'deleted_at' => null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $token = $existing['token'];
        } else {
            $status = $doubleOptin ? 'pending' : 'active';
            (new \Core\Model('newsletter_subscribers'))->save([
                'email'  => $email,
                'status' => $status,
                'source' => $this->input->post('source', false) ?? 'widget',
            ]);
            $row   = (new \Core\Model('newsletter_subscribers'))->where('email', $email)->get(1);
            $token = $row['token'] ?? '';
        }

        if ($doubleOptin && $token) {
            $this->sendConfirmationEmail($email, $token);
            $msg = 'Please check your email to confirm your subscription.';
        } else {
            $msg = 'You are now subscribed!';

            \App\Modules\Newsletter\NewsletterHelper::sendWelcome($email, (string) $token, $this->baseUrl);

            if (\App\CMS\ModuleLoader::isEnabled('webhooks')) {
                try {
                    \App\Modules\Webhooks\WebhookDispatcher::dispatch('newsletter.subscribed', [
                        'email' => $email, 'source' => 'widget',
                    ]);
                } catch (\Throwable) {}
            }
        }

        $this->jsonOrRedirect(['success' => true, 'message' => $msg]);
    }

    public function unsubscribe(): void
    {
        $token = trim($this->input->get('token') ?? '');
        if (!$token) {
            $this->showMessage('Invalid unsubscribe link.');
            return;
        }

        $sub = (new \Core\Model('newsletter_subscribers'))->where('token', $token)->get(1);
        if (!$sub) {
            $this->showMessage('Unsubscribe link not found or already used.');
            return;
        }

        (new \Core\Model('newsletter_subscribers'))->where('id', $sub['id'])->update([
            'status'     => 'unsubscribed',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if (\App\CMS\ModuleLoader::isEnabled('webhooks')) {
            try {
                \App\Modules\Webhooks\WebhookDispatcher::dispatch('newsletter.unsubscribed', [
                    'email' => $sub['email'],
                ]);
            } catch (\Throwable) {}
        }

        $this->showMessage('You have been unsubscribed successfully.');
    }

    public function confirm(): void
    {
        $token = trim($this->input->get('token') ?? '');
        if (!$token) {
            $this->showMessage('Invalid confirmation link.');
            return;
        }

        $sub = (new \Core\Model('newsletter_subscribers'))
            ->where('token', $token)
            ->where('status', 'pending')
            ->get(1);

        if (!$sub) {
            $this->showMessage('Confirmation link not found or already used.');
            return;
        }

        (new \Core\Model('newsletter_subscribers'))->where('id', $sub['id'])->update([
            'status'     => 'active',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        \App\Modules\Newsletter\NewsletterHelper::sendWelcome((string) $sub['email'], (string) $sub['token'], $this->baseUrl);

        if (\App\CMS\ModuleLoader::isEnabled('webhooks')) {
            try {
                \App\Modules\Webhooks\WebhookDispatcher::dispatch('newsletter.subscribed', [
                    'email' => $sub['email'], 'source' => $sub['source'] ?? '',
                ]);
            } catch (\Throwable) {}
        }

        $this->showMessage('Your subscription is confirmed. Thank you!', true);
    }

    /**
     * GET /newsletter/track/open/{campaign_id}/{subscriber_token}
     * 1x1 transparent GIF; counts one open per campaign+subscriber.
     */
    public function trackOpen(string $campaignId, string $token): void
    {
        \App\Modules\Newsletter\NewsletterHelper::ensureSchema();

        try {
            $sub = (new \Core\Model('newsletter_subscribers'))
                ->select('id')
                ->where('token', $token)
                ->get(1);

            if ($sub) {
                $inserted = false;
                try {
                    (new \Core\Model('newsletter_opens'))->withoutTimestamps()->save([
                        'campaign_id'   => $campaignId,
                        'subscriber_id' => (string) $sub['id'],
                    ]);
                    $inserted = true;
                } catch (\Throwable) {
                    // UNIQUE violation = repeat open; do not recount
                }

                if ($inserted) {
                    // Atomic increment - avoids read-modify-write races
                    $db = (new \Core\Model('newsletter_campaigns'))->db;
                    $db->query('UPDATE newsletter_campaigns SET open_count = open_count + 1 WHERE id = :id');
                    $db->arrayBind([':id' => $campaignId]);
                    $db->execute();
                }
            }
        } catch (\Throwable) {
        }

        // Always return the pixel, even on errors
        header('Content-Type: image/gif');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        // Smallest valid transparent GIF
        echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        exit;
    }

    /**
     * GET /newsletter/track/click/{campaign_id}?url=
     * Redirects only to URLs recorded for this campaign at send time
     * (open-redirect protection) and increments the link's click count.
     */
    public function trackClick(string $campaignId): void
    {
        \App\Modules\Newsletter\NewsletterHelper::ensureSchema();

        $url = (string) ($this->input->get('url') ?? '');

        try {
            $link = (new \Core\Model('campaign_links'))->withoutTimestamps()
                ->where('campaign_id', $campaignId)
                ->where('url', $url)
                ->get(1);

            if (!$link) {
                // Unknown URL for this campaign - refuse to act as an open redirect
                $this->redirect($this->baseUrl . '/');
            }

            $db = (new \Core\Model('campaign_links'))->db;
            $db->query('UPDATE campaign_links SET click_count = click_count + 1 WHERE id = :id');
            $db->arrayBind([':id' => (string) $link['id']]);
            $db->execute();

            header('Location: ' . $link['url'], true, 302);
            exit;
        } catch (\Throwable) {
            $this->redirect($this->baseUrl . '/');
        }
    }

    private function sendConfirmationEmail(string $email, string $token): void
    {
        try {
            $confirmUrl = $this->baseUrl . '/newsletter/confirm?token=' . $token;
            $subject    = $this->settings['newsletter_confirm_subject'] ?: 'Please confirm your subscription';
            $siteName   = $this->getSiteName();

            $html = '<p>Hi,</p>'
                . '<p>Click the button below to confirm your subscription to <strong>' . htmlspecialchars($siteName) . '</strong>.</p>'
                . '<p><a href="' . htmlspecialchars($confirmUrl) . '" style="display:inline-block;padding:10px 20px;background:#4f46e5;color:#fff;border-radius:5px;text-decoration:none;font-weight:600;">Confirm Subscription</a></p>'
                . '<p>If you did not request this, you can safely ignore this email.</p>'
                . '<p>Or copy this link: ' . htmlspecialchars($confirmUrl) . '</p>';

            $text = "Please confirm your subscription to {$siteName}.\n\n"
                . "Click here: {$confirmUrl}\n\n"
                . "If you did not request this, ignore this email.";

            Mailer::make()->send(
                (new MailMessage())->to($email)->subject($subject)->htmlBody($html)->textBody($text)
            );
        } catch (\Throwable) {}
    }

    private function showMessage(string $msg, bool $success = false): void
    {
        $siteName = $this->getSiteName();
        $color    = $success ? '#065f46' : '#991b1b';
        $bg       = $success ? '#d1fae5' : '#fee2e2';
        $border   = $success ? '#6ee7b7' : '#fca5a5';
        echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>{$siteName}</title></head><body style='font-family:system-ui,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#f9fafb;'>"
           . "<div style='max-width:420px;padding:2rem;background:{$bg};border:1px solid {$border};border-radius:8px;color:{$color};text-align:center;'>"
           . "<p style='margin:0;font-size:1.1rem;'>" . htmlspecialchars($msg) . "</p>"
           . "<p style='margin:.75rem 0 0;'><a href='" . htmlspecialchars($this->baseUrl) . "' style='color:{$color};font-size:.9rem;'>Return to site</a></p>"
           . "</div></body></html>";
        exit;
    }

    private function jsonOrRedirect(array $data): never
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (str_contains($accept, 'application/json') || ($this->input->post('_format') === 'json')) {
            header('Content-Type: application/json');
            echo json_encode($data);
            exit;
        }
        $referer = $_SERVER['HTTP_REFERER'] ?? $this->baseUrl;
        $this->session->set('newsletter_flash', $data);
        header('Location: ' . $referer);
        exit;
    }

    private function getSiteName(): string
    {
        $row = (new \Core\Model('settings'))->select('value')->where('key', 'site_name')->where('grp', 'general')->get(1);
        return $row ? ($row['value'] ?? 'Vertext') : 'Vertext';
    }
}
