<?php

namespace PelicanMarketplace\PluginMarketplace\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use PelicanMarketplace\PluginMarketplace\Data\MarketplaceSearchQuery;
use PelicanMarketplace\PluginMarketplace\Enums\MarketplaceRepository;
use PelicanMarketplace\PluginMarketplace\Enums\MarketplaceSort;

class SearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'query' => ['sometimes', 'nullable', 'string', 'max:255'],
            'repositories' => ['sometimes', 'array'],
            'repositories.*' => ['string', 'in:hangar,modrinth,spigot'],
            'categories' => ['sometimes', 'array'],
            'categories.*' => ['string', 'max:64'],
            'minecraft_version' => ['sometimes', 'nullable', 'string', 'max:32'],
            'sort' => ['sometimes', 'string', 'in:popular,downloads,updated,rating,name'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:500'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function toQuery(): MarketplaceSearchQuery
    {
        $data = $this->validated();

        return new MarketplaceSearchQuery(
            term: $data['query'] ?? '',
            repositories: array_map(fn (string $r) => MarketplaceRepository::from($r), $data['repositories'] ?? []),
            categories: $data['categories'] ?? [],
            minecraftVersion: $data['minecraft_version'] ?? null,
            sort: isset($data['sort']) ? MarketplaceSort::from($data['sort']) : MarketplaceSort::Popular,
            page: $data['page'] ?? 1,
            perPage: $data['per_page'] ?? 20,
        );
    }
}
