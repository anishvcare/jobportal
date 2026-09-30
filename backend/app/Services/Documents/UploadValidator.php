<?php

namespace App\Services\Documents;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Validates an uploaded document beyond the framework's declarative rules:
 * confirms the real MIME type, rejects HEIC/HEIF that reached the server
 * (client-side conversion failed) and rejects encrypted PDFs.
 */
class UploadValidator
{
    /** Friendly message shown when a HEIC/HEIF image reaches the server. */
    public const HEIC_MESSAGE = 'Please retry: your photo needs to be converted before upload.';

    /** Friendly message shown when a password-protected PDF is uploaded. */
    public const ENCRYPTED_PDF_MESSAGE = 'This PDF is password-protected. Please remove the password and upload again.';

    /** Friendly message shown when the PDF's encryption status cannot be verified. */
    public const UNVERIFIABLE_PDF_MESSAGE = 'We could not verify this PDF. Please try again.';

    /** @var list<string> */
    private const ACCEPTED_MIMES = ['image/jpeg', 'image/png', 'application/pdf'];

    /** @var list<string> */
    private const HEIC_MIMES = ['image/heic', 'image/heif'];

    public function __construct(private readonly PdfInspector $pdfInspector) {}

    /**
     * Validate the file and return metadata: [mime, page_count].
     *
     * @return array{mime:string, page_count:int|null}
     *
     * @throws ValidationException
     */
    public function validate(UploadedFile $file): array
    {
        $mime = strtolower((string) $file->getMimeType());
        $extension = strtolower((string) $file->getClientOriginalExtension());

        // HEIC reaching the server means the client failed to convert it; we
        // cannot convert server-side (no imagick), so ask the user to retry.
        if (in_array($mime, self::HEIC_MIMES, true) || in_array($extension, ['heic', 'heif'], true)) {
            throw ValidationException::withMessages([
                'file' => self::HEIC_MESSAGE,
            ]);
        }

        if (! in_array($mime, self::ACCEPTED_MIMES, true)) {
            throw ValidationException::withMessages([
                'file' => 'Only JPG, PNG or PDF files are accepted.',
            ]);
        }

        $pageCount = null;

        if ($mime === 'application/pdf') {
            $path = $file->getRealPath();

            // Fail CLOSED on the encryption check: only accept a PDF when qpdf
            // confirms it is NOT encrypted (false). A true result is rejected as
            // password-protected; a null result (qpdf missing/unreadable at
            // runtime) is rejected as unverifiable so encrypted PDFs are never
            // silently stored on a host without qpdf.
            $encrypted = $this->pdfInspector->isEncrypted($path);

            if ($encrypted === true) {
                throw ValidationException::withMessages([
                    'file' => self::ENCRYPTED_PDF_MESSAGE,
                ]);
            }

            if ($encrypted !== false) {
                throw ValidationException::withMessages([
                    'file' => self::UNVERIFIABLE_PDF_MESSAGE,
                ]);
            }

            $pageCount = $this->pdfInspector->pageCount($path);
        }

        return ['mime' => $mime, 'page_count' => $pageCount];
    }
}
