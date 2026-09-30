<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('company_name')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('website')->nullable();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('state_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->string('city')->nullable();
            $table->string('logo_disk')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index('status', 'employer_profiles_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employer_profiles');
    }
};
