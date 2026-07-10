<?php

namespace PelicanMarketplace\PluginMarketplace\Enums;

use App\Enums\TablerIcon;
use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum MarketplaceRepository: string implements HasColor, HasIcon, HasLabel
{
    case Hangar = 'hangar';
    case Modrinth = 'modrinth';
    case Spigot = 'spigot';

    public function getLabel(): string
    {
        return match ($this) {
            self::Hangar => 'Hangar',
            self::Modrinth => 'Modrinth',
            self::Spigot => 'SpigotMC',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Hangar => 'warning',
            self::Modrinth => 'success',
            self::Spigot => 'danger',
        };
    }

    public function getIcon(): BackedEnum
    {
        return match ($this) {
            self::Hangar => TablerIcon::Flag2,
            self::Modrinth => TablerIcon::Puzzle,
            self::Spigot => TablerIcon::Flame,
        };
    }

    /**
     * Whether this repository supports direct, in-panel installation.
     * SpigotMC only ever supports discovery - never automated downloads,
     * per their terms of service.
     */
    public function supportsDirectInstall(): bool
    {
        return $this !== self::Spigot;
    }

    public function homepageUrl(string $slugOrId): string
    {
        return match ($this) {
            self::Hangar => 'https://hangar.papermc.io/' . $slugOrId,
            self::Modrinth => 'https://modrinth.com/plugin/' . $slugOrId,
            self::Spigot => 'https://www.spigotmc.org/resources/' . $slugOrId . '.html',
        };
    }
}
