<?php

declare(strict_types=1);

namespace App\Modules\Events\Controllers\Front;

use Core\Controller;
use Core\Model;
use App\Modules\Events\EventHelper;
use App\Theme\ThemeEngine;

/**
 * Public events frontend.
 *
 * GET  /events               → index()
 * GET  /events/{slug}        → detail($slug)
 * POST /events/{slug}/rsvp   → rsvp($slug)      (v2: name+email, capacity, waitlist)
 * GET  /events/{slug}/ical   → ical($slug)      (.ics download)
 * GET  /events/rsvp/cancel   → cancelRsvp()     (?token=)
 */
class EventsFrontController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        EventHelper::ensureSchema();
    }

    public function index(): void
    {
        $now = date('Y-m-d H:i:s');

        // Upcoming = future one-off events PLUS recurring events expanded into
        // their next occurrences (a recurring event whose first date passed
        // still surfaces its future repeats).
        $candidates = (new Model('events'))
            ->select('id, title, slug, description, location, start_at, end_at, rsvp_count, featured_image, recurrence_rule, max_attendees')
            ->where('status', 'published')
            ->whereNull('deleted_at')
            ->whereRaw('(start_at >= :now OR (recurrence_rule IS NOT NULL AND recurrence_rule <> :empty))', [':now' => $now, ':empty' => ''])
            ->orderBy('start_at', 'ASC')
            ->get() ?: [];

        $upcoming = EventHelper::expandRecurrences($candidates);

        $past = (new Model('events'))
            ->select('id, title, slug, description, location, start_at, end_at, rsvp_count, featured_image')
            ->where('status', 'published')
            ->whereNull('deleted_at')
            ->whereRaw('start_at < :now', [':now' => $now])
            ->orderBy('start_at', 'DESC')
            ->limitOffset(20, 0)
            ->get() ?: [];

        ThemeEngine::render('modules/events/front/index', [
            'upcoming'   => $upcoming,
            'past'       => $past,
            'baseUrl'    => $this->baseUrl,
            'page_title' => 'Events',
        ]);
    }

    public function detail(string $slug): void
    {
        $event = $this->loadEvent($slug);

        $flash = $this->session->flash('event_rsvp_flash') ?: [];

        // Pre-fill for logged-in members
        $member = null;
        if (\App\CMS\ModuleLoader::isEnabled('members') && \App\CMS\SiteAuth::check()) {
            $member = \App\CMS\SiteAuth::user();
        }

        ThemeEngine::render('modules/events/front/detail', [
            'event'      => $event,
            'flash'      => is_array($flash) ? $flash : [],
            'baseUrl'    => $this->baseUrl,
            'csrf_token' => $this->csrf->getToken(),
            'page_title' => $event['title'],
            'member'     => $member,
            'spots_left' => EventHelper::spotsLeft($event),
            'tickets'    => EventHelper::tickets($event),
        ]);
    }

    /** POST /events/{slug}/rsvp - per-attendee registration with capacity */
    public function rsvp(string $slug): void
    {
        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->session->set('event_rsvp_flash', ['type' => 'error', 'message' => 'Security token invalid.']);
            $this->redirect($this->baseUrl . '/events/' . $slug);
        }

        $event = $this->loadEvent($slug, false);
        if (!$event) {
            $this->redirect($this->baseUrl . '/events');
        }

        if (strtotime((string) $event['start_at']) < time() && empty($event['recurrence_rule'])) {
            $this->session->set('event_rsvp_flash', ['type' => 'error', 'message' => 'This event has already taken place.']);
            $this->redirect($this->baseUrl . '/events/' . $slug);
        }

        $name  = trim($this->input->post('name', false) ?? '');
        $email = strtolower(trim($this->input->post('email', false) ?? ''));

        if ($name === '' || mb_strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->session->set('event_rsvp_flash', ['type' => 'error', 'message' => 'Please provide your name and a valid email address.']);
            $this->redirect($this->baseUrl . '/events/' . $slug);
        }

        // One RSVP per email per event
        $existing = (new Model('event_rsvps'))
            ->where('event_id', (string) $event['id'])
            ->where('email', $email)
            ->whereNull('deleted_at')
            ->get(1);
        if ($existing && $existing['status'] !== 'cancelled') {
            $label = $existing['status'] === 'waitlist' ? 'on the waiting list' : 'registered';
            $this->session->set('event_rsvp_flash', ['type' => 'info', 'message' => "You are already {$label} for this event."]);
            $this->redirect($this->baseUrl . '/events/' . $slug);
        }

        // Optional ticket type (validated against the event's ticket list)
        $ticket  = trim($this->input->post('ticket', false) ?? '');
        $tickets = EventHelper::tickets($event);
        if ($tickets) {
            $names = array_map(static fn(array $t) => (string) ($t['name'] ?? ''), $tickets);
            if (!in_array($ticket, $names, true)) {
                $this->session->set('event_rsvp_flash', ['type' => 'error', 'message' => 'Please choose a ticket type.']);
                $this->redirect($this->baseUrl . '/events/' . $slug);
            }
        } else {
            $ticket = null;
        }

        // Capacity: confirmed if space remains, waitlist otherwise
        $spots  = EventHelper::spotsLeft($event);
        $status = ($spots === null || $spots > 0) ? 'confirmed' : 'waitlist';

        $memberId = null;
        if (\App\CMS\ModuleLoader::isEnabled('members') && \App\CMS\SiteAuth::check()) {
            $memberId = \App\CMS\SiteAuth::id();
        }

        if ($existing) {
            // Re-registering after a cancellation reuses the row
            (new Model('event_rsvps'))->where('id', (string) $existing['id'])->update([
                'name'          => $name,
                'status'        => $status,
                'ticket'        => $ticket,
                'site_user_id'  => $memberId,
                'registered_at' => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
            $rsvp = (new Model('event_rsvps'))->where('id', (string) $existing['id'])->get(1);
        } else {
            $rsvpId = (string) (new Model('event_rsvps'))->save([
                'event_id'     => (string) $event['id'],
                'site_user_id' => $memberId,
                'name'         => $name,
                'email'        => $email,
                'ticket'       => $ticket,
                'status'       => $status,
            ]);
            $rsvp = (new Model('event_rsvps'))->where('id', $rsvpId)->get(1);
        }

        EventHelper::syncRsvpCount((string) $event['id']);
        $this->sendRsvpEmail($event, $rsvp);

        if (\App\CMS\ModuleLoader::isEnabled('webhooks')) {
            try {
                \App\Modules\Webhooks\WebhookDispatcher::dispatch('event.rsvp', [
                    'event_id'    => (string) $event['id'],
                    'event_slug'  => (string) $event['slug'],
                    'event_title' => (string) $event['title'],
                    'attendee'    => ['name' => $name, 'email' => $email, 'status' => $status, 'ticket' => $ticket],
                    'rsvp_count'  => EventHelper::confirmedCount((string) $event['id']),
                ]);
            } catch (\Throwable) {}
        }

        $message = $status === 'confirmed'
            ? 'You are registered! A confirmation email is on its way.'
            : 'This event is at capacity - you have been added to the waiting list and will be notified if a spot opens.';
        $this->session->set('event_rsvp_flash', ['type' => $status === 'confirmed' ? 'success' : 'info', 'message' => $message]);
        $this->redirect($this->baseUrl . '/events/' . $slug);
    }

    /** GET /events/{slug}/ical - downloadable .ics */
    public function ical(string $slug): void
    {
        $event = $this->loadEvent($slug);

        $ics = EventHelper::buildIcs($event, $this->siteUrl());

        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $event['slug'] . '.ics"');
        header('Content-Length: ' . strlen($ics));
        echo $ics;
        exit;
    }

    /** GET /events/rsvp/cancel?token= - cancel an RSVP, promote the waitlist */
    public function cancelRsvp(): void
    {
        $token = trim((string) ($this->input->get('token') ?? ''));

        if ($token === '' || !preg_match('/^[a-f0-9\-]{36}$/', $token)) {
            $this->redirect($this->baseUrl . '/events');
        }

        $rsvp = (new Model('event_rsvps'))
            ->where('token', $token)
            ->whereNull('deleted_at')
            ->get(1);

        if (!$rsvp || $rsvp['status'] === 'cancelled') {
            $this->session->set('event_rsvp_flash', ['type' => 'info', 'message' => 'That registration was already cancelled or does not exist.']);
            $this->redirect($this->baseUrl . '/events');
        }

        $event = (new Model('events'))->where('id', (string) $rsvp['event_id'])->whereNull('deleted_at')->get(1);

        (new Model('event_rsvps'))->where('id', (string) $rsvp['id'])->update([
            'status'     => 'cancelled',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if ($event) {
            EventHelper::syncRsvpCount((string) $event['id']);

            // A confirmed cancellation may free a spot for the waitlist
            if ($rsvp['status'] === 'confirmed') {
                $promoted = EventHelper::promoteWaitlist($event);
                if ($promoted) {
                    EventHelper::syncRsvpCount((string) $event['id']);
                    $this->sendRsvpEmail($event, $promoted, true);
                }
            }

            $this->session->set('event_rsvp_flash', ['type' => 'success', 'message' => 'Your registration has been cancelled.']);
            $this->redirect($this->baseUrl . '/events/' . $event['slug']);
        }

        $this->redirect($this->baseUrl . '/events');
    }

    // ── Internal ───────────────────────────────────────────────────────────────

    private function loadEvent(string $slug, bool $abort = true): ?array
    {
        $event = (new Model('events'))
            ->where('slug', $slug)
            ->where('status', 'published')
            ->whereNull('deleted_at')
            ->get(1);

        if (!$event && $abort) {
            http_response_code(404);
            ThemeEngine::render('errors/404', ['page_title' => 'Not Found']);
            exit;
        }

        return $event ?: null;
    }

    /** Confirmation / waitlist / promotion email with calendar + cancel links */
    private function sendRsvpEmail(array $event, array $rsvp, bool $promoted = false): void
    {
        try {
            $siteUrl   = $this->siteUrl();
            $icalUrl   = $siteUrl . '/events/' . $event['slug'] . '/ical';
            $cancelUrl = $siteUrl . '/events/rsvp/cancel?token=' . urlencode((string) $rsvp['token']);
            $when      = date('l, F j, Y g:i A', strtotime((string) $event['start_at']));

            if ($promoted) {
                $subject = 'You are off the waiting list: ' . $event['title'];
                $intro   = 'Good news - a spot opened up and your registration is now <strong>confirmed</strong>.';
            } elseif (($rsvp['status'] ?? '') === 'waitlist') {
                $subject = 'Waiting list: ' . $event['title'];
                $intro   = 'The event is currently at capacity, so you are on the <strong>waiting list</strong>. We will email you if a spot opens.';
            } else {
                $subject = 'Registration confirmed: ' . $event['title'];
                $intro   = 'Your registration is <strong>confirmed</strong>. We look forward to seeing you!';
            }

            $html = '<h2>' . htmlspecialchars((string) $event['title']) . '</h2>'
                . '<p>Hi ' . htmlspecialchars((string) $rsvp['name']) . ',</p>'
                . '<p>' . $intro . '</p>'
                . '<table style="width:100%;border-collapse:collapse;margin:16px 0;font-size:13px;">'
                . '<tr><td style="padding:6px 0;color:#64748b;width:90px;">When</td><td>' . htmlspecialchars($when) . '</td></tr>'
                . (!empty($event['location']) ? '<tr><td style="padding:6px 0;color:#64748b;">Where</td><td>' . htmlspecialchars((string) $event['location']) . '</td></tr>' : '')
                . (!empty($rsvp['ticket']) ? '<tr><td style="padding:6px 0;color:#64748b;">Ticket</td><td>' . htmlspecialchars((string) $rsvp['ticket']) . '</td></tr>' : '')
                . '</table>'
                . '<p><a href="' . htmlspecialchars($icalUrl) . '" style="display:inline-block;padding:10px 20px;background:#4f46e5;color:#fff;border-radius:5px;text-decoration:none;font-weight:600;">Add to calendar (.ics)</a></p>'
                . '<p style="font-size:13px;color:#94a3b8;">Can no longer attend? <a href="' . htmlspecialchars($cancelUrl) . '">Cancel your registration</a>.</p>';

            \App\Mail\Mailer::make()->send(
                (new \App\Mail\MailMessage())
                    ->to((string) $rsvp['email'], (string) $rsvp['name'])
                    ->subject($subject)
                    ->htmlBody($html)
            );
        } catch (\Throwable) {
            // Email is best-effort; the RSVP row is the source of truth
        }
    }

    private function siteUrl(): string
    {
        try {
            $row = (new Model('settings'))->select('value')->where('key', 'site_url')->get(1);
            $url = trim((string) ($row['value'] ?? ''));
            if ($url !== '') return rtrim($url, '/');
        } catch (\Throwable) {
        }
        return $this->baseUrl;
    }
}
