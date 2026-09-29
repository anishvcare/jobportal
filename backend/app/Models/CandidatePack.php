<?php

namespace App\Models;

use Database\Factories\CandidatePackFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidatePack extends Model
{
    /** @use HasFactory<CandidatePackFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_BUILDING = 'building';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    public const STATUS_STALE = 'stale';

    protected $fillable = [
        'candidate_profile_id',
        'disk',
        'path',
        'fingerprint',
        'status',
        'generated_at',
        'error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
        ];
    }

    public function candidateProfile(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class);
    }
}
