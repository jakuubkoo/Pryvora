<?php

declare(strict_types=1);

namespace App\Integration\Google;

use App\Entity\ConnectedAccount;
use App\Integration\Exception\IntegrationException;
use App\Service\ConnectedAccountCredentials;
use Doctrine\ORM\EntityManagerInterface;

/**
 * The single choke point for Google tokens. Nothing else reads or writes the
 * credential bag — one place to get the refresh logic right, one place to audit.
 *
 * Refresh is lazy: it happens here, on demand, inside whatever is already making
 * a Gmail call (in practice the sync worker). There is no refresh cron, so a
 * disconnected account costs nothing.
 */
final class GoogleTokenProvider
{
    /**
     * Refresh a minute early. An access token that expires mid-request is a
     * confusing 401 for something that is not actually an auth problem.
     */
    private const EXPIRY_SKEW_SECONDS = 60;

    public function __construct(
        private readonly GoogleOAuthClient $oauth_client,
        private readonly ConnectedAccountCredentials $credentials,
        private readonly EntityManagerInterface $entity_manager,
    ) {
    }

    public function get_access_token(ConnectedAccount $account): string
    {
        $bag = $this->credentials->read($account);
        $access_token = (string) ($bag['access_token'] ?? '');

        if ('' !== $access_token && !$this->is_expired($account)) {
            return $access_token;
        }

        return $this->refresh($account, $bag);
    }

    /**
     * @param array<string, string> $bag
     */
    private function refresh(ConnectedAccount $account, array $bag): string
    {
        $refresh_token = (string) ($bag['refresh_token'] ?? '');

        if ('' === $refresh_token) {
            throw new IntegrationException('Gmail has no refresh token stored. Reconnect Gmail.');
        }

        $tokens = $this->oauth_client->refresh_access_token($refresh_token);

        // Google does not reissue the refresh token on every refresh, so keep the
        // one we have. Overwriting it with '' would strand the account.
        $this->credentials->write($account, [
            'access_token' => $tokens['access_token'],
            'refresh_token' => $refresh_token,
        ]);

        $account->setExpiresAt(new \DateTimeImmutable('@'.(time() + $tokens['expires_in'])));

        if ('' !== $tokens['scope']) {
            $account->setScopes(array_values(array_filter(explode(' ', $tokens['scope']))));
        }

        $this->entity_manager->flush();

        return $tokens['access_token'];
    }

    private function is_expired(ConnectedAccount $account): bool
    {
        $expires_at = $account->getExpiresAt();

        if (!$expires_at instanceof \DateTimeImmutable) {
            return true;
        }

        return $expires_at->getTimestamp() - self::EXPIRY_SKEW_SECONDS <= time();
    }
}
