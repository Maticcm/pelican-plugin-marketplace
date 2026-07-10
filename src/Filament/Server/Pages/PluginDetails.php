<?php

namespace PelicanMarketplace\PluginMarketplace\Filament\Server\Pages;

use App\Enums\TablerIcon;
use App\Models\Server;
use BackedEnum;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use PelicanMarketplace\PluginMarketplace\Data\MarketplacePluginData;
use PelicanMarketplace\PluginMarketplace\Data\MarketplaceVersionData;
use PelicanMarketplace\PluginMarketplace\Enums\InstallJobType;
use PelicanMarketplace\PluginMarketplace\Enums\MarketplaceRepository;
use PelicanMarketplace\PluginMarketplace\Enums\PluginHealthStatus;
use PelicanMarketplace\PluginMarketplace\Jobs\InstallPluginJob;
use PelicanMarketplace\PluginMarketplace\Models\InstalledPlugin;
use PelicanMarketplace\PluginMarketplace\Models\PluginJob;
use PelicanMarketplace\PluginMarketplace\Services\CompatibilityCheckerService;
use PelicanMarketplace\PluginMarketplace\Services\DependencyResolverService;
use PelicanMarketplace\PluginMarketplace\Services\FavoritesService;
use PelicanMarketplace\PluginMarketplace\Services\MarketplaceSearchService;
use PelicanMarketplace\PluginMarketplace\Services\MarketplaceSettingsService;
use PelicanMarketplace\PluginMarketplace\Services\PluginHealthService;
use PelicanMarketplace\PluginMarketplace\Services\RecentPluginsService;
use PelicanMarketplace\PluginMarketplace\Services\RepositoryClientManager;

class PluginDetails extends Page implements HasActions
{
    use InteractsWithActions;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Eye;

    protected string $view = 'plugin-marketplace::filament.server.pages.plugin-details';

    protected static bool $shouldRegisterNavigation = false;

    /**
     * Bound to the `?repository=&projectId=` query string via Livewire's
     * `#[Url]` attribute rather than custom route path segments, since
     * a standalone Filament Page's route is a fixed slug - this is the
     * standard, version-stable way to deep-link a Livewire page with
     * parameters (Marketplace/Installed Plugins link here via
     * `PluginDetails::getUrl(['repository' => ..., 'projectId' => ...])`,
     * and Laravel's `route()` helper automatically appends any
     * `getUrl()` parameters that don't match a path segment as a query
     * string).
     */
    #[Url]
    public string $repository = '';

    #[Url]
    public string $projectId = '';

    public function mount(): void
    {
        abort_if($this->repository === '' || $this->projectId === '', 404);
        abort_if(MarketplaceRepository::tryFrom($this->repository) === null, 404);

        $plugin = $this->plugin();
        abort_if($plugin === null, 404);

        if (user()) {
            app(RecentPluginsService::class)->record(user(), $plugin);
        }
    }

    public static function canAccess(): bool
    {
        return (bool) user()?->can('plugins.view', Filament::getTenant());
    }

    public function getTitle(): string
    {
        return $this->plugin()?->name ?? trans('plugin-marketplace::marketplace.details.title');
    }

    public function plugin(): ?MarketplacePluginData
    {
        return app(MarketplaceSearchService::class)->find(
            MarketplaceRepository::from($this->repository),
            $this->projectId,
        );
    }

    /** @return MarketplaceVersionData[] */
    public function versions(): array
    {
        return app(RepositoryClientManager::class)->for(MarketplaceRepository::from($this->repository))?->versions($this->projectId) ?? [];
    }

