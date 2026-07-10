<?php

namespace PelicanMarketplace\PluginMarketplace\Filament\Admin\Pages;

use App\Enums\TablerIcon;
use BackedEnum;
use Exception;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Contracts\HasSchemas;
use PelicanMarketplace\PluginMarketplace\Services\MarketplaceSettingsService;

/**
 * @property \Filament\Schemas\Schema $form
 */
class MarketplaceSettings extends Page implements HasSchemas
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Settings;

    protected string $view = 'plugin-marketplace::filament.admin.pages.marketplace-settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(MarketplaceSettingsService $settings): void
    {
        $this->form->fill($settings->current()->only([
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
        ]));
    }

    public static function canAccess(): bool
    {
        return (bool) user()?->can('view plugins');
    }

    public static function getNavigationGroup(): ?string
    {
        return trans('plugin-marketplace::marketplace.nav_group');
    }

    public static function getNavigationLabel(): string
    {
        return trans('plugin-marketplace::marketplace.settings.nav_label');
    }

    public function getTitle(): string
    {
        return trans('plugin-marketplace::marketplace.settings.title');
    }

    protected function getFormStatePath(): ?string
    {
        return 'data';
    }

    /** @return Component[] */
    protected function getFormSchema(): array
    {
        $disabled = fn () => !user()?->can('settings plugins');

        return [
            Section::make(trans('plugin-marketplace::marketplace.settings.repositories'))
                ->description(trans('plugin-marketplace::marketplace.settings.repositories_description'))
                ->disabled($disabled)
                ->columns(3)
                ->schema([
                    Toggle::make('hangar_enabled')
                        ->label(trans('plugin-marketplace::marketplace.settings.enable_hangar'))
                        ->onIcon(TablerIcon::Check)
                        ->offIcon(TablerIcon::X),
                    Toggle::make('modrinth_enabled')
                        ->label(trans('plugin-marketplace::marketplace.settings.enable_modrinth'))
                        ->onIcon(TablerIcon::Check)
                        ->offIcon(TablerIcon::X),
                    Toggle::make('spigot_enabled')
                        ->label(trans('plugin-marketplace::marketplace.settings.enable_spigot'))
                        ->onIcon(TablerIcon::Check)
                        ->offIcon(TablerIcon::X)
                        ->helperText(trans('plugin-marketplace::marketplace.settings.spigot_helper')),
                ]),
            Section::make(trans('plugin-marketplace::marketplace.settings.general'))
                ->disabled($disabled)
                ->columns(2)
                ->schema([
                    Select::make('preferred_repository')
                        ->label(trans('plugin-marketplace::marketplace.settings.preferred_repository'))
                        ->options([
                            'hangar' => 'Hangar',
                            'modrinth' => 'Modrinth',
                            'spigot' => 'SpigotMC',
                        ])
                        ->required(),
                    Toggle::make('automatic_update_checks')
                        ->label(trans('plugin-marketplace::marketplace.settings.automatic_update_checks'))
                        ->onIcon(TablerIcon::Check)
                        ->offIcon(TablerIcon::X)
                        ->inline(false),
                    TextInput::make('cache_duration')
                        ->label(trans('plugin-marketplace::marketplace.settings.cache_duration'))
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(1440)
                        ->suffix(trans('plugin-marketplace::marketplace.settings.minutes'))
                        ->required(),
                    TextInput::make('max_download_size')
                        ->label(trans('plugin-marketplace::marketplace.settings.max_download_size'))
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(2048)
                        ->suffix('MB')
                        ->required(),
                    TextInput::make('download_timeout')
                        ->label(trans('plugin-marketplace::marketplace.settings.download_timeout'))
                        ->numeric()
                        ->minValue(5)
                        ->maxValue(600)
                        ->suffix(trans('plugin-marketplace::marketplace.settings.seconds'))
                        ->required(),
                ]),
            Section::make(trans('plugin-marketplace::marketplace.settings.behavior'))
                ->disabled($disabled)
                ->columns(2)
                ->schema([
                    Toggle::make('dependency_installation_enabled')
                        ->label(trans('plugin-marketplace::marketplace.settings.enable_dependency_installation'))
                        ->onIcon(TablerIcon::Check)
                        ->offIcon(TablerIcon::X),
                    Toggle::make('health_warnings_enabled')
                        ->label(trans('plugin-marketplace::marketplace.settings.enable_health_warnings'))
                        ->onIcon(TablerIcon::Check)
                        ->offIcon(TablerIcon::X),
                    Toggle::make('backups_enabled')
                        ->label(trans('plugin-marketplace::marketplace.settings.enable_backups'))
                        ->onIcon(TablerIcon::Check)
                        ->offIcon(TablerIcon::X),
                    Toggle::make('update_notifications_enabled')
                        ->label(trans('plugin-marketplace::marketplace.settings.enable_update_notifications'))
                        ->onIcon(TablerIcon::Check)
                        ->offIcon(TablerIcon::X),
                ]),
        ];
    }

    public function save(MarketplaceSettingsService $settings): void
    {
        abort_unless(user()?->can('settings plugins'), 403);

        try {
            $settings->update($this->form->getState());

            Notification::make()
                ->title(trans('plugin-marketplace::marketplace.settings.save_success'))
                ->success()
                ->send();
        } catch (Exception $exception) {
            Notification::make()
                ->title(trans('plugin-marketplace::marketplace.settings.save_failed'))
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->hiddenLabel()
                ->icon(TablerIcon::DeviceFloppy)
                ->tooltip(trans('plugin-marketplace::marketplace.settings.save'))
                ->action('save')
                ->authorize(fn () => user()?->can('settings plugins'))
                ->keyBindings(['mod+s']),
        ];
    }
}
