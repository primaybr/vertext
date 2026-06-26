<?php
/**
 * Dispatching webhook events from a module
 *
 * Call WebhookDispatcher::dispatch() after any state-changing action in your module.
 * The dispatcher finds all enabled endpoints subscribed to the event, signs the payload
 * with HMAC-SHA256, and delivers via cURL. Delivery failures are logged and silently
 * ignored so a broken webhook endpoint never breaks your module's response.
 *
 * Requires: Webhooks module installed and enabled.
 */

use App\Modules\Webhooks\WebhookDispatcher;
use App\CMS\ModuleLoader;

// ------------------------------------------------------------------
// 1. Check that Webhooks is enabled before dispatching (optional but good practice)
// ------------------------------------------------------------------
if (ModuleLoader::isEnabled('webhooks')) {
    WebhookDispatcher::dispatch('post.published', [
        'id'     => $post['id'],
        'title'  => $post['title'],
        'slug'   => $post['slug'],
        'status' => 'published',
    ]);
}

// ------------------------------------------------------------------
// 2. Dispatching from a controller method (typical usage)
// ------------------------------------------------------------------
class PostsController extends \App\Controllers\Admin\BaseController
{
    public function update(string $id): void
    {
        // ... validate, update DB, etc. ...

        $post = $this->db->table('posts')->where('id', $id)->first();

        if ($post['status'] === 'published' && ModuleLoader::isEnabled('webhooks')) {
            WebhookDispatcher::dispatch('post.published', [
                'id'     => $post['id'],
                'title'  => $post['title'],
                'slug'   => $post['slug'],
                'status' => $post['status'],
            ]);
        }

        $this->flash('success', 'Post updated.');
        $this->redirect('/admin/blog/posts');
    }

    public function delete(string $id): void
    {
        $post = $this->db->table('posts')->where('id', $id)->first();

        $this->db->table('posts')->where('id', $id)->delete()->run();

        if (ModuleLoader::isEnabled('webhooks')) {
            WebhookDispatcher::dispatch('post.deleted', [
                'id'    => $post['id'],
                'title' => $post['title'],
                'slug'  => $post['slug'],
            ]);
        }

        $this->json(['success' => true]);
    }
}

// ------------------------------------------------------------------
// 3. Available event slugs
// ------------------------------------------------------------------
// post.published    - blog post published
// post.deleted      - blog post deleted
// page.published    - page published
// page.deleted      - page deleted
// media.uploaded    - file uploaded to media library
// media.deleted     - file deleted from media library
// ping              - test event (sent via admin UI only)
//
// Custom events: pass any string slug - only endpoints subscribed to it will receive delivery.

// ------------------------------------------------------------------
// 4. Verifying the signature on your receiver
// ------------------------------------------------------------------
// Every POST includes X-Vertext-Signature: sha256={hmac}
// Verify it before trusting the payload:
//
// $body     = file_get_contents('php://input');
// $expected = 'sha256=' . hash_hmac('sha256', $body, YOUR_ENDPOINT_SECRET);
// $received = $_SERVER['HTTP_X_VERTEXT_SIGNATURE'] ?? '';
//
// if (!hash_equals($expected, $received)) {
//     http_response_code(401);
//     exit;
// }
//
// $payload = json_decode($body, true);
// // $payload['event']       - event slug
// // $payload['delivery_id'] - unique UUID per delivery (use for idempotency)
// // $payload['data']        - event-specific data
