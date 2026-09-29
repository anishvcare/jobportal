<?php

namespace App\Console\Commands;

use App\Models\BulkExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Removes expired bulk exports: deletes each expired ZIP from its private disk
 * and deletes the row. Scheduled hourly in routes/console.php.
 *
 * download_audits rows reference the export by nullable FK, so removing an
 * export never destroys the audit trail of who downloaded it.
 */
class PurgeExpiredExports extends Command
{
    protected $signature = 'exports:purge-expired';

    protected $description = 'Delete expired bulk export ZIPs and their rows.';

    public function handle(): int
    {
        $count = 0;

        BulkExport::query()->expired()->get()->each(function (BulkExport $export) use (&$count) {
            if ($export->path && Storage::disk($export->disk)->exists($export->path)) {
                Storage::disk($export->disk)->delete($export->path);
            }

            $export->delete();
            $count++;
        });

        $this->info("Purged {$count} expired export(s).");

        return self::SUCCESS;
    }
}
