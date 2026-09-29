<?php

namespace App\Services\Admin;

use App\Enums\DocumentType;
use App\Models\CandidateProfile;
use App\Models\EducationLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Builds the filtered candidate-search query used by the admin endpoints.
 *
 * The service returns an Eloquent Builder (never executes it) so the caller can
 * add sorting/pagination and derive per-trade aggregates from the same filtered
 * base. Two derived, driver-portable expressions are exposed:
 *
 *   - experience_years: total career length in whole years, computed from
 *     candidate_experiences (open-ended rows use "today"). Date math is guarded
 *     by driver (julianday on SQLite, DATEDIFF on MySQL) because neither is
 *     portable across both engines.
 *   - completeness_pct: profile document completeness as a 0-100 integer, using
 *     the SAME rules as CompletenessService (required base types + a
 *     conditional passport requirement) but expressed as SQL subqueries so a
 *     paginated list never triggers a per-row service call (no N+1).
 *
 * Keyword search matches full_name OR a related skill name. It uses MySQL
 * FULLTEXT when the connection is MySQL and a portable LIKE fallback on SQLite
 * (which has no FULLTEXT), branching explicitly on the driver.
 */
class CandidateSearch
{
    /**
     * Document types that always count towards completeness (mirrors
     * CompletenessService::requiredForCompleteness()).
     */
    private const BASE_REQUIRED = [
        DocumentType::Photo,
        DocumentType::AadhaarFront,
        DocumentType::AadhaarBack,
        DocumentType::Sslc,
    ];

    /**
     * Relations the summary/detail resources read; eager-loaded so results
     * respect Model::shouldBeStrict() (no lazy loading, no N+1).
     *
     * @var list<string>
     */
    private const SUMMARY_RELATIONS = [
        'user',
        'state',
        'district',
        'preferredCategories',
    ];

    /**
     * @param  array<string, mixed>  $filters  Validated filters from SearchCandidatesRequest.
     */
    public function query(array $filters): Builder
    {
        $query = CandidateProfile::query()
            ->with(self::SUMMARY_RELATIONS)
            ->select('candidate_profiles.*')
            ->addSelect([
                'experience_years' => $this->experienceYearsSub(),
                'completeness_pct' => $this->completenessSub(),
            ])
            // Surface photo presence as a single boolean column on the list
            // query so the summary resource never runs a per-row exists()
            // (avoids an N+1 across the page). withExists compiles to a
            // portable correlated `exists (...)` on both SQLite and MySQL.
            ->withExists(['documents as has_photo' => fn (Builder $q) => $q->where('type', DocumentType::Photo->value)]);

        $this->applyKeyword($query, $filters['keyword'] ?? null);
        $this->applyEducationLevel($query, $filters['education_level_id'] ?? null);
        $this->applySkills($query, $filters['skill_ids'] ?? null);
        $this->applyTrade($query, $filters['job_category_id'] ?? null);
        $this->applyAge($query, $filters['age_min'] ?? null, $filters['age_max'] ?? null);
        $this->applyGender($query, $filters['gender'] ?? null);
        $this->applyLocation($query, $filters['state_id'] ?? null, $filters['district_id'] ?? null);
        $this->applyLanguages($query, $filters['language_ids'] ?? null);
        $this->applyPassport($query, $filters['passport'] ?? null);
        $this->applyExperience($query, $filters['experience_min'] ?? null, $filters['experience_max'] ?? null);
        $this->applyCompleteness($query, $filters['completeness_min'] ?? null);

        // TODO(M5): applied-job filter once the applications table exists.

        return $query;
    }

