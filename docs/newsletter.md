# Newsletter Module

The **Newsletter** module provides a subscriber list with double opt-in confirmation, HTML campaign creation, test-send, and blast delivery. It integrates with the Webhooks module for subscribe and unsubscribe events.

---

## Installation

Install from Admin - Module Manager. You are prompted for two required settings:

| Setting Key | Label | Example |
|-------------|-------|---------|
| `newsletter_from_name` | From Name | "My Site Newsletter" |
| `newsletter_from_email` | From Email | "newsletter@example.com" |

These are used as the `From:` header on all outgoing newsletter emails.

Permissions `newsletter.view`, `newsletter.manage`, `newsletter.send` are granted to Administrator on install.

---

## Subscriber Management

**Admin - Newsletter - Subscribers** (`/admin/newsletter/subscribers`):

- Lists all subscribers with name, email, confirmed status, and sign-up date.
- **Filter** by confirmed / unconfirmed.
- **Delete** removes the subscriber record.

### CSV Import

Click **Import CSV** and upload a file with columns `email` and optionally `name`. Rows with invalid emails or duplicates are skipped; a summary shows how many were imported.

### CSV Export

Click **Export CSV** to download all confirmed subscribers as a CSV file.

---

## Double Opt-in Flow

1. A visitor submits the subscribe form embedded in any page or the newsletter front-end view.
2. A record is inserted with `confirmed = false` and a unique `token`.
3. A confirmation email is sent to the submitted address containing a link:
   ```
   GET /newsletter/confirm/{token}
   ```
4. Clicking the link sets `confirmed = true` and shows a success page.
5. The `newsletter.subscribed` webhook fires on confirmation.

Unconfirmed subscribers are excluded from campaign sends.

---

## Unsubscribing

Every sent campaign includes an unsubscribe link at the bottom:

```
GET /newsletter/unsubscribe/{token}
```

Visiting this URL sets `unsubscribed_at` on the subscriber record, marks them as inactive, and fires the `newsletter.unsubscribed` webhook. Unsubscribed addresses are excluded from future sends.

---

## Campaigns

**Admin - Newsletter - Campaigns** (`/admin/newsletter/campaigns`):

1. Click **New Campaign**.
2. Enter a **Subject** and compose the **HTML Body** using the Quill rich-text editor.
3. Save as draft.

### Test Send

Before blasting, click **Send Test** to send the campaign to your admin account email. Confirms rendering and delivery before committing to the full list.

### Sending a Campaign

Click **Send Campaign** on a draft campaign. A confirmation prompt shows the estimated recipient count (all confirmed, non-unsubscribed subscribers). On confirm:

1. Each confirmed subscriber receives the email with their personal unsubscribe link substituted in.
2. `sent_at` and `recipient_count` are recorded on the campaign row.
3. Sent campaigns cannot be re-sent.

---

## Webhooks

| Event | Fired when |
|-------|-----------|
| `newsletter.subscribed` | A subscriber confirms their email |
| `newsletter.unsubscribed` | A subscriber clicks the unsubscribe link |

---

## Permissions

| Permission | Description |
|------------|-------------|
| `newsletter.view` | View subscribers and campaigns |
| `newsletter.manage` | Create, edit, delete campaigns and manage subscribers |
| `newsletter.send` | Send test and blast campaigns |


---

## What's new in v0.0.2 (Vertext 0.0.8)

- **Audience segments** - Newsletter > Segments defines saved filters (source, subscribed date
  range) with live match counts. Campaigns pick a segment (or all active subscribers) in the
  editor's Audience select.
- **Scheduled sends** - schedule a draft for a future date/time from the campaign sidebar. Due
  campaigns send automatically the next time an admin opens the Campaigns page (cron-free, same
  pattern as scheduled posts); the schedule can be cancelled while pending.
- **Open tracking** - a per-subscriber pixel records unique opens (`newsletter_opens`); totals
  show in the campaigns list and editor sidebar.
- **Click tracking** - links in the HTML body are rewritten through
  `/newsletter/track/click/{campaign}`; per-URL click counts live in `campaign_links`. The
  redirect only honors URLs recorded at send time, so it cannot be abused as an open redirect.
- **Welcome email** - optional subject/body (Newsletter > Settings) sent once when a subscriber
  becomes active - immediately, or after confirming when double opt-in is enabled.
- **CSV file import** - the Import modal accepts a `.csv` file upload as well as pasted rows.
- **Signup shortcode** - place `[newsletter_signup]` in any Page or post body to embed a
  subscribe box.
