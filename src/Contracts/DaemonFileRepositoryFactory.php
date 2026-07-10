<?php

namespace PelicanMarketplace\PluginMarketplace\Contracts;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;

/**
 * Every service in this plugin that needs to talk to Wings' file
 * endpoints depends on this instead of instantiating
 * `App\Repositories\Daemon\DaemonFileRepository` directly, so tests can
 * substitute a fake/mock without touching a real (or even in-memory)
 * Wings daemon.
 */
interface DaemonFileRepositoryFactory
{
    public function forServer(Server $server): DaemonFileRepository;
}
