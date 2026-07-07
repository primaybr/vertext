<?php

declare(strict_types=1);

namespace Tests\App\CMS;

use Tests\App\Support\DatabaseTestCase;
use App\CMS\Auth;
use App\CMS\TotpHelper;
use App\CMS\LoginRateLimiter;
use Core\Model;
use Core\Security\Password;

/**
 * Covers admin login (Auth::attempt()) and 2FA (TotpHelper) by calling those
 * classes directly rather than driving AuthController - its login flow ends in
 * redirect() on every branch, which would abort the whole PHPUnit process if
 * invoked in-process (this codebase has no HTTP test client).
 */
final class AuthTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Both tables are lazily created on first real use (TotpHelper::ensureTable(),
        // LoginRateLimiter's constructor) - create them before truncating so a test
        // run where this class runs first doesn't fail on "relation does not exist".
        TotpHelper::ensureTable();
        new LoginRateLimiter('0.0.0.0', 'schema-bootstrap@example.com');

        $this->truncate(['users', 'user_2fa_secrets', 'login_attempts']);
    }

    public function testAttemptSucceedsWithCorrectPassword(): void
    {
        $email = 'login-test@example.com';
        (new Model('users'))->save([
            'name'     => 'Login Test',
            'email'    => $email,
            'password' => Password::hash('correct-password'),
            'status'   => 'active',
        ]);

        $user = Auth::attempt($email, 'correct-password');

        $this->assertIsArray($user);
        $this->assertSame($email, $user['email']);
    }

    public function testAttemptFailsWithWrongPassword(): void
    {
        $email = 'login-test-2@example.com';
        (new Model('users'))->save([
            'name'     => 'Login Test 2',
            'email'    => $email,
            'password' => Password::hash('correct-password'),
            'status'   => 'active',
        ]);

        $this->assertNull(Auth::attempt($email, 'wrong-password'));
    }

    public function testAttemptFailsForInactiveUser(): void
    {
        $email = 'login-test-3@example.com';
        (new Model('users'))->save([
            'name'     => 'Login Test 3',
            'email'    => $email,
            'password' => Password::hash('correct-password'),
            'status'   => 'suspended',
        ]);

        $this->assertNull(Auth::attempt($email, 'correct-password'));
    }

    public function testTotpVerifyAcceptsCurrentCodeAndRejectsWrongCode(): void
    {
        $secret = TotpHelper::generateSecret();

        // Derive the currently-valid code the same way TotpHelper does
        // internally, via reflection into its private hotp()/b32Decode(), so
        // this test doesn't need a second TOTP implementation to compare against.
        $reflection = new \ReflectionClass(TotpHelper::class);
        $b32Decode  = $reflection->getMethod('b32Decode');
        $b32Decode->setAccessible(true);
        $hotp = $reflection->getMethod('hotp');
        $hotp->setAccessible(true);

        $raw  = $b32Decode->invoke(null, $secret);
        $step = (int) floor(time() / 30);
        $validCode = $hotp->invoke(null, $raw, $step);

        $this->assertTrue(TotpHelper::verify($secret, $validCode));
        $this->assertFalse(TotpHelper::verify($secret, '000000'));
    }

    public function testBackupCodeMatchesOnceThenFails(): void
    {
        $plainCodes = TotpHelper::generateBackupCodes();
        $hashes     = TotpHelper::hashBackupCodes($plainCodes);

        $idx = TotpHelper::matchBackupCode($plainCodes[0], $hashes);
        $this->assertSame(0, $idx);

        $this->assertSame(-1, TotpHelper::matchBackupCode('00000-00000', $hashes));
    }

    public function testLoginRateLimiterBlocksAfterMaxAttemptsAndUnblocksAfterWindow(): void
    {
        $limiter = new LoginRateLimiter('203.0.113.1', 'rate-limit-test@example.com');

        $this->assertFalse($limiter->isBlocked());

        for ($i = 0; $i < 5; $i++) {
            $limiter->recordFailure();
        }

        $this->assertTrue($limiter->isBlocked());

        // Backdate every recorded attempt past the window instead of sleeping
        // 15 real minutes - exercises the same "attempted_at > cutoff" branch.
        (new Model('login_attempts'))
            ->withoutTimestamps()
            ->where('ip_address', '203.0.113.1')
            ->where('email', 'rate-limit-test@example.com')
            ->update(['attempted_at' => date('Y-m-d H:i:s', time() - 3600)]);

        $this->assertFalse($limiter->isBlocked());
    }
}
