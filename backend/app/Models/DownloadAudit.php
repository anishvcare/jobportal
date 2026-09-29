<?php

namespace App\Models;

use Database\Factories\DownloadAuditFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DownloadAudit extends Model
{
    /** @use HasFactory<DownloadAuditFactory> */
    use HasFactory;

    public const string KIND_DOCUMENT = 'document';

    public const string KIND_RESUME = 'resume';

    public const string KIND_PACK = 'pack';

    public const string KIND_BULK_ZIP = 'bulk_zip';

    protected $fillable = [
        'actor_user_id',
        'candidate_profile_id',
        'kind',
        'bulk_export_id',
        'document_id',
        'ip',
        'user_agent',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function candidateProfile(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class);
    }

    public function bulkExport(): BelongsTo
    {
        return $this->belongsTo(BulkExport::class);
    }
}
