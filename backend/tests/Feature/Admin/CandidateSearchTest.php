<?php

use App\Enums\DocumentType;
use App\Models\CandidateProfile;
use App\Models\Country;
use App\Models\District;
use App\Models\Document;
use App\Models\EducationLevel;
use App\Models\JobCategory;
use App\Models\Language;
use App\Models\Skill;
use App\Models\State;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake('documents');
});

/**
 * Create a candidate profile with the given attribute overrides.
 *
 * @param  array<string, mixed>  $overrides
 */
function searchCandidate(array $overrides = []): CandidateProfile
{
    return CandidateProfile::factory()->create($overrides);
}

/**
 * Attach a document of a given type to a profile (writes to the fake disk).
 */
function searchDocument(CandidateProfile $profile, DocumentType $type): Document
{
    $bytes = 'binary-'.Str::uuid()->toString();
    $path = "documents/{$profile->id}/{$type->value}/".Str::uuid()->toString().'.bin';
    Storage::disk('documents')->put($path, $bytes);

    return Document::query()->create([
        'candidate_profile_id' => $profile->id,
        'type' => $type,
        'disk' => 'documents',
        'path' => $path,
        'original_name' => $type->value.'.bin',
        'mime' => $type === DocumentType::Photo ? 'image/jpeg' : 'application/pdf',
        'size' => strlen($bytes),
        'page_count' => null,
        'sort_order' => 0,
        'sha256' => hash('sha256', $bytes),
    ]);
}

/** Give a profile all four base required documents (100% for a no-passport candidate). */
function searchCompleteDocuments(CandidateProfile $profile): void
{
    foreach ([DocumentType::Photo, DocumentType::AadhaarFront, DocumentType::AadhaarBack, DocumentType::Sslc] as $type) {
        searchDocument($profile, $type);
    }
}

// ---------------------------------------------------------------------------
// Access control
// ---------------------------------------------------------------------------

it('rejects guests on the candidate search endpoints', function (string $uri) {
    $this->getJson($uri)->assertUnauthorized();
})->with([
    '/api/admin/candidates',
    '/api/admin/candidates/1',
]);

it('forbids non-admin roles from candidate search', function (string $state) {
    $profile = searchCandidate();
    $user = User::factory()->{$state}()->create();

    actingAsUser($user)->getJson('/api/admin/candidates')->assertForbidden();
    actingAsUser($user)->getJson("/api/admin/candidates/{$profile->id}")->assertForbidden();
})->with(['candidate', 'employer']);

it('lets an admin list and view candidates', function () {
    $profile = searchCandidate();
    $admin = User::factory()->admin()->create();

    actingAsUser($admin)->getJson('/api/admin/candidates')->assertOk();
    actingAsUser($admin)->getJson("/api/admin/candidates/{$profile->id}")->assertOk();
});

// ---------------------------------------------------------------------------
// Filters
// ---------------------------------------------------------------------------

it('filters by trade (preferred category)', function () {
    $group = JobCategory::create(['name' => 'Skilled', 'slug' => 'skilled']);
    $welder = JobCategory::create(['name' => 'Welder', 'slug' => 'welder', 'parent_id' => $group->id]);
    $electrician = JobCategory::create(['name' => 'Electrician', 'slug' => 'electrician', 'parent_id' => $group->id]);

    $a = searchCandidate();
    $a->preferredCategories()->attach($welder);
    $b = searchCandidate();
    $b->preferredCategories()->attach($electrician);

    $admin = User::factory()->admin()->create();

    $response = actingAsUser($admin)->getJson("/api/admin/candidates?job_category_id={$welder->id}")->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.id'))->toBe($a->id);
});

it('filters by education level rank (>=)', function () {
    $low = EducationLevel::create(['name' => 'SSLC', 'slug' => 'sslc', 'rank' => 1]);
    $high = EducationLevel::create(['name' => 'Degree', 'slug' => 'degree', 'rank' => 5]);

    $graduate = searchCandidate();
    $graduate->educations()->create(['education_level_id' => $high->id, 'sort_order' => 0]);

    $schooled = searchCandidate();
    $schooled->educations()->create(['education_level_id' => $low->id, 'sort_order' => 0]);

    $admin = User::factory()->admin()->create();

    // Selecting rank 5 excludes the rank-1 candidate.
    $response = actingAsUser($admin)->getJson("/api/admin/candidates?education_level_id={$high->id}")->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.id'))->toBe($graduate->id);
});

