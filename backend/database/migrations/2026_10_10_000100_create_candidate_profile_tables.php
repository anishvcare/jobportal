<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('full_name')->nullable(); // exactly as printed on the passport
            $table->date('dob')->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('whatsapp', 30)->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('pincode', 20)->nullable();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('state_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->text('passport_number')->nullable(); // holds the encrypted value
            $table->boolean('has_passport')->default(false);
            $table->date('passport_expiry')->nullable();
            $table->text('summary')->nullable();
            $table->unsignedTinyInteger('wizard_step')->default(0);
            $table->timestamp('consent_at')->nullable();
            $table->string('consent_version', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('candidate_educations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('education_level_id')->nullable()->constrained()->nullOnDelete();
            $table->string('institution')->nullable();
            $table->string('field_of_study')->nullable();
            $table->unsignedSmallInteger('year_completed')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['candidate_profile_id', 'sort_order']);
        });

        Schema::create('candidate_experiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_profile_id')->constrained()->cascadeOnDelete();
            $table->string('job_title');
            $table->string('company')->nullable();
            $table->foreignId('job_category_id')->nullable()->constrained()->nullOnDelete(); // a trade
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_current')->default(false);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['candidate_profile_id', 'sort_order']);
        });

        Schema::create('candidate_skill', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['candidate_profile_id', 'skill_id']);
        });

        Schema::create('candidate_language', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('language_id')->constrained()->cascadeOnDelete();
            $table->string('proficiency', 20)->nullable(); // basic/conversational/fluent/native
            $table->timestamps();

            $table->unique(['candidate_profile_id', 'language_id']);
        });

        Schema::create('candidate_preferred_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_category_id')->constrained()->cascadeOnDelete(); // a trade child
            $table->timestamps();

            $table->unique(['candidate_profile_id', 'job_category_id']);
        });

        Schema::create('candidate_preferred_country', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['candidate_profile_id', 'country_id']);
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_profile_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('disk')->default('documents');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 150);
            $table->unsignedBigInteger('size');
            $table->unsignedSmallInteger('page_count')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->char('sha256', 64)->nullable();
            $table->timestamps();

            $table->index(['candidate_profile_id', 'type', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
        Schema::dropIfExists('candidate_preferred_country');
        Schema::dropIfExists('candidate_preferred_category');
        Schema::dropIfExists('candidate_language');
        Schema::dropIfExists('candidate_skill');
        Schema::dropIfExists('candidate_experiences');
        Schema::dropIfExists('candidate_educations');
        Schema::dropIfExists('candidate_profiles');
    }
};
