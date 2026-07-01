<?php

declare(strict_types=1);

namespace App\Modules\Events\Controllers\Front;

use Core\Controller;
use App\Theme\ThemeEngine;

/**
 * Public events frontend.
 *
 * GET  /events           → index()
 * GET  /events/{slug}    → detail($slug)
 * POST /events/{slug}/rsvp → rsvp($slug)
 */
class EventsFrontController extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index(): void
    {
        $now = date('Y-m-d H:i:s');

        $upcoming = (new \Core\Model('events'))
            ->select('id, title, slug, description, location, start_at, end_at, rsvp_count, featured_image')
            ->where('status', 'published')
            ->whereNull('deleted_at')
            ->whereRaw('start_at >= :now', [':now' => $now])
            ->orderBy('start_at', 'ASC')
            ->get() ?: [];

        $past = (new \Core\Model('events'))
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
        $event = (new \Core\Model('events'))
            ->where('slug', $slug)
            ->where('status', 'published')
            ->whereNull('deleted_at')
            ->get(1);

        if (!$event) {
            http_response_code(404);
            ThemeEngine::render('errors/404', ['page_title' => 'Not Found']);
            exit;
        }

        $flash = $this->session->flash('event_rsvp_flash') ?: [];

        ThemeEngine::render('modules/events/front/detail', [
            'event'      => $event,
            'flash'      => is_array($flash) ? $flash : [],
            'baseUrl'    => $this->baseUrl,
            'csrf_token' => $this->csrf->getToken(),
            'page_title' => $event['title'],
        ]);
    }

    public function rsvp(string $slug): void
    {
        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->session->set('event_rsvp_flash', ['type' => 'error', 'message' => 'Security token invalid.']);
            $this->redirect($this->baseUrl . '/events/' . $slug);
        }

        $event = (new \Core\Model('events'))
            ->where('slug', $slug)
            ->where('status', 'published')
            ->whereNull('deleted_at')
            ->get(1);

        if (!$event) {
            $this->redirect($this->baseUrl . '/events');
        }

        // Cookie-based double-submit prevention
        $cookieKey = 'rsvp_' . $event['id'];
        if (!empty($_COOKIE[$cookieKey])) {
            $this->session->set('event_rsvp_flash', ['type' => 'info', 'message' => 'You have already indicated interest in this event.']);
            $this->redirect($this->baseUrl . '/events/' . $slug);
        }

        // Increment rsvp_count
        (new \Core\Model('events'))->where('id', $event['id'])->update([
            'rsvp_count' => (int) $event['rsvp_count'] + 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Set cookie for 30 days
        setcookie($cookieKey, '1', time() + 86400 * 30, '/', '', false, true);

        if (\App\CMS\ModuleLoader::isEnabled('webhooks')) {
            try {
                \App\Modules\Webhooks\WebhookDispatcher::dispatch('event.rsvp', [
                    'event_id'   => $event['id'],
                    'event_slug' => $event['slug'],
                    'event_title'=> $event['title'],
                    'rsvp_count' => (int) $event['rsvp_count'] + 1,
                ]);
            } catch (\Throwable) {}
        }

        $this->session->set('event_rsvp_flash', ['type' => 'success', 'message' => 'Thanks! Your interest has been recorded.']);
        $this->redirect($this->baseUrl . '/events/' . $slug);
    }
}
