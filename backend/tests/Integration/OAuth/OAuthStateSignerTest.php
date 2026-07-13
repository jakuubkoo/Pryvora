<?php

declare(strict_types=1);

namespace App\Tests\Integration\OAuth;

use App\Integration\Exception\IntegrationException;
use App\Integration\OAuth\OAuthStateSigner;
use PHPUnit\Framework\TestCase;

class OAuthStateSignerTest extends TestCase
{
    private OAuthStateSigner $signer;

    protected function setUp(): void
    {
        $this->signer = new OAuthStateSigner('a-test-secret');
    }

    public function testRoundTripsTheUserAndProvider(): void
    {
        $claims = $this->signer->verify($this->signer->sign(42, 'gmail'));

        $this->assertSame(42, $claims['user_id']);
        $this->assertSame('gmail', $claims['provider']);
    }

    /**
     * The whole point of signing: without the secret an attacker cannot mint a
     * state that binds their Google account to someone else's Pryvora user.
     */
    public function testStateForgedWithADifferentSecretIsRejected(): void
    {
        $forged = (new OAuthStateSigner('not-the-real-secret'))->sign(42, 'gmail');

        $this->expectException(IntegrationException::class);
        $this->signer->verify($forged);
    }

    public function testTamperedPayloadIsRejected(): void
    {
        $state = $this->signer->sign(42, 'gmail');
        [, $signature] = explode('.', $state);

        // Re-point the state at user 1 while keeping the original signature.
        $payload = rtrim(strtr(base64_encode(json_encode([
            'uid' => 1,
            'p' => 'gmail',
            'n' => 'deadbeefdeadbeef',
            'exp' => time() + 600,
        ], \JSON_THROW_ON_ERROR)), '+/', '-_'), '=');

        $this->expectException(IntegrationException::class);
        $this->signer->verify($payload.'.'.$signature);
    }

    public function testExpiredStateIsRejected(): void
    {
        $expired = $this->sign_with_expiry(42, 'gmail', time() - 1);

        $this->expectException(IntegrationException::class);
        $this->signer->verify($expired);
    }

    public function testMalformedStateIsRejected(): void
    {
        $this->expectException(IntegrationException::class);
        $this->signer->verify('garbage');
    }

    public function testEmptyStateIsRejected(): void
    {
        $this->expectException(IntegrationException::class);
        $this->signer->verify('');
    }

    /**
     * Two states for the same user differ — the nonce is doing its job, so a
     * captured state is not a reusable credential beyond its own TTL.
     */
    public function testEachStateIsUnique(): void
    {
        $this->assertNotSame(
            $this->signer->sign(42, 'gmail'),
            $this->signer->sign(42, 'gmail'),
        );
    }

    /**
     * Builds a correctly-signed state with a chosen expiry, which sign() will not
     * do for us.
     */
    private function sign_with_expiry(int $user_id, string $provider, int $expires_at): string
    {
        $payload = rtrim(strtr(base64_encode(json_encode([
            'uid' => $user_id,
            'p' => $provider,
            'n' => bin2hex(random_bytes(8)),
            'exp' => $expires_at,
        ], \JSON_THROW_ON_ERROR)), '+/', '-_'), '=');

        $mac = hash_hmac('sha256', $payload, 'a-test-secret', true);

        return $payload.'.'.rtrim(strtr(base64_encode($mac), '+/', '-_'), '=');
    }
}
