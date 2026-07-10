{{--
    `.pm-clamp-2` is hand-written CSS rather than Tailwind's `line-clamp-2`
    utility - see the comment at the top of plugin-details.blade.php for
    why utilities that aren't already used elsewhere in the host panel
    are risky to rely on from a plugin's own views.
--}}
<style>
    .pm-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
</style>

<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Search & filters --}}
        <div class="fi-section rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 shadow-sm p-4 space-y-4">
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="flex-1">
                    <input
                        type="text"
                        wire:model.live.debounce.400ms="search"
                        placeholder="{{ trans('plugin-marketplace::marketplace.marketplace.search_placeholder') }}"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white shadow-sm text-sm focus:border-primary-500 focus:ring-primary-500"
                    />
                </div>

                <select wire:model.live="sort" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white shadow-sm text-sm">
                    @foreach (\PelicanMarketplace\PluginMarketplace\Enums\MarketplaceSort::cases() as $sortOption)
                        <option value="{{ $sortOption->value }}">{{ $sortOption->getLabel() }}</option>
                    @endforeach
                </select>

                <select wire:model.live="category" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white shadow-sm text-sm">
                    <option value="">{{ trans('plugin-marketplace::marketplace.marketplace.all_categories') }}</option>
                    @foreach ($this->categoryOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>

                <input
                    type="text"
                    wire:model.live.debounce.400ms="minecraftVersion"
                    placeholder="{{ trans('plugin-marketplace::marketplace.marketplace.mc_version_placeholder') }}"
                    class="w-32 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white shadow-sm text-sm"
                />
            </div>

            <div class="flex flex-wrap items-center gap-4">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ trans('plugin-marketplace::marketplace.marketplace.repositories') }}</span>
                @foreach (\PelicanMarketplace\PluginMarketplace\Enums\MarketplaceRepository::cases() as $repositoryOption)
                    <label class="inline-flex items-center gap-1.5 text-sm text-gray-700 dark:text-gray-300">
                        <input type="checkbox" wire:model.live="repositories" value="{{ $repositoryOption->value }}" class="rounded border-gray-300 dark:border-gray-600 text-primary-600" />
                        {{ $repositoryOption->getLabel() }}
                    </label>
                @endforeach

                <span class="mx-2 h-4 w-px bg-gray-200 dark:bg-white/10"></span>

                <label class="inline-flex items-center gap-1.5 text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" wire:model.live="favoritesOnly" class="rounded border-gray-300 dark:border-gray-600 text-primary-600" />
                    {{ trans('plugin-marketplace::marketplace.marketplace.favorites_only') }}
                </label>

                <a href="{{ $this->installedPluginsUrl() }}" class="ms-auto text-sm text-primary-600 dark:text-primary-400 hover:underline">
                    {{ trans('plugin-marketplace::marketplace.marketplace.view_installed') }} &rarr;
                </a>
            </div>
        </div>

        {{-- Recently viewed --}}
        @if (!$favoritesOnly && count($this->recentlyViewed()) > 0)
            <div>
                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">
                    {{ trans('plugin-marketplace::marketplace.marketplace.recently_viewed') }}
                </h3>
                <div class="flex gap-3 overflow-x-auto pb-2">
                    @foreach ($this->recentlyViewed() as $recent)
                        <a
                            href="{{ \PelicanMarketplace\PluginMarketplace\Filament\Server\Pages\PluginDetails::getUrl(['repository' => $recent->repository->value, 'projectId' => $recent->project_id]) }}"
                            class="flex-shrink-0 flex items-center gap-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 px-3 py-2 hover:border-primary-400 transition"
                        >
                            <img src="{{ \PelicanMarketplace\PluginMarketplace\Support\PlaceholderIcon::or($recent->icon_url) }}" alt="" class="h-6 w-6 rounded" loading="lazy" />
                            <span class="text-sm text-gray-700 dark:text-gray-200 whitespace-nowrap">{{ $recent->name }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Results --}}
        @php($results = $this->results())

        @if (count($results->items) === 0)
            <div class="text-center py-16">
                <x-filament::icon icon="tabler-puzzle-off" class="h-12 w-12 mx-auto text-gray-300 dark:text-gray-600" />
                <h3 class="mt-4 text-base font-semibold text-gray-900 dark:text-white">
                    {{ trans('plugin-marketplace::marketplace.marketplace.empty_heading') }}
                </h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ trans('plugin-marketplace::marketplace.marketplace.empty_description') }}
                </p>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                @foreach ($results->items as $plugin)
                    <div wire:key="plugin-{{ $plugin->key() }}" class="flex flex-col rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 shadow-sm hover:shadow-md transition overflow-hidden">
                        <div class="p-4 flex-1 flex flex-col gap-3">
                            <div class="flex items-start gap-3">
                                <img src="{{ $plugin->iconUrlOrPlaceholder() }}" alt="" class="h-12 w-12 rounded-lg object-cover flex-shrink-0 bg-gray-100 dark:bg-gray-800" loading="lazy" />
                                <div class="min-w-0 flex-1">
                                    <a href="{{ \PelicanMarketplace\PluginMarketplace\Filament\Server\Pages\PluginDetails::getUrl(['repository' => $plugin->repository->value, 'projectId' => $plugin->projectId]) }}" class="font-semibold text-gray-900 dark:text-white hover:text-primary-600 dark:hover:text-primary-400 truncate block">
                                        {{ $plugin->name }}
                                    </a>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $plugin->author }}</p>
                                </div>
                                <button
                                    type="button"
                                    wire:click="toggleFavorite('{{ $plugin->repository->value }}', '{{ $plugin->projectId }}')"
                                    class="flex-shrink-0 text-gray-400 hover:text-danger-500 transition"
                                    title="{{ trans('plugin-marketplace::marketplace.marketplace.favorite') }}"
                                >
                                    <x-filament::icon :icon="$this->isFavorited($plugin->repository->value, $plugin->projectId) ? 'tabler-heart-filled' : 'tabler-heart'" class="h-5 w-5 {{ $this->isFavorited($plugin->repository->value, $plugin->projectId) ? 'text-danger-500' : '' }}" />
                                </button>
                            </div>

                            <p class="text-sm text-gray-600 dark:text-gray-300 pm-clamp-2">{{ $plugin->summary }}</p>

                            <div class="flex flex-wrap items-center gap-1.5 text-xs">
                                <x-filament::badge :color="$plugin->repository->getColor()">{{ $plugin->repository->getLabel() }}</x-filament::badge>
                                @if ($plugin->latestVersion)
                                    <x-filament::badge color="gray">v{{ $plugin->latestVersion }}</x-filament::badge>
                                @endif
                                @if (!$plugin->repository->supportsDirectInstall())
                                    <x-filament::badge color="warning">{{ trans('plugin-marketplace::marketplace.marketplace.manual_download') }}</x-filament::badge>
                                @endif
                            </div>

                            <div class="mt-auto flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 pt-2 border-t border-gray-100 dark:border-white/5">
                                <span class="inline-flex items-center gap-1">
                                    <x-filament::icon icon="tabler-download" class="h-3.5 w-3.5" />
                                    {{ format_number($plugin->downloads) }}
                                </span>
                                @if ($plugin->minecraftVersions !== [])
                                    <span class="truncate max-w-[10rem]" title="{{ implode(', ', $plugin->minecraftVersions) }}">
                                        {{ $plugin->minecraftVersions[0] }}{{ count($plugin->minecraftVersions) > 1 ? ' +' . (count($plugin->minecraftVersions) - 1) : '' }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <a
                            href="{{ \PelicanMarketplace\PluginMarketplace\Filament\Server\Pages\PluginDetails::getUrl(['repository' => $plugin->repository->value, 'projectId' => $plugin->projectId]) }}"
                            class="block text-center text-sm font-medium py-2 border-t border-gray-100 dark:border-white/5 text-primary-600 dark:text-primary-400 hover:bg-primary-50 dark:hover:bg-primary-500/10 transition"
                        >
                            {{ $plugin->repository->supportsDirectInstall() ? trans('plugin-marketplace::marketplace.marketplace.view_and_install') : trans('plugin-marketplace::marketplace.marketplace.view_details') }}
                        </a>
                    </div>
                @endforeach
            </div>

            {{-- Pagination --}}
            @if (!$favoritesOnly)
                <div class="flex items-center justify-between pt-2">
                    <x-filament::button color="gray" size="sm" :disabled="$page <= 1" wire:click="previousPage">
                        {{ trans('plugin-marketplace::marketplace.marketplace.previous') }}
                    </x-filament::button>
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ trans('plugin-marketplace::marketplace.marketplace.page', ['page' => $page]) }}</span>
                    <x-filament::button color="gray" size="sm" :disabled="!$results->hasMore" wire:click="nextPage">
                        {{ trans('plugin-marketplace::marketplace.marketplace.next') }}
                    </x-filament::button>
                </div>
            @endif

            @if ($results->errors !== [])
                <div class="text-xs text-warning-600 dark:text-warning-400">
                    {{ trans('plugin-marketplace::marketplace.marketplace.partial_results_warning') }}
                </div>
            @endif
        @endif
    </div>

    <div wire:loading.flex wire:target="search,repositories,category,minecraftVersion,sort,page,favoritesOnly" class="fixed bottom-6 right-6 items-center gap-2 rounded-full bg-gray-900 dark:bg-white text-white dark:text-gray-900 px-4 py-2 text-sm shadow-lg">
        <x-filament::loading-indicator class="h-4 w-4" />
        {{ trans('plugin-marketplace::marketplace.marketplace.loading') }}
    </div>
</x-filament-panels::page>