    /**
     * Keyword: match the candidate name OR a related skill name.
     */
    private function applyKeyword(Builder $query, ?string $keyword): void
    {
        $keyword = is_string($keyword) ? trim($keyword) : '';

        if ($keyword === '') {
            return;
        }

        if (DB::connection()->getDriverName() === 'mysql') {
            // MySQL: use the FULLTEXT index on full_name, OR-ed with a skill match.
            $query->where(function (Builder $q) use ($keyword) {
                $q->whereRaw('MATCH(full_name) AGAINST (? IN BOOLEAN MODE)', [$keyword.'*'])
                    ->orWhereHas('skills', fn (Builder $s) => $s->where('name', 'like', "%{$keyword}%"));
            });

            return;
        }

        // SQLite (and any non-MySQL driver): portable LIKE fallback.
        $query->where(function (Builder $q) use ($keyword) {
            $q->where('full_name', 'like', "%{$keyword}%")
                ->orWhereHas('skills', fn (Builder $s) => $s->where('name', 'like', "%{$keyword}%"));
        });
    }

    /**
     * Education level: candidates who hold a qualification at or above the
     * selected level's rank.
     */
    private function applyEducationLevel(Builder $query, ?int $levelId): void
    {
        if ($levelId === null) {
            return;
        }

        $rank = EducationLevel::whereKey($levelId)->value('rank');

        if ($rank === null) {
            return;
        }

        $query->whereHas('educations.educationLevel', fn (Builder $q) => $q->where('rank', '>=', $rank));
    }

    /**
     * Skills use AND semantics: the candidate must have ALL selected skills.
     *
     * @param  array<int, int>|null  $skillIds
     */
    private function applySkills(Builder $query, ?array $skillIds): void
    {
        if (empty($skillIds)) {
            return;
        }

        foreach (array_unique($skillIds) as $skillId) {
            $query->whereHas('skills', fn (Builder $q) => $q->whereKey($skillId));
        }
    }

    private function applyTrade(Builder $query, ?int $tradeId): void
    {
        if ($tradeId === null) {
            return;
        }

        $query->whereHas('preferredCategories', fn (Builder $q) => $q->whereKey($tradeId));
    }

    /**
     * Age range converted to portable dob bounds in PHP (no DB date math).
     */
    private function applyAge(Builder $query, ?int $ageMin, ?int $ageMax): void
    {
        if ($ageMin !== null) {
            // Age >= ageMin  =>  born on or before (today - ageMin years).
            $query->whereDate('dob', '<=', Carbon::today()->subYears($ageMin));
        }

        if ($ageMax !== null) {
            // Age <= ageMax  =>  born after (today - (ageMax + 1) years).
            $query->whereDate('dob', '>', Carbon::today()->subYears($ageMax + 1));
        }
    }

    private function applyGender(Builder $query, ?string $gender): void
    {
        if ($gender !== null) {
            $query->where('gender', $gender);
        }
    }

    private function applyLocation(Builder $query, ?int $stateId, ?int $districtId): void
    {
        if ($stateId !== null) {
            $query->where('state_id', $stateId);
        }

        if ($districtId !== null) {
            $query->where('district_id', $districtId);
        }
    }

    /**
     * @param  array<int, int>|null  $languageIds
     */
    private function applyLanguages(Builder $query, ?array $languageIds): void
    {
        if (empty($languageIds)) {
            return;
        }

        $query->whereHas('languages', fn (Builder $q) => $q->whereIn('languages.id', array_unique($languageIds)));
    }

    private function applyPassport(Builder $query, ?string $passport): void
    {
        if ($passport === 'has') {
            $query->where('has_passport', true);

            return;
        }

        if ($passport === 'valid') {
            $query->where('has_passport', true)
                ->whereNotNull('passport_expiry')
                ->whereDate('passport_expiry', '>=', Carbon::today());
        }
        // 'any'/null: no filter.
    }

    /**
     * Experience years min/max, filtered on the derived experience_years
     * subquery. HAVING is unavailable without a GROUP BY, so we re-inline the
     * subquery as a whereRaw comparison to keep the filter portable.
     */
    private function applyExperience(Builder $query, ?int $min, ?int $max): void
    {
        if ($min === null && $max === null) {
            return;
        }

        [$sql, $bindings] = $this->experienceYearsExpression();

        if ($min !== null) {
            $query->whereRaw("({$sql}) >= ?", [...$bindings, $min]);
        }

        if ($max !== null) {
            $query->whereRaw("({$sql}) <= ?", [...$bindings, $max]);
        }
    }

