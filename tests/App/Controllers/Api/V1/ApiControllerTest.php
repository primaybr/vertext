<?php

declare(strict_types=1);

namespace Tests\App\Controllers\Api\V1;

use Tests\App\Support\DatabaseTestCase;
use App\Controllers\Api\V1\PostsController;
use Core\Model;

/**
 * Covers API key resolution and construction-time rate limiting shared by
 * every Api\V1\*Controller (via ApiController's constructor).
 *
 * Doesn't call any endpoint action (index()/show()/etc.): every one of them
 * ends in respond()/paginated()/fail(), all `never`-returning (they exit) -
 * like every redirect() elsewhere in this codebase, that would abort the
 * whole PHPUnit process if invoked in-process. Constructing the controller
 * itself is safe as long as the request doesn't exceed the rate limit or
 * present an invalid key (both of which call fail() -> exit from inside the
 * constructor) - this test only exercises the valid-key and anonymous paths,
 * and checks the invalid-key case at the Model-query level instead.
 */
final class ApiControllerTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->truncate(['api_keys', 'api_rate_windows']);
        unset($_SERVER['HTTP_AUTHORIZATION']);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_AUTHORIZATION']);
        parent::tearDown();
    }

    public function testAnonymousRequestIsAllowedWithNoApiKey(): void
    {
        $controller = new PostsController();

        $this->assertNull($this->getApiKey($controller));
    }

    public function testValidBearerKeyResolvesToItsApiKeysRow(): void
    {
        $plaintext = $this->seedApiKey('CI Test Key');

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $plaintext;
        $controller = new PostsController();

        $resolved = $this->getApiKey($controller);
        $this->assertIsArray($resolved);
        $this->assertSame('CI Test Key', $resolved['name']);
    }

    public function testUnknownKeyHashHasNoMatchingRow(): void
    {
        $this->seedApiKey('Some Other Key');

        // Mirrors resolveApiKey()'s lookup without constructing the controller
        // with a bogus Authorization header - that path calls fail() -> exit.
        $match = (new Model('api_keys'))
            ->where('key_hash', hash('sha256', 'not-a-real-key'))
            ->whereNull('revoked_at')
            ->get(1);

        $this->assertFalse($match);
    }

    public function testRevokedKeyIsExcludedFromLookup(): void
    {
        $plaintext = $this->seedApiKey('Revoked Key');
        (new Model('api_keys'))->where('key_hash', hash('sha256', $plaintext))
            ->update(['revoked_at' => date('Y-m-d H:i:s')]);

        $match = (new Model('api_keys'))
            ->where('key_hash', hash('sha256', $plaintext))
            ->whereNull('revoked_at')
            ->get(1);

        $this->assertFalse($match);
    }

    private function getApiKey(PostsController $controller): ?array
    {
        $reflection = new \ReflectionProperty($controller, 'apiKey');
        $reflection->setAccessible(true);
        return $reflection->getValue($controller);
    }
}
