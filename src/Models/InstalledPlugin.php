<?php

namespace PelicanMarketplace\PluginMarketplace\Models;

use App\Models\Server;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use PelicanMarketplace\PluginMarketplace\Enums\MarketplaceRepository;

/**
 * @property int $id
 * @property int $server_id
 * @property string $file_name
 * @property string $name
 * @property string|null $version
 * @property string[]|null $authors
 * @property string|null $description
 * @property string|null $main_class
 * @property string|null $api_version
 * @property string[]|null $depend
 * @property string[]|null $softdepend
 * @property int|null $size
 * @property bool $enabled
 * @property MarketplaceRepository|null $repository
 * @property string|null $project_id
 * @property string|null $version_id
 * @property string|null $checksum
 * @property string|null $latest_version
 * @property bool $update_available
 * @property Carbon|null $installed_at
 * @property Carbon|null $last_scanned_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Server $server
 */
class InstalledPlugin extends Model
{
    use HasFactory;

    protected $table = 'plugin_marketplace_installed_plugins';

    protected $fillable = [
        'server_id',
        'file_name',
        'name',
        'version',
        'authors',
        'description',
        'main_class',
        'api_version',
        'depend',
        'softdepend',
        'size',
        'enabled',
        'repository',
        'project_id',
        'version_id',
        'checksum',
        'latest_version',
        'update_available',
        'installed_at',
        'last_scanned_at',
    ];

    protected function casts(): array
    {
        return [
            'authors' => 'array',
            'depend' => 'array',
            'softdepend' => 'array',
            'size' => 'integer',
            'enabled' => 'boolean',
            'repository' => MarketplaceRepository::class,
            'update_available' => 'boolean',
            'installed_at' => 'datetime',
            'last_scanned_at' => 'datetime',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function isFromMarketplace(): bool
    {
        return $this->repository !== null && $this->project_id !== null;
    }

    public function authorsLabel(): string
    {
        if (blank($this->authors)) {
            return 'Unknown';
        }

        return implode(', ', $this->authors);
    }
}
