# Backup & Restore

Vertext ships a CLI backup/restore tool: a single archive containing your database data,
`Public/uploads/`, and your `Storage/` config files.

```
php vertext backup [--include-secrets] [--output=path]
php vertext restore <archive-path> [--force]
```

## What's in a backup archive

A `.zip` file containing:

- `db/{table}.jsonl` - one file per database table, one JSON object per row
- `uploads/` - a full copy of `Public/uploads/`
- `storage/db.php`, `storage/app.php` - copies of your config files
- `manifest.json` - Vertext version, PHP version, creation time, and whether secrets were included

## Data-only, not a schema clone

This is a **data-only** database backup: it does not attempt to reconstruct table structure (column
types, constraints, indexes). Restoring assumes the target database already has the right schema -
either a fresh install that has run `php vertext migrate up`, or the same Vertext version that made
the backup. Restoring a backup into an empty, un-migrated database will fail (or restore nothing,
since `restore` skips any table the target schema doesn't have).

This keeps the backup mechanism entirely PHP/PDO-based - no dependency on the `pg_dump` binary being
installed or on `PATH`, which can't be assumed on every self-hosted server. The trade-off: this is
not a substitute for a full `pg_dump`-style database dump if you need one for other purposes.

## Secrets

By default (no `--include-secrets`), credential-shaped values are redacted before being written to
the archive:

- `Storage/db.php`'s `password` field
- Any `settings` table row whose `key` ends in `_password`, `_secret`, or `_token` (e.g.
  `mail_password`)

Redacted values are replaced with the literal string `__REDACTED__`. This means a backup made
without `--include-secrets` is safe to move off the server (e.g. to cloud storage) without leaking
plaintext credentials - but it also means `restore` will **not** overwrite your live
`Storage/db.php`/`Storage/app.php` from a redacted archive (that would break the site's DB
connection); it restores database data and uploads only, and prints a message saying your current
config was left alone.

Pass `--include-secrets` when you want a fully self-contained archive (e.g. for moving to a new
server) - understand that the resulting `.zip` then contains plaintext credentials and should be
treated with the same care as `Storage/db.php` itself.

## Restoring

```
php vertext restore Storage/backups/vertext-backup-2026-07-07_120000.zip
```

Restore **truncates every table the archive has data for** before reinserting rows - this is
destructive to whatever is currently in those tables. You'll be prompted to confirm unless you pass
`--force` (for scripted/cron use). There is no automated rollback if you restore the wrong archive;
take a fresh backup first if you're unsure.

`Public/uploads/` is copied on top of the existing directory (files with the same name are
overwritten; nothing is deleted first).

## What's not backed up

- `Cache/` - regenerable, not included
- Application source code - this backs up your data and config, not your Vertext install itself;
  that comes from your own git checkout/deployment

## Scheduling

Vertext does not schedule backups for you. Run `php vertext backup` on a recurring basis yourself
(e.g. a cron job) and decide how many backups to keep - there's no built-in retention policy. See
[Going to Production](going-to-production.md).
