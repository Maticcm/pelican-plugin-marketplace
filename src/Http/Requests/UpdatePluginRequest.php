<?php

namespace PelicanMarketplace\PluginMarketplace\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePluginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'version_id' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
