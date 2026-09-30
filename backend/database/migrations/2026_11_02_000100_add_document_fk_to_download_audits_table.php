<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Short, portable (<=64 char) index name for the FK constraint. */
    private const FK_NAME = 'download_audits_document_fk';

    public function up(): void
    {
        // The download_audits.document_id column already exists (created in
        // 2026_11_01_000100 as a bare nullable column). Add only the FK so a
        // deleted document nulls the audit reference instead of dangling,
        // mirroring the nullOnDelete pattern used for candidate_profile_id.
        Schema::table('download_audits', function (Blueprint $table) {
            $table->foreign('document_id', self::FK_NAME)
                ->references('id')->on('documents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // SQLite cannot drop a foreign key by name (it rebuilds tables and has
        // no ALTER ... DROP CONSTRAINT). The FK is harmless on the way down and
        // disappears when the table is dropped, so skip the drop there.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('download_audits', function (Blueprint $table) {
            $table->dropForeign(self::FK_NAME);
        });
    }
};
