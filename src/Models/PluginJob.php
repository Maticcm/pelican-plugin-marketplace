<?php

namespace PelicanMarketplace\PluginMarketplace\Models;

use App\Models\Server;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use PelicanMarketplace\PluginMarketplace\Enums\InstallJobStatus;
use PelicanMarketplace\PluginMarketplace\Enums\InstallJobType;
use PelicanMarketplace\PluginMarketplace\Enums\MarketplaceRepository;

/**
 * Tracks the live progress of an install/update/uninstall/scan operation
 * so the Filament UI can poll it and render a determinate progress bar
 * and, on completion, the outcome and any changelog/rollback info.
 *
 * @property int $id
 * @property int|null $server_id
 * @property int|null $user_id
 * @property InstallJobType $type
 * @property InstallJobStatus $status
 * @property string|null $plugin_name
 * @property MarketplaceRepository|null $repository
 * @property string|null $project_id
 * @property string|null $message
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property-read Server|null $server
 * @property-read User|null $user
 */
class PluginJob extends Model
{
    use HasFactory;

    protected $table = 'plugin_marketplace_jobs';

    protected $fillable = [
        'server_id',
        'user_id',
        'type',
        'status',
        'plugin_name',
        'repository',
        'project_id',
        'message',
        'meta',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => InstallJobType::class,
            'status' => InstallJobStatus::class,
            'repository' => MarketplaceRepository::class,
            'meta' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function progressPercent(): int
    {
        return $this->status->progressPercent();
    }

    public function markStatus(InstallJobStatus $status, ?string $message = null): void
    {
        $this->status = $status;
        $this->message = $message ?? $this->message;

        if ($status === InstallJobStatus::Downloading && !$this->started_at) {
            $this->started_at = now();
        }

        if ($status->isFinished()) {
            $this->finished_at = now();
        }

        $this->save();
    }
}
