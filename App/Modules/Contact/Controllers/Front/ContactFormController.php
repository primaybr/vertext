<?php

declare(strict_types=1);

namespace App\Modules\Contact\Controllers\Front;

use Core\Controller;
use App\Theme\ThemeEngine;
use App\Mail\Mailer;
use App\Mail\MailMessage;
use App\Mail\MailTemplate;

/**
 * Public contact form.
 *
 * GET  /contact  → show()
 * POST /contact  → submit()
 */
class ContactFormController extends Controller
{
    private array $settings = [];

    public function __construct()
    {
        parent::__construct();

        $rows = (new \Core\Model('settings'))->where('grp', 'contact')->get() ?: [];
        foreach ($rows as $r) {
            $this->settings[$r['key']] = $r['value'];
        }
    }

    public function show(): void
    {
        $flash = $this->session->flash('contact_flash') ?: [];

        ThemeEngine::render('modules/contact/front/form', [
            'flash'      => is_array($flash) ? $flash : [],
            'baseUrl'    => $this->baseUrl,
            'csrf_token' => $this->csrf->getToken(),
            'page_title' => 'Contact',
        ]);
    }

    public function submit(): void
    {
        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->session->set('contact_flash', ['type' => 'error', 'message' => 'Security token invalid. Please try again.']);
            $this->redirect($this->baseUrl . '/contact');
        }

        // Basic rate limit: 1 submission per IP per 10 minutes
        $ip      = $this->getIp();
        $since   = date('Y-m-d H:i:s', time() - 600);
        $recent  = (new \Core\Model('contact_submissions'))
            ->where('ip_address', $ip)
            ->whereRaw("submitted_at > :since", [':since' => $since])
            ->get(1);

        if ($recent) {
            $this->session->set('contact_flash', ['type' => 'error', 'message' => 'Please wait a few minutes before submitting another message.']);
            $this->redirect($this->baseUrl . '/contact');
        }

        $name    = substr(trim($this->input->post('name',    false) ?? ''), 0, 120);
        $email   = substr(trim($this->input->post('email',   false) ?? ''), 0, 180);
        $subject = substr(trim($this->input->post('subject', false) ?? ''), 0, 200);
        $message = substr(trim($this->input->post('message', false) ?? ''), 0, 3000);

        if (!$name || !$email || !$message) {
            $this->session->set('contact_flash', ['type' => 'error', 'message' => 'Name, email, and message are required.']);
            $this->redirect($this->baseUrl . '/contact');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->session->set('contact_flash', ['type' => 'error', 'message' => 'Please enter a valid email address.']);
            $this->redirect($this->baseUrl . '/contact');
        }

        $id = (string) (new \Core\Model('contact_submissions'))->withoutTimestamps()->save([
            'name'       => $name,
            'email'      => $email,
            'subject'    => $subject ?: '(no subject)',
            'message'    => $message,
            'status'     => 'unread',
            'ip_address' => $ip,
        ]);

        // Email notifications
        $adminEmail = trim($this->settings['contact_admin_email'] ?? '');
        if ($adminEmail) {
            $this->sendAdminNotification($name, $email, $subject, $message, $adminEmail, $id);
        }

        if ((bool) ($this->settings['contact_auto_reply'] ?? false)) {
            $this->sendAutoReply($name, $email);
        }

        $this->session->set('contact_flash', ['type' => 'success', 'message' => 'Thank you! Your message has been sent.']);
        $this->redirect($this->baseUrl . '/contact');
    }

    private function sendAdminNotification(string $name, string $email, string $subject, string $message, string $adminEmail, string $submissionId): void
    {
        try {
            $siteRow   = (new \Core\Model('settings'))->select('value')->where('key', 'site_name')->where('grp', 'general')->get(1);
            $siteName  = $siteRow ? ($siteRow['value'] ?? 'Vertext') : 'Vertext';
            $siteUrl   = $this->baseUrl;
            $inboxUrl  = $siteUrl . '/admin/contact/' . $submissionId;

            $html = MailTemplate::render('contact_notification', [
                'senderName'  => $name,
                'senderEmail' => $email,
                'subject'     => $subject ?: '(no subject)',
                'messageBody' => $message,
                'submittedAt' => date('F j, Y \a\t g:i A'),
                'inboxUrl'    => $inboxUrl,
                'siteName'    => $siteName,
                'siteUrl'     => $siteUrl,
            ]);

            $msg = (new MailMessage())
                ->to($adminEmail)
                ->subject("New contact message from {$name}")
                ->htmlBody($html)
                ->textBody("From: {$name} <{$email}>\n\n{$message}");

            Mailer::make()->send($msg);
        } catch (\Throwable) {
            // Notification failure must not break the user flow
        }
    }

    private function sendAutoReply(string $name, string $senderEmail): void
    {
        try {
            $siteRow  = (new \Core\Model('settings'))->select('value')->where('key', 'site_name')->where('grp', 'general')->get(1);
            $siteName = $siteRow ? ($siteRow['value'] ?? 'Vertext') : 'Vertext';

            $customMsg = trim($this->settings['contact_auto_reply_msg'] ?? '')
                ?: "We've received your message and will get back to you as soon as possible.";

            $html = MailTemplate::render('contact_autoreply', [
                'senderName'    => $name,
                'customMessage' => $customMsg,
                'siteName'      => $siteName,
                'siteUrl'       => $this->baseUrl,
            ]);

            $msg = (new MailMessage())
                ->to($senderEmail, $name)
                ->subject("We received your message - {$siteName}")
                ->htmlBody($html)
                ->textBody("{$customMsg}\n\n- {$siteName}");

            Mailer::make()->send($msg);
        } catch (\Throwable) {
            // Auto-reply failure must not break the user flow
        }
    }

    private function getIp(): string
    {
        return substr(
            $_SERVER['HTTP_CF_CONNECTING_IP']
                ?? $_SERVER['HTTP_X_FORWARDED_FOR']
                ?? $_SERVER['REMOTE_ADDR']
                ?? '',
            0,
            45
        );
    }
}
