<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\District;
use App\Models\EducationLevel;
use App\Models\JobCategory;
use App\Models\Language;
use App\Models\State;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Public, cacheable lookup lists. Cache keys are flushed by
 * LookupController::flush() whenever an admin edits a list.
 */
class LookupController extends Controller
{
    public const CACHE_KEY = 'lookups:v1';

    private const TTL = 3600;

    public function index(): JsonResponse
    {
        $data = Cache::remember(self::CACHE_KEY, self::TTL, fn () => [
            'countries' => Country::active()
                ->orderBy('sort_order')->orderBy('name')
                ->get(['id', 'name', 'iso2', 'has_states']),
            // Groups (Skilled / Unskilled) with their trades nested.
            'job_categories' => JobCategory::active()->groups()
                ->with(['children' => fn ($q) => $q->active()
                    ->orderBy('sort_order')->orderBy('name')
                    ->select(['id', 'parent_id', 'name', 'slug'])])
                ->orderBy('sort_order')->orderBy('name')
                ->get(['id', 'name', 'slug'])
                ->map(fn (JobCategory $group) => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'slug' => $group->slug,
                    'trades' => $group->children
                        ->map(fn (JobCategory $trade) => $trade->only(['id', 'name', 'slug']))
                        ->values(),
                ]),
            'education_levels' => EducationLevel::active()
                ->orderBy('rank')
                ->get(['id', 'name', 'slug', 'rank']),
            'languages' => Language::active()
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
        ]);

        return $this->cached(['data' => $data]);
    }

    public function states(Country $country): JsonResponse
    {
        $states = Cache::remember(self::CACHE_KEY.":states:{$country->id}", self::TTL, fn () => State::active()
            ->where('country_id', $country->id)
            ->orderBy('name')
            ->get(['id', 'name', 'type']));

        return $this->cached(['data' => $states]);
    }

    public function districts(State $state): JsonResponse
    {
        $districts = Cache::remember(self::CACHE_KEY.":districts:{$state->id}", self::TTL, fn () => District::active()
            ->where('state_id', $state->id)
            ->orderBy('name')
            ->get(['id', 'name']));

        return $this->cached(['data' => $districts]);
    }

    public static function flush(): void
    {
        // Lookup lists are small; bump the whole namespace by clearing known keys.
        Cache::forget(self::CACHE_KEY);
        State::query()->pluck('country_id')->unique()
            ->each(fn ($id) => Cache::forget(self::CACHE_KEY.":states:{$id}"));
        State::query()->pluck('id')
            ->each(fn ($id) => Cache::forget(self::CACHE_KEY.":districts:{$id}"));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function cached(array $payload): JsonResponse
    {
        return response()->json($payload)
            ->setPublic()
            ->setMaxAge(300)
            ->setSharedMaxAge(3600);
    }
}
