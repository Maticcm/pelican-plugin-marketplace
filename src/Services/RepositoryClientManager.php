<?php

namespace PelicanMarketplace\PluginMarketplace\Services;

use PelicanMarketplace\PluginMarketplace\Contracts\RepositoryClient;
use PelicanMarketplace\PluginMarketplace\Enums\MarketplaceRepository;

/**
 * Registry of every {@see RepositoryClient} implementation this plugin
 * ships with. Bound as a singleton in the plugin's service provider so
 * new repository integrations only ever need to be added in one place.
 */
class RepositoryClientManager
{
    /** @var array<string, RepositoryClient> */
    private array $clients = [];

    public function register(RepositoryClient $client): void
    {
        $this->clients[$client->repository()->value] = $client;
    }

    public function for(MarketplaceRepository $repository): ?RepositoryClient
    {
        return $this->clients[$repository->value] ?? null;
    }

    /** @return RepositoryClient[] */
    public function all(): array
    {
        return array_values($this->clients);
    }

    /** @return RepositoryClient[] */
    public function enabled(): array
    {
        return array_values(array_filter($this->clients, fn (RepositoryClient $client) => $client->isEnabled()));
    }
}
