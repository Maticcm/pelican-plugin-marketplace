<?php

namespace PelicanMarketplace\PluginMarketplace\Filament\Server\Resources\InstalledPlugins;

use App\Enums\TablerIcon;
use App\Models\Server;
use BackedEnum;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use PelicanMarketplace\PluginMarketplace\Enums\InstallJobType;
use PelicanMarketplace\PluginMarketplace\Filament\Server\Resources\InstalledPlugins\Pages\ListInstalledPlugins;
use PelicanMarketplace\PluginMarketplace\Jobs\ScanInstalledPluginsJob;
use PelicanMarketplace\PluginMarketplace\Jobs\UninstallPluginJob;
use PelicanMarketplace\PluginMarketplace\Jobs\UpdatePluginJob;
use PelicanMarketplace\PluginMarketplace\Models\InstalledPlugin;
use PelicanMarketplace\PluginMarketplace\Models\PluginJob;
use PelicanMarketplace\PluginMarketplace\Services\PluginRemovalService;

class InstalledPluginResource extends Resource
{
    protected static ?string $model = InstalledPlugin::class;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Packages;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return trans('plugin-marketplace::marketplace.nav_group');
    }

    public static function getNavigationLabel(): string
    {
        return trans('plugin-marketplace::marketplace.installed.nav_label');
    }

    public static function getModelLabel(): string
    {
        return trans('plugin-marketplace::marketplace.installed.model_label');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->count() ?: null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(trans('plugin-marketplace::marketplace.installed.columns.name'))
                    ->description(fn (InstalledPlugin $plugin) => $plugin->file_name)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('version')
                    ->label(trans('plugin-marketplace::marketplace.installed.columns.version'))
                    ->badge()
                    ->color(fn (InstalledPlugin $plugin) => $plugin->update_available ? 'warning' : 'gray')
                    ->icon(fn (InstalledPlugin $plugin) => $plugin->update_available ? TablerIcon::VersionsOff : null)
                    ->tooltip(fn (InstalledPlugin $plugin) => $plugin->update_available ? trans('plugin-marketplace::marketplace.installed.update_available', ['version' => $plugin->latest_version]) : null),
                TextColumn::make('authors')
                    ->label(trans('plugin-marketplace::marketplace.installed.columns.author'))
                    ->formatStateUsing(fn (InstalledPlugin $plugin) => $plugin->authorsLabel())
                    ->toggleable(),
                TextColumn::make('size')
                    ->label(trans('plugin-marketplace::marketplace.installed.columns.size'))
                    ->formatStateUsing(fn (?int $state) => $state ? convert_bytes_to_readable($state) : '—')
                    ->toggleable(),
                TextColumn::make('installed_at')
                    ->label(trans('plugin-marketplace::marketplace.installed.columns.installed_at'))
                    ->dateTime()
                    ->since()
                    ->toggleable(),
                TextColumn::make('repository')
                    ->label(trans('plugin-marketplace::marketplace.installed.columns.repository'))
                    ->badge()
                    ->placeholder(trans('plugin-marketplace::marketplace.installed.unknown_origin'))
                    ->sortable(),
                IconColumn::make('enabled')
                    ->label(trans('plugin-marketplace::marketplace.installed.columns.enabled'))
                    ->boolean()
                    ->sortable(),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('update')
                        ->label(trans('plugin-marketplace::marketplace.installed.actions.update'))
                        ->icon(TablerIcon::Download)
                        ->color('success')
                        ->authorize(fn () => user()?->can('plugins.update', Filament::getTenant()))
                        ->visible(fn (InstalledPlugin $plugin) => $plugin->update_available)
                        ->requiresConfirmation()
                        ->modalDescription(fn (InstalledPlugin $plugin) => trans('plugin-marketplace::marketplace.installed.update_confirm', ['name' => $plugin->name, 'version' => $plugin->latest_version]))
                        ->action(fn (InstalledPlugin $plugin) => static::dispatchUpdate($plugin)),
                    Action::make('toggle')
                        ->label(fn (InstalledPlugin $plugin) => $plugin->enabled ? trans('plugin-marketplace::marketplace.installed.actions.disable') : trans('plugin-marketplace::marketplace.installed.actions.enable'))
                        ->icon(fn (InstalledPlugin $plugin) => $plugin->enabled ? TablerIcon::X : TablerIcon::Check)
                        ->color(fn (InstalledPlugin $plugin) => $plugin->enabled ? 'warning' : 'success')
                        ->authorize(fn () => user()?->can('plugins.update', Filament::getTenant()))
                        ->requiresConfirmation()
                        ->action(function (InstalledPlugin $plugin, PluginRemovalService $removal) {
                            try {
                                $removal->setEnabled(Filament::getTenant(), $plugin, !$plugin->enabled);

                                Notification::make()
                                    ->success()
                                    ->title(trans('plugin-marketplace::marketplace.installed.notifications.toggled'))
                                    ->body(trans('plugin-marketplace::marketplace.restart_required'))
                                    ->send();
                            } catch (Exception $exception) {
                                Notification::make()->danger()->title(trans('plugin-marketplace::marketplace.installed.notifications.toggle_failed'))->body($exception->getMessage())->send();
                            }
                        }),
                    Action::make('homepage')
                        ->label(trans('plugin-marketplace::marketplace.installed.actions.homepage'))
                        ->icon(TablerIcon::ExternalLink)
                        ->color('gray')
                        ->visible(fn (InstalledPlugin $plugin) => $plugin->isFromMarketplace())
                        ->url(fn (InstalledPlugin $plugin) => $plugin->repository?->homepageUrl($plugin->project_id), true),
                    Action::make('uninstall')
                        ->label(trans('plugin-marketplace::marketplace.installed.actions.uninstall'))
                        ->icon(TablerIcon::Trash)
                        ->color('danger')
                        ->authorize(fn () => user()?->can('plugins.delete', Filament::getTenant()))
                        ->requiresConfirmation()
                        ->modalDescription(fn (InstalledPlugin $plugin) => trans('plugin-marketplace::marketplace.installed.uninstall_confirm', ['name' => $plugin->name]))
                        ->action(fn (InstalledPlugin $plugin) => static::dispatchUninstall($plugin)),
                ]),
            ])
            ->headerActions([
                Action::make('scan')
                    ->label(trans('plugin-marketplace::marketplace.installed.actions.scan'))
                    ->icon(TablerIcon::Refresh)
                    ->authorize(fn () => user()?->can('plugins.view', Filament::getTenant()))
                    ->action(function () {
                        /** @var Server $server */
                        $server = Filament::getTenant();

                        $job = PluginJob::create([
                            'server_id' => $server->id,
                            'user_id' => user()->id,
                            'type' => InstallJobType::Scan,
                        ]);

                        ScanInstalledPluginsJob::dispatch(user(), $server, $job->id, notifyOnUpdatesFound: true);

                        Notification::make()
                            ->success()
                            ->title(trans('plugin-marketplace::marketplace.installed.notifications.scan_started'))
                            ->send();
                    }),
            ])
            ->emptyStateIcon(TablerIcon::Packages)
            ->emptyStateHeading(trans('plugin-marketplace::marketplace.installed.empty_heading'))
            ->emptyStateDescription(trans('plugin-marketplace::marketplace.installed.empty_description'));
    }

    public static function dispatchUpdate(InstalledPlugin $plugin): void
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        $job = PluginJob::create([
            'server_id' => $server->id,
            'user_id' => user()->id,
            'type' => InstallJobType::Update,
            'plugin_name' => $plugin->name,
        ]);

        UpdatePluginJob::dispatch(user(), $server, $plugin->id, null, $job->id);

        Notification::make()
            ->success()
            ->title(trans('plugin-marketplace::marketplace.installed.notifications.update_started', ['name' => $plugin->name]))
            ->send();
    }

    public static function dispatchUninstall(InstalledPlugin $plugin): void
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        $job = PluginJob::create([
            'server_id' => $server->id,
            'user_id' => user()->id,
            'type' => InstallJobType::Uninstall,
            'plugin_name' => $plugin->name,
        ]);

        UninstallPluginJob::dispatch(user(), $server, $plugin->id, $job->id);

        Notification::make()
            ->success()
            ->title(trans('plugin-marketplace::marketplace.installed.notifications.uninstall_started', ['name' => $plugin->name]))
            ->send();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInstalledPlugins::route('/'),
        ];
    }
}
