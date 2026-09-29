<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('download_audits', function (Blueprint $table) {
            $table->id();
            // The admin who performed the download.
            $table->foreignId('actor_user_id')->constrained('users')->cascadeOnDelete();
            // Whose candidate the download concerned. Nullable + nullOnDelete so hard-deleting a
            // candidate leaves the audit row intact with a null candidate id.
            $table->unsignedBigInteger('candidate_profile_id')->nullable();
            $table->foreign('candidate_profile_id', 'download_audits_candidate_profile_fk')
                ->references('id')->on('candidate_profiles')->nullOnDelete();
            $table->string('kind', 20); // document, resume, pack, bulk_zip
            $table->foreignId('bulk_export_id')->nullable()->constrained('bulk_exports')->nullOnDelete();
            // Which document (for kind=document). Kept simple: nullable, no FK cascade requirement.
            $table->unsignedBigInteger('document_id')->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['actor_user_id', 'created_at'], 'download_audits_actor_created_at_idx');
            $table->index('candidate_profile_id', 'download_audits_candidate_profile_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('download_audits');
    }
};
