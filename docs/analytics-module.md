# Analytics Module

The Analytics module (`slug: analytics`, version 0.0.2) provides privacy-friendly page-view tracking with a dashboard, date range filtering, period-over-period comparison, and CSV export.

## Features

- Automatic page-view recording via `ThemeEngine::render()` - no code changes required in themes or modules
- Bot filter covers 18+ known crawler user-agent patterns
- Privacy-friendly: stores only hostname of referrer, never full URL; IP stored as a non-reversible SHA-256 hash with a daily rotating salt
- Date range filter with quick presets (Today, 7 Days, 30 Days, 90 Days) and custom from/to dates
- Period-over-period delta: compares selected period vs the equivalent preceding period
- CSV export of raw page-view data for the selected date range
- Chart with adaptive X-axis label density for long ranges

## Installation

Go to **Admin → Modules** and click **Install** next to Analytics. Creates the `page_views` table and seeds 2 permissions.

## Database Table

**`page_views`**:

| Column | Type | Description |
|--------|------|-------------|
| `id` | UUID | Primary key |
| `url_path` | VARCHAR | Requested path (e.g. `/blog/my-post`) |
| `page_title` | VARCHAR | `<title>` of the page at render time |
| `referrer_host` | VARCHAR | Hostname extracted from `Referer` header (null if none) |
| `ip_hash` | VARCHAR | SHA-256(ip + daily salt) - not reversible |
| `viewed_at` | TIMESTAMP | When the view was recorded |

## Admin Routes

| Method | Path | Description |
|--------|------|-------------|
| GET | `/admin/analytics` | Dashboard (date range filter, KPI cards, chart, top pages, referrers) |
| GET | `/admin/analytics/data` | JSON endpoint for chart refresh |
| GET | `/admin/analytics/export` | CSV download for the selected date range |

## Date Range Filter

The dashboard `from` and `to` query parameters control all stats. Defaults to the last 30 days if omitted.

```
/admin/analytics?from=2026-06-01&to=2026-06-30
```

Quick presets link to the same URL with computed dates. A custom date range shows a label like "Jun 1 - Jun 30 (30 days)".

## KPI Cards

| Card | Shows | Delta |
|------|-------|-------|
| Selected Period | Total views in from/to range | vs preceding equivalent period |
| Today | Views since midnight | vs yesterday |
| Daily Average | `total / days` for selected range | - |

Delta is shown as `▲ x%` (green) or `▼ x%` (red). "No prior data" is shown when the comparison period has zero views.

## CSV Export

`GET /admin/analytics/export?from=YYYY-MM-DD&to=YYYY-MM-DD` streams a CSV file with columns:

```
url_path, page_title, referrer_host, viewed_at
```

The file is named `analytics_{from}_to_{to}.csv` via `Content-Disposition: attachment`.

## Permissions

| Permission slug | Description |
|----------------|-------------|
| `analytics.view` | View the analytics dashboard |
| `analytics.manage` | Access future analytics management features |

## Tracking Logic

`Tracker::record()` is called from `ThemeEngine::render()` after the page is rendered. It is wrapped in a `try-catch` so a tracking failure never causes a 500 error.

Requests are skipped if:

- The user-agent matches any of 18+ bot patterns
- The request is an AJAX call (`X-Requested-With: XMLHttpRequest`)
- The URL path starts with `/admin`
