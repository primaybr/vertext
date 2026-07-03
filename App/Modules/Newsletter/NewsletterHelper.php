<?php

declare(strict_types=1);

namespace App\Modules\Newsletter;

use Core\Model;
use App\Mail\Mailer;
use App\Mail\MailMessage;

/**
 * Shared newsletter delivery logic (v0.0.2).
 *
 * Used by CampaignsController::send(), the scheduled-send check that runs on
 * admin page load (no cron in Vertext - same pattern as scheduled posts), and
 * the public tracking endpoints.
 */
class NewsletterHelper
{
    /** Idempotent schema upgrades for existing 0.0.1 installs. */
    public static function ensureSchema(): void
    {
        static $checked = false;
        if ($checked) return;
        $checked = true;

        try {
            $db = (new Model('newsletter_campaigns'))->db;
            foreach ([
                "ALTER TABLE newsletter_campaigns ADD COLUMN IF NOT EXISTS segment_id UUID",
                "ALTER TABLE newsletter_campaigns ADD COLUMN IF NOT EXISTS open_count INT NOT NULL DEFAULT 0",
                "CREATE TABLE IF NOT EXISTS newsletter_segments (
                    id         UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
                    name       VARCHAR(150) NOT NULL,
                    rules      TEXT         NOT NULL DEFAULT '{}',
                    created_at TIMESTAMP    DEFAULT NOW(),
                    updated_at TIMESTAMP    DEFAULT NOW(),
                    deleted_at TIMESTAMP,
                    created_by UUID,
                    updated_by UUID,
                    deleted_by UUID
                )",
                "CREATE TABLE IF NOT EXISTS newsletter_opens (
                    id            UUID      PRIMARY KEY DEFAULT gen_random_uuid(),
                    campaign_id   UUID      NOT NULL,
                    subscriber_id UUID      NOT NULL,
                    opened_at     TIMESTAMP NOT NULL DEFAULT NOW(),
                    UNIQUE (campaign_id, subscriber_id)
                )",
                "CREATE TABLE IF NOT EXISTS campaign_links (
                    id          UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
                    campaign_id UUID          NOT NULL,
                    url         VARCHAR(2000) NOT NULL,
                    click_count INT           NOT NULL DEFAULT 0,
                    created_at  TIMESTAMP     NOT NULL DEFAULT NOW()
                )",
            ] as $ddl) {
                $db->query($ddl);
                $db->execute();
            }
        } catch (\Throwable $e) {
            // Non-fatal; features degrade to v1 behavior
        }
    }

    /**
     * Resolve a segment's rules to the matching active subscribers.
     * Null/unknown segment = all active subscribers.
     * Rules JSON: {source?: string, subscribed_after?: Y-m-d, subscribed_before?: Y-m-d}
     */
    public static function resolveRecipients(?string $segmentId): array
    {
        $q = (new Model('newsletter_subscribers'))
            ->select('id, email, name, token')
            ->where('status', 'active')
            ->whereNull('deleted_at');

        if ($segmentId) {
            try {
                $segment = (new Model('newsletter_segments'))
                    ->where('id', $segmentId)
                    ->whereNull('deleted_at')
                    ->get(1);
                $rules = $segment ? (json_decode($segment['rules'] ?: '{}', true) ?: []) : [];

                if (!empty($rules['source'])) {
                    $q->where('source', (string) $rules['source']);
                }
                if (!empty($rules['subscribed_after'])) {
                    $q->whereRaw('created_at >= :after', [':after' => $rules['subscribed_after'] . ' 00:00:00']);
                }
                if (!empty($rules['subscribed_before'])) {
                    $q->whereRaw('created_at <= :before', [':before' => $rules['subscribed_before'] . ' 23:59:59']);
                }
            } catch (\Throwable $e) {
                // Broken segment -> fall back to all active
            }
        }

        return $q->get() ?: [];
    }

    /**
     * Deliver a campaign to its recipients. Returns the sent count.
     * Handles link-rewrite for click tracking + open pixel injection.
     */
    public static function deliverCampaign(array $campaign, string $siteUrl): int
    {
        self::ensureSchema();

        $settings = [];
        foreach ((new Model('settings'))->where('grp', 'newsletter')->get() ?: [] as $r) {
            $settings[$r['key']] = $r['value'];
        }
        $fromName  = $settings['newsletter_from_name'] ?: self::siteName();
        $fromEmail = $settings['newsletter_from_email'] ?: '';

        $subject  = (string) $campaign['subject'];
        $bodyText = (string) ($campaign['body_text'] ?? '');

        // Rewrite links once per campaign (per-subscriber token appended at send)
        $bodyHtml = self::rewriteLinks((string) ($campaign['body_html'] ?? ''), (string) $campaign['id'], $siteUrl);

        $recipients = self::resolveRecipients($campaign['segment_id'] ?? null);

        $sent = 0;
        foreach ($recipients as $sub) {
            $unsub  = $siteUrl . '/newsletter/unsubscribe?token=' . $sub['token'];
            $pixel  = '<img src="' . htmlspecialchars($siteUrl . '/newsletter/track/open/' . $campaign['id'] . '/' . $sub['token']) . '" width="1" height="1" alt="" style="display:block;border:0;">';
            $footer = "\n\n--\nYou received this because you subscribed at {$siteUrl}.\nUnsubscribe: {$unsub}";
            $htmlFooter = '<div style="margin-top:32px;padding-top:16px;border-top:1px solid #e5e7eb;font-size:12px;color:#9ca3af;">'
                . 'You received this because you subscribed at <a href="' . htmlspecialchars($siteUrl) . '">' . htmlspecialchars($siteUrl) . '</a>.'
                . ' <a href="' . htmlspecialchars($unsub) . '">Unsubscribe</a></div>';

            try {
                $msg = (new MailMessage())
                    ->to($sub['email'], $sub['name'] ?? '')
                    ->subject($subject)
                    ->htmlBody($bodyHtml . $htmlFooter . $pixel)
                    ->textBody($bodyText . $footer);

                if ($fromEmail && method_exists($msg, 'from')) {
                    $msg->from($fromEmail, $fromName);
                }

                Mailer::make()->send($msg);
                $sent++;
            } catch (\Throwable $e) {
                // continue on per-recipient failure
            }
        }

        return $sent;
    }

    /**
     * Replace every http(s) link in the campaign body with a tracked redirect.
     * Each unique URL gets a campaign_links row; the redirect endpoint only
     * honors URLs present in that table (open-redirect protection).
     */
    public static function rewriteLinks(string $html, string $campaignId, string $siteUrl): string
    {
        if ($html === '') return $html;

        return (string) preg_replace_callback(
            '/href="(https?:\/\/[^"]+)"/i',
            static function (array $m) use ($campaignId, $siteUrl): string {
                $url = html_entity_decode($m[1]);

                try {
                    $existing = (new Model('campaign_links'))->withoutTimestamps()
                        ->where('campaign_id', $campaignId)
                        ->where('url', $url)
                        ->get(1);
                    if (!$existing) {
                        (new Model('campaign_links'))->withoutTimestamps()->save([
                            'campaign_id' => $campaignId,
                            'url'         => substr($url, 0, 2000),
                        ]);
                    }
                } catch (\Throwable $e) {
                    return $m[0]; // tracking failed - leave the original link
                }

                $tracked = $siteUrl . '/newsletter/track/click/' . $campaignId . '?url=' . rawurlencode($url);
                return 'href="' . htmlspecialchars($tracked) . '"';
            },
            $html
        );
    }

    /**
     * Send any scheduled campaigns whose time has come. Called on admin
     * newsletter page load (no cron - mirrors scheduled posts). Returns the
     * number of campaigns processed.
     */
    public static function processScheduled(string $siteUrl): int
    {
        self::ensureSchema();

        try {
            $due = (new Model('newsletter_campaigns'))
                ->where('status', 'scheduled')
                ->whereRaw('scheduled_at <= NOW()', [])
                ->whereNull('deleted_at')
                ->get() ?: [];
        } catch (\Throwable $e) {
            return 0;
        }

        $processed = 0;
        foreach ($due as $campaign) {
            try {
                // Claim first so a concurrent page load cannot double-send
                (new Model('newsletter_campaigns'))->where('id', $campaign['id'])
                    ->where('status', 'scheduled')
                    ->update(['status' => 'sending', 'updated_at' => date('Y-m-d H:i:s')]);

                $sent = self::deliverCampaign($campaign, $siteUrl);

                (new Model('newsletter_campaigns'))->where('id', $campaign['id'])->update([
                    'status'     => 'sent',
                    'sent_count' => $sent,
                    'sent_at'    => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                if (\App\CMS\ModuleLoader::isEnabled('webhooks')) {
                    try {
                        \App\Modules\Webhooks\WebhookDispatcher::dispatch('campaign.sent', [
                            'campaign_id' => (string) $campaign['id'],
                            'subject'     => (string) $campaign['subject'],
                            'sent_count'  => $sent,
                            'sent_at'     => date('c'),
                            'scheduled'   => true,
                        ]);
                    } catch (\Throwable $e) {
                    }
                }
                $processed++;
            } catch (\Throwable $e) {
                // Leave campaign in 'sending' for manual inspection
            }
        }

        return $processed;
    }

    /** Send the optional welcome email when a subscriber becomes active. */
    public static function sendWelcome(string $email, string $token, string $siteUrl): void
    {
        try {
            $settings = [];
            foreach ((new Model('settings'))->where('grp', 'newsletter')->get() ?: [] as $r) {
                $settings[$r['key']] = $r['value'];
            }
            if (empty($settings['newsletter_welcome_enabled']) || $settings['newsletter_welcome_enabled'] !== '1') {
                return;
            }

            $siteName = self::siteName();
            $subject  = $settings['newsletter_welcome_subject'] ?: ('Welcome to the ' . $siteName . ' newsletter');
            $body     = $settings['newsletter_welcome_body'] ?: ('Thanks for subscribing to ' . $siteName . '! You will hear from us soon.');

            $unsub = $siteUrl . '/newsletter/unsubscribe?token=' . $token;
            $html  = '<p>' . nl2br(htmlspecialchars($body)) . '</p>'
                . '<div style="margin-top:32px;padding-top:16px;border-top:1px solid #e5e7eb;font-size:12px;color:#9ca3af;">'
                . '<a href="' . htmlspecialchars($unsub) . '">Unsubscribe</a></div>';

            Mailer::make()->send(
                (new MailMessage())->to($email)->subject($subject)->htmlBody($html)->textBody($body . "\n\nUnsubscribe: " . $unsub)
            );
        } catch (\Throwable $e) {
            // Welcome email is best-effort
        }
    }

    private static function siteName(): string
    {
        try {
            $row = (new Model('settings'))->select('value')->where('key', 'site_name')->get(1);
            return $row['value'] ?? 'Vertext';
        } catch (\Throwable $e) {
            return 'Vertext';
        }
    }
}
