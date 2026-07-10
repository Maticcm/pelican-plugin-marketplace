<?php

namespace PelicanMarketplace\PluginMarketplace\Filament\Server\Resources\PluginUpdates\Pages;

use Filament\Resources\Pages\ListRecords;
use PelicanMarketplace\PluginMarketplace\Filament\Server\Resources\PluginUpdates\PluginUpdateResource;

class ListPluginUpdates extends ListRecords
{
    protected static string $resource = PluginUpdateResource::class;
}
