# Events Module

The **Events** module provides event listings with RSVP collection, a Canvas calendar sidebar, and webhook dispatch on RSVP.

---

## Installation

Install from Admin - Module Manager. Permissions `events.view`, `events.manage`, `events.rsvp` are granted to Administrator on install.

---

## Admin - Creating Events

Go to **Admin - Events** (`/admin/events`), then **New Event**:

| Field | Notes |
|-------|-------|
| Title | Required; slug auto-generated |
| Slug | URL-safe identifier, used in the public URL |
| Description | Rich text body shown on the detail page |
| Location | Text field (address, room, or URL for virtual events) |
| Start Date/Time | Required |
| End Date/Time | Optional |
| Max Attendees | Leave blank for unlimited; RSVP closes when the cap is reached |
| Featured Image | URL of the event banner image |
| Status | `draft` or `published` |

Published events are visible on the front-end listing.

---

## Admin - RSVP Management

Each event has an **RSVPs** sub-page listing all submitted registrations:

- Name, email, notes, RSVP date.
- **Attended** toggle to mark attendance after the event.
- **Export CSV** downloads all RSVPs for the event.

---

## Public Listing

Events are listed at the URL registered by the module (default: `/events`).

The listing page has:

- **Upcoming** and **Past** tabs.
- **Event cards** with date badge, title, location, and RSVP count.
- **Canvas calendar sidebar** - a 230x190 pixel month-grid calendar:
  - Accent dot appears on days that have at least one upcoming event.
  - The current day is highlighted.
  - Clicking a date scrolls the card list to the matching `#ev-YYYY-MM-DD` anchor.
  - Redraws automatically on `vtx:themeChanged` events (dark/light mode toggle).

---

## Public Event Detail

Accessing `/events/{slug}` shows:

- Two-column layout: main content on the left, info card + RSVP card on the right.
- Layout collapses to single column at 720 px.

### RSVP Form

The RSVP card shows a sign-up form with Name, Email, and optional Notes fields.

**Submission rules:**
- A cookie `rsvp_{event_id}` is set on success to prevent duplicate RSVPs from the same browser.
- RSVP is disabled automatically when `max_attendees` is reached.
- RSVP is disabled when the event's `start_at` is in the past.
- Flash messages from the `event_rsvp_flash` session key are shown above the form.

On accepted submission:
1. Row inserted into `event_rsvps`.
2. The `event.rsvp` webhook fires with the RSVP payload.
3. Browser cookie set; success message shown.

---

## Webhooks

| Event | Fired when | Payload includes |
|-------|-----------|-----------------|
| `event.rsvp` | An RSVP is accepted | `event_id`, `event_title`, `name`, `email`, `rsvped_at` |

---

## CSS / Dark Mode

All event styles use `--clr-*` CSS custom properties (never hardcoded hex), making them compatible with the admin dark mode and any custom theme overrides.

Semantic alert colors (success, warning, error) include explicit `@media (prefers-color-scheme: dark)` overrides.

---

## Permissions

| Permission | Description |
|------------|-------------|
| `events.view` | View event list and RSVPs in admin |
| `events.manage` | Create, edit, delete events |
| `events.rsvp` | View and export RSVPs |


---

## What's new in v0.0.2 (Vertext 0.0.8)

- **Per-attendee RSVPs** - visitors register with name + email (pre-filled for logged-in
  members). Each RSVP is a row in `event_rsvps` with a status and a cancellation token;
  `rsvp_count` now always equals confirmed registrations.
- **Capacity & waiting list** - set Max Attendees on an event; when full, new registrations
  join the waiting list. Cancelling a confirmed spot (by the attendee or an admin)
  auto-promotes the earliest waitlisted person, who is notified by email.
- **Ticket types** - optional named tickets with display prices. The RSVP form shows a ticket
  select and the choice is stored per attendee. Display only - no payment processing.
- **iCal** - `GET /events/{slug}/ical` downloads an RFC 5545 `.ics` (recurring events include
  their RRULE). Confirmation emails carry an "Add to calendar" link.
- **Recurring events** - daily/weekly/monthly with an interval and optional until-date; the
  public listing and calendar expand future occurrences automatically.
- **Attendee admin** - `/admin/events/{id}/attendees`: status dropdown per attendee
  (confirmed / waitlist / cancelled, with a capacity guard), counts, and CSV export.
- **Webhook** - `event.rsvp` now includes the attendee payload (name, email, status, ticket).
