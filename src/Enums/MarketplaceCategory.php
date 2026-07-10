<?php

namespace PelicanMarketplace\PluginMarketplace\Enums;

/**
 * Normalized category taxonomy shown in the marketplace filter UI. Each
 * repository client is responsible for mapping its own category/tag
 * vocabulary onto this list inside its `normalizeCategories()` method.
 */
enum MarketplaceCategory: string
{
    case Admin = 'admin_tools';
    case Chat = 'chat';
    case Economy = 'economy';
    case Fun = 'fun';
    case Games = 'games';
    case Library = 'library';
    case Magic = 'magic';
    case Mechanics = 'mechanics';
    case Minigame = 'minigame';
    case Moderation = 'moderation';
    case Optimization = 'optimization';
    case Protection = 'protection';
    case Roleplay = 'roleplay';
    case Social = 'social';
    case Storage = 'storage';
    case Transportation = 'transportation';
    case Utility = 'utility';
    case WorldGeneration = 'world_generation';
    case WorldManagement = 'world_management';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin Tools',
            self::Chat => 'Chat',
            self::Economy => 'Economy',
            self::Fun => 'Fun',
            self::Games => 'Games',
            self::Library => 'Library',
            self::Magic => 'Magic',
            self::Mechanics => 'Mechanics',
            self::Minigame => 'Minigame',
            self::Moderation => 'Moderation',
            self::Optimization => 'Optimization',
            self::Protection => 'Protection',
            self::Roleplay => 'Roleplay',
            self::Social => 'Social',
            self::Storage => 'Storage',
            self::Transportation => 'Transportation',
            self::Utility => 'Utility',
            self::WorldGeneration => 'World Generation',
            self::WorldManagement => 'World Management',
            self::Other => 'Other',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