    /**
     * Renders the plugin's long-form description as safe HTML.
     *
     * Hangar/Modrinth descriptions are Markdown (converted via
     * Laravel's `Str::markdown()`, which uses league/commonmark with
     * raw HTML escaped by default); Spigot's is already HTML decoded
     * from base64. Either way, none of these three are first-party
     * content, so the result is passed through `strip_tags()` with a
     * conservative allow-list before being echoed unescaped in the
     * view. Deliberately excludes `<a>` and `<img>`: `strip_tags()`
     * only filters tag *names*, not attributes, so allowing either
     * would let an `href="javascript:..."` or `onerror="..."` payload
     * straight through. Everything else in the allow-list is a plain
     * text-formatting tag that carries no attributes worth exploiting.
     */
    public function descriptionHtml(): string
    {
        $plugin = $this->plugin();
        if ($plugin === null || blank($plugin->description)) {
            return '';
        }

        $html = $plugin->repository === MarketplaceRepository::Spigot
            ? $plugin->description
            : Str::markdown($plugin->description);

        return strip_tags($html, '<p><br><b><strong><i><em><ul><ol><li><h1><h2><h3><h4><blockquote><code><pre><table><thead><tbody><tr><th><td>');
    }

    public function health(): PluginHealthStatus
    {
        $plugin = $this->plugin();

        return $plugin ? app(PluginHealthService::class)->status($plugin) : PluginHealthStatus::Healthy;
    }

    public function healthMessage(): ?string
    {
        $plugin = $this->plugin();

        return $plugin ? app(PluginHealthService::class)->warningMessage($plugin) : null;
    }

    public function isFavorited(): bool
    {
        return app(FavoritesService::class)->isFavorited(user(), MarketplaceRepository::from($this->repository), $this->projectId);
    }

    public function toggleFavorite(FavoritesService $favorites): void
    {
        $plugin = $this->plugin();
        if ($plugin === null) {
            return;
        }

        $favorited = $favorites->toggle(user(), $plugin);

        Notification::make()
            ->success()
            ->title($favorited ? trans('plugin-marketplace::marketplace.marketplace.favorited') : trans('plugin-marketplace::marketplace.marketplace.unfavorited'))
            ->send();
    }

