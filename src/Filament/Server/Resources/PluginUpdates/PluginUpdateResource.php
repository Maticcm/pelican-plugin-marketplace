<?php

namespace PelicanMarketplace\PluginMarketplace\Filament\Server\Resources\PluginUpdates;

use App\Enums\TablerIcon;
use App\Models\Server;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use PelicanMarketplace\PluginMarketplace\Enums\InstallJobType;
use PelicanMarketplace\PluginMarketplace\Enums\MarketplaceRepository;
use PelicanMarketplace\PluginMarketplace\Filament\Server\Resources\PluginUpdates\Pages\ListPluginUpdates;
use PelicanMarketplace\PluginMarketplace\Jobs\BulkUpdatePluginsJob;
use PelicanMarketplace\PluginMarketplace\Jobs\UpdatePluginJob;
use PelicanMarketplace\PluginMarketplace\Models\InstalledPlugin;
use PelicanMarketplace\PluginMarketplace\Models\PluginJob;
use PelicanMarketplace\PluginMarketplace\Services\RepositoryClientManager;

class PluginUpdateResource extends Resource
{
    protected static ?string $model = InstalledPlugin::class;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Download;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('update_available', true);
    }

    public static function getNavigationGroup(): ?string
    {
        return trans('plugin-marketplace::marketplace.nav_group');
    }

    public static function getNavigationLabel(): string
    {
        return trans('plugin-marketplace::marketplace.updates.nav_label');
    }

    public static function getModelLabel(): string
    {
        return trans('plugin-marketplace::marketplace.updates.model_label');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label(trans('plugin-marketplace::marketplace.updates.columns.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('version')
                    ->label(trans('plugin-marketplace::marketplace.updates.columns.current'))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('latest_version')
                    ->label(trans('plugin-marketplace::marketplace.updates.columns.latest'))
                    ->badge()
                    ->color('success'),
                TextColumn::make('repository')
                    ->label(trans('plugin-marketplace::marketplace.updates.columns.repository'))
                    ->badge(),
            ])
            ->recordActions([
                Action::make('update')
                    ->label(trans('plugin-marketplace::marketplace.updates.actions.update'))
                    ->icon(TablerIcon::Download)
                    ->color('success')
                    ->authorize(fn () => user()?->can('plugins.update', Filament::getTenant()))
                    ->requiresConfirmation()
                    ->modalHeading(fn (InstalledPlugin $plugin) => trans('plugin-marketplace::marketplace.updates.changelog_heading', ['name' => $plugin->name]))
                    ->schema(fn (InstalledPlugin $plugin) => [
                        TextEntry::make('changelog')
                            ->hiddenLabel()
                            ->markdown()
                            ->state(fn () => static::changelogFor($plugin) ?? trans('plugin-marketplace::marketplace.updates.no_changelog')),
                    ])
                    ->action(fn (InstalledPlugin $plugin) => static::dispatchUpdate($plugin)),
            ])
            ->headerActions([
                Action::make('update_all')
                    ->label(trans('plugin-marketplace::marketplace.updates.actions.update_all'))
                    ->icon(TablerIcon::Download)
                    ->color('success')
                    ->authorize(fn () => user()?->can('plugins.update', Filament::getTenant()))
                    ->requiresConfirmation()
                    ->action(function () {
                        /** @var Server $server */
                        $server = Filament::getTenant();

                        $job = PluginJob::create([
                            'server_id' => $server->id,
                            'user_id' => user()->id,
                            'type' => InstallJobType::Update,
                            'plugin_name' => trans('plugin-marketplace::marketplace.updates.bulk_label'),
                        ]);

                        BulkUpdatePluginsJob::dispatch(user(), $server, null, $job->id);

                        Notification::make()
                            ->success()
                            ->title(trans('plugin-marketplace::marketplace.updates.notifications.bulk_started'))
                            ->send();
                    }),
            ])
            ->emptyStateIcon(TablerIcon::HeartCheck)
            ->emptyStateHeading(trans('plugin-marketplace::marketplace.updates.empty_heading'))
            ->emptyStateDescription(trans('plugin-marketplace::marketplace.updates.empty_description'));
    }

    private static function changelogFor(InstalledPlugin $plugin): ?string
    {
        if (!$plugin->isFromMarketplace()) {
            return null;
        }

        $repository = $plugin->repository instanceof MarketplaceRepository
            ? $plugin->repository
            : MarketplaceRepository::tryFrom((string) $plugin->repository);

        if ($repository === null) {
            return null;
        }

        $client = app(RepositoryClientManager::class)->for($repository);

        return $client?->latestCompatibleVersion($plugin->project_id)?->changelog;
    }

    private static function dispatchUpdate(InstalledPlugin $plugin): void
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
            ->title(trans('plugin-marketplace::marketplace.updates.notifications.update_started', ['name' => $plugin->name]))
            ->send();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPluginUpdates::route('/'),
        ];
    }
}
