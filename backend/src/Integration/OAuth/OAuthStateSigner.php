<?php

declare(strict_types=1);

namespace App\Integration\OAuth;

use App\Integration\Exception\IntegrationException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Signs the OAuth `state` parameter.
 *
 * The api firewall is stateless, and the provider's callback is a top-level browser
 * navigation carrying no Authorization header and no session — so there is nowhere
 * to keep a server-side state value. The state must therefore carry its own proof,
 * which is what this does.
 *
 * It is the CSRF defence for the OAuth dance: without a valid signature an attacker
 * cannot mint a state that binds *their* Google account to *your* Pryvora user. The
 * expiry bounds replay, and the auth code itself is single-use at the provider.
 */
final class OAuthStateSigner
{
    private const TTL_SECONDS = 600;

    public function __construct(
        #[Autowire('%kernel.secret%')]
        private readonly string $secret,
    ) {
    }

    public function sign(int $user_id, string $provider_key): string
    {
        $payload = $this->encode(json_encode([
            'uid' => $user_id,
            'p' => $provider_key,
            'n' => bin2hex(random_bytes(8)),
            'exp' => time() + self::TTL_SECONDS,
        ], \JSON_THROW_ON_ERROR));

        return $payload.'.'.$this->encode($this->mac($payload));
    }

    /**
     * @return array{user_id: int, provider: string}
     *
     * @throws IntegrationException when the state is malformed, forged or expired
     */
    public function verify(string $state): array
    {
        $parts = explode('.', $state);

        if (2 !== \count($parts)) {
            throw new IntegrationException('Malformed sign-in state.');
        }

        [$payload, $signature] = $parts;

        // hash_equals, not ===, so a wrong signature cannot be recovered byte by
        // byte from response timing.
        if (!hash_equals($this->mac($payload), $this->decode($signature))) {
            throw new IntegrationException('Sign-in state failed verification.');
        }

        $claims = json_decode($this->decode($payload), true);

        if (!\is_array($claims) || !isset($claims['uid'], $claims['p'], $claims['exp'])) {
            throw new IntegrationException('Malformed sign-in state.');
        }

        if (time() > (int) $claims['exp']) {
            throw new IntegrationException('The sign-in attempt expired. Try connecting again.');
        }

        return [
            'user_id' => (int) $claims['uid'],
            'provider' => (string) $claims['p'],
        ];
    }

    private function mac(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->secret, true);
    }

    private function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function decode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return false === $decoded ? '' : $decoded;
    }
}
