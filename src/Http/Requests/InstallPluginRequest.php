<?php

namespace PelicanMarketplace\PluginMarketplace\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InstallPluginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'repository' => ['required', 'string', 'in:hangar,modrinth,spigot'],
            'project_id' => ['required', 'string', 'max:255'],
            'version_id' => ['required', 'string', 'max:255'],
            'overwrite' => ['sometimes', 'boolean'],
        ];
    }
}
