<?php

/*
|--------------------------------------------------------------------------
| Subuser permission group translations
|--------------------------------------------------------------------------
|
| Consumed by the panel's own Subusers permission-picker UI
| (app/Filament/Server/Resources/Subusers/SubuserResource.php), which
| looks up "{translation_prefix}.{group}_title", "..._desc" and
| "..._{permission}" for whatever `translation_prefix` a custom
| permission group registers via Subuser::registerCustomPermissions()
| - see PluginMarketplaceProvider::registerPermissions().
|
*/

return [
    'plugins_title' => 'Plugin Marketplace',
    'plugins_desc' => 'Controls what this subuser can do with the Plugin Marketplace: browse, install, update and remove plugins on this server.',
    'plugins_view' => 'View the marketplace and this server\'s installed plugins',
    'plugins_install' => 'Install plugins (and their dependencies)',
    'plugins_update' => 'Update installed plugins',
    'plugins_delete' => 'Uninstall plugins',
];
