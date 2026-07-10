<?php

return [
    /*
    |--------------------------------------------------------------------
    | Repository toggles
    |--------------------------------------------------------------------
    |
    | These are the compiled-in defaults. Once the plugin is installed,
    | the admin-editable copies of these values live in the
    | `plugin_marketplace_settings` database table and are managed from
    | the "Marketplace Settings" page in the admin panel. The values
    | below are only used the first time that table is seeded.
    |
    */
    'repositories' => [
        'hangar' => [
            'enabled' => env('PLUGIN_MARKETPLACE_HANGAR_ENABLED', true),
            'base_url' => env('PLUGIN_MARKETPLACE_HANGAR_URL', 'https://hangar.papermc.io/api/v1'),
        ],
        'modrinth' => [
            'enabled' => env('PLUGIN_MARKETPLACE_MODRINTH_ENABLED', true),
            'base_url' => env('PLUGIN_MARKETPLACE_MODRINTH_URL', 'https://api.modrinth.com/v2'),
        ],
        'spigot' => [
            'enabled' => env('PLUGIN_MARKETPLACE_SPIGOT_ENABLED', true),
            // SpiGet is a free, read-only, third-party mirror of SpigotMC resource
            // metadata. It is used for discovery only - this plugin never proxies
            // or scrapes actual file downloads from SpigotMC, per their ToS.
            'base_url' => env('PLUGIN_MARKETPLACE_SPIGOT_URL', 'https://api.spiget.org/v2'),
            'resource_url' => env('PLUGIN_MARKETPLACE_SPIGOT_RESOURCE_URL', 'https://www.spigotmc.org/resources/'),
        ],
    ],

    'preferred_repository' => env('PLUGIN_MARKETPLACE_PREFERRED_REPOSITORY', 'hangar'),

    'user_agent' => env('PLUGIN_MARKETPLACE_USER_AGENT', 'Pelican-Plugin-Marketplace/1.0 (+https://github.com/pelican-eggs/plugin-marketplace)'),

    'http' => [
        'timeout' => env('PLUGIN_MARKETPLACE_HTTP_TIMEOUT', 10),
        'connect_timeout' => env('PLUGIN_MARKETPLACE_HTTP_CONNECT_TIMEOUT', 5),
    ],

    /*
    |--------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------
    */
    'cache' => [
        // Minutes. Applies to search results, plugin metadata, versions,
        // icons and category listings.
        'duration' => env('PLUGIN_MARKETPLACE_CACHE_DURATION', 30),
        'prefix' => 'plugin-marketplace',
    ],

    /*
    |--------------------------------------------------------------------
    | Downloads
    |--------------------------------------------------------------------
    */
    'downloads' => [
        // Megabytes.
        'max_size' => env('PLUGIN_MARKETPLACE_MAX_DOWNLOAD_SIZE', 250),
        'timeout' => env('PLUGIN_MARKETPLACE_DOWNLOAD_TIMEOUT', 120),
        'directory' => env('PLUGIN_MARKETPLACE_PLUGIN_DIRECTORY', '/plugins'),
    ],

    'dependency_installation_enabled' => env('PLUGIN_MARKETPLACE_DEPENDENCY_INSTALL', true),

    'health_warnings_enabled' => env('PLUGIN_MARKETPLACE_HEALTH_WARNINGS', true),

    'backups_enabled' => env('PLUGIN_MARKETPLACE_BACKUPS', true),

    'update_notifications_enabled' => env('PLUGIN_MARKETPLACE_UPDATE_NOTIFICATIONS', true),

    'automatic_update_checks' => env('PLUGIN_MARKETPLACE_AUTO_UPDATE_CHECKS', true),

    /*
    |--------------------------------------------------------------------
    | Known dependency projects
    |--------------------------------------------------------------------
    |
    | A small curated map of common plugin.yml `depend` / `softdepend`
    | names to their marketplace listing, used so the dependency
    | resolver can offer a one-click install even when the depending
    | plugin only knows the other plugin by its plugin.yml `name`
    | (rather than a resolvable project id).
    |
    | Every entry below was verified against the live Hangar/Modrinth/
    | Spiget APIs while building this plugin. For Hangar, `slug` is the
    | full "owner/slug" namespace Hangar itself requires. Vault and
    | Citizens have never been published to Hangar or Modrinth by their
    | authors, so they intentionally point at Spigot (discovery + manual
    | download only, per this plugin's policy of never proxying
    | SpigotMC downloads) rather than an unrelated same-named project.
    |
    */
    'known_dependencies' => [
        'PlaceholderAPI' => ['repository' => 'modrinth', 'slug' => 'placeholderapi'],
        'ProtocolLib' => ['repository' => 'hangar', 'slug' => 'dmulloy2/ProtocolLib'],
        'Vault' => ['repository' => 'spigot', 'slug' => '34315'],
        'LuckPerms' => ['repository' => 'modrinth', 'slug' => 'luckperms'],
        'WorldEdit' => ['repository' => 'modrinth', 'slug' => 'worldedit'],
        'WorldGuard' => ['repository' => 'modrinth', 'slug' => 'worldguard'],
        'Multiverse-Core' => ['repository' => 'modrinth', 'slug' => 'multiverse-core'],
        'Citizens' => ['repository' => 'spigot', 'slug' => '13811'],
        'MythicMobs' => ['repository' => 'modrinth', 'slug' => 'mythicmobs'],
    ],

    /*
    |--------------------------------------------------------------------
    | Known abandoned / deprecated projects
    |--------------------------------------------------------------------
    |
    | Slugs (per repository) that are known to be archived/abandoned with
    | a suggested replacement. Merged with the automatic "no releases in
    | over a year" heuristic used by the Plugin Health service.
    |
    */
    'known_replacements' => [
        // 'modrinth:essentialsx-old' => ['repository' => 'modrinth', 'slug' => 'essentialsx'],
    ],

    /*
    |--------------------------------------------------------------------
    | Server-software / Minecraft-version compatible loader tags
    |--------------------------------------------------------------------
    */
    'supported_loaders' => ['bukkit', 'spigot', 'paper', 'purpur', 'folia'],

    // Modrinth "categories" that indicate a mod loader we must hide,
    // per the requirement to only display Bukkit-family plugins.
    'excluded_loaders' => ['fabric', 'forge', 'neoforge', 'quilt'],
];
