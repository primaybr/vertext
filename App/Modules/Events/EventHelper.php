<?php

declare(strict_types=1);

namespace App\Modules\Events;

use Core\Model;

/**
 * Shared Events v0.0.2 logic: schema upgrades, recurrence expansion,
 * iCal generation, and RSVP capacity/waitlist handling.
 */
class EventHelper
{
    /** Idempotent schema upgrades for existing 0.0.1 installs. */
    public static function ensureSchema(): void
    {
        static $checked = false;
        if ($checked) return;
        $checked = true;

        try {
            $db = (new Model('events'))->db;
            foreach ([
                "ALTER TABLE events ADD COLUMN IF NOT EXISTS max_attendees INT",
                "ALTER TABLE events ADD COLUMN IF NOT EXISTS recurrence_rule TEXT",
                "ALTER TABLE events ADD COLUMN IF NOT EXISTS tickets TEXT",
                "CREATE TABLE IF NOT EXISTS event_rsvps (
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
                )",
            ] as $ddl) {
                $db->query($ddl);
                $db->execute();
            }
        } catch (\Throwable $e) {
            // Non-fatal; features degrade to v1 behavior
        }
    }

    /** Confirmed attendee count for an event. */
    public static function confirmedCount(string $eventId): int
    {
        try {
            return (int) ((new Model('event_rsvps'))
                ->select('COUNT(*) AS n')
                ->where('event_id', $eventId)
                ->where('status', 'confirmed')
                ->whereNull('deleted_at')
                ->get(1)['n'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** Spots left (null = unlimited). */
    public static function spotsLeft(array $event): ?int
    {
        $max = (int) ($event['max_attendees'] ?? 0);
        if ($max <= 0) return null;
        return max(0, $max - self::confirmedCount((string) $event['id']));
    }

    /** Decoded ticket types: [{name, price, capacity}] */
    public static function tickets(array $event): array
    {
        $tickets = json_decode((string) ($event['tickets'] ?? '[]'), true);
        return is_array($tickets) ? array_values(array_filter($tickets, 'is_array')) : [];
    }

    /**
     * Promote the earliest waitlisted RSVP when a confirmed spot frees up.
     * Returns the promoted row or null.
     */
    public static function promoteWaitlist(array $event): ?array
    {
        $spots = self::spotsLeft($event);
        if ($spots !== null && $spots <= 0) {
            return null;
        }

        try {
            $next = (new Model('event_rsvps'))
                ->where('event_id', (string) $event['id'])
                ->where('status', 'waitlist')
                ->whereNull('deleted_at')
                ->orderBy('registered_at', 'ASC')
                ->get(1);

            if (!$next) return null;

            (new Model('event_rsvps'))->where('id', (string) $next['id'])->update([
                'status'     => 'confirmed',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $next['status'] = 'confirmed';
            return $next;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Keep the legacy events.rsvp_count column in sync with confirmed rows. */
    public static function syncRsvpCount(string $eventId): void
    {
        try {
            (new Model('events'))->where('id', $eventId)->update([
                'rsvp_count' => self::confirmedCount($eventId),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
        }
    }

    /**
     * Expand recurring events into upcoming occurrences.
     *
     * @param array $events      Base event rows (must include recurrence_rule, start_at, end_at)
     * @param int   $horizonDays How far ahead to expand
     * @param int   $maxPerEvent Cap occurrences per recurring event
     * @return array Occurrence rows sorted by start_at; recurring instances get
     *               is_occurrence=true and keep the base event's slug/id.
     */
    public static function expandRecurrences(array $events, int $horizonDays = 180, int $maxPerEvent = 26): array
    {
        $now     = time();
        $horizon = $now + $horizonDays * 86400;
        $out     = [];

        foreach ($events as $event) {
            $rule = json_decode((string) ($event['recurrence_rule'] ?? ''), true);
            $freq = is_array($rule) ? ($rule['freq'] ?? '') : '';

            if (!in_array($freq, ['daily', 'weekly', 'monthly'], true)) {
                // Non-recurring: include as-is when still upcoming
                if (strtotime((string) $event['start_at']) >= $now) {
                    $out[] = $event;
                }
                continue;
            }

            $interval = max(1, (int) ($rule['interval'] ?? 1));
            $until    = !empty($rule['until']) ? strtotime($rule['until'] . ' 23:59:59') : null;
            $start    = strtotime((string) $event['start_at']);
            $duration = !empty($event['end_at']) ? (strtotime((string) $event['end_at']) - $start) : 0;

            $cursor = $start;
            $count  = 0;
            while ($cursor <= $horizon && $count < $maxPerEvent) {
                if ($until !== null && $cursor > $until) break;

                if ($cursor >= $now) {
                    $occurrence = $event;
                    $occurrence['start_at']      = date('Y-m-d H:i:s', $cursor);
                    $occurrence['end_at']        = $duration > 0 ? date('Y-m-d H:i:s', $cursor + $duration) : null;
                    $occurrence['is_occurrence'] = ($cursor !== $start);
                    $out[] = $occurrence;
                    $count++;
                }

                $cursor = match ($freq) {
                    'daily'   => strtotime("+{$interval} day", $cursor),
                    'weekly'  => strtotime("+{$interval} week", $cursor),
                    'monthly' => strtotime("+{$interval} month", $cursor),
                };
            }
        }

        usort($out, static fn(array $a, array $b) => strcmp((string) $a['start_at'], (string) $b['start_at']));
        return $out;
    }

    /** Build an RFC 5545 VCALENDAR string for one event. */
    public static function buildIcs(array $event, string $siteUrl): string
    {
        $fmt = static fn(string $ts): string => gmdate('Ymd\THis\Z', strtotime($ts));

        $esc = static function (string $text): string {
            return str_replace(["\\", ";", ",", "\n", "\r"], ["\\\\", "\\;", "\\,", "\\n", ''], $text);
        };

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Vertext CMS//Events//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:' . $event['id'] . '@' . parse_url($siteUrl, PHP_URL_HOST),
            'DTSTAMP:' . gmdate('Ymd\THis\Z'),
            'DTSTART:' . $fmt((string) $event['start_at']),
        ];
        if (!empty($event['end_at'])) {
            $lines[] = 'DTEND:' . $fmt((string) $event['end_at']);
        }
        $lines[] = 'SUMMARY:' . $esc((string) $event['title']);
        if (!empty($event['description'])) {
            $lines[] = 'DESCRIPTION:' . $esc((string) $event['description']);
        }
        if (!empty($event['location'])) {
            $lines[] = 'LOCATION:' . $esc((string) $event['location']);
        }
        $lines[] = 'URL:' . $siteUrl . '/events/' . $event['slug'];

        // Recurring events export their RRULE so calendar apps repeat them
        $rule = json_decode((string) ($event['recurrence_rule'] ?? ''), true);
        if (is_array($rule) && in_array($rule['freq'] ?? '', ['daily', 'weekly', 'monthly'], true)) {
            $rrule = 'RRULE:FREQ=' . strtoupper($rule['freq']);
            if ((int) ($rule['interval'] ?? 1) > 1) {
                $rrule .= ';INTERVAL=' . (int) $rule['interval'];
            }
            if (!empty($rule['until'])) {
                $rrule .= ';UNTIL=' . gmdate('Ymd\THis\Z', strtotime($rule['until'] . ' 23:59:59'));
            }
            $lines[] = $rrule;
        }

        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        // 75-octet line folding per RFC 5545
        $folded = [];
        foreach ($lines as $line) {
            while (strlen($line) > 73) {
                $folded[] = substr($line, 0, 73);
                $line = ' ' . substr($line, 73);
            }
            $folded[] = $line;
        }

        return implode("\r\n", $folded) . "\r\n";
    }
}
