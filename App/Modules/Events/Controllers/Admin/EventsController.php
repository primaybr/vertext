<?php

declare(strict_types=1);

namespace App\Modules\Events\Controllers\Admin;

use App\Controllers\Admin\BaseController;
use App\CMS\Auth;
use App\Modules\Events\EventHelper;
use Core\Utilities\Text\Str;

/**
 * Events admin CRUD.
 *
 * GET  /admin/events               → index()
 * GET  /admin/events/create        → createForm()
 * POST /admin/events/store         → store()
 * GET  /admin/events/{id}/edit     → editForm($id)
 * POST /admin/events/{id}/update   → update($id)
 * POST /admin/events/{id}/delete   → delete($id)
 */
class EventsController extends BaseController
{
    protected string $module = 'events';

    public function __construct()
    {
        parent::__construct();
        EventHelper::ensureSchema();
    }

    /** Shared v2 field extraction for store()/update() */
    private function readV2Fields(): array
    {
        $max = (int) ($this->input->post('max_attendees') ?? 0);

        // Recurrence: freq none|daily|weekly|monthly (+ interval, until)
        $freq = $this->input->post('recurrence_freq') ?? '';
        $rule = null;
        if (in_array($freq, ['daily', 'weekly', 'monthly'], true)) {
            $rule = json_encode([
                'freq'     => $freq,
                'interval' => max(1, (int) ($this->input->post('recurrence_interval') ?? 1)),
                'until'    => preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->input->post('recurrence_until') ?? '')
                    ? $this->input->post('recurrence_until')
                    : null,
            ]);
        }

        // Tickets: parallel arrays ticket_name[] / ticket_price[]
        $tickets   = [];
        $tNames    = (array) ($this->input->post('ticket_name') ?? []);
        $tPrices   = (array) ($this->input->post('ticket_price') ?? []);
        foreach ($tNames as $i => $tName) {
            $tName = substr(trim((string) $tName), 0, 100);
            if ($tName === '') continue;
            $price = trim((string) ($tPrices[$i] ?? ''));
            $tickets[] = [
                'name'  => $tName,
                'price' => is_numeric($price) ? round((float) $price, 2) : 0,
            ];
            if (count($tickets) >= 10) break;
        }

