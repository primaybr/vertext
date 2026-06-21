# Admin Guide

The Vertext admin panel lives at `/admin`. All sections require authentication and appropriate role permissions.

## Dashboard

`/admin` — Shows a high-level overview of the system:

- Total users, roles, and modules
- Site settings summary
- Recent audit log entries (who did what, when, from which IP)

## Authentication

### Login

`/admin/login` — Standard username/password login form.

- **Rate limiting**: After repeated failures, the account is temporarily locked (brute-force protection via `LoginRateLimiter`).
- **Session security**: Session ID is regenerated on successful login. Cookies are `HttpOnly`, `Secure`, and `SameSite=Strict`.

### Logout

`/admin/logout` — Destroys the session and clears all session data.

## User Management

`/admin/users` — Requires `users.view` permission.

### User Fields

| Field | Notes |
|-------|-------|
| Name | Display name |
| Email | Must be unique; used for login |
| Password | bcrypt with cost 12; never stored plain-text |
| Status | `active` (can log in) or `inactive` (blocked) |
| Roles | One or more roles assigned to the user |

### User Operations

| Action | Permission required |
|--------|---------------------|
| View list | `users.view` |
| Create | `users.create` |
| Edit | `users.update` |
| Delete (soft) | `users.delete` |

Deletion is a soft-delete — the user record is kept with `deleted_at` set. Deleted users cannot log in.

## Role Management

`/admin/roles` — Requires `roles.view` permission.

Roles group permissions together. Users can have multiple roles; their effective permissions are the union of all assigned roles.

### Default Roles

| Role | Description |
|------|-------------|
| Administrator | Full system access (system role — cannot be deleted) |
| Editor | Can manage content in installed modules |
| Author | Can create content but not publish or delete |

### Creating Custom Roles

1. Go to **Admin → Roles → Create Role**.
2. Enter a name and optional description.
3. Check the permissions you want to grant.
4. Save.

**System roles** (`is_system = true`) cannot be deleted through the UI.

### Permission Slugs

Permissions follow the pattern `resource.action`:

**Core**:
- `users.view`, `users.create`, `users.update`, `users.delete`
- `roles.view`, `roles.manage`
- `modules.view`, `modules.install`, `modules.uninstall`, `modules.toggle`
- `settings.view`, `settings.manage`
- `dashboard.view`

**Blog module**:
- `posts.view`, `posts.create`, `posts.edit`, `posts.publish`, `posts.delete`
- `categories.view`, `categories.create`, `categories.edit`, `categories.delete`
- `tags.view`, `tags.create`, `tags.edit`, `tags.delete`
- `comments.view`, `comments.moderate`, `comments.delete`
- `blog.settings`

**Media module**:
- `media.view`, `media.upload`, `media.edit`, `media.delete`

## Settings

`/admin/settings` — Requires `settings.view`.

Edit core site settings via a form. Click **Save Settings** to persist. Only whitelisted keys are accepted; arbitrary key injection is blocked.

**Clear Cache** button deletes all files from the `Cache/` directory. Use this after any template or CSS/JS change.

## Module Manager

`/admin/modules` — Requires `modules.view`.

Lists all discovered modules (from `App/Modules/`) and their current status.

### Operations

| Operation | What it does | Permission |
|-----------|--------------|------------|
| Install | Creates DB tables, seeds permissions, deploys views | `modules.install` |
| Enable/Disable | Toggles module routes and nav visibility | `modules.toggle` |
| Uninstall | Drops DB tables, removes permissions, deletes views | `modules.uninstall` |
| Sync Views | Re-deploys view files from module source to `App/Views/modules/` | `modules.install` |

### When to Sync Views

After updating a module's view files during development, click **Sync Views** to redeploy them. Never edit files in `App/Views/modules/` directly — they are owned by the install lifecycle and will be overwritten.

## Admin Navigation

The sidebar is built dynamically from:
1. Core links (Dashboard, Users, Roles, Settings, Modules)
2. Module-contributed links from each enabled module's `module.json` → `nav` key

Nav items are only shown if the user has the required permission defined in `nav.permission`.

## Audit Logs

Every state-changing admin action is logged automatically. Log entries include:
- User who performed the action
- Action type (e.g. `user.created`, `post.deleted`)
- Resource type and ID
- IP address and User-Agent
- Timestamp

Logs are stored in the `audit_logs` table. The Dashboard shows the most recent entries.

## Security Headers

All admin responses include the following HTTP headers:
- `Content-Security-Policy` — restricts asset sources
- `X-Frame-Options: DENY` — prevents clickjacking
- `X-Content-Type-Options: nosniff` — prevents MIME sniffing
