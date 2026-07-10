<?php

namespace PelicanMarketplace\PluginMarketplace\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use PelicanMarketplace\PluginMarketplace\Enums\MarketplaceRepository;

/**
 * @property int $id
 * @property int $user_id
 * @property MarketplaceRepository $repository
 * @property string $project_id
 * @property string $slug
 * @property string $name
 * @property string|null $icon_url
 * @property Carbon $viewed_at
 * @property-read User $user
 */
class RecentlyViewed extends Model
{
    use HasFactory;

    protected $table = 'plugin_marketplace_recently_viewed';

    protected $fillable = [
        'user_id',
        'repository',
        'project_id',
        'slug',
        'name',
        'icon_url',
        'viewed_at',
    ];

    protected function casts(): array
    {
        return [
            'repository' => MarketplaceRepository::class,
            'viewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
