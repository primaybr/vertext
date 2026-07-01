<?php

declare(strict_types=1);

namespace App\Modules\Events\Controllers\Admin;

use App\Controllers\Admin\BaseController;
use App\CMS\Auth;
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

        $id = (string) $this->db('events')->save([
            'title'       => $title,
            'slug'        => $slug,
            'description' => trim($this->input->post('description', false) ?? ''),
            'body'        => $this->input->post('body', false) ?? '',
            'location'    => trim($this->input->post('location', false) ?? ''),
            'start_at'    => $startAt,
            'end_at'      => trim($this->input->post('end_at', false) ?? '') ?: null,
            'status'      => $this->input->post('status') === 'published' ? 'published' : 'draft',
            'created_by'  => $this->currentUser['id'] ?? null,
        ]);

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

        $data = [
            'title'       => $title,
            'description' => trim($this->input->post('description', false) ?? ''),
            'body'        => $this->input->post('body', false) ?? '',
            'location'    => trim($this->input->post('location', false) ?? ''),
            'start_at'    => $startAt,
            'end_at'      => trim($this->input->post('end_at', false) ?? '') ?: null,
            'status'      => $this->input->post('status') === 'published' ? 'published' : 'draft',
            'updated_at'  => date('Y-m-d H:i:s'),
            'updated_by'  => $this->currentUser['id'] ?? null,
        ];
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
