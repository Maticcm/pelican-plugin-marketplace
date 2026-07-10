<?php

namespace PelicanMarketplace\PluginMarketplace\Data;

use PelicanMarketplace\PluginMarketplace\Enums\MarketplaceRepository;

/**
 * A single dependency reported by a plugin's manifest or by the
 * repository's own version metadata (e.g. a Modrinth "required" project
 * relation, or a plugin.yml `depend` entry resolved against the curated
 * `known_dependencies` config map).
 */
final readonly class MarketplaceDependencyData
{
    public function __construct(
        public string $name,
        public bool $required,
        public ?MarketplaceRepository $repository = null,
        public ?string $projectId = null,
        public ?string $slug = null,
        public bool $resolvable = false,
        public bool $satisfied = false,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'required' => $this->required,
            'repository' => $this->repository?->value,
            'project_id' => $this->projectId,
            'slug' => $this->slug,
            'resolvable' => $this->resolvable,
            'satisfied' => $this->satisfied,
        ];
    }

    public function withSatisfied(bool $satisfied): self
    {
        return new self(
            name: $this->name,
            required: $this->required,
            repository: $this->repository,
            projectId: $this->projectId,
            slug: $this->slug,
            resolvable: $this->resolvable,
            satisfied: $satisfied,
        );
    }
}
