<?php

namespace App\Models;

use App\Enums\EmployerStatus;
use Database\Factories\EmployerProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployerProfile extends Model
{
    /** @use HasFactory<EmployerProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'company_name',
        'contact_person',
        'phone',
        'website',
        'country_id',
        'state_id',
        'district_id',
        'city',
        'logo_disk',
        'logo_path',
        'status',
        'approved_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EmployerStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function jobPosts(): HasMany
    {
        return $this->hasMany(JobPost::class);
    }

    public function isApproved(): bool
    {
        return $this->status === EmployerStatus::Approved;
    }

    public function isPending(): bool
    {
        return $this->status === EmployerStatus::Pending;
    }

    public function isSuspended(): bool
    {
        return $this->status === EmployerStatus::Suspended;
    }
}
