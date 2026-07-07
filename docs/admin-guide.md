# Admin Guide

The Vertext admin panel lives at `/admin`. All sections require authentication and appropriate role permissions.

## Dashboard

`/admin` - Shows a high-level overview of the system:

- Total users, roles, and modules
- Site settings summary
- Recent audit log entries (who did what, when, from which IP)

## Profile Page

`/admin/profile` - Available to all authenticated users; no extra permission required.

Any logged-in user can update their own:

- **Display name** - shown in the admin sidebar and audit logs
- **Email address** - must be unique; used for login and mail notifications
- **Password** - leave blank to keep the current password; new passwords are bcrypt hashed (cost 12)

Changes are logged to the audit trail under `profile.updated`.

## Authentication

### Login

`/admin/login` - Standard username/password login form.

- **Rate limiting**: After repeated failures, the account is temporarily locked (brute-force protection via `LoginRateLimiter`).
- **Session security**: Session ID is regenerated on successful login. Cookies are `HttpOnly`, `Secure`, and `SameSite=Strict`.

### Logout

`/admin/logout` - Destroys the session and clears all session data.

## User Management

`/admin/users` - Requires `users.view` permission.

### User Fields

| Field | Notes |
| --- | --- |
| Name | Display name |
| Email | Must be unique; used for login |
| Password | bcrypt with cost 12; never stored plain-text |
| Status | `active` (can log in) or `inactive` (blocked) |
| Roles | One or more roles assigned to the user |

### User Operations

| Action | Permission required |
| --- | --- |
| View list | `users.view` |
| Create | `users.create` |
| Edit | `users.update` |
| Delete (soft) | `users.delete` |

Deletion is a soft-delete - the user record is kept with `deleted_at` set. Deleted users cannot log in.

## Role Management

`/admin/roles` - Requires `roles.view` permission.

Roles group permissions together. Users can have multiple roles; their effective permissions are the union of all assigned roles.

### Default Roles

| Role | Description |
| --- | --- |
| Administrator | Full system access (system role - cannot be deleted) |
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

**Pages module**:

- `pages.view`, `pages.create`, `pages.edit`, `pages.delete`, `pages.publish`

**Gallery module**:

- `gallery.view`, `gallery.create`, `gallery.edit`, `gallery.delete`, `gallery.publish`

**Contact module**:

- `contact.view`, `contact.delete`, `contact.settings`

**Videos module**:

- `videos.view`, `videos.create`, `videos.edit`, `videos.delete`, `videos.publish`

**Navigation module**:

- `navigation.view`, `navigation.manage`

**Analytics module**:

- `analytics.view`, `analytics.manage`

**Sitemap module**:

- No permissions required (public route at `/sitemap.xml`)

**Webhooks module**:

- `webhooks.view`, `webhooks.manage`

## Settings

`/admin/settings` - Requires `settings.view`.

### General tab

Edit core site settings: site name, URL, description, admin email, timezone, date/time format, maintenance mode. Only whitelisted keys are accepted.

### Mail tab

Configure the mail transport (PHP `mail()` or SMTP). SMTP fields: host, port, encryption, username, password, from address, from name. **Send Test Email** button verifies the config by sending a test to your account address.

### Cache

**Clear Cache** button deletes all files from the `Cache/` directory. Use this after any template, CSS, or JS change when caching is enabled.

## Themes

`/admin/themes` - Requires `settings.view`.

Displays all themes found in `App/Themes/`. Each theme is shown as a card with its name, description, version, and author. The active theme is highlighted with an "Active" badge.

Click **Activate** on any inactive theme to switch immediately. `ThemeEngine::deploy()` runs automatically to sync theme assets to `Public/themes/`. The change takes effect on the next front-end page load.

Theme Manager is a core system module and cannot be disabled or uninstalled.

## Module Manager

`/admin/modules` - Requires `modules.view`.

### Layout

**System section** (collapsible) - lists core built-in modules (Auth, Dashboard, Users, Roles, Module Manager, Theme Manager, CMS Settings) as compact read-only rows. Core modules are always enabled and cannot be uninstalled.

**Category sections** - add-on modules grouped by their `category` field from `module.json`. Each module appears as a card showing:

- Module icon and name
- Version badge
- Description
- Status badge (Enabled / Disabled / Not Installed)
- Action buttons

### Operations

| Operation | What it does | Permission |
| --- | --- | --- |
| Install | Creates DB tables, seeds permissions, deploys views | `modules.install` |
| Enable/Disable | Toggles module routes and nav visibility | `modules.toggle` |
| Uninstall | Drops DB tables, removes permissions, deletes views | `modules.uninstall` |
| Sync Views | Re-deploys view files from module source to `App/Views/modules/` | `modules.install` |

### Module Dependencies

If a module's `module.json` lists other modules in `requires.modules`, the Install button is disabled with a tooltip listing the missing modules until they are installed first. Uninstalling a module that others depend on is also blocked with an error listing the dependents.

### When to Sync Views

After updating a module's view files during development, click **Sync Views** to redeploy them. Never edit files in `App/Views/modules/` directly - they are owned by the install lifecycle and will be overwritten on the next sync.

## Admin Navigation

The sidebar is built from:

1. **Core links** (always visible to all admins): Dashboard, Users, Roles, Module Manager, Themes, Settings
2. **Module-contributed links** from each enabled module's `module.json` → `nav` key, grouped under a "Modules" section

Nav items are only shown if the user has the required permission defined in `nav.permission`.

## Audit Logs

Every state-changing admin action is logged automatically. Log entries include:

- User who performed the action
- Action type (e.g. `user.created`, `post.deleted`, `settings.set_theme`)
- Resource type and ID
- IP address and User-Agent
- Timestamp

Logs are stored in the `audit_logs` table. The Dashboard shows the most recent entries.

## Security Headers

Every response - admin, public front-end, and the REST API - includes the following HTTP headers:

- `Content-Security-Policy` - restricts asset sources (admin allows inline scripts/styles it relies
  on; the public site and API use a stricter policy with no exceptions)
- `X-Frame-Options: DENY` - prevents clickjacking
- `X-Content-Type-Options: nosniff` - prevents MIME sniffing
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Strict-Transport-Security` - added when `'https' => true` is set in config (see
  [Going to Production](going-to-production.md))