    /** @return Collection<int, InstalledPlugin> */
    private function installedPlugins(): Collection
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        return InstalledPlugin::query()->where('server_id', $server->id)->get();
    }

    public function installAction(): Action
    {
        return Action::make('install')
            ->label(trans('plugin-marketplace::marketplace.details.install'))
            ->icon(TablerIcon::Download)
            ->color('primary')
            ->authorize(fn () => user()?->can('plugins.install', Filament::getTenant()))
            ->schema(function () {
                $plugin = $this->plugin();
                $versions = $this->versions();

                $versionOptions = collect($versions)->mapWithKeys(fn (MarketplaceVersionData $version) => [
                    $version->id => $version->versionNumber . ' (' . ($version->publishedAt?->diffForHumans() ?? 'unknown date') . ')',
                ]);

                return [
                    Select::make('version_id')
                        ->label(trans('plugin-marketplace::marketplace.details.select_version'))
                        ->options($versionOptions)
                        ->default($versions[0]?->id ?? null)
                        ->required()
                        ->live()
                        ->helperText(function (?string $state) use ($versions, $plugin) {
                            $version = collect($versions)->firstWhere('id', $state) ?? ($versions[0] ?? null);
                            if ($version === null || $plugin === null) {
                                return null;
                            }

                            $warnings = app(CompatibilityCheckerService::class)->check(
                                $version,
                                null,
                                $this->installedPlugins(),
                                $version->fileName ?? ($plugin->slug . '.jar'),
                                $plugin->name,
                            );

                            if ($warnings === []) {
                                return trans('plugin-marketplace::marketplace.details.no_warnings');
                            }

                            return collect($warnings)->pluck('message')->implode(' | ');
                        }),
                ];
            })
            ->action(function (array $data) {
                $this->performInstall($data['version_id'] ?? null, overwrite: false);
            });
    }

    private function performInstall(?string $versionId, bool $overwrite): void
    {
        $plugin = $this->plugin();
        if ($plugin === null) {
            Notification::make()->danger()->title(trans('plugin-marketplace::marketplace.marketplace.plugin_not_found'))->send();

            return;
        }

        $versions = $this->versions();
        $version = $versionId ? collect($versions)->firstWhere('id', $versionId) : ($versions[0] ?? null);

        if ($version === null) {
            Notification::make()->danger()->title(trans('plugin-marketplace::marketplace.details.no_version_available'))->send();

            return;
        }

        /** @var Server $server */
        $server = Filament::getTenant();

        $dependencyResolver = app(DependencyResolverService::class);
        $dependencies = $dependencyResolver->resolve($version->dependencies, $this->installedPlugins());
        $missingRequired = collect($dependencies)->filter(fn ($d) => $d->required && !$d->satisfied);

        if ($missingRequired->isNotEmpty()) {
            $dependenciesEnabled = app(MarketplaceSettingsService::class)->dependencyInstallationEnabled();

            if ($dependenciesEnabled) {
                // "Allow installing all dependencies with one click": every
                // required dependency this plugin was able to resolve to a
                // marketplace listing is queued for install alongside the
                // plugin itself, in the same click.
                $installable = $dependencyResolver->findInstallable($missingRequired->all());

                foreach ($installable as $dependencyPlugin) {
                    $this->installDependency($server, $dependencyPlugin);
                }

                $unresolvable = $missingRequired->reject(fn ($d) => array_key_exists($d->name, $installable));

                Notification::make()
                    ->warning()
                    ->title(trans('plugin-marketplace::marketplace.details.dependencies_required'))
                    ->body(
                        ($installable !== [] ? trans('plugin-marketplace::marketplace.details.dependencies_auto_installing', ['names' => implode(', ', array_keys($installable))]) . ' ' : '')
                        . ($unresolvable->isNotEmpty() ? trans('plugin-marketplace::marketplace.details.dependencies_manual', ['names' => $unresolvable->pluck('name')->implode(', ')]) : '')
                    )
                    ->send();
            } else {
                Notification::make()
                    ->warning()
                    ->title(trans('plugin-marketplace::marketplace.details.dependencies_required'))
                    ->body($missingRequired->pluck('name')->implode(', '))
                    ->send();
            }
        }

        $job = PluginJob::create([
            'server_id' => $server->id,
            'user_id' => user()->id,
            'type' => InstallJobType::Install,
            'repository' => $this->repository,
            'project_id' => $this->projectId,
            'plugin_name' => $plugin->name,
        ]);

        try {
            InstallPluginJob::dispatch(user(), $server, $this->repository, $this->projectId, $version->id, $overwrite, $job->id);

            Notification::make()
                ->success()
                ->title(trans('plugin-marketplace::marketplace.details.install_started', ['name' => $plugin->name]))
                ->body(trans('plugin-marketplace::marketplace.restart_required'))
                ->send();
        } catch (Exception $exception) {
            Notification::make()->danger()->title(trans('plugin-marketplace::marketplace.details.install_failed'))->body($exception->getMessage())->send();
        }
    }

    /**
     * Queues the install of a resolved dependency plugin using its own
     * latest compatible version. Deliberately does not recurse into
     * *that* plugin's own dependencies - one level of automatic
     * dependency installation is enough to cover the common case
     * (PlaceholderAPI, Vault, ProtocolLib, ...) without risking a long
     * or cyclical dependency chain silently installing a large tree of
     * unrelated plugins.
     */
    private function installDependency(Server $server, MarketplacePluginData $dependencyPlugin): void
    {
        $client = app(RepositoryClientManager::class)->for($dependencyPlugin->repository);
        $version = $client?->latestCompatibleVersion($dependencyPlugin->projectId);

        if ($version === null || $version->downloadUrl === null) {
            return;
        }

        $job = PluginJob::create([
            'server_id' => $server->id,
            'user_id' => user()->id,
            'type' => InstallJobType::Install,
            'repository' => $dependencyPlugin->repository->value,
            'project_id' => $dependencyPlugin->projectId,
            'plugin_name' => $dependencyPlugin->name,
        ]);

        InstallPluginJob::dispatch(user(), $server, $dependencyPlugin->repository->value, $dependencyPlugin->projectId, $version->id, false, $job->id);
    }
}
