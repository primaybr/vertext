# Members Module

Front-end visitor accounts (v0.0.8). Site members live in the `site_users`
table and are completely separate from admin `users` - a member can never
reach `/admin`, and the two sessions coexist independently.

## Public routes

| Route | Description |
| ----- | ----------- |
| `GET/POST /account/register` | Create an account (honeypot-protected) |
| `GET /account/verify?token=` | Email verification link |
| `GET/POST /account/login` | Sign in (rate limited, separate scope from admin) |
| `GET /account` | Member profile: name + password change |
| `POST /account/update` | Save profile changes |
| `GET /account/logout` | Sign out (member session only) |

Views are theme-rendered with `--clr-*` variables and dark-mode support.

## Email verification

The `members_require_verification` install setting (default on) controls
whether new accounts start as `pending` and must click an emailed link before
signing in. Admins can resend the verification email or manually activate an
account from **Admin > Members**.

## Admin

`/admin/members` lists members with status tabs (All / Active / Pending /
Suspended), search, and in-place AJAX actions: activate, suspend, resend
verification, delete. Permissions: `members.view`, `members.manage`.

## SiteAuth helper

`App\CMS\SiteAuth` mirrors the admin `Auth` helper for the front-end:

```php
use App\CMS\SiteAuth;

SiteAuth::check();          // bool - is a member logged in?
SiteAuth::id();             // string|null - member UUID
SiteAuth::user();           // ['id','name','email']|null (session cache)
SiteAuth::attempt($e, $p);  // login attempt (Argon2id verify + rehash)
SiteAuth::logout();         // clears only the member session keys
```

Passwords use `Core\Security\Password` (Argon2id, bcrypt fallback).

## Integrations

- **Forms** - logged-in members get their name/email pre-filled on public forms
- **Events** - RSVPs record `site_user_id` and pre-fill the RSVP form
- **Webhooks** - `user.registered` fires when an account becomes active

## Table

```sql
site_users (
  id UUID PK, name VARCHAR(120), email VARCHAR(180) UNIQUE, password VARCHAR(255),
  status VARCHAR(20) 'pending'|'active'|'suspended',
  verify_token UUID, verified_at TIMESTAMP, last_login TIMESTAMP,
  created_at/updated_at/deleted_at + created_by/updated_by/deleted_by
)
```
