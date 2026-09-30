<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Public\LookupController as PublicLookupController;
use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\EducationLevel;
use App\Models\JobCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin management of the lookup lists the master spec calls out: job
 * categories, countries and education levels. Endpoints are intentionally
 * minimal (create/rename/toggle-active, plus reorder for education levels) and
 * keep the existing lookup shapes intact.
 *
 * Every mutation flushes the public lookup cache so the changes surface on the
 * public/candidate lookup endpoints immediately.
 */
class LookupController extends Controller
{
    // -------------------------------------------------------------------
    // Job categories (two-level tree: group or trade)
    // -------------------------------------------------------------------

    public function storeJobCategory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            // A trade has a parent group; a group has no parent.
            'parent_id' => ['nullable', 'integer', Rule::exists('job_categories', 'id')],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65000'],
        ]);

        $category = JobCategory::create([
            'parent_id' => $data['parent_id'] ?? null,
            'name' => $data['name'],
            'slug' => $this->uniqueSlug(JobCategory::class, $data['name']),
            'is_active' => true,
            'sort_order' => $data['sort_order'] ?? 1000,
        ]);

        PublicLookupController::flush();

        return response()->json(['data' => $this->jobCategoryPayload($category)], Response::HTTP_CREATED);
    }

    public function updateJobCategory(Request $request, JobCategory $jobCategory): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65000'],
        ]);

        if (array_key_exists('name', $data)) {
            $jobCategory->name = $data['name'];
        }

        if (array_key_exists('sort_order', $data) && $data['sort_order'] !== null) {
            $jobCategory->sort_order = $data['sort_order'];
        }

        $jobCategory->save();

        PublicLookupController::flush();

        return response()->json(['data' => $this->jobCategoryPayload($jobCategory)]);
    }

    public function toggleJobCategory(JobCategory $jobCategory): JsonResponse
    {
        $jobCategory->update(['is_active' => ! $jobCategory->is_active]);

        PublicLookupController::flush();

        return response()->json(['data' => $this->jobCategoryPayload($jobCategory)]);
    }

    // -------------------------------------------------------------------
    // Countries
    // -------------------------------------------------------------------

    public function storeCountry(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'iso2' => ['required', 'string', 'size:2', Rule::unique('countries', 'iso2')],
            'iso3' => ['nullable', 'string', 'size:3'],
            'has_states' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65000'],
        ]);

        $country = Country::create([
            'name' => $data['name'],
            'iso2' => Str::upper($data['iso2']),
            'iso3' => isset($data['iso3']) ? Str::upper($data['iso3']) : null,
            'has_states' => $data['has_states'] ?? false,
            'is_active' => true,
            'sort_order' => $data['sort_order'] ?? 1000,
        ]);

        PublicLookupController::flush();

        return response()->json(['data' => $this->countryPayload($country)], Response::HTTP_CREATED);
    }

    public function toggleCountry(Country $country): JsonResponse
    {
        $country->update(['is_active' => ! $country->is_active]);

        PublicLookupController::flush();

        return response()->json(['data' => $this->countryPayload($country)]);
    }

    // -------------------------------------------------------------------
    // Education levels
    // -------------------------------------------------------------------

    public function storeEducationLevel(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'rank' => ['required', 'integer', 'min:0', 'max:65000'],
        ]);

        $level = EducationLevel::create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug(EducationLevel::class, $data['name']),
            'rank' => $data['rank'],
            'is_active' => true,
        ]);

        PublicLookupController::flush();

        return response()->json(['data' => $this->educationLevelPayload($level)], Response::HTTP_CREATED);
    }

    public function updateEducationLevel(Request $request, EducationLevel $educationLevel): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'rank' => ['sometimes', 'required', 'integer', 'min:0', 'max:65000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('name', $data)) {
            $educationLevel->name = $data['name'];
        }

        if (array_key_exists('rank', $data)) {
            $educationLevel->rank = $data['rank'];
        }

        if (array_key_exists('is_active', $data)) {
            $educationLevel->is_active = $data['is_active'];
        }

        $educationLevel->save();

        PublicLookupController::flush();

        return response()->json(['data' => $this->educationLevelPayload($educationLevel)]);
    }

    /**
     * Reorder education levels by assigning each id a new rank (its position
     * in the given ordered list). Ranks drive the "at least X" filters.
     */
    public function reorderEducationLevels(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', Rule::exists('education_levels', 'id')],
        ]);

        foreach (array_values($data['ids']) as $index => $id) {
            EducationLevel::whereKey($id)->update(['rank' => $index + 1]);
        }

        PublicLookupController::flush();

        $levels = EducationLevel::query()->orderBy('rank')->get(['id', 'name', 'slug', 'rank', 'is_active']);

        return response()->json(['data' => $levels]);
    }

    // -------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------

    /**
     * @param  class-string<Model>  $model
     */
    private function uniqueSlug(string $model, string $name): string
    {
        $base = Str::slug($name) ?: 'item';
        $slug = $base;
        $suffix = 2;

        while ($model::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    /**
     * @return array<string, mixed>
     */
    private function jobCategoryPayload(JobCategory $category): array
    {
        return [
            'id' => $category->id,
            'parent_id' => $category->parent_id,
            'name' => $category->name,
            'slug' => $category->slug,
            'is_active' => (bool) $category->is_active,
            'sort_order' => $category->sort_order,
            'is_group' => $category->isGroup(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function countryPayload(Country $country): array
    {
        return [
            'id' => $country->id,
            'name' => $country->name,
            'iso2' => $country->iso2,
            'iso3' => $country->iso3,
            'has_states' => (bool) $country->has_states,
            'is_active' => (bool) $country->is_active,
            'sort_order' => $country->sort_order,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function educationLevelPayload(EducationLevel $level): array
    {
        return [
            'id' => $level->id,
            'name' => $level->name,
            'slug' => $level->slug,
            'rank' => $level->rank,
            'is_active' => (bool) $level->is_active,
        ];
    }
}
