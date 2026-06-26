<?php

declare(strict_types=1);

namespace App\Modules\Webhooks;

use Core\Model;

/**
 * Dispatches outgoing webhooks to subscribed endpoints.
 *
 * Usage from any module:
 *   if (\App\CMS\ModuleLoader::isEnabled('webhooks')) {
 *       \App\Modules\Webhooks\WebhookDispatcher::dispatch('post.published', $payload);
 *   }
 */
class WebhookDispatcher
{
    /** Events available for subscription. */
    public const EVENTS = [
        'post.published' => 'Post Published',
        'post.deleted'   => 'Post Deleted',
        'page.published' => 'Page Published',
        'page.deleted'   => 'Page Deleted',
        'media.uploaded' => 'Media Uploaded',
        'media.deleted'  => 'Media Deleted',
        'ping'           => 'Ping (test)',
    ];

    /**
     * Dispatch an event to all enabled endpoints subscribed to it.
     */
    public static function dispatch(string $event, array $payload): void
    {
        try {
            $endpoints = (new Model('webhook_endpoints'))
                ->where('enabled', true)
                ->get() ?: [];
        } catch (\Throwable) {
            return;
        }

        foreach ($endpoints as $ep) {
            $events = json_decode($ep['events'] ?? '[]', true) ?: [];
            if (!in_array($event, $events, true) && $event !== 'ping') {
                continue;
            }
            self::deliver($ep, $event, $payload);
        }
    }

    /**
     * Dispatch to a specific endpoint regardless of subscription (used for test ping).
     */
    public static function dispatchToEndpoint(string $endpointId, string $event, array $payload): array
    {
        try {
            $ep = (new Model('webhook_endpoints'))->where('id', $endpointId)->get(1);
        } catch (\Throwable) {
            return ['success' => false, 'message' => 'Endpoint not found.'];
        }

        if (!$ep) {
            return ['success' => false, 'message' => 'Endpoint not found.'];
        }

        return self::deliver($ep, $event, $payload);
    }

    private static function deliver(array $ep, string $event, array $payload): array
    {
        $deliveryId = bin2hex(random_bytes(8));
        $body       = json_encode([
            'event'       => $event,
            'delivery_id' => $deliveryId,
            'data'        => $payload,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $sig = 'sha256=' . hash_hmac('sha256', $body, $ep['secret'] ?? '');

        $code     = 0;
        $respBody = '';
        $success  = false;

        try {
            if (!function_exists('curl_init')) {
                throw new \RuntimeException('cURL not available.');
            }

            $ch = curl_init($ep['url']);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'X-Vertext-Event: '     . $event,
                    'X-Vertext-Delivery: '  . $deliveryId,
                    'X-Vertext-Signature: ' . $sig,
                    'User-Agent: Vertext-Webhooks/0.0.1',
                ],
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);

            $start    = microtime(true);
            $respBody = (string) curl_exec($ch);
            $elapsed  = (int) ((microtime(true) - $start) * 1000);
            $code     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            if ($curlErr) {
                $respBody = 'cURL error: ' . $curlErr;
            }

            $success = $code >= 200 && $code < 300;
        } catch (\Throwable $e) {
            $respBody = $e->getMessage();
            $elapsed  = 0;
        }

        // Log the delivery
        try {
            $log = new Model('webhook_logs');
            $log->endpoint_id   = $ep['id'];
            $log->event         = $event;
            $log->payload       = $body;
            $log->response_code = $code;
            $log->response_body = mb_substr($respBody, 0, 500);
            $log->duration_ms   = $elapsed ?? 0;
            $log->success       = $success;
            $log->save();
        } catch (\Throwable) {}

        return [
            'success'       => $success,
            'response_code' => $code,
            'duration_ms'   => $elapsed ?? 0,
        ];
    }
}
