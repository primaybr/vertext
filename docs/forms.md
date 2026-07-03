# Forms Builder Module

The **Forms** module lets administrators build custom forms with a drag-to-reorder field builder, collect submissions in the database, export to CSV, and fire webhooks on submission.

---

## Installation

Install from Admin - Module Manager. Grants permissions `forms.view`, `forms.manage`, `forms.export` to the Administrator role automatically.

---

## Creating a Form

1. Go to **Admin - Forms** (`/admin/forms`).
2. Click **New Form**.
3. Fill in:
   - **Name** - used as the form heading on the public page.
   - **Slug** - auto-generated from the name; determines the public URL (`/forms/{slug}`).
   - **Description** - optional sub-heading.
   - **Notification Email** - receives an email on every accepted submission.
   - **Success Message** - shown to the visitor after successful submission.
4. Add fields in the **Fields** section:
   - Click **Add Field** to append a new field row.
   - Drag the handle to reorder fields.
   - Each field has: **Label**, **Type**, **Required** toggle, and **Placeholder / Options** (for select, radio, checkbox).
5. Set **Status** to `published` and save.

### Field Types

| Type | Description |
|------|-------------|
| `text` | Single-line input |
| `textarea` | Multi-line input |
| `email` | Email address input with browser validation |
| `select` | Dropdown; provide options as a newline-separated list |
| `checkbox` | Single yes/no checkbox |
| `radio` | Mutually-exclusive choice; provide options as a newline-separated list |

---

## Public Form

Published forms are accessible at:

```
GET /forms/{slug}
```

The form renders inside the active front-end theme. On submission:

1. The honeypot field is checked. Filled honeypots are silently discarded.
2. Rate limiting is applied: 3 attempts per 60 seconds per IP per form. Excess requests receive an error message.
3. Required fields are validated server-side.
4. The submission is stored in `form_submissions`.
5. A notification email is sent to the configured address (if set).
6. The `form.submitted` webhook event fires with the full submission payload.
7. The success message is shown to the visitor.

---

## Submissions

View submissions at **Admin - Forms - [Form Name] - Submissions** or via the Submissions tab in the form edit view.

- Each row shows the submission date, IP, and all field values.
- Click **Export CSV** (requires `forms.export` permission) to download all submissions for that form.

---

## Webhooks

The Forms module fires the `form.submitted` event via `WebhookDispatcher`. The payload is:

```json
{
  "form_id":   "uuid",
  "form_name": "Contact Request",
  "form_slug": "contact-request",
  "data":      { "name": "Jane", "email": "jane@example.com", "message": "Hello" },
  "submitted_at": "2026-06-30T10:00:00+00:00"
}
```

---

## Permissions

| Permission | Description |
|------------|-------------|
| `forms.view` | View form list and submissions |
| `forms.manage` | Create, edit, and delete forms |
| `forms.export` | Download CSV exports of submissions |


---

## What's new in v0.0.2 (Vertext 0.0.8)

- **Conditional logic** - each field can carry one show/hide rule against another field
  (operators: equals, not equals, contains, empty, not empty). Configured per field in the
  builder ("Conditional logic"); evaluated live in the browser and re-checked server-side,
  so hidden required fields never block submission.
- **Multi-step forms** - add "Step Break" blocks in the builder to split the form into pages.
  Visitors get a numbered progress bar and validated Next/Back navigation; the server still
  validates the whole submission at the end.
- **File uploads** - the file field now stores uploads under `Public/uploads/forms/{form_id}/`
  (jpg, png, gif, webp, pdf, txt, doc, docx; 10 MB max; extension + MIME sniff). The submission
  detail view links to the stored file.
- **Email notification** - set a notification address in the builder's Form Settings to receive
  a formatted email for every submission (uses the shared Mailer).
- **Anti-spam** - optional math challenge ("what is 3 + 4?") and optional reCAPTCHA v3
  (enter both keys in Form Settings; server-side verification, scores below 0.5 rejected).
- **Shortcode embed** - place `[form slug="contact"]` inside any Page or Blog post body to
  embed the form inline. The embed and the standalone `/forms/{slug}` page share one partial.
- **Fix** - Form Settings (success message etc.) now actually persist when saving fields.
