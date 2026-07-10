<?php

namespace PelicanMarketplace\PluginMarketplace\Enums;

use Filament\Support\Contracts\HasLabel;

enum InstallJobType: string implements HasLabel
{
    case Install = 'install';
    case Update = 'update';
    case Uninstall = 'uninstall';
    case Scan = 'scan';

    public function getLabel(): string
    {
        return match ($this) {
            self::Install => 'Install',
            self::Update => 'Update',
            self::Uninstall => 'Uninstall',
            self::Scan => 'Scan',
        };
    }
}