it('filters by age range', function () {
    $young = searchCandidate(['dob' => Carbon::today()->subYears(22)->format('Y-m-d')]);
    $mid = searchCandidate(['dob' => Carbon::today()->subYears(35)->format('Y-m-d')]);
    $old = searchCandidate(['dob' => Carbon::today()->subYears(55)->format('Y-m-d')]);

    $admin = User::factory()->admin()->create();

    $response = actingAsUser($admin)->getJson('/api/admin/candidates?age_min=30&age_max=40')->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())->toBe([$mid->id]);
    expect(collect($response->json('data'))->pluck('id')->all())
        ->not->toContain($young->id)
        ->not->toContain($old->id);
});

it('filters by gender', function () {
    $male = searchCandidate(['gender' => 'male']);
    $female = searchCandidate(['gender' => 'female']);

    $admin = User::factory()->admin()->create();

    $response = actingAsUser($admin)->getJson('/api/admin/candidates?gender=female')->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())->toBe([$female->id]);
});

it('filters by district', function () {
    $country = Country::create(['name' => 'India', 'iso2' => 'IN']);
    $state = State::create(['country_id' => $country->id, 'name' => 'Kerala']);
    $d1 = District::create(['state_id' => $state->id, 'name' => 'Kochi']);
    $d2 = District::create(['state_id' => $state->id, 'name' => 'Thrissur']);

    $inKochi = searchCandidate(['state_id' => $state->id, 'district_id' => $d1->id]);
    searchCandidate(['state_id' => $state->id, 'district_id' => $d2->id]);

    $admin = User::factory()->admin()->create();

    $response = actingAsUser($admin)->getJson("/api/admin/candidates?district_id={$d1->id}")->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())->toBe([$inKochi->id]);
});

it('filters by valid passport, excluding expired and passport-less candidates', function () {
    $valid = CandidateProfile::factory()->withPassport()->create();
    $expired = searchCandidate([
        'has_passport' => true,
        'passport_number' => 'B9999999',
        'passport_expiry' => Carbon::today()->subDay()->format('Y-m-d'),
    ]);
    $none = searchCandidate(['has_passport' => false]);

    $admin = User::factory()->admin()->create();

    $response = actingAsUser($admin)->getJson('/api/admin/candidates?passport=valid')->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($valid->id)
        ->not->toContain($expired->id)
        ->not->toContain($none->id);
});

it('filters by languages', function () {
    $english = Language::create(['name' => 'English', 'code' => 'en']);
    $hindi = Language::create(['name' => 'Hindi', 'code' => 'hi']);

    $speaksEnglish = searchCandidate();
    $speaksEnglish->languages()->attach($english, ['proficiency' => 'fluent']);
    $speaksHindi = searchCandidate();
    $speaksHindi->languages()->attach($hindi, ['proficiency' => 'native']);

    $admin = User::factory()->admin()->create();

    $response = actingAsUser($admin)->getJson("/api/admin/candidates?language_ids[]={$english->id}")->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())->toBe([$speaksEnglish->id]);
});

it('filters by skills with AND semantics', function () {
    $welding = Skill::create(['name' => 'Welding', 'slug' => 'welding']);
    $fitting = Skill::create(['name' => 'Pipe Fitting', 'slug' => 'pipe-fitting']);

    $both = searchCandidate();
    $both->skills()->attach([$welding->id, $fitting->id]);
    $onlyOne = searchCandidate();
    $onlyOne->skills()->attach($welding->id);

    $admin = User::factory()->admin()->create();

    $response = actingAsUser($admin)
        ->getJson("/api/admin/candidates?skill_ids[]={$welding->id}&skill_ids[]={$fitting->id}")
        ->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())->toBe([$both->id]);
});