        return [
            'max_attendees'   => $max > 0 ? $max : null,
            'recurrence_rule' => $rule,
            'tickets'         => $tickets ? json_encode($tickets) : null,
        ];
    }

    public function index(): void
    {
        $this->requirePermission('events.view');

        $search  = trim($this->input->get('search') ?? '');
        $status  = $this->input->get('status') ?? '';
        $page    = max(1, (int) ($this->input->get('page') ?? 1));
        $perPage = 20;
        $offset  = ($page - 1) * $perPage;

        $q  = $this->db('events')
            ->select('id, title, slug, location, start_at, end_at, status, rsvp_count')
            ->whereNull('deleted_at')
            ->orderBy('start_at', 'DESC')
            ->limitOffset($perPage, $offset);
        $qc = $this->db('events')->whereNull('deleted_at');

        if ($search) {
            $q->whereRaw('title ILIKE :s', [':s' => "%{$search}%"]);
            $qc->whereRaw('title ILIKE :s', [':s' => "%{$search}%"]);
        }
        if (in_array($status, ['draft', 'published'], true)) {
            $q->where('status', $status);
            $qc->where('status', $status);
        }

        $total  = (int) ($qc->totalRows() ?: 0);
        $events = $q->get() ?: [];

        $counts = [
            'published' => (int) ($this->db('events')->where('status', 'published')->whereNull('deleted_at')->totalRows() ?: 0),
            'draft'     => (int) ($this->db('events')->where('status', 'draft')->whereNull('deleted_at')->totalRows() ?: 0),
        ];

        $this->adminRender('modules/events/admin/index', [
            'events'  => $events,
            'total'   => $total,
            'page'    => $page,
            'pages'   => max(1, (int) ceil($total / $perPage)),
            'search'  => $search,
            'status'  => $status,
            'counts'  => $counts,
        ], 'Events', 'events');
    }

    public function createForm(): void
    {
        $this->requirePermission('events.manage');
        $vars = [
            'event'   => null,
            'action'  => $this->baseUrl . '/admin/events/store',
            'isModal' => $this->input->isAjax(),
        ];
        if ($this->input->isAjax()) {
            $this->renderPartial('modules/events/admin/event_form', $vars);
            return;
        }
        $this->adminRender('modules/events/admin/event_form', $vars, 'New Event', 'events');
    }

    public function store(): void
    {
        $this->requirePermission('events.manage');
        $this->validateCsrf();

        $title = trim($this->input->post('title', false) ?? '');
        if (!$title) {
            if ($this->input->isAjax()) {
                $this->json(['success' => false, 'message' => 'Event title is required.']);
                return;
            }
            $this->flash('error', 'Event title is required.');
            $this->redirect($this->baseUrl . '/admin/events/create');
        }

        $rawSlug  = trim($this->input->post('slug', false) ?? '');
        $slug     = $rawSlug ? Str::slug($rawSlug) : Str::slug($title);
        $slug     = $this->uniqueSlug($slug);

        $startAt = trim($this->input->post('start_at', false) ?? '');
        if (!$startAt) {
            if ($this->input->isAjax()) {
                $this->json(['success' => false, 'message' => 'Start date/time is required.']);
                return;
            }
            $this->flash('error', 'Start date/time is required.');
            $this->redirect($this->baseUrl . '/admin/events/create');
        }

        $id = (string) $this->db('events')->save(array_merge([
            'title'       => $title,
            'slug'        => $slug,
            'description' => trim($this->input->post('description', false) ?? ''),
            'body'        => $this->input->post('body', false) ?? '',
            'location'    => trim($this->input->post('location', false) ?? ''),
            'start_at'    => $startAt,
            'end_at'      => trim($this->input->post('end_at', false) ?? '') ?: null,
            'status'      => $this->input->post('status') === 'published' ? 'published' : 'draft',
            'created_by'  => $this->currentUser['id'] ?? null,
        ], $this->readV2Fields()));

        Auth::audit('event.create', 'events', $id, ['title' => $title]);

        if ($this->input->isAjax()) {
            $this->json(['success' => true, 'message' => "Event \"{$title}\" created."]);
            return;
        }
        $this->flash('success', "Event \"{$title}\" created.");
        $this->redirect($this->baseUrl . "/admin/events/{$id}/edit");
    }

    public function editForm(string $id): void
    {
        $this->requirePermission('events.manage');
        $event = $this->db('events')->where('id', $id)->whereNull('deleted_at')->get(1);
        if (!$event) {
            if ($this->input->isAjax()) {
                $this->json(['success' => false, 'message' => 'Event not found.'], 404);
                return;
            }
            $this->flash('error', 'Event not found.');
            $this->redirect($this->baseUrl . '/admin/events');
        }

        $vars = [
            'event'   => $event,
            'action'  => $this->baseUrl . "/admin/events/{$id}/update",
            'isModal' => $this->input->isAjax(),
        ];
        if ($this->input->isAjax()) {
            $this->renderPartial('modules/events/admin/event_form', $vars);
            return;
        }
        $this->adminRender('modules/events/admin/event_form', $vars, 'Edit - ' . $event['title'], 'events');
    }

    public function update(string $id): void
    {
        $this->requirePermission('events.manage');
        $this->validateCsrf();

        $event = $this->db('events')->where('id', $id)->whereNull('deleted_at')->get(1);
        if (!$event) {
            $this->json(['success' => false, 'message' => 'Event not found.'], 404);
        }

        $title = trim($this->input->post('title', false) ?? '');
        if (!$title) {
            $this->json(['success' => false, 'message' => 'Title is required.']);
        }

        $startAt = trim($this->input->post('start_at', false) ?? '');
        if (!$startAt) {
            $this->json(['success' => false, 'message' => 'Start date/time is required.']);
        }

        $rawSlug = trim($this->input->post('slug', false) ?? '');
        $newSlug = $rawSlug ? Str::slug($rawSlug) : null;
        if ($newSlug && $newSlug !== $event['slug']) {
            $newSlug = $this->uniqueSlug($newSlug, $id);
        }

        $data = array_merge([
            'title'       => $title,
            'description' => trim($this->input->post('description', false) ?? ''),
            'body'        => $this->input->post('body', false) ?? '',
            'location'    => trim($this->input->post('location', false) ?? ''),
            'start_at'    => $startAt,
            'end_at'      => trim($this->input->post('end_at', false) ?? '') ?: null,
            'status'      => $this->input->post('status') === 'published' ? 'published' : 'draft',
            'updated_at'  => date('Y-m-d H:i:s'),
            'updated_by'  => $this->currentUser['id'] ?? null,
        ], $this->readV2Fields());
        if ($newSlug) {
            $data['slug'] = $newSlug;
        }

        $this->db('events')->where('id', $id)->update($data);
        Auth::audit('event.update', 'events', $id, ['title' => $title]);
        $this->json(['success' => true, 'message' => 'Event updated.']);
    }

    public function delete(string $id): void
    {
        $this->requirePermission('events.manage');
        $this->validateCsrf();

        $event = $this->db('events')->where('id', $id)->whereNull('deleted_at')->get(1);
        if (!$event) {
            $this->json(['success' => false, 'message' => 'Event not found.'], 404);
        }

        $this->db('events')->where('id', $id)->update([
            'deleted_at' => date('Y-m-d H:i:s'),
            'deleted_by' => $this->currentUser['id'] ?? null,
        ]);

        Auth::audit('event.delete', 'events', $id, ['title' => $event['title']]);
        $this->json(['success' => true, 'message' => "Event \"{$event['title']}\" deleted."]);
    }

    // ── Attendees (v0.0.2) ─────────────────────────────────────────────────────

    /** GET /admin/events/{id}/attendees */
    public function attendees(string $id): void
    {
        $this->requirePermission('events.view');

        $event = $this->db('events')->where('id', $id)->whereNull('deleted_at')->get(1);
        if (!$event) {
            $this->flash('error', 'Event not found.');
            $this->redirect($this->baseUrl . '/admin/events');
        }

        $attendees = $this->db('event_rsvps')
            ->where('event_id', $id)
            ->whereNull('deleted_at')
            ->orderBy('registered_at', 'ASC')
            ->get() ?: [];

        $counts = ['confirmed' => 0, 'waitlist' => 0, 'cancelled' => 0];
        foreach ($attendees as $a) {
            $counts[(string) $a['status']] = ($counts[(string) $a['status']] ?? 0) + 1;
        }

        $this->adminRender('modules/events/admin/attendees', [
            'event'     => $event,
            'attendees' => $attendees,
            'counts'    => $counts,
            'spotsLeft' => EventHelper::spotsLeft($event),
        ], 'Attendees - ' . $event['title'], 'events');
    }

    /** POST /admin/events/{id}/attendees/{rid}/status - AJAX status change */
    public function setAttendeeStatus(string $id, string $rid): void
    {
        $this->requirePermission('events.manage');
        $this->validateCsrf();

        $event = $this->db('events')->where('id', $id)->whereNull('deleted_at')->get(1);
        $rsvp  = $this->db('event_rsvps')->where('id', $rid)->where('event_id', $id)->whereNull('deleted_at')->get(1);
        if (!$event || !$rsvp) {
            $this->json(['success' => false, 'message' => 'Attendee not found.'], 404);
        }

        $to = $this->input->post('status') ?? '';
        if (!in_array($to, ['confirmed', 'waitlist', 'cancelled'], true)) {
            $this->json(['success' => false, 'message' => 'Invalid status.'], 422);
        }

        // Capacity guard when force-confirming
        if ($to === 'confirmed' && $rsvp['status'] !== 'confirmed') {
            $spots = EventHelper::spotsLeft($event);
            if ($spots !== null && $spots <= 0) {
                $this->json(['success' => false, 'message' => 'Event is at capacity. Raise the limit first or cancel another attendee.']);
            }
        }

        $this->db('event_rsvps')->where('id', $rid)->update([
            'status'     => $to,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Cancelling a confirmed spot can promote the next in line
        $promotedName = null;
        if ($to === 'cancelled' && $rsvp['status'] === 'confirmed') {
            $promoted = EventHelper::promoteWaitlist($event);
            if ($promoted) {
                $promotedName = (string) $promoted['name'];
            }
        }

        EventHelper::syncRsvpCount($id);

        Auth::audit('event.attendee_status', 'event_rsvps', $rid, [
            'event' => $event['title'], 'from' => $rsvp['status'], 'to' => $to,
        ]);

        $message = "\"{$rsvp['name']}\" is now {$to}.";
        if ($promotedName !== null) {
            $message .= " \"{$promotedName}\" was promoted from the waiting list.";
        }
        $this->json(['success' => true, 'message' => $message, 'promoted' => $promotedName]);
    }

    /** GET /admin/events/{id}/attendees/export - CSV */
    public function exportAttendees(string $id): void
    {
        $this->requirePermission('events.view');

        $event = $this->db('events')->where('id', $id)->whereNull('deleted_at')->get(1);
        if (!$event) {
            $this->flash('error', 'Event not found.');
            $this->redirect($this->baseUrl . '/admin/events');
        }

        $attendees = $this->db('event_rsvps')
            ->where('event_id', $id)
            ->whereNull('deleted_at')
            ->orderBy('registered_at', 'ASC')
            ->get() ?: [];

        Auth::audit('event.attendees_exported', 'events', $id, ['count' => count($attendees)]);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="attendees-' . $event['slug'] . '-' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
        fputcsv($out, ['Name', 'Email', 'Ticket', 'Status', 'Registered At']);
        foreach ($attendees as $a) {
            fputcsv($out, [
                $a['name'], $a['email'], $a['ticket'] ?? '', $a['status'],
                $a['registered_at'],
            ]);
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

    private function uniqueSlug(string $base, string $excludeId = ''): string
    {
        $slug   = $base;
        $suffix = 2;
        while (true) {
            $q = $this->db('events')->select('id')->where('slug', $slug)->whereNull('deleted_at');
            if ($excludeId) $q->whereRaw('id != ?', [$excludeId]);
            if (!$q->get(1)) break;
            $slug = $base . '-' . $suffix++;
        }
        return $slug;
    }
}
