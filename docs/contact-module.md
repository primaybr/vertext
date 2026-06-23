# Contact Form Module

The Contact module (`slug: contact`, version 0.0.1) adds a public contact form and an admin submission inbox to Vertext. Submissions trigger email notifications via the Mail system.

## Features

- Public contact form with CSRF protection and IP-based rate limiting (1 per IP per 10 minutes)
- Admin inbox with status filtering (unread / read / spam)
- Unread count badge in the admin sidebar
- Mark as read, mark as spam, delete
- Admin email notification on new submission (via Mailer)
- Visitor auto-reply on submission (optional, customizable message)
- Configurable notification email, auto-reply toggle, and auto-reply body

## Installation

Go to **Admin → Modules** and click **Install** next to Contact. A "Mail" tab in Admin → Settings should be configured for email delivery.

## Database Table

**`contact_submissions`**:

| Column | Type | Description |
| ------ | ---- | ----------- |
| `id` | UUID | Primary key |
| `name` | VARCHAR(255) | Sender name |
| `email` | VARCHAR(255) | Sender email |
| `subject` | VARCHAR(255) | Subject line |
| `message` | TEXT | Message body |
| `status` | VARCHAR(20) | `unread`, `read`, `spam`, `replied` |
| `ip_address` | VARCHAR(45) | Submitter IP (for rate limiting) |
| `submitted_at` | TIMESTAMP | Submission time |
| `read_at` | TIMESTAMP | When status changed to `read` |
| `replied_at` | TIMESTAMP | When manually marked as replied |

## Admin Routes

| Method | Path | Description |
| ------ | ---- | ----------- |
| GET | `/admin/contact` | Inbox list (filterable by status) |
| GET | `/admin/contact/settings` | Contact settings form |
| POST | `/admin/contact/settings/save` | Save settings |
| GET | `/admin/contact/{id}` | View a submission (auto-marks as read) |
| POST | `/admin/contact/{id}/mark-read` | Mark as read |
| POST | `/admin/contact/{id}/mark-spam` | Mark as spam |
| POST | `/admin/contact/{id}/delete` | Delete submission |

## Front-End Routes

| Method | Path | Description |
| ------ | ---- | ----------- |
| GET | `/contact` | Public contact form |
| POST | `/contact` | Submit the form |

## Settings

Managed via **Admin → Contact → Settings**:

| Setting key | Default | Description |
| ----------- | ------- | ----------- |
| `contact_path` | `contact` | URL path for the contact form (informational; route is currently fixed to `/contact`) |
| `contact_admin_email` | — | Email address to notify on new submissions |
| `contact_auto_reply` | `0` | `1` to send an auto-reply to the visitor |
| `contact_auto_reply_msg` | — | Custom text for the auto-reply body |

## Permissions

| Slug | Description |
| ---- | ----------- |
| `contact.view` | View the inbox and individual submissions |
| `contact.delete` | Delete submissions |
| `contact.settings` | Configure contact form settings |

## Email Flow

1. Visitor submits the form → submission saved to DB
2. If `contact_admin_email` is set → admin notification sent via `contact_notification` template
3. If `contact_auto_reply` = `1` → auto-reply sent to visitor via `contact_autoreply` template

See [Mail System](mail-system.md) for template details.

## Rate Limiting

One submission per IP address per 10 minutes. Blocked submissions show a friendly error; no DB row is created. The check uses `submitted_at` and does not require the LoginRateLimiter table.
