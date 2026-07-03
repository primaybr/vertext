<?php

declare(strict_types=1);

namespace App\Modules\Events;

use App\CMS\ModuleInterface;

class Module implements ModuleInterface
{
    public function install(\Core\Database\Connection $db): void
    {
        $db->query("CREATE TABLE IF NOT EXISTS events (
            id              UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
            title           VARCHAR(255) NOT NULL,
            slug            VARCHAR(255) UNIQUE NOT NULL,
            description     TEXT,
            body            TEXT,
            location        VARCHAR(255),
            start_at        TIMESTAMP    NOT NULL,
            end_at          TIMESTAMP,
            status          VARCHAR(20)  NOT NULL DEFAULT 'draft',
            rsvp_count      INT          NOT NULL DEFAULT 0,
            featured_image  VARCHAR(500),
            meta_title      VARCHAR(160),
            meta_description VARCHAR(320),
            created_at      TIMESTAMP    DEFAULT NOW(),
            updated_at      TIMESTAMP    DEFAULT NOW(),
            deleted_at      TIMESTAMP,
            created_by      UUID,
            updated_by      UUID,
            deleted_by      UUID
        )");
        $db->execute();

        // v0.0.2 schema: capacity, recurrence, tickets, per-attendee RSVPs
        $db->query("ALTER TABLE events ADD COLUMN IF NOT EXISTS max_attendees INT");
        $db->execute();
        $db->query("ALTER TABLE events ADD COLUMN IF NOT EXISTS recurrence_rule TEXT");
        $db->execute();
        $db->query("ALTER TABLE events ADD COLUMN IF NOT EXISTS tickets TEXT");
        $db->execute();
        $db->query("CREATE TABLE IF NOT EXISTS event_rsvps (
            id            UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
            event_id      UUID         NOT NULL REFERENCES events(id) ON DELETE CASCADE,
            site_user_id  UUID,
            name          VARCHAR(120) NOT NULL,
            email         VARCHAR(180) NOT NULL,
            ticket        VARCHAR(100),
            status        VARCHAR(20)  NOT NULL DEFAULT 'confirmed',
            token         UUID         NOT NULL DEFAULT gen_random_uuid(),
            registered_at TIMESTAMP    NOT NULL DEFAULT NOW(),
            created_at    TIMESTAMP    DEFAULT NOW(),
            updated_at    TIMESTAMP    DEFAULT NOW(),
            deleted_at    TIMESTAMP,
            created_by    UUID,
            updated_by    UUID,
            deleted_by    UUID,
            UNIQUE (event_id, email)
        )");
        $db->execute();

        // Permissions
        $permSql = "INSERT INTO permissions (name, slug, description, module)
                    VALUES (:name, :slug, :desc, 'events')
                    ON CONFLICT (slug) DO NOTHING";
        foreach ([
            ['Events - View',   'events.view',   'View events'],
            ['Events - Manage', 'events.manage', 'Create and edit events'],
        ] as [$n, $s, $d]) {
            $db->query($permSql);
            $db->arrayBind([':name' => $n, ':slug' => $s, ':desc' => $d]);
            $db->execute();
        }

        $db->query(
            "INSERT INTO role_permissions (role_id, permission_id)
             SELECT r.id, p.id
             FROM   roles r, permissions p
             WHERE  r.slug = 'administrator' AND p.module = 'events'
             ON CONFLICT DO NOTHING"
        );
        $db->execute();
    }

    public function uninstall(\Core\Database\Connection $db): void
    {
        $db->query("DROP TABLE IF EXISTS event_rsvps CASCADE"); $db->execute();
        $db->query("DROP TABLE IF EXISTS events CASCADE"); $db->execute();

        $db->query("DELETE FROM role_permissions WHERE permission_id IN (SELECT id FROM permissions WHERE module = 'events')");
        $db->execute();
        $db->query("DELETE FROM permissions WHERE module = 'events'");
        $db->execute();
    }

    public function registerRoutes(\Core\Router $router): void
    {
        $admin = 'App\\Modules\\Events\\Controllers\\Admin\\EventsController';
        $front = 'App\\Modules\\Events\\Controllers\\Front\\EventsFrontController';

        $id   = '([a-zA-Z0-9\-]+)';
        $slug = '([a-z0-9\-]+)';

        // Admin
        $router->get( '/admin/events',                  $admin, 'index');
        $router->get( '/admin/events/create',           $admin, 'createForm');
        $router->post('/admin/events/store',            $admin, 'store');
        $router->get( "/admin/events/{$id}/edit",       $admin, 'editForm');
        $router->post("/admin/events/{$id}/update",     $admin, 'update');
        $router->post("/admin/events/{$id}/delete",     $admin, 'delete');
        $router->get( "/admin/events/{$id}/attendees/export",       $admin, 'exportAttendees');
        $router->post("/admin/events/{$id}/attendees/{$id}/status", $admin, 'setAttendeeStatus');
        $router->get( "/admin/events/{$id}/attendees",              $admin, 'attendees');

        // Public (static /events/rsvp/cancel registered before slug wildcards)
        $router->get(  '/events',                       $front, 'index');
        $router->get(  '/events/rsvp/cancel',           $front, 'cancelRsvp');
        $router->post( "/events/{$slug}/rsvp",          $front, 'rsvp');
        $router->get(  "/events/{$slug}/ical",          $front, 'ical');
        $router->get(  "/events/{$slug}",               $front, 'detail');
    }
}
