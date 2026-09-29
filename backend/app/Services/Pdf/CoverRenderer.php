<?php

namespace App\Services\Pdf;

use App\Enums\DocumentType;
use App\Models\CandidateProfile;
use App\Services\Documents\CompletenessService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Renders the candidate pack cover page: photo, key details, and the list of
 * any missing documents (computed by CompletenessService).
 */
class CoverRenderer
{
    /**
     * Human-readable labels for each document type shown in the missing list.
     *
     * @var array<string, string>
     */
    private const LABELS = [
        'photo' => 'Photo',
        'aadhaar_front' => 'Aadhaar (front)',
        'aadhaar_back' => 'Aadhaar (back)',
        'sslc' => 'SSLC certificate',
        'education_cert' => 'Education certificate',
        'skill_cert' => 'Skill certificate',
        'experience_cert' => 'Experience certificate',
        'passport' => 'Passport',
        'cv' => 'CV',
        'profile_pdf' => 'Profile PDF',
    ];

    public function __construct(private readonly CompletenessService $completeness) {}

    /**
     * Render the cover page and return raw PDF bytes.
     *
     * @param  array{percentage:int, missing:list<string>, required:list<string>, has_profile_pdf:bool, pack_ready:bool}|null  $completeness
     */
    public function render(CandidateProfile $profile, ?array $completeness = null): string
    {
        $profile->loadMissing(['documents', 'country', 'state', 'district']);

        $completeness ??= $this->completeness->compute($profile);

        return Pdf::loadView('pdf.cover', [
            'fullName' => (string) $profile->full_name,
            'photoDataUri' => $this->photoDataUri($profile),
            'details' => $this->details($profile),
            'missing' => $this->missingLabels($completeness['missing']),
        ])
            ->setPaper('a4')
            ->output();
    }

    private function photoDataUri(CandidateProfile $profile): ?string
    {
        $photo = $profile->documents
            ->firstWhere('type', DocumentType::Photo);

        if ($photo === null) {
            return null;
        }

        try {
            $disk = Storage::disk($photo->disk);

            if (! $disk->exists($photo->path)) {
                return null;
            }

            $bytes = $disk->get($photo->path);

            if ($bytes === null || $bytes === '') {
                return null;
            }
        } catch (\Throwable $e) {
            Log::warning('Could not read candidate photo for pack cover.', [
                'profile_id' => $profile->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $mime = $photo->mime ?: 'image/jpeg';

        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }

    /**
     * @return array<string, string>
     */
    private function details(CandidateProfile $profile): array
    {
        $details = [
            'Full name' => (string) $profile->full_name,
        ];

        if ($profile->phone) {
            $details['Phone'] = (string) $profile->phone;
        }

        if ($profile->whatsapp) {
            $details['WhatsApp'] = (string) $profile->whatsapp;
        }

        $location = array_filter([
            $profile->city,
            $profile->district?->name,
            $profile->state?->name,
            $profile->country?->name,
        ]);

        if ($location !== []) {
            $details['Location'] = implode(', ', $location);
        }

        if ($profile->has_passport) {
            // passport_number uses an encrypted cast; access only on the cover.
            if ($profile->passport_number) {
                $details['Passport number'] = (string) $profile->passport_number;
            }

            if ($profile->passport_expiry) {
                $details['Passport expiry'] = $profile->passport_expiry->format('d M Y');
            }
        }

        return $details;
    }

    /**
     * @param  list<string>  $missing
     * @return list<string>
     */
    private function missingLabels(array $missing): array
    {
        return array_values(array_map(
            fn (string $type) => self::LABELS[$type] ?? $type,
            $missing
        ));
    }
}
