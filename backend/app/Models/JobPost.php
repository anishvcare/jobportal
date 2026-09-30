<?php

namespace App\Models;

use Database\Factories\JobPostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class JobPost extends Model
{
    /** @use HasFactory<JobPostFactory> */
    use HasFactory;

    protected $fillable = [
        'employer_profile_id',
        'title',
        'slug',
        'description',
        'job_category_id',
        'country_id',
        'state_id',
        'district_id',
        'city',
        'education_level_id',
        'experience_min',
        'experience_max',
        'vacancies',
        'salary_min',
        'salary_max',
        'salary_currency',
        'deadline',
        'published_at',
        'is_hidden',
        'closed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'deadline' => 'date',
            'published_at' => 'datetime',
            'closed_at' => 'datetime',
            'is_hidden' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function employerProfile(): BelongsTo
    {
        return $this->belongsTo(EmployerProfile::class);
    }

    public function jobCategory(): BelongsTo
    {
        return $this->belongsTo(JobCategory::class);
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

    public function educationLevel(): BelongsTo
    {
        return $this->belongsTo(EducationLevel::class);
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'job_post_skill')->withTimestamps();
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /**
     * A job visible on the public board: published, not hidden, not closed,
     * and either open-ended or with a deadline that has not passed.
     * Uses a bound date value so it stays portable across SQLite and MySQL.
     */
    public function scopeLive(Builder $query): void
    {
        $today = Carbon::today()->toDateString();

        $query->whereNotNull('published_at')
            ->where('is_hidden', false)
            ->whereNull('closed_at')
            ->where(function (Builder $q) use ($today) {
                $q->whereNull('deadline')
                    ->orWhere('deadline', '>=', $today);
            });
    }

    public function isLive(): bool
    {
        if ($this->published_at === null || $this->is_hidden || $this->closed_at !== null) {
            return false;
        }

        return $this->deadline === null || $this->deadline->toDateString() >= Carbon::today()->toDateString();
    }
}
