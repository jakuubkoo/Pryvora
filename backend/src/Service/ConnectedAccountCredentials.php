<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ConnectedAccount;

/**
 * The only place a credential bag is encrypted or decrypted. Providers go
 * through this and never touch EncryptionService directly.
 */
final class ConnectedAccountCredentials
{
    public function __construct(private readonly EncryptionService $encryption_service)
    {
    }

    /**
     * @return array<string, string>
     */
    public function read(ConnectedAccount $account): array
    {
        $credentials = $account->getCredentials();

        if (!$credentials) {
            return [];
        }

        $decoded = json_decode($this->encryption_service->decrypt($credentials), true);

        return \is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, string> $bag
     */
    public function write(ConnectedAccount $account, array $bag): void
    {
        $account->setCredentials($this->encryption_service->encrypt((string) json_encode($bag)));
    }
}
