<?php

namespace App\Services\Pdf;

use RuntimeException;
use Symfony\Component\Process\Exception\ProcessStartFailedException;
use Symfony\Component\Process\Process;

/**
 * Merges an ordered list of PDF files into a single PDF using the qpdf binary.
 *
 * Ghostscript and imagick are not available in this environment, so qpdf is
 * the sole merge backend: `qpdf --empty --pages f1 f2 ... -- out.pdf`.
 */
class QpdfMerger
{
    /**
     * Merge the given PDF paths, in order, into $outAbsolutePath.
     *
     * @param  list<string>  $orderedPdfPaths
     *
     * @throws RuntimeException when there is nothing to merge or qpdf fails.
     */
    public function merge(array $orderedPdfPaths, string $outAbsolutePath): void
    {
        $orderedPdfPaths = array_values(array_filter($orderedPdfPaths));

        if ($orderedPdfPaths === []) {
            throw new RuntimeException('No PDF pages were supplied to merge.');
        }

        $arguments = [$this->binary(), '--empty', '--pages'];

        foreach ($orderedPdfPaths as $path) {
            $arguments[] = $path;
        }

        $arguments[] = '--';
        $arguments[] = $outAbsolutePath;

        $process = new Process($arguments);

        try {
            $process->run();
        } catch (ProcessStartFailedException $e) {
            throw new RuntimeException(
                'qpdf binary unavailable: '.$e->getMessage(),
                previous: $e
            );
        }

        // qpdf exit code 3 is a warning (non-fatal); still produces output.
        if (! $process->isSuccessful() && $process->getExitCode() !== 3) {
            throw new RuntimeException(sprintf(
                'qpdf merge failed (exit %s): %s',
                $process->getExitCode(),
                trim($process->getErrorOutput()) ?: trim($process->getOutput())
            ));
        }
    }

    /**
     * Whether the qpdf binary can be invoked in this environment.
     */
    public function isAvailable(): bool
    {
        $process = new Process([$this->binary(), '--version']);

        try {
            $process->run();
        } catch (ProcessStartFailedException) {
            return false;
        }

        return $process->isSuccessful();
    }

    private function binary(): string
    {
        return (string) config('nexus.qpdf_path', 'qpdf');
    }
}
