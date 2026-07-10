<?php

namespace PelicanMarketplace\PluginMarketplace\Services\Daemon;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use PelicanMarketplace\PluginMarketplace\Contracts\DaemonFileRepositoryFactory;

/**
 * The real, production implementation of {@see DaemonFileRepositoryFactory},
 * bound in {@see \PelicanMarketplace\PluginMarketplace\Providers\PluginMarketplaceProvider}.
 * Mirrors the exact pattern the panel's own code uses to obtain a
 * server-bound file repository (see `App\Models\File::getRows()`).
 */
class PelicanDaemonFileRepositoryFactory implements DaemonFileRepositoryFactory
{
    public function forServer(Server $server): DaemonFileRepository
    {
        return (new DaemonFileRepository())->setServer($server);
    }
}
