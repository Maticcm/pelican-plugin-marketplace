<?php

namespace PelicanMarketplace\PluginMarketplace\Enums;

use App\Enums\TablerIcon;
use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum InstallJobStatus: string implements HasColor, HasIcon, HasLabel
{
    case Pending = 'pending';
    case Downloading = 'downloading';
    case Validating = 'validating';
    case BackingUp = 'backing_up';
    case Installing = 'installing';
    case Completed = 'completed';
    case Failed = 'failed';
    case RolledBack = 'rolled_back';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Downloading => 'Downloading',
            self::Validating => 'Validating',
            self::BackingUp => 'Backing Up',
            self::Installing => 'Installing',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
            self::RolledBack => 'Rolled Back',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Completed => 'success',
            self::Failed => 'danger',
            self::RolledBack => 'warning',
            default => 'info',
        };
    }

    public function getIcon(): BackedEnum
    {
        return match ($this) {
            self::Pending => TablerIcon::Clock,
            self::Downloading => TablerIcon::Download,
            self::Validating => TablerIcon::ShieldCheck,
            self::BackingUp => TablerIcon::FileZip,
            self::Installing => TablerIcon::Loader2,
            self::Completed => TablerIcon::Check,
            self::Failed => TablerIcon::X,
            self::RolledBack => TablerIcon::History,
        };
    }

    /**
     * Percentage weight used to render a determinate progress bar while
     * a job is in flight, since we don't get granular byte-level progress
     * from Wings for the write/pull endpoints.
     */
    public function progressPercent(): int
    {
        return match ($this) {
            self::Pending => 5,
            self::Downloading => 30,
            self::Validating => 55,
            self::BackingUp => 70,
            self::Installing => 85,
            self::Completed => 100,
            self::Failed, self::RolledBack => 100,
        };
    }

    public function isFinished(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::RolledBack], true);
    }
}
