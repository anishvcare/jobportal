<?php

use App\Models\Country;
use App\Models\District;
use App\Models\EmployerProfile;
use App\Models\JobCategory;
use App\Models\JobPost;
use App\Models\State;

/**
 * Create a live job with the given attribute overrides. A fresh employer
 * profile and trade category are created unless supplied.
 *
 * @param  array<string, mixed>  $overrides
 */
function liveJob(array $overrides = []): JobPost
{
    return JobPost::factory()->create($overrides);
}

it('lists only live jobs and excludes draft, hidden, closed and expired', function () {
    $live = liveJob(['title' => 'Live Welder']);
    JobPost::factory()->draft()->create(['title' => 'Draft Job']);
    JobPost::factory()->hidden()->create(['title' => 'Hidden Job']);
    JobPost::factory()->closed()->create(['title' => 'Closed Job']);
    JobPost::factory()->expired()->create(['title' => 'Expired Job']);

    $response = $this->getJson('/api/public/jobs');

    $response->assertOk();

    $slugs = collect($response->json('data'))->pluck('slug');
    expect($slugs)->toContain($live->slug)
        ->and($slugs)->toHaveCount(1);
});

it('returns a live job detail with JSON-LD fields', function () {
    $country = Country::create(['name' => 'India', 'iso2' => 'IN']);
    $state = State::create(['country_id' => $country->id, 'name' => 'Kerala']);
    $district = District::create(['state_id' => $state->id, 'name' => 'Ernakulam']);
    $employer = EmployerProfile::factory()->create(['company_name' => 'Acme Corp']);

    $job = liveJob([
        'employer_profile_id' => $employer->id,
        'country_id' => $country->id,
        'state_id' => $state->id,
        'district_id' => $district->id,
        'salary_min' => 20000,
        'salary_max' => 40000,
        'salary_currency' => 'INR',
        'deadline' => now()->addWeek()->toDateString(),
    ]);

    $response = $this->getJson("/api/public/jobs/{$job->slug}");

    $response->assertOk()
        ->assertJsonPath('data.slug', $job->slug)
        ->assertJsonPath('data.hiring_organization.name', 'Acme Corp')
        ->assertJsonPath('data.job_location.country', 'India')
        ->assertJsonPath('data.job_location.state', 'Kerala')
        ->assertJsonPath('data.job_location.district', 'Ernakulam')
        ->assertJsonPath('data.base_salary.min', 20000)
        ->assertJsonPath('data.base_salary.max', 40000)
        ->assertJsonPath('data.base_salary.currency', 'INR');

    expect($response->json('data.date_posted'))->not->toBeNull()
        ->and($response->json('data.valid_through'))->not->toBeNull();
});

it('404s on the detail endpoint for non-live jobs', function () {
    $draft = JobPost::factory()->draft()->create();
    $hidden = JobPost::factory()->hidden()->create();
    $closed = JobPost::factory()->closed()->create();
    $expired = JobPost::factory()->expired()->create();

    $this->getJson("/api/public/jobs/{$draft->slug}")->assertNotFound();
    $this->getJson("/api/public/jobs/{$hidden->slug}")->assertNotFound();
    $this->getJson("/api/public/jobs/{$closed->slug}")->assertNotFound();
    $this->getJson("/api/public/jobs/{$expired->slug}")->assertNotFound();
});

it('filters by keyword using a portable LIKE on title or description', function () {
    $match = liveJob(['title' => 'Senior Plumber', 'description' => 'Pipes and taps']);
    liveJob(['title' => 'Electrician', 'description' => 'Wiring work']);

    $byTitle = $this->getJson('/api/public/jobs?keyword=plumber');
    expect(collect($byTitle->json('data'))->pluck('slug'))->toContain($match->slug)
        ->toHaveCount(1);

    $byDescription = $this->getJson('/api/public/jobs?keyword=pipes');
    expect(collect($byDescription->json('data'))->pluck('slug'))->toContain($match->slug)
        ->toHaveCount(1);
});

it('filters by country, state and district', function () {
    $country = Country::create(['name' => 'India', 'iso2' => 'IN']);
    $state = State::create(['country_id' => $country->id, 'name' => 'Kerala']);
    $district = District::create(['state_id' => $state->id, 'name' => 'Kochi']);

    $match = liveJob([
        'country_id' => $country->id,
        'state_id' => $state->id,
        'district_id' => $district->id,
    ]);
    liveJob(); // elsewhere

    $response = $this->getJson("/api/public/jobs?country_id={$country->id}&state_id={$state->id}&district_id={$district->id}");

    expect(collect($response->json('data'))->pluck('slug'))->toContain($match->slug)
        ->toHaveCount(1);
});

it('filters by trade slug and by group slug', function () {
    $group = JobCategory::factory()->group()->create();
    $tradeA = JobCategory::factory()->create(['parent_id' => $group->id]);
    $tradeB = JobCategory::factory()->create(['parent_id' => $group->id]);
    $otherGroup = JobCategory::factory()->group()->create();
    $otherTrade = JobCategory::factory()->create(['parent_id' => $otherGroup->id]);

    $jobA = liveJob(['job_category_id' => $tradeA->id]);
    $jobB = liveJob(['job_category_id' => $tradeB->id]);
    $jobOther = liveJob(['job_category_id' => $otherTrade->id]);

    // Trade slug matches only that trade's jobs.
    $byTrade = $this->getJson('/api/public/jobs?category='.$tradeA->slug);
    expect(collect($byTrade->json('data'))->pluck('slug'))
        ->toContain($jobA->slug)
        ->toHaveCount(1);

    // Group slug matches all child trades' jobs.
    $byGroup = $this->getJson('/api/public/jobs?category='.$group->slug);
    $groupSlugs = collect($byGroup->json('data'))->pluck('slug');
    expect($groupSlugs)->toContain($jobA->slug)
        ->toContain($jobB->slug)
        ->not->toContain($jobOther->slug)
        ->toHaveCount(2);
});

it('filters by experience and salary', function () {
    $easy = liveJob(['experience_min' => 0, 'salary_max' => 50000]);
    $senior = liveJob(['experience_min' => 10, 'salary_max' => 10000]);

    // Candidate with 2 years: only jobs requiring <= 2 years.
    $byExp = $this->getJson('/api/public/jobs?experience=2');
    expect(collect($byExp->json('data'))->pluck('slug'))
        ->toContain($easy->slug)
        ->not->toContain($senior->slug);

    // Candidate wants >= 40000: only jobs whose salary_max >= 40000.
    $bySalary = $this->getJson('/api/public/jobs?salary_min=40000');
    expect(collect($bySalary->json('data'))->pluck('slug'))
        ->toContain($easy->slug)
        ->not->toContain($senior->slug);
});

it('paginates with meta', function () {
    JobPost::factory()->count(3)->create();

    $response = $this->getJson('/api/public/jobs?per_page=2');

    $response->assertOk()
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.current_page', 1);

    expect($response->json('meta.total'))->toBe(3)
        ->and($response->json('meta.last_page'))->toBe(2)
        ->and($response->json('data'))->toHaveCount(2);
});

it('marks public job responses as cacheable, not no-store', function () {
    liveJob();

    $list = $this->getJson('/api/public/jobs');
    $list->assertOk();
    expect($list->headers->get('Cache-Control'))->not->toContain('no-store');

    $job = liveJob();
    $detail = $this->getJson("/api/public/jobs/{$job->slug}");
    $detail->assertOk();
    expect($detail->headers->get('Cache-Control'))->not->toContain('no-store');
});
