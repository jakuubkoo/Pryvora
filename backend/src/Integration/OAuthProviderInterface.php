<?php

declare(strict_types=1);

namespace App\Integration;

use App\Entity\ConnectedAccount;
use App\Entity\User;

/**
 * A provider whose credentials come from an OAuth redirect rather than a form.
 *
 * Not tagged: implementors are already tagged through IntegrationProviderInterface.
 * This is an optional capability marker, exactly like CalendarWriteInterface, and
 * IntegrationController instanceof-checks it in the same way — so nothing here
 * knows the word "Google".
 *
 * The redirect URI is deliberately not a parameter on any of these methods. It is
 * app-level config that must byte-match what is registered with the provider, and
 * passing it around per-call is how it ends up mismatched.
 */
interface OAuthProviderInterface
{
    /**
     * @return list<string>
     */
    public function get_scopes(): array;

    /**
     * Whether the app-level client id/secret are configured on this server. A
     * provider that is not configured is still listed in the UI, but cannot be
     * connected — a self-hoster who has not set up a Google project should see
     * why, not a button that fails.
     */
    public function is_configured(): bool;

    /**
     * The consent URL to send the browser to. $state is opaque here and is only
     * round-tripped; the caller signs and verifies it.
     */
    public function get_authorization_url(string $state): string;

    /**
     * Exchanges the one-time code for tokens, and updates $existing in place when
     * this is a reconnect rather than a first connect.
     *
     * Throws IntegrationException when the code is rejected or the user withheld a
     * required scope on the consent screen.
     */
    public function complete_authorization(User $user, string $code, ?ConnectedAccount $existing): ConnectedAccount;
}
