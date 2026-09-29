<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete(); // the admin who requested the export
            $table->json('candidate_ids');
            $table->string('status', 20)->default('queued'); // queued, processing, ready, failed
            $table->unsignedSmallInteger('progress')->default(0); // build progress as a 0-100 percent value
            $table->string('disk')->default('documents');
            $table->string('path')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['requested_by', 'created_at'], 'bulk_exports_requested_by_created_at_idx');
            $table->index('expires_at', 'bulk_exports_expires_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_exports');
    }
};
