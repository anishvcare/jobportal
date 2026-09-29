<?php

namespace App\Jobs;

use App\Enums\DocumentType;
use App\Models\BulkExport;
use App\Models\CandidateProfile;
use App\Models\Document;
use App\Services\Pdf\CandidatePackBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;
use ZipArchive;

/**
 * Builds a bulk candidate ZIP in the background. Carries only the bulk export
 * id (never models) so it stays small and queue-serialisable.
 *
 * The ZIP contains one folder per candidate (Profile.pdf = generated pack,
 * CV.pdf = generated resume, plus the candidate's uploaded CV when present) and
 * a root candidates.csv manifest. It is written to the private 'documents' disk
 * and marked READY with a 24h expiry. A candidate deleted between dispatch and
 * execution is skipped gracefully.
 *
 * $tries = 1: a bulk build is expensive and largely non-idempotent (partial
 * progress), so we do not blindly retry; failed() marks the export FAILED.
 */
class BuildBulkPackZip implements ShouldQueue
{
    use Queueable;

    /**
     * Attempts before the job is considered failed. Kept at 1 because the job
     * is a large, mostly non-idempotent build; a failure surfaces to the admin
     * as a FAILED export they can re-request rather than silently retrying.
     */
    public int $tries = 1;

    /** Disk (private) the finished ZIP is written to. */
    private const DISK = 'documents';

    /** CSV manifest header row. */
    private const CSV_HEADER = [
        'Name',
        'Trade',
        'Phone',
        'Passport Number',
        'Passport Expiry',
        'Experience (years)',
        'District',
    ];

    public function __construct(public int $bulkExportId) {}

