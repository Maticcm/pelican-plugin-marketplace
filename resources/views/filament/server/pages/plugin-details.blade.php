@php($plugin = $this->plugin())

{{--
    Plain, self-contained CSS for the third-party description content
    rather than Tailwind's `.prose` typography classes: this view lives
    inside a plugin directory that the host panel's Tailwind content
    scanner may not cover (see docs/ARCHITECTURE.md), so relying on a
    Tailwind plugin class here risks it being purged from the compiled
    bundle. A hand-written, scoped <style> block has no such dependency.
--}}
<style>
    .pm-content :where(h1, h2, h3, h4) { font-weight: 600; margin-top: 1em; margin-bottom: 0.4em; color: inherit; }
    .pm-content :where(p, ul, ol, blockquote, pre, table) { margin-top: 0.6em; margin-bottom: 0.6em; }
    .pm-content :where(ul, ol) { padding-inline-start: 1.5em; }
    .pm-content ul { list-style: disc; }
    .pm-content ol { list-style: decimal; }
    .pm-content blockquote { border-inline-start: 3px solid rgb(209 213 219); padding-inline-start: 0.75em; color: rgb(107 114 128); }
    .pm-content code { font-family: ui-monospace, monospace; font-size: 0.875em; background: rgba(127, 127, 127, 0.15); padding: 0.1em 0.35em; border-radius: 0.25em; }
    .pm-content pre { background: rgba(127, 127, 127, 0.12); padding: 0.75em; border-radius: 0.5em; overflow-x: auto; }
    .pm-content pre code { background: none; padding: 0; }
    .pm-content table { border-collapse: collapse; width: 100%; }
    .pm-content :where(th, td) { border: 1px solid rgba(127, 127, 127, 0.3); padding: 0.4em 0.6em; text-align: start; }
    .pm-clamp-3 { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
</style>

<x-filament-panels::page>
    @if ($plugin)
        <div class="space-y-6">
            {{-- Header --}}
            <div class="fi-section rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 shadow-sm p-6">
                <div class="flex flex-col sm:flex-row gap-4">
                    <img src="{{ $plugin->iconUrlOrPlaceholder() }}" alt="" class="h-20 w-20 rounded-xl object-cover bg-gray-100 dark:bg-gray-800 flex-shrink-0" />

                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ $plugin->name }}</h2>
                            <x-filament::badge :color="$plugin->repository->getColor()">{{ $plugin->repository->getLabel() }}</x-filament::badge>
                            @if ($this->health() !== \PelicanMarketplace\PluginMarketplace\Enums\PluginHealthStatus::Healthy)
                                <x-filament::badge :color="$this->health()->getColor()">{{ $this->health()->getLabel() }}</x-filament::badge>
                            @endif
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            {{ trans('plugin-marketplace::marketplace.details.by') }} {{ $plugin->author }}
                        </p>
                        <p class="text-sm text-gray-600 dark:text-gray-300 mt-2">{{ $plugin->summary }}</p>

                        @if ($this->healthMessage())
                            <div class="mt-3 rounded-lg bg-warning-50 dark:bg-warning-500/10 border border-warning-200 dark:border-warning-500/20 px-3 py-2 text-sm text-warning-700 dark:text-warning-300">
                                {{ $this->healthMessage() }}
                            </div>
                        @endif
                    </div>

                    <div class="flex flex-row sm:flex-col gap-2 flex-shrink-0">
                        @if ($plugin->repository->supportsDirectInstall())
                            <x-filament::button wire:click="mountAction('install')" icon="tabler-download">
                                {{ trans('plugin-marketplace::marketplace.details.install') }}
                            </x-filament::button>
                        @else
                            <x-filament::button tag="a" href="{{ $plugin->externalHomepageUrl ?? $plugin->homepageUrl() }}" target="_blank" color="gray" icon="tabler-external-link">
                                {{ trans('plugin-marketplace::marketplace.details.manual_download') }}
                            </x-filament::button>
                        @endif

                        <x-filament::button wire:click="toggleFavorite" color="gray" :icon="$this->isFavorited() ? 'tabler-heart-filled' : 'tabler-heart'">
                            {{ $this->isFavorited() ? trans('plugin-marketplace::marketplace.details.unfavorite') : trans('plugin-marketplace::marketplace.details.favorite') }}
                        </x-filament::button>
                    </div>
                </div>

                @if (!$plugin->repository->supportsDirectInstall())
                    <div class="mt-4 rounded-lg bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 px-3 py-2 text-sm text-gray-600 dark:text-gray-300">
                        {{ trans('plugin-marketplace::marketplace.details.manual_download_notice') }}
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {{-- Main column --}}
                <div class="lg:col-span-2 space-y-6">
                    @if ($plugin->gallery !== [])
                        <div class="fi-section rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 shadow-sm p-4">
                            <div class="flex gap-3 overflow-x-auto">
                                @foreach ($plugin->gallery as $image)
                                    <img src="{{ $image->url }}" alt="{{ $image->caption }}" class="h-40 rounded-lg object-cover flex-shrink-0" loading="lazy" />
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="fi-section rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 shadow-sm p-6">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">{{ trans('plugin-marketplace::marketplace.details.description') }}</h3>
                        <div class="pm-content text-sm text-gray-700 dark:text-gray-300 max-w-none">
                            {!! $this->descriptionHtml() !!}
                        </div>
                    </div>

                    <div class="fi-section rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 shadow-sm p-6">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">{{ trans('plugin-marketplace::marketplace.details.release_history') }}</h3>
                        <div class="divide-y divide-gray-100 dark:divide-white/5">
                            @forelse ($this->versions() as $version)
                                <div class="py-3 flex items-start justify-between gap-4">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $version->versionNumber }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $version->publishedAt?->diffForHumans() }} &middot; {{ implode(', ', $version->minecraftVersions) }}</p>
                                        @if ($version->changelog)
                                            <p class="text-xs text-gray-600 dark:text-gray-300 mt-1 pm-clamp-3">{{ $version->changelog }}</p>
                                        @endif
                                    </div>
                                    @if ($version->fileSize)
                                        <span class="text-xs text-gray-400 flex-shrink-0">{{ convert_bytes_to_readable($version->fileSize) }}</span>
                                    @endif
                                </div>
                            @empty
                                <p class="text-sm text-gray-500 dark:text-gray-400 py-2">{{ trans('plugin-marketplace::marketplace.details.no_versions') }}</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                {{-- Sidebar --}}
                <div class="space-y-6">
                    <div class="fi-section rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 shadow-sm p-6 space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">{{ trans('plugin-marketplace::marketplace.details.downloads') }}</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ format_number($plugin->downloads) }}</span>
                        </div>
                        @if ($plugin->followers !== null)
                            <div class="flex justify-between">
                                <span class="text-gray-500 dark:text-gray-400">{{ trans('plugin-marketplace::marketplace.details.followers') }}</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ format_number($plugin->followers) }}</span>
                            </div>
                        @endif
                        @if ($plugin->rating !== null)
                            <div class="flex justify-between">
                                <span class="text-gray-500 dark:text-gray-400">{{ trans('plugin-marketplace::marketplace.details.rating') }}</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ number_format($plugin->rating, 1) }} / 5</span>
                            </div>
                        @endif
                        @if ($plugin->license)
                            <div class="flex justify-between">
                                <span class="text-gray-500 dark:text-gray-400">{{ trans('plugin-marketplace::marketplace.details.license') }}</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ $plugin->license }}</span>
                            </div>
                        @endif
                        @if ($plugin->categories !== [])
                            <div>
                                <span class="text-gray-500 dark:text-gray-400 block mb-1">{{ trans('plugin-marketplace::marketplace.details.categories') }}</span>
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($plugin->categories as $categoryKey)
                                        <x-filament::badge color="gray" size="sm">{{ \Illuminate\Support\Str::headline($categoryKey) }}</x-filament::badge>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        @if ($plugin->minecraftVersions !== [])
                            <div>
                                <span class="text-gray-500 dark:text-gray-400 block mb-1">{{ trans('plugin-marketplace::marketplace.details.mc_versions') }}</span>
                                <p class="text-gray-900 dark:text-white text-xs">{{ implode(', ', array_slice($plugin->minecraftVersions, -10)) }}</p>
                            </div>
                        @endif
                    </div>

                    <div class="fi-section rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 shadow-sm p-6 space-y-2 text-sm">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-2">{{ trans('plugin-marketplace::marketplace.details.links') }}</h3>
                        <a href="{{ $plugin->homepageUrl() }}" target="_blank" class="flex items-center gap-2 text-primary-600 dark:text-primary-400 hover:underline">
                            <x-filament::icon icon="tabler-home" class="h-4 w-4" /> {{ trans('plugin-marketplace::marketplace.details.homepage') }}
                        </a>
                        @if ($plugin->sourceUrl)
                            <a href="{{ $plugin->sourceUrl }}" target="_blank" class="flex items-center gap-2 text-primary-600 dark:text-primary-400 hover:underline">
                                <x-filament::icon icon="tabler-brand-github" class="h-4 w-4" /> {{ trans('plugin-marketplace::marketplace.details.source') }}
                            </a>
                        @endif
                        @if ($plugin->issuesUrl)
                            <a href="{{ $plugin->issuesUrl }}" target="_blank" class="flex items-center gap-2 text-primary-600 dark:text-primary-400 hover:underline">
                                <x-filament::icon icon="tabler-bug" class="h-4 w-4" /> {{ trans('plugin-marketplace::marketplace.details.issues') }}
                            </a>
                        @endif
                        @if ($plugin->wikiUrl)
                            <a href="{{ $plugin->wikiUrl }}" target="_blank" class="flex items-center gap-2 text-primary-600 dark:text-primary-400 hover:underline">
                                <x-filament::icon icon="tabler-book" class="h-4 w-4" /> {{ trans('plugin-marketplace::marketplace.details.wiki') }}
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="text-center py-16">
            <p class="text-gray-500 dark:text-gray-400">{{ trans('plugin-marketplace::marketplace.marketplace.plugin_not_found') }}</p>
        </div>
    @endif

    {{-- Actions modal portal for the install action --}}
    <x-filament-actions::modals />
</x-filament-panels::page>
