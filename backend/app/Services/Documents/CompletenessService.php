<?php

namespace App\Services\Documents;

use App\Enums\DocumentType;
use App\Models\CandidateProfile;

/**
 * Computes profile document completeness: which required documents are
 * present, the completion percentage, and whether a "pack" is ready.
 */
class CompletenessService
{
    /**
     * @return array{percentage:int, missing:list<string>, required:list<string>, has_profile_pdf:bool, pack_ready:bool}
     */
    public function compute(CandidateProfile $profile): array
    {
        $documents = $profile->relationLoaded('documents')
            ? $profile->documents
            : $profile->documents()->get();

        $presentTypes = $documents
            ->map(fn ($document) => $document->type->value)
            ->unique()
            ->all();

        $required = array_map(
            fn (DocumentType $type) => $type->value,
            DocumentType::requiredForCompleteness()
        );

        // The passport is only required when the candidate declares they hold one.
        if ($profile->has_passport) {
            $required[] = DocumentType::Passport->value;
        }

        $missing = array_values(array_filter(
            $required,
            fn (string $type) => ! in_array($type, $presentTypes, true)
        ));

        $total = count($required);
        $present = $total - count($missing);
        $percentage = $total === 0 ? 100 : (int) round($present / $total * 100);

        $hasProfilePdf = in_array(DocumentType::ProfilePdf->value, $presentTypes, true);

        return [
            'percentage' => $percentage,
            'missing' => $missing,
            'required' => array_values($required),
            'has_profile_pdf' => $hasProfilePdf,
            // A ready-made profile PDF satisfies the "pack" purpose even while
            // individual documents are still missing (which stay listed above).
            'pack_ready' => $hasProfilePdf || $missing === [],
        ];
    }
}