it('matches a keyword against the candidate name', function () {
    $match = searchCandidate(['full_name' => 'Ramesh Kumar']);
    searchCandidate(['full_name' => 'Suresh Nair']);

    $admin = User::factory()->admin()->create();

    $response = actingAsUser($admin)->getJson('/api/admin/candidates?keyword=Ramesh')->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())->toBe([$match->id]);
});

it('matches a keyword against a skill name (SQLite LIKE fallback path)', function () {
    $welding = Skill::create(['name' => 'Underwater Welding', 'slug' => 'underwater-welding']);

    $skilled = searchCandidate(['full_name' => 'Anil Joseph']);
    $skilled->skills()->attach($welding);
    searchCandidate(['full_name' => 'Manoj Pillai']);

    $admin = User::factory()->admin()->create();

    // The keyword matches only a skill name, not any candidate name.
    $response = actingAsUser($admin)->getJson('/api/admin/candidates?keyword=Underwater')->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())->toBe([$skilled->id]);
});

it('filters by experience years', function () {
    $senior = searchCandidate();
    $senior->experiences()->create([
        'job_title' => 'Welder',
        'start_date' => Carbon::today()->subYears(10)->format('Y-m-d'),
        'end_date' => Carbon::today()->subYears(2)->format('Y-m-d'),
        'is_current' => false,
        'sort_order' => 0,
    ]);

    $junior = searchCandidate();
    $junior->experiences()->create([
        'job_title' => 'Helper',
        'start_date' => Carbon::today()->subYear()->format('Y-m-d'),
        'end_date' => null,
        'is_current' => true,
        'sort_order' => 0,
    ]);

    $admin = User::factory()->admin()->create();

    $response = actingAsUser($admin)->getJson('/api/admin/candidates?experience_min=5')->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())->toBe([$senior->id]);
});

it('filters by minimum completeness', function () {
    $complete = searchCandidate(['has_passport' => false]);
    searchCompleteDocuments($complete);

    $incomplete = searchCandidate(['has_passport' => false]);
    searchDocument($incomplete, DocumentType::Photo);

    $admin = User::factory()->admin()->create();

    $response = actingAsUser($admin)->getJson('/api/admin/candidates?completeness_min=100')->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())->toBe([$complete->id]);
    expect($response->json('data.0.completeness'))->toBe(100);
});

// ---------------------------------------------------------------------------
// Pagination / sorting / trade counts
// ---------------------------------------------------------------------------

it('paginates results with meta', function () {
    CandidateProfile::factory()->count(5)->create();

    $admin = User::factory()->admin()->create();

    $response = actingAsUser($admin)->getJson('/api/admin/candidates?per_page=2')->assertOk();

    expect($response->json('data'))->toHaveCount(2)
        ->and($response->json('meta.per_page'))->toBe(2)
        ->and($response->json('meta.total'))->toBe(5)
        ->and($response->json('meta.last_page'))->toBe(3);
});

it('sorts by name ascending and descending', function () {
    searchCandidate(['full_name' => 'Charlie']);
    searchCandidate(['full_name' => 'Alice']);
    searchCandidate(['full_name' => 'Bob']);

    $admin = User::factory()->admin()->create();

    $asc = actingAsUser($admin)->getJson('/api/admin/candidates?sort=name&direction=asc')->assertOk();
    expect(collect($asc->json('data'))->pluck('full_name')->all())->toBe(['Alice', 'Bob', 'Charlie']);

    $desc = actingAsUser($admin)->getJson('/api/admin/candidates?sort=name&direction=desc')->assertOk();
    expect(collect($desc->json('data'))->pluck('full_name')->all())->toBe(['Charlie', 'Bob', 'Alice']);
});

it('sorts by age', function () {
    $young = searchCandidate(['full_name' => 'Young', 'dob' => Carbon::today()->subYears(20)->format('Y-m-d')]);
    $old = searchCandidate(['full_name' => 'Old', 'dob' => Carbon::today()->subYears(60)->format('Y-m-d')]);

    $admin = User::factory()->admin()->create();

    // age ascending => youngest first.
    $asc = actingAsUser($admin)->getJson('/api/admin/candidates?sort=age&direction=asc')->assertOk();
    expect(collect($asc->json('data'))->pluck('id')->all())->toBe([$young->id, $old->id]);
});

