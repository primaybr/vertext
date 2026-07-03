# REST API

Vertext 0.0.8 ships a read-only JSON API under `/api/v1/`.

## Endpoints

| Method | Path | Description |
| ------ | ---- | ----------- |
| GET | `/api/v1/posts` | Published blog posts (paginated) |
| GET | `/api/v1/posts/{slug}` | Single post including body |
| GET | `/api/v1/pages` | Published pages (paginated) |
| GET | `/api/v1/pages/{slug}` | Single page including content |
| GET | `/api/v1/events` | Published events (`?upcoming=1` for future only) |
| GET | `/api/v1/events/{slug}` | Single event with tickets and recurrence rule |

Endpoints return `404` when the owning module is disabled. Only published,
non-expired content is ever exposed.

## Query parameters

| Param | Applies to | Description |
| ----- | ---------- | ----------- |
| `page` | listings | Page number (default 1) |
| `per_page` | listings | Items per page (default 10, max 50) |
| `lang` | posts, pages | Filter by content language, e.g. `?lang=id` |
| `upcoming` | events | `1` = only events starting in the future |

## Response envelope

```json
{
  "data": [ ... ],
  "meta": { "current_page": 1, "per_page": 10, "total": 42, "last_page": 5 }
}
```

Single resources return `{"data": { ... }}` without `meta`. Errors return:

```json
{ "error": { "status": 404, "message": "Post not found." } }
```

## Authentication

All GET endpoints are public. Sending a valid API key raises your rate limit:

```
Authorization: Bearer vtx_0123abcd...
```

Create keys under **Admin > API Keys** (permission `api.manage`). The plaintext
key is shown exactly once at creation - only its SHA-256 hash is stored. Keys
can be revoked instantly; `last_used_at` is tracked per key.

Note for Apache + FastCGI setups: `Public/.htaccess` forwards the
`Authorization` header to PHP (`RewriteRule ... [E=HTTP_AUTHORIZATION:...]`).
Keep that rule if you customize the rewrite rules.

## Rate limiting

Fixed 60-second windows, tracked in the `api_rate_windows` table:

| Client | Limit |
| ------ | ----- |
| Anonymous (per IP) | 30 requests/minute |
| With API key | 100 requests/minute |

Exceeding the limit returns `429 Too Many Requests` with a `Retry-After`
header (seconds until the window resets).

## Example

```bash
curl -H "Authorization: Bearer vtx_..." \
  "https://example.com/api/v1/posts?per_page=5&lang=en"
```
