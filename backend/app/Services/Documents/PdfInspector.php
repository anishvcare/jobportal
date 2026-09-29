<?php

namespace App\Services\Documents;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessStartFailedException;
use Symfony\Component\Process\Process;

/**
 * Inspects PDF files with the qpdf binary. If qpdf is unavailable the
 * inspector degrades gracefully: encryption status and page count are
 * reported as unknown (null) and the situation is logged.
 */
class PdfInspector
{
    /**
     * Determine whether a PDF is encrypted/password-protected.
     *
     * `qpdf --is-encrypted <path>` exits 0 when encrypted and 2 when not.
     *
     * @return bool|null true = encrypted, false = not encrypted, null = unknown
     */
    public function isEncrypted(string $path): ?bool
    {
        $process = new Process([$this->binary(), '--is-encrypted', $path]);

        try {
            $process->run();
        } catch (ProcessStartFailedException $e) {
            $this->warnMissing($e);

            return null;
        }

        return match ($process->getExitCode()) {
            0 => true,
            2 => false,
            default => null,
        };
    }

    /**
     * Count the number of pages in a PDF, or null when it cannot be read.
     */
    public function pageCount(string $path): ?int
    {
        $process = new Process([$this->binary(), '--show-npages', $path]);

        try {
            $process->run();
        } catch (ProcessStartFailedException $e) {
            $this->warnMissing($e);

            return null;
        }

        if (! $process->isSuccessful()) {
            return null;
        }

        $output = trim($process->getOutput());

        return is_numeric($output) ? (int) $output : null;
    }

    private function binary(): string
    {
        return (string) config('nexus.qpdf_path', 'qpdf');
    }

    private function warnMissing(ProcessStartFailedException $e): void
    {
        Log::warning('qpdf binary unavailable; skipping PDF inspection.', [
            'binary' => $this->binary(),
            'error' => $e->getMessage(),
        ]);
    }
}
