<?php

namespace App\Models;

use App\Observers\CandidateProfileObserver;
use Database\Factories\CandidateProfileFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[ObservedBy(CandidateProfileObserver::class)]
class CandidateProfile extends Model
{
    /** @use HasFactory<CandidateProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'full_name',
        'dob',
        'gender',
        'phone',
        'whatsapp',
        'address',
        'city',
        'pincode',
        'country_id',
        'state_id',
        'district_id',
        'passport_number',
        'has_passport',
        'passport_expiry',
        'summary',
        'wizard_step',
        'consent_at',
        'consent_version',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'passport_expiry' => 'date',
            'has_passport' => 'boolean',
            'consent_at' => 'datetime',
            'wizard_step' => 'integer',
            'passport_number' => 'encrypted',
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

    public function educations(): HasMany
    {
        return $this->hasMany(CandidateEducation::class);
    }

    public function experiences(): HasMany
    {
        return $this->hasMany(CandidateExperience::class);
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'candidate_skill')->withTimestamps();
    }

    public function languages(): BelongsToMany
    {
        return $this->belongsToMany(Language::class, 'candidate_language')
            ->withPivot('proficiency')
            ->withTimestamps();
    }

    public function preferredCategories(): BelongsToMany
    {
        return $this->belongsToMany(JobCategory::class, 'candidate_preferred_category')->withTimestamps();
    }

    public function preferredCountries(): BelongsToMany
    {
        return $this->belongsToMany(Country::class, 'candidate_preferred_country')->withTimestamps();
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function candidatePack(): HasOne
    {
        return $this->hasOne(CandidatePack::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}
