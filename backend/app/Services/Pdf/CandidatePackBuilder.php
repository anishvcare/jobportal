<?php

namespace App\Services\Pdf;

use App\Enums\DocumentType;
use App\Models\CandidatePack;
use App\Models\CandidateProfile;
use App\Models\Document;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Orchestrates candidate pack generation. Assembles the cover page and resume
 * (built with DomPDF) together with the candidate's uploaded documents in the
 * authoritative order, merges them with qpdf, caches the result on the private
 * disk, and records state on the candidate_packs row.
 *
 * Pack order (authoritative):
 *   cover -> resume -> Aadhaar (front, back) -> SSLC -> education certs
 *   -> skill certs -> experience certs -> passport (all pages in sort_order)
 */
class CandidatePackBuilder
{
    /** Disk (private) the finished pack is written to. */
    private const DISK = 'documents';

    /** Path prefix for cached packs on the private disk. */
    private const PREFIX = 'packs';

    /**
     * Document types included in the pack, in authoritative order. Photo,
     * Cv and ProfilePdf are intentionally excluded (the photo appears on the
     * cover; an uploaded profile_pdf never substitutes for the generated pack).
     *
     * @var list<DocumentType>
     */
    private const DOCUMENT_ORDER = [
        DocumentType::AadhaarFront,
        DocumentType::AadhaarBack,
        DocumentType::Sslc,
        DocumentType::EducationCert,
        DocumentType::SkillCert,
        DocumentType::ExperienceCert,
        DocumentType::Passport,
    ];

    public function __construct(
        private readonly ResumeRenderer $resumeRenderer,
        private readonly CoverRenderer $coverRenderer,
        private readonly ImagePageRenderer $imagePageRenderer,
        private readonly QpdfMerger $merger,
    ) {}

    /**
     * Whether the qpdf merge backend is available in this environment.
     */
    public function mergerAvailable(): bool
    {
        return $this->merger->isAvailable();
    }

    /**
     * Render the resume PDF for this profile. Shared with the resume endpoint
     * so the pack and the standalone download use one renderer.
     */
    public function renderResume(CandidateProfile $profile): string
    {
        return $this->resumeRenderer->render($profile);
    }

    /**
     * Force-build the pack regardless of freshness.
     */
    public function build(CandidateProfile $profile): CandidatePack
    {
        $profile->loadMissing(array_merge(ResumeRenderer::RELATIONS, ['documents', 'candidatePack']));

        $pack = $this->packRow($profile);

        $fingerprint = $this->currentFingerprint($profile);

        $tempDir = storage_path('app/tmp/pack-'.Str::uuid()->toString());

        try {
            File::ensureDirectoryExists($tempDir);

            $orderedPaths = $this->assemble($profile, $tempDir);

            $mergedPath = $tempDir.'/candidate-pack.pdf';
            $this->merger->merge($orderedPaths, $mergedPath);

            $storagePath = self::PREFIX.'/'.$profile->id.'/candidate-pack.pdf';
            Storage::disk(self::DISK)->put($storagePath, File::get($mergedPath));

            $pack->forceFill([
                'disk' => self::DISK,
                'path' => $storagePath,
                'fingerprint' => $fingerprint,
                'status' => CandidatePack::STATUS_READY,
                'generated_at' => now(),
                'error' => null,
            ])->save();
        } catch (Throwable $e) {
            Log::error('Candidate pack build failed.', [
                'profile_id' => $profile->id,
                'error' => $e->getMessage(),
            ]);

            $pack->forceFill([
                'status' => CandidatePack::STATUS_FAILED,
                'error' => Str::limit($e->getMessage(), 1000),
            ])->save();
        } finally {
            File::deleteDirectory($tempDir);
        }

        return $pack;
    }

    /**
     * Build synchronously only when the cached pack is missing or stale;
     * otherwise return the existing pack. Used by the on-request path.
     */
    public function ensureFresh(CandidateProfile $profile): CandidatePack
    {
        if (! $this->isStale($profile)) {
            return $profile->candidatePack;
        }

        return $this->build($profile);
    }

    /**
     * A pack is stale when the row is missing, the file is missing on disk, or
     * the stored fingerprint no longer matches the current profile/documents.
     */
    public function isStale(CandidateProfile $profile): bool
    {
        $pack = $profile->relationLoaded('candidatePack')
            ? $profile->candidatePack
            : $profile->candidatePack()->first();

        if ($pack === null || $pack->status !== CandidatePack::STATUS_READY) {
            return true;
        }

        if (! $pack->path || ! Storage::disk($pack->disk)->exists($pack->path)) {
            return true;
        }

        return $pack->fingerprint !== $this->currentFingerprint($profile);
    }

