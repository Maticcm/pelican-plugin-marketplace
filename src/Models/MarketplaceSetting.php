<?php

namespace PelicanMarketplace\PluginMarketplace\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A deliberately single-row settings table (see the migration, which
 * seeds row #1 on creation). Always accessed through
 * MarketplaceSettingsService::current(), never queried directly, so
 * caching / cache invalidation stays in one place.
 *
 * @property int $id
 * @property bool $hangar_enabled
 * @property bool $modrinth_enabled
 * @property bool $spigot_enabled
 * @property string $preferred_repository
 * @property bool $automatic_update_checks
 * @property int $cache_duration
 * @property int $max_download_size
 * @property int $download_timeout
 * @property bool $dependency_installation_enabled
 * @property bool $health_warnings_enabled
 * @property bool $backups_enabled
 * @property bool $update_notifications_enabled
 */
class MarketplaceSetting extends Model
{
    protected $table = 'plugin_marketplace_settings';

    protected $fillable = [
        'hangar_enabled',
        'modrinth_enabled',
        'spigot_enabled',
        'preferred_repository',
        'automatic_update_checks',
        'cache_duration',
        'max_download_size',
        'download_timeout',
        'dependency_installation_enabled',
        'health_warnings_enabled',
        'backups_enabled',
        'update_notifications_enabled',
    ];

    protected function casts(): array
    {
        return [
            'hangar_enabled' => 'boolean',
            'modrinth_enabled' => 'boolean',
            'spigot_enabled' => 'boolean',
            'automatic_update_checks' => 'boolean',
            'cache_duration' => 'integer',
            'max_download_size' => 'integer',
            'download_timeout' => 'integer',
            'dependency_installation_enabled' => 'boolean',
            'health_warnings_enabled' => 'boolean',
            'backups_enabled' => 'boolean',
            'update_notifications_enabled' => 'boolean',
        ];
    }
}
