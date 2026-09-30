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
        $process = $this->newProcess([$this->binary(), '--is-encrypted', $path]);

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
        $process = $this->newProcess([$this->binary(), '--show-npages', $path]);

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
        return (string) config('nexus.qpdf_binary', 'qpdf');
    }

    /**
     * Build a qpdf Process. When a qpdf library path is configured it is
     * exported as LD_LIBRARY_PATH on the child process only (merged with the
     * inherited environment); otherwise no env override is applied so the
     * behaviour is byte-identical to invoking qpdf directly.
     *
     * @param  list<string>  $arguments
     */
    private function newProcess(array $arguments): Process
    {
        return new Process($arguments, null, $this->processEnv());
    }

    /**
     * Environment overrides for the qpdf child process, or null when none.
     *
     * @return array<string, string>|null
     */
    private function processEnv(): ?array
    {
        $libraryPath = config('nexus.qpdf_library_path');

        if (is_string($libraryPath) && $libraryPath !== '') {
            return ['LD_LIBRARY_PATH' => $libraryPath];
        }

        return null;
    }

    private function warnMissing(ProcessStartFailedException $e): void
    {
        Log::warning('qpdf binary unavailable; skipping PDF inspection.', [
            'binary' => $this->binary(),
            'error' => $e->getMessage(),
        ]);
    }
}
