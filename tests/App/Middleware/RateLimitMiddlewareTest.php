<?php

declare(strict_types=1);

namespace Tests\App\Middleware;

use PHPUnit\Framework\TestCase;
use Core\Middleware\RateLimitMiddleware;
use Core\Cache\CacheManager;

/**
 * Covers Core\Middleware\RateLimitMiddleware without any real waiting: window
 * expiry is tested by reaching into the CacheManager-backed state and
 * overwriting its stored resetAt to a past timestamp, exercising the exact
 * "reset if resetAt <= now" branch RateLimitMiddleware::process() takes on
 * its own, rather than sleeping past the real window.
 */
final class RateLimitMiddlewareTest extends TestCase
{
    private string $cacheName = 'rate_limit_test';

    protected function tearDown(): void
    {
        CacheManager::get($this->cacheName)->clear();
    }

    public function testAllowsRequestsUpToTheLimit(): void
    {
        $key        = 'test:' . bin2hex(random_bytes(4));
        $middleware = new RateLimitMiddleware($key, maxAttempts: 3, windowSeconds: 60, cacheName: $this->cacheName);
        $next       = static fn() => 'ok';

        $this->assertSame('ok', $middleware->process($next));
        $this->assertSame('ok', $middleware->process($next));
        $this->assertSame('ok', $middleware->process($next));
    }

    public function testBlocksOnceLimitIsExceeded(): void
    {
        $key        = 'test:' . bin2hex(random_bytes(4));
        $middleware = new RateLimitMiddleware($key, maxAttempts: 2, windowSeconds: 60, cacheName: $this->cacheName);
        $next       = static fn() => 'ok';

        $middleware->process($next);
        $middleware->process($next);
        $result = $middleware->process($next);

        $this->assertSame('Too Many Requests', $result);
        $this->assertSame(429, http_response_code());
    }

    public function testResetsAfterWindowExpiresWithoutRealWaiting(): void
    {
        $key        = 'test:' . bin2hex(random_bytes(4));
        $middleware = new RateLimitMiddleware($key, maxAttempts: 1, windowSeconds: 60, cacheName: $this->cacheName);
        $next       = static fn() => 'ok';

        $middleware->process($next);
        $this->assertSame('Too Many Requests', $middleware->process($next), 'Second call should already be over the limit of 1.');

        // Force the window to have already ended, instead of sleeping 60s.
        $cache = CacheManager::get($this->cacheName);
        $state = $cache->get($key);
        $state['resetAt'] = time() - 1;
        $cache->set($key, $state, 60);

        $this->assertSame('ok', $middleware->process($next), 'A new window should have started and allow the request through.');
    }
}
