<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_packs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_profile_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('disk')->default('documents');
            $table->string('path')->nullable();
            $table->string('fingerprint')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('generated_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_packs');
    }
};