it('sorts by created_at', function () {
    $first = searchCandidate(['created_at' => Carbon::now()->subDays(3)]);
    $second = searchCandidate(['created_at' => Carbon::now()->subDay()]);

    $admin = User::factory()->admin()->create();

    $asc = actingAsUser($admin)->getJson('/api/admin/candidates?sort=created_at&direction=asc')->assertOk();
    expect(collect($asc->json('data'))->pluck('id')->all())->toBe([$first->id, $second->id]);
});

it('returns per-trade counts over the whole filtered set', function () {
    $group = JobCategory::create(['name' => 'Skilled', 'slug' => 'skilled']);
    $welder = JobCategory::create(['name' => 'Welder', 'slug' => 'welder', 'parent_id' => $group->id]);
    $electrician = JobCategory::create(['name' => 'Electrician', 'slug' => 'electrician', 'parent_id' => $group->id]);

    foreach (range(1, 3) as $i) {
        $c = searchCandidate(['gender' => 'male']);
        $c->preferredCategories()->attach($welder);
    }
    $e = searchCandidate(['gender' => 'male']);
    $e->preferredCategories()->attach($electrician);

    // A female welder that the gender filter should exclude from the counts.
    $f = searchCandidate(['gender' => 'female']);
    $f->preferredCategories()->attach($welder);

    $admin = User::factory()->admin()->create();

    // per_page=1 proves counts span the whole filtered set, not just the page.
    $response = actingAsUser($admin)
        ->getJson('/api/admin/candidates?gender=male&per_page=1')
        ->assertOk();

    $counts = collect($response->json('meta.trade_counts'))->keyBy('trade_id');

    expect($counts[$welder->id]['count'])->toBe(3)
        ->and($counts[$electrician->id]['count'])->toBe(1);
});

it('issues a bounded number of queries regardless of page size (no photo N+1)', function () {
    // Seed several candidates, each with a photo document, so a per-row photo
    // exists() would show up as one extra query per candidate.
    foreach (range(1, 5) as $i) {
        $c = searchCandidate();
        searchDocument($c, DocumentType::Photo);
    }

    $admin = User::factory()->admin()->create();

    DB::enableQueryLog();

    actingAsUser($admin)->getJson('/api/admin/candidates?per_page=20')->assertOk();

    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    // The list is a fixed handful of queries (base list + eager loads +
    // pagination count + trade counts), NOT one-per-candidate. A regression to
    // the per-row photo exists() would push this well past the ceiling.
    expect($queryCount)->toBeLessThanOrEqual(12);
});

// ---------------------------------------------------------------------------
// Detail
// ---------------------------------------------------------------------------

it('returns full detail with the decrypted passport number and document metadata', function () {
    $profile = CandidateProfile::factory()->withPassport()->create();
    searchDocument($profile, DocumentType::Photo);
    $cv = searchDocument($profile, DocumentType::Cv);

    $admin = User::factory()->admin()->create();

    $response = actingAsUser($admin)->getJson("/api/admin/candidates/{$profile->id}")->assertOk();

    $response->assertJsonPath('data.passport_number', 'A1234567');

    $documents = collect($response->json('data.documents'));
    expect($documents)->not->toBeEmpty();

    $cvRow = $documents->firstWhere('id', $cv->id);
    expect($cvRow['download_url'])->toBe("/api/admin/candidates/{$profile->id}/documents/{$cv->id}/download");

    // No storage disk path is ever leaked.
    $raw = $response->getContent();
    expect($raw)->not->toContain('documents/'.$profile->id.'/');
    expect($documents->pluck('download_url')->implode(' '))->not->toContain($cv->path);
});

it('streams a candidate photo for admins and 404s when absent', function () {
    $withPhoto = searchCandidate();
    searchDocument($withPhoto, DocumentType::Photo);
    $withoutPhoto = searchCandidate();

    $admin = User::factory()->admin()->create();

    actingAsUser($admin)->get("/api/admin/candidates/{$withPhoto->id}/photo")->assertOk();
    actingAsUser($admin)->get("/api/admin/candidates/{$withoutPhoto->id}/photo")->assertNotFound();
});
