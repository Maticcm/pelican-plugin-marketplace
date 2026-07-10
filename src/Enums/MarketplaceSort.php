<?php

namespace PelicanMarketplace\PluginMarketplace\Enums;

use Filament\Support\Contracts\HasLabel;

enum MarketplaceSort: string implements HasLabel
{
    case Popular = 'popular';
    case Downloads = 'downloads';
    case Updated = 'updated';
    case Rating = 'rating';
    case Name = 'name';

    public function getLabel(): string
    {
        return match ($this) {
            self::Popular => 'Most Popular',
            self::Downloads => 'Most Downloads',
            self::Updated => 'Recently Updated',
            self::Rating => 'Highest Rated',
            self::Name => 'Name (A-Z)',
        };
    }
}
