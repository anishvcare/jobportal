<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_profile_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('applied');
            $table->text('cover_note')->nullable();
            $table->timestamps();

            // Enforces no double-apply for the same job by the same candidate.
            $table->unique(['job_post_id', 'candidate_profile_id'], 'applications_job_candidate_unique');
            $table->index(['candidate_profile_id', 'status'], 'applications_candidate_status_idx');
            $table->index(['job_post_id', 'status'], 'applications_job_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
