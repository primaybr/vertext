# Upgrading

Vertext has no self-update mechanism (see [Known Limitations](known-limitations.md)) - upgrades
are git-pull-based, and always require running pending migrations.

## Before you upgrade

- **Back up first.** Run `php vertext backup` (see [Backup & Restore](backup-restore.md)) before
  touching anything. There is no automated rollback if an upgrade goes wrong.
- Check `CHANGELOG.md` for the versions between your current install and the one you're upgrading
  to, for anything version-specific called out below.

## Standard upgrade steps

1. Fetch the new version:
   ```
   git fetch
   git checkout <tag-or-branch>
   ```
   (or `git pull` if you're tracking a branch directly).
2. Install/update dependencies:
   ```
   composer install --no-dev --optimize-autoloader
   ```
3. Run any pending database migrations:
   ```
   php vertext migrate up
   ```
   This is safe to run even if there's nothing pending - `migrate up` only applies migrations not
   already recorded as applied.
4. Clear the cache directory (compiled templates/queries are versioned, but a stale entry lingering
   past an upgrade is not worth debugging):
   ```
   rm -rf Cache/*
   ```
5. If your server uses PHP opcache, restart PHP-FPM (or your web server's PHP process) so it picks
   up the new code.

## Version-specific notes

### Upgrading to 0.1.0-beta

- If you installed an earlier 0.0.x release, your `Storage/app.php` predates the `env` config key
  entirely. After upgrading, open `Storage/app.php` and add `'env' => 'production'` (assuming this
  is a real, public-facing install) if it's not already there - see
  [Going to Production](going-to-production.md) for what this controls. New installs get this set
  automatically by the setup wizard; existing installs need it added by hand, since Vertext won't
  silently rewrite a config file you may have hand-edited.
- Security headers (CSP, X-Frame-Options, etc.) now apply to the public front-end and REST API, not
  just the admin panel - see [Security: Security Headers](security.md#security-headers). No action
  needed, but if you've built a custom theme with inline `<script>`/`<style>` blocks, the front-end's
  stricter CSP (no `unsafe-inline`) may block them; move inline styles/scripts to your theme's CSS/JS
  files.

Future releases will add their own subsection here.

## Rolling back

There's no automated rollback. If an upgrade breaks something, restore from the backup you took in
step 0 (`php vertext restore <archive>`) and investigate before retrying. This is a real limitation
for this release - see [Known Limitations](known-limitations.md).
