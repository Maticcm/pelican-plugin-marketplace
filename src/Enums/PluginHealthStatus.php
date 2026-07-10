<?php

namespace PelicanMarketplace\PluginMarketplace\Enums;

use App\Enums\TablerIcon;
use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PluginHealthStatus: string implements HasColor, HasIcon, HasLabel
{
    case Healthy = 'healthy';
    case Abandoned = 'abandoned';
    case Deprecated = 'deprecated';
    case Archived = 'archived';

    public function getLabel(): string
    {
        return match ($this) {
            self::Healthy => 'Actively Maintained',
            self::Abandoned => 'No updates in over a year',
            self::Deprecated => 'Deprecated by author',
            self::Archived => 'Archived project',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Healthy => 'success',
            self::Abandoned => 'warning',
            self::Deprecated => 'danger',
            self::Archived => 'gray',
        };
    }

    public function getIcon(): BackedEnum
    {
        return match ($this) {
            self::Healthy => TablerIcon::Heart,
            self::Abandoned => TablerIcon::ClockExclamation,
            self::Deprecated => TablerIcon::AlertTriangle,
            self::Archived => TablerIcon::Archive,
        };
    }

    public function isWarning(): bool
    {
        return $this !== self::Healthy;
    }
}
