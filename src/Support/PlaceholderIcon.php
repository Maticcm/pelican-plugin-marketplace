<?php

namespace PelicanMarketplace\PluginMarketplace\Support;

/**
 * A neutral puzzle-piece icon, inlined as a data URI so it renders with
 * zero dependency on any asset-publishing step - a plugin-shipped SVG
 * under `public/` would need either Vite manifest wiring or a
 * `filament:assets` publish step that this plugin does not register
 * (see docs/ARCHITECTURE.md). Used anywhere a plugin/favorite/recently
 * viewed entry has no icon of its own.
 */
final class PlaceholderIcon
{
    public const DATA_URI = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgc3Ryb2tlPSJjdXJyZW50Q29sb3IiIHN0cm9rZS13aWR0aD0iMS41IiBzdHJva2UtbGluZWNhcD0icm91bmQiIHN0cm9rZS1saW5lam9pbj0icm91bmQiIGNsYXNzPSJ0ZXh0LWdyYXktMzAwIj48cGF0aCBzdHJva2U9Im5vbmUiIGQ9Ik0wIDBoMjR2MjRIMHoiIGZpbGw9Im5vbmUiLz48cGF0aCBkPSJNNCA3aDNhMSAxIDAgMCAwIDEgLTFWNGExIDEgMCAwIDEgMSAtMWg2YTEgMSAwIDAgMSAxIDF2MmExIDEgMCAwIDAgMSAxaDNhMSAxIDAgMCAxIDEgMXY2YTEgMSAwIDAgMSAtMSAxaC0yYTEgMSAwIDAgMCAtMSAxdjNhMSAxIDAgMCAxIC0xIDFoLTZhMSAxIDAgMCAxIC0xIC0xdi0zYTEgMSAwIDAgMCAtMSAtMWgtM2ExIDEgMCAwIDEgLTEgLTF2LTZhMSAxIDAgMCAxIDEgLTF6IiAvPjwvc3ZnPg==';

    public static function or(?string $iconUrl): string
    {
        return $iconUrl ?? self::DATA_URI;
    }
}
