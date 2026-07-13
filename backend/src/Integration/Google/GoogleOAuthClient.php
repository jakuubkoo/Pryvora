<?php

declare(strict_types=1);

namespace App\Integration\Google;

use App\Integration\Exception\IntegrationException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Google's OAuth2 endpoints, spoken directly over HTTP.
 *
 * No google/apiclient: this is plain JSON, CalDavClient already sets the precedent
 * for hand-rolling a transport, and a self-hosted privacy tool has every reason to
 * keep its dependency surface small.
 */
final class GoogleOAuthClient
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

    public function __construct(
        private readonly HttpClientInterface $http_client,
        private readonly string $client_id,
        private readonly string $client_secret,
        private readonly string $redirect_uri,
    ) {
    }

    public function is_configured(): bool
    {
        return '' !== $this->client_id && '' !== $this->client_secret;
    }

    /**
     * @param list<string> $scopes
     */
    public function build_authorization_url(string $state, array $scopes): string
    {
        return self::AUTH_URL.'?'.http_build_query([
            'client_id' => $this->client_id,
            'redirect_uri' => $this->redirect_uri,
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
            'state' => $state,

            // Both are mandatory, and for the same reason: without them a user who
            // has consented before is sent back with an access token and NO refresh
            // token, and the integration dies silently at the first expiry with no
            // way to recover except disconnecting.
            'access_type' => 'offline',
            'prompt' => 'consent',

            // Never quietly inherit scopes granted to some other Pryvora feature.
            // What we ask for is what we get.
            'include_granted_scopes' => 'false',
        ]);
    }

    /**
     * @return array{access_token: string, refresh_token: string, expires_in: int, scope: string}
     */
    public function exchange_code(string $code): array
    {
        $payload = $this->post(self::TOKEN_URL, [
            'code' => $code,
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'redirect_uri' => $this->redirect_uri,
            'grant_type' => 'authorization_code',
        ]);

        $refresh_token = (string) ($payload['refresh_token'] ?? '');

        if ('' === $refresh_token) {
            throw new IntegrationException('Google did not return a refresh token. Revoke Pryvora at myaccount.google.com and connect again.');
        }

        return [
            'access_token' => (string) ($payload['access_token'] ?? ''),
            'refresh_token' => $refresh_token,
            'expires_in' => (int) ($payload['expires_in'] ?? 3600),
            'scope' => (string) ($payload['scope'] ?? ''),
        ];
    }

    /**
     * @return array{access_token: string, expires_in: int, scope: string}
     */
    public function refresh_access_token(string $refresh_token): array
    {
        $payload = $this->post(self::TOKEN_URL, [
            'refresh_token' => $refresh_token,
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'grant_type' => 'refresh_token',
        ]);

        return [
            'access_token' => (string) ($payload['access_token'] ?? ''),
            'expires_in' => (int) ($payload['expires_in'] ?? 3600),
            'scope' => (string) ($payload['scope'] ?? ''),
        ];
    }

    /**
     * Best effort. A token Google has already forgotten is not an error worth
     * blocking a disconnect over.
     */
    public function revoke(string $token): void
    {
        try {
            $this->http_client->request('POST', self::REVOKE_URL, [
                'body' => ['token' => $token],
            ])->getStatusCode();
        } catch (ExceptionInterface) {
            // Ignored on purpose.
        }
    }

    /**
     * @param array<string, string> $body
     *
     * @return array<string, mixed>
     */
    private function post(string $url, array $body): array
    {
        try {
            $response = $this->http_client->request('POST', $url, ['body' => $body]);
            $status = $response->getStatusCode();
            $payload = json_decode($response->getContent(false), true);
        } catch (ExceptionInterface $exception) {
            throw new IntegrationException('Could not reach Google: '.$exception->getMessage());
        }

        if (!\is_array($payload)) {
            throw new IntegrationException('Google returned an unreadable response.');
        }

        if ($status >= 400) {
            throw $this->to_exception($payload);
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function to_exception(array $payload): IntegrationException
    {
        $error = (string) ($payload['error'] ?? 'unknown_error');

        // The one error the user can actually act on, and the one they will see most:
        // a personal @gmail.com account on an External app stuck in Testing gets its
        // refresh token expired by Google every 7 days. Say so, rather than leaving
        // "invalid_grant" in lastError for them to google.
        if ('invalid_grant' === $error) {
            return new IntegrationException('Google access expired or was revoked. Google forces this every 7 days for personal accounts on unverified apps. Reconnect Gmail to continue.');
        }

        $description = (string) ($payload['error_description'] ?? '');

        return new IntegrationException('' !== $description ? 'Google rejected the request: '.$description : 'Google rejected the request.');
    }
}