    /**
     * Stable hash of the profile's updated_at plus each pack document's id and
     * content hash, in pack order. Changes whenever the profile or any relevant
     * document changes.
     */
    public function currentFingerprint(CandidateProfile $profile): string
    {
        $documents = $this->packDocuments($profile);

        $parts = [
            'profile:'.($profile->updated_at?->toIso8601String() ?? ''),
        ];

        foreach ($documents as $document) {
            $parts[] = $document->id.':'.($document->sha256 ?? '');
        }

        return hash('sha256', implode('|', $parts));
    }

    /**
     * Assemble the ordered temp-file list: cover, resume, then each document
     * (PDFs copied as-is, images rendered to a one-page A4 PDF).
     *
     * @return list<string>
     */
    private function assemble(CandidateProfile $profile, string $tempDir): array
    {
        $paths = [];
        $index = 0;

        $coverPath = $tempDir.'/'.($index++).'-cover.pdf';
        File::put($coverPath, $this->coverRenderer->render($profile));
        $paths[] = $coverPath;

        $resumePath = $tempDir.'/'.($index++).'-resume.pdf';
        File::put($resumePath, $this->resumeRenderer->render($profile));
        $paths[] = $resumePath;

        foreach ($this->packDocuments($profile) as $document) {
            $rendered = $this->renderDocument($document, $tempDir, $index);

            if ($rendered !== null) {
                $paths[] = $rendered;
                $index++;
            }
        }

        return $paths;
    }

    /**
     * Convert a single document to a temp PDF file, or null when it should be
     * skipped (unreadable image / unreadable source).
     */
    private function renderDocument(Document $document, string $tempDir, int $index): ?string
    {
        try {
            $disk = Storage::disk($document->disk);

            if (! $disk->exists($document->path)) {
                Log::warning('Skipping missing document in candidate pack.', [
                    'document_id' => $document->id,
                    'path' => $document->path,
                ]);

                return null;
            }

            $bytes = $disk->get($document->path);
        } catch (Throwable $e) {
            Log::warning('Skipping unreadable document in candidate pack.', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if ($bytes === null || $bytes === '') {
            return null;
        }

        $mime = strtolower((string) $document->mime);

        if ($mime === 'application/pdf' || Str::endsWith(strtolower((string) $document->path), '.pdf')) {
            $target = $tempDir.'/'.$index.'-doc-'.$document->id.'.pdf';
            File::put($target, $bytes);

            return $target;
        }

        $pageBytes = $this->imagePageRenderer->render($bytes, $mime ?: 'image/jpeg');

        if ($pageBytes === null) {
            return null;
        }

        $target = $tempDir.'/'.$index.'-img-'.$document->id.'.pdf';
        File::put($target, $pageBytes);

        return $target;
    }

    /**
     * Documents that belong in the pack body, in authoritative type order and,
     * within each type, by sort_order then id.
     *
     * @return Collection<int, Document>
     */
    private function packDocuments(CandidateProfile $profile): Collection
    {
        $documents = $profile->relationLoaded('documents')
            ? $profile->documents
            : $profile->documents()->get();

        $ordered = collect();

        foreach (self::DOCUMENT_ORDER as $type) {
            $ofType = $documents
                ->filter(fn (Document $doc) => $doc->type === $type)
                ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
                ->values();

            foreach ($ofType as $doc) {
                $ordered->push($doc);
            }
        }

        return $ordered;
    }

    /**
     * Fetch or create the candidate_packs row without triggering observers on
     * the profile/documents (writing the pack row is a separate model).
     */
    private function packRow(CandidateProfile $profile): CandidatePack
    {
        $pack = $profile->relationLoaded('candidatePack')
            ? $profile->candidatePack
            : $profile->candidatePack()->first();

        if ($pack === null) {
            $pack = new CandidatePack([
                'candidate_profile_id' => $profile->id,
                'disk' => self::DISK,
                'status' => CandidatePack::STATUS_BUILDING,
            ]);
            $pack->candidate_profile_id = $profile->id;
            $pack->save();
            $profile->setRelation('candidatePack', $pack);
        }

        return $pack;
    }
}
