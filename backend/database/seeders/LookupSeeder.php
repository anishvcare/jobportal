<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\District;
use App\Models\EducationLevel;
use App\Models\JobCategory;
use App\Models\Language;
use App\Models\State;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Idempotent seeder for all lookup lists. Safe to re-run in production.
 */
class LookupSeeder extends Seeder
{
    /** Countries pinned to the top of pickers, in order. */
    private const PINNED = ['IN' => 1, 'RU' => 2];

    public function run(): void
    {
        $this->seedCountries();
        $this->seedRegions();
        $this->seedJobCategories();
        $this->seedEducationLevels();
        $this->seedLanguages();
    }

    private function seedCountries(): void
    {
        /** @var list<array{iso2: string, name: string}> $countries */
        $countries = json_decode((string) file_get_contents(database_path('data/countries.json')), true);

        foreach ($countries as $country) {
            Country::updateOrCreate(
                ['iso2' => $country['iso2']],
                ['name' => $country['name'], 'sort_order' => self::PINNED[$country['iso2']] ?? 1000],
            );
        }
    }

    private function seedRegions(): void
    {
        /** @var array<string, array{states: list<array{name: string, type: string}>, districts: array<string, list<string>>}> $regions */
        $regions = require database_path('data/regions.php');

        foreach ($regions as $iso2 => $data) {
            $country = Country::where('iso2', $iso2)->firstOrFail();
            $country->update(['has_states' => true]);

            foreach ($data['states'] as $state) {
                State::updateOrCreate(
                    ['country_id' => $country->id, 'name' => $state['name']],
                    ['type' => $state['type']],
                );
            }

            foreach ($data['districts'] as $stateName => $districts) {
                $state = State::where('country_id', $country->id)->where('name', $stateName)->firstOrFail();
                foreach ($districts as $district) {
                    District::firstOrCreate(['state_id' => $state->id, 'name' => $district]);
                }
            }
        }
    }

    /** Groups => trades. Admins can edit this list later. */
    public const CATEGORIES = [
        'Skilled' => [
            'Electrician', 'Welder', 'Carpenter', 'Plumber', 'Mason', 'Fitter', 'Painter',
            'Steel Fixer', 'AC/Refrigeration Technician', 'Mechanic', 'Heavy Driver', 'Light Driver',
            'Crane/Forklift Operator', 'Machine Operator', 'Tailor', 'Cook/Chef', 'Nurse',
            'Other Skilled',
        ],
        'Unskilled' => [
            'General Labourer', 'Helper', 'Packer', 'Cleaner', 'Loader', 'Farm Worker',
            'Kitchen Helper', 'Security Guard', 'Waiter', 'Housekeeping', 'Other Unskilled',
        ],
    ];

    private function seedJobCategories(): void
    {
        $groupOrder = 0;
        foreach (self::CATEGORIES as $groupName => $trades) {
            $group = JobCategory::updateOrCreate(
                ['slug' => Str::slug($groupName)],
                ['name' => $groupName, 'parent_id' => null, 'sort_order' => ++$groupOrder],
            );

            foreach ($trades as $i => $trade) {
                JobCategory::updateOrCreate(
                    ['slug' => Str::slug($trade)],
                    ['name' => $trade, 'parent_id' => $group->id, 'sort_order' => $i + 1],
                );
            }
        }
    }

    private function seedEducationLevels(): void
    {
        $levels = [
            ['Below 10th', 10],
            ['SSLC / 10th', 20],
            ['Plus Two / 12th', 30],
            ['ITI', 35],
            ['Diploma', 40],
            ["Bachelor's Degree", 50],
            ["Master's Degree", 60],
            ['Doctorate (PhD)', 70],
        ];

        foreach ($levels as [$name, $rank]) {
            EducationLevel::updateOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'rank' => $rank]);
        }
    }

    private function seedLanguages(): void
    {
        $languages = [
            'en' => 'English', 'ml' => 'Malayalam', 'hi' => 'Hindi', 'ta' => 'Tamil',
            'kn' => 'Kannada', 'te' => 'Telugu', 'mr' => 'Marathi', 'bn' => 'Bengali',
            'gu' => 'Gujarati', 'pa' => 'Punjabi', 'ur' => 'Urdu', 'or' => 'Odia',
            'kok' => 'Konkani', 'ar' => 'Arabic', 'ru' => 'Russian', 'fr' => 'French',
            'de' => 'German',
        ];

        foreach ($languages as $code => $name) {
            Language::updateOrCreate(['code' => $code], ['name' => $name]);
        }
    }
}
