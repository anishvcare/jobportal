<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->char('iso2', 2)->unique();
            $table->char('iso3', 3)->nullable();
            // True when states/regions are structured for this country (India, Russia).
            $table->boolean('has_states')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(1000);
            $table->timestamps();

            $table->index(['is_active', 'sort_order', 'name']);
        });

        Schema::create('states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 40)->nullable(); // state, union_territory, oblast, republic, krai, ...
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['country_id', 'name']);
        });

        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('state_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['state_id', 'name']);
        });

        // Two levels: groups (parent_id null: Skilled, Unskilled) and trades (Electrician, Welder, ...).
        Schema::create('job_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->index()
                ->constrained('job_categories')->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(1000);
            $table->timestamps();
        });

        Schema::create('education_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            // Higher rank = higher qualification; enables "at least X" filters.
            $table->unsignedSmallInteger('rank')->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 10)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skills');
        Schema::dropIfExists('languages');
        Schema::dropIfExists('education_levels');
        Schema::dropIfExists('job_categories');
        Schema::dropIfExists('districts');
        Schema::dropIfExists('states');
        Schema::dropIfExists('countries');
    }
};
