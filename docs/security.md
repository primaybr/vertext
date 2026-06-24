# Security

Vertext implements multiple layers of security. This document describes each mechanism and how to use it correctly in your modules and controllers.

## CSRF Protection

All state-changing requests (POST, PUT, DELETE) must include a valid CSRF token. Tokens are cryptographically secure (32 random bytes, hex-encoded), stored in the session, and validated with timing-safe comparison.

### In Forms

Use the `csrf_field()` helper or directly use the CSRF class:

```php
<!-- In any HTML form -->
<form method="POST" action="/admin/my-module/store">
    <?= csrf_field() ?>
    <!-- ... -->
</form>

<!-- Or manually -->
<?= \Core\Security\CSRF::getTokenInput() ?>
```

### In Controllers

Call `$this->validateCsrf()` at the top of every POST handler:

```php
public function store(): void
{
    $this->validateCsrf(); // aborts with 419 if token is invalid
    // ... safe to process
}
```

`BaseController::validateCsrf()` handles this automatically. Tokens expire after 1 hour.

## Role-Based Access Control (RBAC)

### Checking Permissions in Controllers

```php
// Abort with 403 if the authenticated user lacks the permission
$this->requirePermission('posts.create');

// Check without aborting
if (Auth::can('posts.publish')) {
    // show publish button
}

// Check role
if (Auth::hasRole('editor')) { ... }
```

### Checking in Views

```php
<?php if (Auth::can('posts.delete')): ?>
    <button class="btn btn-danger">Delete</button>
<?php endif; ?>
```

### How Permissions Are Loaded

On login, the user's effective permissions (union of all assigned roles) are loaded from the database into the session. `Auth::can()` reads from the session - no DB query per check.

## Authentication

### Login Security

- Passwords are hashed with bcrypt (cost 12) via `password_hash()` / `password_verify()`.
- `LoginRateLimiter` blocks accounts after repeated failures.
- On successful login, `session_regenerate_id(true)` prevents session fixation.

### Session Security

Sessions are configured with:
- `HttpOnly` cookie - not accessible via JavaScript
- `Secure` cookie - only sent over HTTPS (when `https => true` in config)
- `SameSite=Strict` - prevents CSRF via cross-site requests
- Session hijacking detection: stores user-agent and IP; mismatches are logged

### Auth Helper Methods

```php
use App\CMS\Auth;

Auth::check()        // bool - is any user logged in?
Auth::user()         // stdObject|null - current user record
Auth::id()           // int|null - current user ID
Auth::can('slug')    // bool - has permission?
Auth::hasRole('slug')// bool - has role?
Auth::logout()       // destroy session, redirect to login
```

## Input Sanitization

All user input is sanitized by default via `htmlspecialchars()` with `ENT_QUOTES | ENT_HTML5`:

```php
// Sanitized (default - safe for HTML output)
$title = $this->input->post('title');

// Raw - only when you need unescaped content (e.g. Quill rich text)
$body = $this->input->post('body', false);
```

Never output raw user input directly into HTML without using the template system's `{{var}}` escaping.

## SQL Injection Prevention

All database queries use PDO prepared statements with bound parameters. The ORM query builder parameterizes all values.

**Always use the ORM or parameterized queries:**

```php
// Safe - ORM parameterizes automatically
$this->db->table('posts')->where('slug', $slug)->first();

// Safe - manual prepared statement
$this->db->query("SELECT * FROM posts WHERE id = :id", [':id' => $id]);

// NEVER do this
$this->db->query("SELECT * FROM posts WHERE slug = '{$slug}'"); // DANGER
```

## File Upload Security

When handling file uploads (via the Media module or custom code):

- File MIME type is validated against an allowlist
- File extension is validated separately from MIME type
- Uploaded files are stored with randomized names (`timestamp_randombytes.ext`)
- Upload directory contains an `.htaccess` that blocks PHP execution
- Files are organized by `year/month/` to limit directory size

## Audit Logging

Every state-changing operation should call `$this->audit()`:

```php
$this->audit(
    'post.created',        // action string
    'post',                // resource type
    $newPostId,            // resource ID (or null)
    ['title' => $title]    // extra context (stored as JSONB)
);
```

Audit logs are stored in the `audit_logs` table and visible in the Dashboard.

## Security Headers

All admin responses are sent with:

```
Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'
X-Frame-Options: DENY
X-Content-Type-Options: nosniff
```

These are set in `BaseController::adminRender()`. Add them to public controller responses if needed.

## IP Address Detection

By default, `Client::getIpAddress()` always returns `REMOTE_ADDR` (the direct TCP connection). Proxy forwarding headers (`X-Forwarded-For`, `CF-Connecting-IP`) are only trusted if you explicitly configure trusted proxies:

```php
use Core\Http\Client;
Client::setTrustedProxies(['10.0.0.10']); // your load balancer IP
```

Never configure this unless you know your infrastructure setup.

## Configuration Secrets

`Storage/db.php` and `Storage/app.php` contain credentials and are gitignored. Never commit them. In production:
- Use strong PostgreSQL passwords
- Set `env => 'production'` in `Config/Config.php`
- Ensure `Logs/` and `Cache/` are not web-accessible
- Remove or disable the `/setup` route after installation (it's automatically blocked once `installed.lock` exists)
