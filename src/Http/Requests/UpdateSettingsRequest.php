<?php

namespace PelicanMarketplace\PluginMarketplace\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('settings plugins');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'hangar_enabled' => ['sometimes', 'boolean'],
            'modrinth_enabled' => ['sometimes', 'boolean'],
            'spigot_enabled' => ['sometimes', 'boolean'],
            'preferred_repository' => ['sometimes', 'string', 'in:hangar,modrinth,spigot'],
            'automatic_update_checks' => ['sometimes', 'boolean'],
            'cache_duration' => ['sometimes', 'integer', 'min:1', 'max:1440'],
            'max_download_size' => ['sometimes', 'integer', 'min:1', 'max:2048'],
            'download_timeout' => ['sometimes', 'integer', 'min:5', 'max:600'],
            'dependency_installation_enabled' => ['sometimes', 'boolean'],
            'health_warnings_enabled' => ['sometimes', 'boolean'],
            'backups_enabled' => ['sometimes', 'boolean'],
            'update_notifications_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
