<?php

declare(strict_types=1);

namespace App\Integration;

use App\Integration\Exception\IntegrationException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class ProviderRegistry
{
    /**
     * @param iterable<IntegrationProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator('app.integration_provider')]
        private readonly iterable $providers,
    ) {
    }

    public function get(string $key): IntegrationProviderInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->get_key() === $key) {
                return $provider;
            }
        }

        throw new IntegrationException(\sprintf('Unknown integration provider "%s".', $key));
    }

    public function has(string $key): bool
    {
        foreach ($this->providers as $provider) {
            if ($provider->get_key() === $key) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<IntegrationProviderInterface>
     */
    public function all(): array
    {
        return iterator_to_array($this->providers, false);
    }
}
