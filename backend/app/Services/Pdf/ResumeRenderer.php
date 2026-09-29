<?php

namespace App\Services\Pdf;

use App\Models\CandidateProfile;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Renders a clean, professional resume PDF from candidate profile data.
 *
 * The renderer is deterministic: it reads only profile data (never uploaded
 * files) so it can be reused by both the standalone resume endpoint and the
 * candidate pack builder.
 */
class ResumeRenderer
{
    /**
     * Relations the resume view reads. Eager-loaded to respect
     * Model::shouldBeStrict() (no lazy loading during rendering).
     *
     * @var list<string>
     */
    public const RELATIONS = [
        'user',
        'country',
        'state',
        'district',
        'educations.educationLevel',
        'experiences',
        'skills',
        'languages',
        'preferredCategories',
        'preferredCountries',
    ];

    /**
     * Render the resume and return the raw PDF bytes.
     */
    public function render(CandidateProfile $profile): string
    {
        $profile->loadMissing(self::RELATIONS);

        return Pdf::loadView('pdf.resume', $this->viewData($profile))
            ->setPaper('a4')
            ->output();
    }

    /**
     * @return array<string, mixed>
     */
    private function viewData(CandidateProfile $profile): array
    {
        return [
            'fullName' => (string) $profile->full_name,
            'contactLines' => $this->contactLines($profile),
            'summary' => trim((string) ($profile->summary ?? '')),
            'experiences' => $this->experiences($profile),
            'educations' => $this->educations($profile),
            'skills' => $profile->skills->pluck('name')->filter()->values()->all(),
            'languages' => $this->languages($profile),
            'preferredCategories' => $profile->preferredCategories->pluck('name')->filter()->values()->all(),
            'preferredCountries' => $profile->preferredCountries->pluck('name')->filter()->values()->all(),
        ];
    }

    /**
     * @return list<string>
     */
    private function contactLines(CandidateProfile $profile): array
    {
        $lines = [];

        if ($profile->phone) {
            $lines[] = 'Phone: '.$profile->phone;
        }

        if ($profile->whatsapp) {
            $lines[] = 'WhatsApp: '.$profile->whatsapp;
        }

        $email = $profile->user?->email;
        if ($email) {
            $lines[] = $email;
        }

        $location = array_filter([
            $profile->city,
            $profile->district?->name,
            $profile->state?->name,
            $profile->country?->name,
        ]);

        if ($location !== []) {
            $lines[] = implode(', ', $location);
        }

        return $lines;
    }

    /**
     * @return list<array{title:string, meta:string, description:string}>
     */
    private function experiences(CandidateProfile $profile): array
    {
        return $profile->experiences
            ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
            ->map(function ($experience) {
                $title = array_filter([$experience->job_title, $experience->company]);

                $period = $experience->start_date?->format('M Y');
                $end = $experience->is_current
                    ? 'Present'
                    : $experience->end_date?->format('M Y');

                $meta = array_filter([
                    $period && $end ? $period.' - '.$end : ($period ?: $end),
                ]);

                return [
                    'title' => implode(' at ', $title) ?: 'Experience',
                    'meta' => implode(' ', $meta),
                    'description' => trim((string) ($experience->description ?? '')),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{title:string, meta:string}>
     */
    private function educations(CandidateProfile $profile): array
    {
        return $profile->educations
            ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
            ->map(function ($education) {
                $title = array_filter([
                    $education->educationLevel?->name,
                    $education->field_of_study,
                ]);

                $meta = array_filter([
                    $education->institution,
                    $education->year_completed,
                ]);

                return [
                    'title' => implode(' - ', $title) ?: 'Education',
                    'meta' => implode(', ', $meta),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function languages(CandidateProfile $profile): array
    {
        return $profile->languages
            ->map(function ($language) {
                $proficiency = $language->pivot?->proficiency;

                return $proficiency
                    ? $language->name.' ('.$proficiency.')'
                    : $language->name;
            })
            ->filter()
            ->values()
            ->all();
    }
}
