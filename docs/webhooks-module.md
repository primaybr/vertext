# Webhooks Module

The Webhooks module (`slug: webhooks`, version 0.0.1) delivers signed outgoing HTTP POST payloads to external URLs when CMS events occur, with an admin UI for managing endpoints and viewing delivery logs.

## Features

- Outgoing webhooks to any publicly accessible HTTPS endpoint
- HMAC-SHA256 payload signing (`X-Vertext-Signature` header)
- Admin UI to create, edit, and delete endpoints; subscribe each to specific events
- Delivery logs: last 50 deliveries per endpoint with HTTP status, duration, and response preview
- Test ping button to verify an endpoint is reachable before real events fire
- Fails silently on delivery error so a webhook failure never breaks a CMS request

## Installation

Go to **Admin → Modules** and click **Install** next to Webhooks. Creates two tables and seeds 2 permissions.

## Database Tables

**`webhook_endpoints`**:

| Column | Type | Description |
|--------|------|-------------|
| `id` | UUID | Primary key |
| `name` | VARCHAR | Human-readable label (e.g. "Slack Notifications") |
| `url` | VARCHAR | Payload URL (must be https) |
| `secret` | VARCHAR | HMAC secret - store securely on the receiver side |
| `events` | TEXT | JSON array of subscribed event slugs |
| `enabled` | BOOLEAN | Whether to deliver events to this endpoint |
| `created_at` | TIMESTAMP | Creation time |
| `updated_at` | TIMESTAMP | Last modification |

**`webhook_logs`**:

| Column | Type | Description |
|--------|------|-------------|
| `id` | UUID | Primary key |
| `endpoint_id` | UUID | FK → webhook_endpoints (CASCADE DELETE) |
| `event` | VARCHAR | Event slug that triggered delivery |
| `payload` | TEXT | JSON body sent |
| `response_code` | SMALLINT | HTTP status code received (0 on network error) |
| `response_body` | TEXT | First 500 chars of response body |
| `duration_ms` | INTEGER | Round-trip time in milliseconds |
| `success` | BOOLEAN | `true` if HTTP 2xx was received |
| `created_at` | TIMESTAMP | Delivery time |

## Admin Routes

| Method | Path | Description |
|--------|------|-------------|
| GET | `/admin/webhooks` | Endpoint list |
| GET | `/admin/webhooks/create` | Create endpoint form |
| POST | `/admin/webhooks/store` | Save new endpoint |
| GET | `/admin/webhooks/{id}/edit` | Edit endpoint form |
| POST | `/admin/webhooks/{id}/update` | Update endpoint |
| POST | `/admin/webhooks/{id}/delete` | Delete endpoint (logs cascade) |
| GET | `/admin/webhooks/{id}/logs` | Delivery log for an endpoint |
| POST | `/admin/webhooks/{id}/test` | Send a test `ping` event immediately |

## Permissions

| Permission slug | Description |
|----------------|-------------|
| `webhooks.view` | View endpoint list and delivery logs |
| `webhooks.manage` | Create, edit, and delete endpoints |

Both permissions are auto-granted to the Administrator role on install.

## Available Events

| Event slug | When it fires |
|------------|---------------|
| `post.published` | A blog post status changes to `published` |
| `post.deleted` | A blog post is deleted |
| `page.published` | A page status changes to `published` |
| `page.deleted` | A page is deleted |
| `media.uploaded` | A file is uploaded to the media library |
| `media.deleted` | A file is deleted from the media library |
| `ping` | Test event sent from the admin UI |

## Payload Format

Every delivery sends a JSON body:

```json
{
    "event": "post.published",
    "delivery_id": "a1b2c3d4-...",
    "data": {
        "id": "...",
        "title": "My Post",
        "slug": "my-post",
        "status": "published"
    }
}
```

The `data` object shape depends on the event. The `delivery_id` is a UUID generated per delivery for idempotency.

## Signature Verification

Every request includes the header:

```
X-Vertext-Signature: sha256={HMAC-SHA256 of raw JSON body}
X-Vertext-Event: post.published
X-Vertext-Delivery: a1b2c3d4-...
```

To verify the signature in your receiver:

```php
$body      = file_get_contents('php://input');
$secret    = 'your-endpoint-secret';
$expected  = 'sha256=' . hash_hmac('sha256', $body, $secret);
$signature = $_SERVER['HTTP_X_VERTEXT_SIGNATURE'] ?? '';

if (!hash_equals($expected, $signature)) {
    http_response_code(401);
    exit;
}

$payload = json_decode($body, true);
```

Always use `hash_equals()` to prevent timing attacks.

## Dispatching Events from Your Module

Call `WebhookDispatcher::dispatch()` anywhere in your module code:

```php
use App\Modules\Webhooks\WebhookDispatcher;

// After publishing a post
WebhookDispatcher::dispatch('post.published', [
    'id'     => $post['id'],
    'title'  => $post['title'],
    'slug'   => $post['slug'],
    'status' => 'published',
]);
```

The dispatcher finds all enabled endpoints subscribed to the event, delivers to each via cURL, and logs the result. Delivery happens synchronously within the request but is non-blocking on failure.

See [examples/dispatching-webhooks.php](../examples/dispatching-webhooks.php) for a complete example.

## Notes

- Delivery timeout is 10 seconds per endpoint. Slow endpoints will delay the response to your user. For high-traffic sites, consider dispatching via a queue.
- The `ping` event is only sent via the admin test button and is never triggered by a real CMS event.
- Logs are retained indefinitely. Old entries accumulate until manually cleared.
