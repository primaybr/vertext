# Going to Production

A checklist for taking a Vertext install from "just finished the setup wizard" to "safe for real
visitors." Read this before announcing your site publicly.

## 1. Confirm you're out of debug mode

The setup wizard writes `'env' => 'production'` to `Storage/app.php` by default (unless you
explicitly checked "Enable debug mode for this install" on the Site Information step). Open
`Storage/app.php` and confirm it contains `'env' => 'production'`.

What this controls: in `development`, unhandled errors show a full stack trace and internal file
paths; in `production`, visitors see a generic error page instead, and details go to `Logs/`
only. Never leave a public site in `development` - it leaks internal paths and code details to
anyone who can trigger an error.

If you installed before this check existed (upgrading from an earlier 0.0.x release), your
`Storage/app.php` may have no `env` key at all, which defaults to `development`. Add the key by
hand - see [Upgrading](upgrading.md).

## 2. File permissions

`Storage/` and `Cache/` must be writable by the web server's process user, but neither should be
directly web-accessible (both already live outside `Public/`, which is the only web-servable
directory - don't move them).

Recommended: the web server user owns both directories with `750` permissions (owner read/write/
execute, group read/execute, no access for anyone else) rather than `777`. On a typical Linux host:

```bash
chown -R www-data:www-data Storage Cache
chmod -R 750 Storage Cache
```

Adjust `www-data` to whatever user your PHP-FPM/Apache/Nginx process actually runs as.

## 3. HTTPS

Once TLS is terminated (either directly on this server or by a reverse proxy/load balancer in
front of it), set `'https' => true` in `Storage/app.php`. This does two things:

- Activates the `Secure` flag on session cookies (cookies are only sent over HTTPS).
- Activates the `Strict-Transport-Security` (HSTS) response header, telling browsers to always
  use HTTPS for this domain.

If you're behind a reverse proxy or CDN (e.g. Cloudflare, an nginx/HAProxy load balancer), also
configure trusted proxy IPs - see [Configuration: Trusted Proxy Configuration](configuration.md).
Without it, client IP logging (audit logs, rate limiting) will see the proxy's IP instead of the
real visitor's.

## 4. Cron

Nothing in Vertext currently requires a cron job. Newsletter scheduled campaigns, content
scheduled/expired publishing, and login-rate-limit pruning are all processed lazily - triggered by
the next relevant page load or admin visit, not a background scheduler. On a very low-traffic
site this can mean a small delay between a scheduled time and when it's actually processed (the
delay is bounded by how often *anyone* loads the relevant page), which is an acceptable trade-off
for this release rather than requiring self-hosters to configure a cron job.

## 5. Backups

Set up a recurring `vertext backup` (see [Backup & Restore](backup-restore.md)) before you have
real user data to lose. Vertext ships the tooling; scheduling it (e.g. via your own cron job) and
deciding how many backups to retain is on you - there's no built-in rotation policy in this
release.

## 6. Read the known limitations

[Known Limitations](known-limitations.md) lists what this beta explicitly does not cover yet
(accessibility, load testing, self-update, multi-server deployments, and more). Read it before
committing to this release for anything business-critical.
