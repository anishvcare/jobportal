<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employer_profile_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique('job_posts_slug_unique');
            $table->text('description');
            $table->foreignId('job_category_id')->constrained()->restrictOnDelete(); // a trade
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('state_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->string('city')->nullable();
            $table->foreignId('education_level_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('experience_min')->nullable();
            $table->unsignedSmallInteger('experience_max')->nullable();
            $table->unsignedInteger('vacancies')->default(1);
            $table->unsignedInteger('salary_min')->nullable();
            $table->unsignedInteger('salary_max')->nullable();
            $table->string('salary_currency', 3)->nullable();
            $table->date('deadline')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            // job_category_id already has an FK index from constrained().
            $table->index(['published_at', 'is_hidden'], 'job_posts_published_hidden_idx');
            $table->index(['country_id', 'state_id', 'district_id'], 'job_posts_location_idx');
        });

        // Optional MySQL-only FULLTEXT for keyword search acceleration. The board
        // query uses portable LIKE and must NOT depend on this index existing.
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE job_posts ADD FULLTEXT job_posts_title_description_fulltext (title, description)');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE job_posts DROP INDEX job_posts_title_description_fulltext');
        }

        Schema::dropIfExists('job_posts');
    }
};
