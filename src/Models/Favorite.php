<?php

namespace PelicanMarketplace\PluginMarketplace\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use PelicanMarketplace\PluginMarketplace\Data\MarketplacePluginData;
use PelicanMarketplace\PluginMarketplace\Enums\MarketplaceRepository;

/**
 * @property int $id
 * @property int $user_id
 * @property MarketplaceRepository $repository
 * @property string $project_id
 * @property string $slug
 * @property string $name
 * @property string|null $icon_url
 * @property-read User $user
 */
class Favorite extends Model
{
    use HasFactory;

    protected $table = 'plugin_marketplace_favorites';

    protected $fillable = [
        'user_id',
        'repository',
        'project_id',
        'slug',
        'name',
        'icon_url',
    ];

    protected function casts(): array
    {
        return [
            'repository' => MarketplaceRepository::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function fromPluginData(int $userId, MarketplacePluginData $plugin): array
    {
        return [
            'user_id' => $userId,
            'repository' => $plugin->repository->value,
            'project_id' => $plugin->projectId,
            'slug' => $plugin->slug,
            'name' => $plugin->name,
            'icon_url' => $plugin->iconUrl,
        ];
    }
}
