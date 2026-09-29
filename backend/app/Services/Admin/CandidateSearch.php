<?php

namespace App\Services\Admin;

use App\Enums\DocumentType;
use App\Models\CandidateProfile;
use App\Models\EducationLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
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
            // The derived columns are real query-builder sub-selects, so
            // Laravel compiles them as correlated subqueries and registers
            // their ? bindings on the outer query in the correct order for
            // BOTH MySQL and SQLite (never inlining bare literals).
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

        if ($min !== null) {
            // Passing a query-builder sub-select to where() lets Laravel manage
            // both the placeholder and its bindings, so the correlated
            // subquery's ? bindings are never inlined as bare literals.
            $query->where($this->experienceYearsSub(), '>=', $min);
        }

        if ($max !== null) {
            $query->where($this->experienceYearsSub(), '<=', $max);
        }
    }

    private function applyCompleteness(Builder $query, ?int $min): void
    {
        if ($min === null) {
            return;
        }

        $query->where($this->completenessSub(), '>=', $min);
    }

    /**
     * Correlated subquery selecting a candidate's total experience in whole
     * years. Date math is driver-guarded (portable across SQLite and MySQL).
     *
     * Returned as a real query-builder sub-select so Laravel registers the
     * date ? binding on whichever outer clause consumes it (select, where or
     * order by). Each call returns a fresh builder to avoid binding reuse.
     */
    private function experienceYearsSub(): QueryBuilder
    {
        $today = Carbon::today()->toDateString();

        $builder = DB::query()
            ->from('candidate_experiences')
            ->whereColumn('candidate_experiences.candidate_profile_id', 'candidate_profiles.id')
            ->whereNotNull('start_date');

        if (DB::connection()->getDriverName() === 'mysql') {
            // SUM of day-spans across all experience rows, converted to years.
            return $builder->selectRaw(
                'coalesce(floor(sum(datediff(coalesce(end_date, ?), start_date)) / 365.25), 0)',
                [$today]
            );
        }

        // SQLite: julianday() gives day counts; same year conversion.
        return $builder->selectRaw(
            'coalesce(cast(sum(julianday(coalesce(end_date, ?)) - julianday(start_date)) / 365.25 as integer), 0)',
            [$today]
        );
    }

    /**
     * Correlated subquery selecting a candidate's completeness percentage,
     * mirroring CompletenessService: base required types plus a conditional
     * passport requirement, expressed entirely in SQL to avoid N+1.
     *
     * Built from real query-builder sub-selects (present-count and passport
     * exists) so every value binds through Laravel and compiles to portable,
     * quoted parameters on both MySQL and SQLite. A fresh builder is returned
     * each call to keep bindings isolated per consuming clause.
     */
    private function completenessSub(): QueryBuilder
    {
        $baseTypes = array_map(fn (DocumentType $type) => $type->value, self::BASE_REQUIRED);
        $baseCount = count($baseTypes);
        $passportType = DocumentType::Passport->value;

        // present = distinct required doc types the candidate has uploaded.
        $presentBase = DB::query()
            ->from('documents as d')
            ->whereColumn('d.candidate_profile_id', 'candidate_profiles.id')
            ->whereIn('d.type', $baseTypes)
            ->selectRaw('count(distinct d.type)');

        // whether the declared passport document is present.
        $passportPresent = DB::query()
            ->from('documents as dp')
            ->whereColumn('dp.candidate_profile_id', 'candidate_profiles.id')
            ->where('dp.type', $passportType)
            ->selectRaw('count(*)');

        // required total = baseCount + (has_passport ? 1 : 0)
        // present total  = presentBase + (has_passport AND passport doc exists ? 1 : 0)
        // A profile-PDF document also satisfies the pack, but CompletenessService
        // only bumps `pack_ready`, not the percentage, so we mirror the
        // percentage math exactly here.
        return DB::query()->selectRaw(
            'cast(round('
            .'(('.$this->wrapSub($presentBase).') + (case when candidate_profiles.has_passport = 1 and ('.$this->wrapSub($passportPresent).') > 0 then 1 else 0 end)) '
            .'* 100.0 / '
            .'('.$baseCount.' + (case when candidate_profiles.has_passport = 1 then 1 else 0 end))'
            .') as integer)',
            [...$presentBase->getBindings(), ...$passportPresent->getBindings()]
        );
    }

    /**
     * Inline a sub-builder's SQL as a parenthesised scalar subquery. The
     * caller is responsible for appending the sub-builder's bindings in the
     * same left-to-right order the placeholders appear.
     */
    private function wrapSub(QueryBuilder $sub): string
    {
        return '('.$sub->toSql().')';
    }

    /**
     * The base sub-select used to sort by experience years.
     */
    public function experienceSortExpression(): QueryBuilder
    {
        return $this->experienceYearsSub();
    }

    /**
     * The base sub-select used to sort by completeness.
     */
    public function completenessSortExpression(): QueryBuilder
    {
        return $this->completenessSub();
    }
}
