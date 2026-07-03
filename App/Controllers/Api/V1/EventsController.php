<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use Core\Model;

/**
 * GET /api/v1/events          - published events (paginated; ?upcoming=1 filters future)
 * GET /api/v1/events/{slug}   - single published event
 */
class EventsController extends ApiController
{
    public function index(): void
    {
        if (!\App\CMS\ModuleLoader::isEnabled('events')) {
            $this->fail(404, 'The events module is not enabled.');
        }

        [$page, $perPage] = $this->pageParams();

        $q = (new Model('events'))
            ->select('id, title, slug, description, location, start_at, end_at, rsvp_count, max_attendees, tickets, recurrence_rule, featured_image, created_at')
            ->where('status', 'published')
            ->whereNull('deleted_at')
            ->orderBy('start_at', 'ASC');

        $qc = (new Model('events'))->where('status', 'published')->whereNull('deleted_at');

        if (($this->input->get('upcoming') ?? '') === '1') {
            $now = date('Y-m-d H:i:s');
            $q->whereRaw('start_at >= :now', [':now' => $now]);
            $qc->whereRaw('start_at >= :now', [':now' => $now]);
        }

        $total = (int) ($qc->totalRows() ?: 0);
        $items = $q->limitOffset($perPage, ($page - 1) * $perPage)->get() ?: [];

        foreach ($items as &$item) {
            $item['tickets'] = json_decode((string) ($item['tickets'] ?? '[]'), true) ?: null;
            $item['recurrence_rule'] = json_decode((string) ($item['recurrence_rule'] ?? ''), true) ?: null;
        }
        unset($item);

        $this->paginated($items, $page, $perPage, $total);
    }

    public function show(string $slug): void
    {
        if (!\App\CMS\ModuleLoader::isEnabled('events')) {
            $this->fail(404, 'The events module is not enabled.');
        }

        $event = (new Model('events'))
            ->select('id, title, slug, description, body, location, start_at, end_at, rsvp_count, max_attendees, tickets, recurrence_rule, featured_image, meta_title, meta_description, created_at, updated_at')
            ->where('slug', $slug)
            ->where('status', 'published')
            ->whereNull('deleted_at')
            ->get(1);

        if (!$event) {
            $this->fail(404, 'Event not found.');
        }

        $event['tickets']         = json_decode((string) ($event['tickets'] ?? '[]'), true) ?: null;
        $event['recurrence_rule'] = json_decode((string) ($event['recurrence_rule'] ?? ''), true) ?: null;

        $this->respond($event);
    }
}