    public function handle(CandidatePackBuilder $builder): void
    {
        $export = BulkExport::query()->find($this->bulkExportId);

        // The export row may have been deleted between dispatch and execution.
        if ($export === null) {
            return;
        }

        $export->forceFill([
            'status' => BulkExport::STATUS_PROCESSING,
            'progress' => 0,
            'error' => null,
        ])->save();

        // The pack merge backend (qpdf) is required to generate Profile.pdf.
        if (! $builder->mergerAvailable()) {
            $export->forceFill([
                'status' => BulkExport::STATUS_FAILED,
                'error' => 'pack merger (qpdf) unavailable',
            ])->save();

            return;
        }

        $tempDir = storage_path('app/tmp/bulk-'.Str::uuid()->toString());
        $zipPath = $tempDir.'/candidates-export.zip';

        try {
            File::ensureDirectoryExists($tempDir);

            $zip = new ZipArchive;
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Unable to create export archive.');
            }

            $ids = $export->candidate_ids ?? [];
            $total = max(count($ids), 1);
            $manifest = [];
            $position = 0;

            foreach ($ids as $index => $id) {
                $profile = $this->loadProfile((int) $id);

                // A candidate deleted mid-run is skipped gracefully.
                if ($profile === null) {
                    $export->forceFill(['progress' => (int) round(($index + 1) / $total * 100)])->save();

                    continue;
                }

                $position++;
                $folder = $this->folderName($position, $profile);

                $pack = $builder->ensureFresh($profile);
                $packBytes = ($pack->path && Storage::disk($pack->disk)->exists($pack->path))
                    ? Storage::disk($pack->disk)->get($pack->path)
                    : null;

                if ($packBytes !== null && $packBytes !== '') {
                    $zip->addFromString($folder.'/Profile.pdf', $packBytes);
                }

                $zip->addFromString($folder.'/CV.pdf', $builder->renderResume($profile));

                $this->addUploadedCv($zip, $folder, $profile);

                $manifest[] = $this->manifestRow($profile);

                $export->forceFill(['progress' => (int) round(($index + 1) / $total * 100)])->save();
            }

            $zip->addFromString('candidates.csv', $this->buildCsv($manifest));

            $zip->close();

            $storagePath = 'exports/'.$export->id.'/candidates-export.zip';
            Storage::disk(self::DISK)->put($storagePath, File::get($zipPath));

            $export->forceFill([
                'status' => BulkExport::STATUS_READY,
                'disk' => self::DISK,
                'path' => $storagePath,
                'progress' => 100,
                'expires_at' => now()->addHours((int) config('nexus.bulk_export.ttl_hours', 24)),
                'error' => null,
            ])->save();
        } catch (Throwable $e) {
            Log::error('Bulk export build failed.', [
                'bulk_export_id' => $export->id,
                'error' => $e->getMessage(),
            ]);

            $export->forceFill([
                'status' => BulkExport::STATUS_FAILED,
                'error' => Str::limit($e->getMessage(), 1000),
            ])->save();
        } finally {
            File::deleteDirectory($tempDir);
        }
    }

    /**
     * Mark the export FAILED if the job ultimately fails and it is not already
     * in a terminal state.
     */
    public function failed(?Throwable $exception): void
    {
        $export = BulkExport::query()->find($this->bulkExportId);

        if ($export === null || $export->status === BulkExport::STATUS_READY) {
            return;
        }

        $export->forceFill([
            'status' => BulkExport::STATUS_FAILED,
            'error' => $exception !== null
                ? Str::limit($exception->getMessage(), 1000)
                : 'Bulk export job failed.',
        ])->save();
    }

    /**
     * Load a candidate profile with the relations the ZIP + CSV read, or null
     * if the candidate no longer exists.
     */
    private function loadProfile(int $id): ?CandidateProfile
    {
        return CandidateProfile::query()
            ->with(['documents', 'preferredCategories', 'district', 'experiences'])
            ->find($id);
    }

    /**
     * Per-candidate folder name, e.g. "001 - anita-rao".
     */
    private function folderName(int $position, CandidateProfile $profile): string
    {
        $slug = Str::slug($profile->full_name ?: 'candidate-'.$profile->id);

        if ($slug === '') {
            $slug = 'candidate-'.$profile->id;
        }

        return sprintf('%03d - %s', $position, $slug);
    }

    /**
     * Add the candidate's uploaded CV document to the folder, when present and
     * readable.
     */
    private function addUploadedCv(ZipArchive $zip, string $folder, CandidateProfile $profile): void
    {
        $cv = $profile->documents
            ->first(fn (Document $doc) => $doc->type === DocumentType::Cv);

        if ($cv === null) {
            return;
        }

        try {
            $disk = Storage::disk($cv->disk);

            if (! $disk->exists($cv->path)) {
                return;
            }

            $bytes = $disk->get($cv->path);
        } catch (Throwable $e) {
            Log::warning('Skipping unreadable uploaded CV in bulk export.', [
                'document_id' => $cv->id,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        if ($bytes === null || $bytes === '') {
            return;
        }

        $extension = pathinfo((string) $cv->path, PATHINFO_EXTENSION) ?: 'pdf';

        $zip->addFromString($folder.'/Uploaded-CV.'.$extension, $bytes);
    }

    /**
     * Build one CSV manifest row for a candidate. passport_number is an
     * 'encrypted' cast, so reading it here decrypts it (admin is authorized).
     *
     * @return array<int, string>
     */
    private function manifestRow(CandidateProfile $profile): array
    {
        $trade = $profile->preferredCategories
            ->map(fn ($category) => $category->name)
            ->filter()
            ->implode(', ');

        return [
            (string) ($profile->full_name ?? ''),
            $trade,
            (string) ($profile->phone ?? ''),
            (string) ($profile->passport_number ?? ''),
            $profile->passport_expiry?->toDateString() ?? '',
            (string) $this->experienceYears($profile),
            (string) ($profile->district?->name ?? ''),
        ];
    }

    /**
     * Total experience in whole years, summed across the candidate's
     * experience rows (open-ended rows run to today). Computed in PHP so it is
     * driver-independent for the manifest.
     */
    private function experienceYears(CandidateProfile $profile): int
    {
        $days = 0.0;

        foreach ($profile->experiences as $experience) {
            if ($experience->start_date === null) {
                continue;
            }

            $end = $experience->end_date ?? now();
            $days += $experience->start_date->diffInDays($end);
        }

        return (int) floor($days / 365.25);
    }

    /**
     * Render the manifest rows to CSV text via native fputcsv on a memory
     * stream.
     *
     * @param  array<int, array<int, string>>  $rows
     */
    private function buildCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, self::CSV_HEADER);

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
