<?php

use App\Models\Country;
use App\Models\JobCategory;
use App\Models\State;
use Database\Seeders\LookupSeeder;

beforeEach(fn () => $this->seed(LookupSeeder::class));

it('serves lookup lists publicly with India and Russia pinned first', function () {
    $response = $this->getJson('/api/public/lookups')->assertOk();

    $countries = $response->json('data.countries');
    expect(array_slice(array_column($countries, 'iso2'), 0, 2))->toBe(['IN', 'RU'])
        ->and(count($countries))->toBeGreaterThan(240)
        ->and($response->json('data.job_categories'))->not->toBeEmpty()
        ->and($response->json('data.education_levels.0.rank'))->toBeLessThan($response->json('data.education_levels.1.rank'));

    expect($response->headers->get('Cache-Control'))->toContain('public');
});

it('serves Indian states, Kerala districts and Russian regions', function () {
    $india = Country::where('iso2', 'IN')->first();
    $russia = Country::where('iso2', 'RU')->first();
    $kerala = State::where('country_id', $india->id)->where('name', 'Kerala')->first();

    expect($this->getJson("/api/public/countries/{$india->id}/states")->json('data'))->toHaveCount(36);
    expect($this->getJson("/api/public/countries/{$russia->id}/states")->json('data'))->toHaveCount(83);
    expect(array_column($this->getJson("/api/public/states/{$kerala->id}/districts")->json('data'), 'name'))
        ->toContain('Kottayam')
        ->toHaveCount(14);
});

it('groups trades under Skilled and Unskilled only', function () {
    $groups = $this->getJson('/api/public/lookups')->json('data.job_categories');

    expect(array_column($groups, 'name'))->toBe(['Skilled', 'Unskilled']);

    $skilled = array_column($groups[0]['trades'], 'name');
    expect($skilled)->toContain('Electrician', 'Welder', 'Carpenter')
        ->and(array_column($groups[1]['trades'], 'name'))->toContain('Helper', 'Packer')
        ->and($skilled)->not->toContain('Helper');
});

it('can be re-seeded without creating duplicates', function () {
    $this->seed(LookupSeeder::class);

    expect(Country::count())->toBe(249)
        ->and(State::count())->toBe(119)
        ->and(JobCategory::count())->toBe(31);
});
