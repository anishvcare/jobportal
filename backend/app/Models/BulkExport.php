<?php

namespace App\Models;

use Database\Factories\BulkExportFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BulkExport extends Model
{
    /** @use HasFactory<BulkExportFactory> */
    use HasFactory;

    public const string STATUS_QUEUED = 'queued';

    public const string STATUS_PROCESSING = 'processing';

    public const string STATUS_READY = 'ready';

    public const string STATUS_FAILED = 'failed';

    /**
     * `progress` is a 0-100 percent value (not a candidate count): the build
     * job stores round(processed / total * 100).
     *
     * @var list<string>
     */
    protected $fillable = [
        'requested_by',
        'candidate_ids',
        'status',
        'progress',
        'disk',
        'path',
        'error',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'candidate_ids' => 'array',
            'expires_at' => 'datetime',
            'progress' => 'integer',
        ];
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function downloadAudits(): HasMany
    {
        return $this->hasMany(DownloadAudit::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * @param  Builder<BulkExport>  $query
     * @return Builder<BulkExport>
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<', now());
    }
}
