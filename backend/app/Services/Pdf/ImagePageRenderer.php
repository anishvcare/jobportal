<?php

namespace App\Services\Pdf;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;

/**
 * Renders a single uploaded image (JPEG/PNG) as a one-page A4 PDF, scaled to
 * fit within the page margins. HEIC never reaches storage (converted client
 * side in M2), so only JPEG and PNG are handled here.
 */
class ImagePageRenderer
{
    /**
     * Produce a one-page A4 PDF containing the image, or null when the image
     * cannot be decoded so the caller can skip it gracefully.
     */
    public function render(string $imageBytes, string $mime): ?string
    {
        if (! $this->isSupported($mime)) {
            Log::warning('Skipping unsupported image type in candidate pack.', [
                'mime' => $mime,
            ]);

            return null;
        }

        // Verify the bytes actually decode as an image; a corrupt upload should
        // be skipped rather than crash the whole pack.
        $image = @imagecreatefromstring($imageBytes);
        if ($image === false) {
            Log::warning('Skipping unreadable image in candidate pack.', [
                'mime' => $mime,
            ]);

            return null;
        }

        imagedestroy($image);

        $dataUri = 'data:'.$mime.';base64,'.base64_encode($imageBytes);

        try {
            return Pdf::loadView('pdf.image-page', ['imageDataUri' => $dataUri])
                ->setPaper('a4')
                ->output();
        } catch (\Throwable $e) {
            Log::warning('Failed to render image page in candidate pack.', [
                'mime' => $mime,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function isSupported(string $mime): bool
    {
        return in_array(strtolower($mime), [
            'image/jpeg',
            'image/jpg',
            'image/png',
        ], true);
    }
}
