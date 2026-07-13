<?php

declare(strict_types=1);

namespace App\Integration;

use App\Entity\ConnectedAccount;
use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Deliberately carries no OAuth methods: Apple is basic auth over CalDAV and
 * Telegram is a bot token. OAuth providers implement OAuthProviderInterface
 * on top of this.
 */
#[AutoconfigureTag('app.integration_provider')]
interface IntegrationProviderInterface
{
    public function get_key(): string;

    public function get_label(): string;

    public function get_credential_form(): CredentialForm;

    /**
     * Validates the credentials against the remote service and returns a
     * persisted-ready account. Throws IntegrationException when they are wrong,
     * so the user finds out at the form rather than in a worker log.
     *
     * @param array<string, string> $credentials plaintext, straight from the connect form
     */
    public function connect(User $user, array $credentials): ConnectedAccount;

    public function test_connection(ConnectedAccount $account): bool;

    /**
     * @return int number of events created or updated
     */
    public function sync(ConnectedAccount $account): int;

    public function disconnect(ConnectedAccount $account): void;
}
