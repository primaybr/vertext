# Troubleshooting

Common issues when installing or running Vertext, and where to look.

## Turning on debug output

Whether errors show a full stack trace or a generic message depends on the `env` key in
`Storage/app.php`:

- `'env' => 'development'` - shows detailed error output (stack trace, file/line, message).
- `'env' => 'production'` - shows a generic error page; details still go to `Logs/`.

The setup wizard sets this to `production` by default. If you need to debug a live issue, it's
usually safer to read `Logs/` than to temporarily flip to `development` on a public site - see
[Going to Production](going-to-production.md).

## Installation issues

**Setup wizard won't load / blank page**
- Confirm your web server's document root points at `Public/`, not the repo root.
- Confirm PHP is 8.2+ and the required extensions are loaded: `pdo`, `pdo_pgsql`, `json`,
  `mbstring`, `fileinfo`, `gd`. Run `php -m` to list loaded extensions, or open `/setup` itself -
  step 1 runs the same check the installer does.

**"Storage directory not writable" / "Cache directory not writable"**
- The web server's process user needs write access to `Storage/` and `Cache/`. On Linux:
  ```
  chown -R www-data:www-data Storage Cache
  chmod -R 750 Storage Cache
  ```
  (adjust `www-data` to your actual PHP-FPM/Apache user).

## Database connection issues

**"Could not connect to the database"**
- Vertext only supports **PostgreSQL** - there is no MySQL/SQLite support. If you're coming from a
  MySQL-based host, you'll need a Postgres database instead.
- Check `Storage/db.php` - host, port, database name, username, password all correct?
- Confirm Postgres is running and accepting TCP connections on the configured host/port.
- Confirm the `pdo_pgsql` PHP extension is loaded (`php -m | grep pgsql`).
- Check Postgres's `pg_hba.conf` authentication method for the connecting user/host - a common
  gotcha for self-hosters unfamiliar with Postgres specifically is `peer`/`ident` auth rejecting a
  password-based connection from PHP.

## Blank pages / white screen

- With `env => production`, errors don't display - check `Logs/` for the actual exception.
- With `env => development`, the error should show inline; if you're still seeing a truly blank
  page (no error text at all), check your PHP error log directly (`php.ini`'s `error_log` setting)
  and confirm `display_errors` isn't being suppressed at the server level.

## Upload / permission issues

- Media/file upload failures usually mean `Public/uploads/` isn't writable by the web server user -
  same permission fix as `Storage/`/`Cache/` above.
- If specific file types are rejected, confirm the `fileinfo` extension is loaded - Vertext uses it
  to validate MIME types against file extensions, and uploads fail closed (rejected) when it's
  missing.

## Where to get help

- Non-security bugs: open an issue on GitHub.
- Security issues: **do not** file a public issue - see the Security section of `README.md` for how
  to report privately.
