<?php

namespace PelicanMarketplace\PluginMarketplace\Filament\Server\Resources\InstalledPlugins\Pages;

use Filament\Resources\Pages\ListRecords;
use PelicanMarketplace\PluginMarketplace\Filament\Server\Resources\InstalledPlugins\InstalledPluginResource;

class ListInstalledPlugins extends ListRecords
{
    protected static string $resource = InstalledPluginResource::class;
}
