<?php

namespace PelicanMarketplace\PluginMarketplace\Data;

final readonly class MarketplaceGalleryImageData
{
    public function __construct(
        public string $url,
        public ?string $caption = null,
        public bool $featured = false,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'caption' => $this->caption,
            'featured' => $this->featured,
        ];
    }
}