    private function applyCompleteness(Builder $query, ?int $min): void
    {
        if ($min === null) {
            return;
        }

        [$sql, $bindings] = $this->completenessExpression();

        $query->whereRaw("({$sql}) >= ?", [...$bindings, $min]);
    }

    /**
     * Correlated subquery selecting a candidate's total experience in whole
     * years. Date math is driver-guarded (portable across SQLite and MySQL).
     */
    private function experienceYearsSub(): \Illuminate\Database\Query\Builder|\Closure
    {
        [$sql, $bindings] = $this->experienceYearsExpression();

        return DB::query()->selectRaw("({$sql})", $bindings);
    }

    /**
     * @return array{0:string,1:array<int, mixed>}
     */
    private function experienceYearsExpression(): array
    {
        $today = Carbon::today()->toDateString();

        if (DB::connection()->getDriverName() === 'mysql') {
            // SUM of day-spans across all experience rows, converted to years.
            $sql = 'select coalesce(floor(sum(datediff('
                .'coalesce(end_date, ?), start_date)) / 365.25), 0) '
                .'from candidate_experiences '
                .'where candidate_experiences.candidate_profile_id = candidate_profiles.id '
                .'and start_date is not null';

            return [$sql, [$today]];
        }

        // SQLite: julianday() gives day counts; same year conversion.
        $sql = 'select coalesce(cast(sum(julianday(coalesce(end_date, ?)) - julianday(start_date)) / 365.25 as integer), 0) '
            .'from candidate_experiences '
            .'where candidate_experiences.candidate_profile_id = candidate_profiles.id '
            .'and start_date is not null';

        return [$sql, [$today]];
    }

    /**
     * Correlated subquery selecting a candidate's completeness percentage,
     * mirroring CompletenessService: base required types plus a conditional
     * passport requirement, expressed entirely in SQL to avoid N+1.
     */
    private function completenessSub(): \Illuminate\Database\Query\Builder
    {
        [$sql, $bindings] = $this->completenessExpression();

        return DB::query()->selectRaw("({$sql})", $bindings);
    }

    /**
     * @return array{0:string,1:array<int, mixed>}
     */
    private function completenessExpression(): array
    {
        $baseTypes = array_map(fn (DocumentType $type) => $type->value, self::BASE_REQUIRED);
        $placeholders = implode(',', array_fill(0, count($baseTypes), '?'));

        // present = distinct required doc types the candidate has uploaded.
        // required = base types + 1 when the candidate declares a passport.
        // A profile-PDF document also satisfies the pack, but CompletenessService
        // only bumps `pack_ready`, not the percentage, so we mirror the percentage
        // math exactly here.
        $presentBase = 'select count(distinct d.type) from documents d '
            ."where d.candidate_profile_id = candidate_profiles.id and d.type in ({$placeholders})";

        $passportPresent = 'select count(*) from documents dp '
            .'where dp.candidate_profile_id = candidate_profiles.id and dp.type = ?';

        $baseCount = count($baseTypes);

        // required total = baseCount + (has_passport ? 1 : 0)
        // present total  = presentBase + (has_passport AND passport doc exists ? 1 : 0)
        $sql = 'cast(round('
            .'(('.$presentBase.') + (case when candidate_profiles.has_passport = 1 and ('.$passportPresent.') > 0 then 1 else 0 end)) '
            .'* 100.0 / '
            .'('.$baseCount.' + (case when candidate_profiles.has_passport = 1 then 1 else 0 end))'
            .') as integer)';

        $bindings = [...$baseTypes, DocumentType::Passport->value];

        return [$sql, $bindings];
    }

    /**
     * The base column expression used to sort by experience years.
     *
     * @return array{0:string,1:array<int, mixed>}
     */
    public function experienceSortExpression(): array
    {
        return $this->experienceYearsExpression();
    }

    /**
     * The base column expression used to sort by completeness.
     *
     * @return array{0:string,1:array<int, mixed>}
     */
    public function completenessSortExpression(): array
    {
        return $this->completenessExpression();
    }
}
