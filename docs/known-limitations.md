# Known Limitations

Vertext 0.1.0 is a **public self-host beta** - good enough for a stranger to install on their own
server and use for real, but not enterprise-hardened. This is the honest list of what it does not
cover yet. None of these are secret or "coming very soon" promises - they're explicit trade-offs
for this release.

- **Accessibility.** No WCAG audit has been performed. The admin panel and bundled front-end themes
  have not been tested for screen-reader compatibility or full keyboard navigation.
- **Performance at scale.** No load or performance testing has been done, and there are no published
  benchmarks for concurrent users, large content volumes, or high traffic. Vertext is suitable for
  personal sites and small-to-medium projects; it has not been validated for high-traffic production
  use.
- **No self-update mechanism.** Upgrades are manual - `git pull`/`checkout` plus `php vertext migrate
  up`. See [Upgrading](upgrading.md).
- **Single-server only.** There is no multi-server or high-availability support. Sessions, file
  uploads, and caching are all file/single-database based, with no documented clustering path.
- **No formal backup policy.** `vertext backup`/`vertext restore` exist as tooling (see
  [Backup & Restore](backup-restore.md)), but Vertext does not manage backup scheduling, rotation,
  or retention - that's the operator's responsibility. Backups are also data-only (no full schema
  DDL clone).
- **Secrets storage.** Database and app configuration - including credentials - live in
  `Storage/db.php` / `Storage/app.php` as plain PHP files (already excluded from git), not
  environment variables. This is a deliberate choice for this release, not a bug, and is not being
  rearchitected for 0.1.0.
- **PostgreSQL only.** No MySQL or SQLite support, by design.
- **Test coverage is representative, not exhaustive.** The automated test suite covers the
  framework layer and the highest-risk CMS flows (install, login/2FA, Blog CRUD, Forms submission,
  REST API + rate limiting) to establish the pattern - it does not cover every module or every edge
  case.

This is a beta for people comfortable running their own PHP/PostgreSQL server - not yet an
enterprise-hardened or accessibility-certified product.
