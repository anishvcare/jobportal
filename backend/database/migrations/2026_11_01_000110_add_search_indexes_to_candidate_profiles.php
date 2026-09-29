<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_profiles', function (Blueprint $table) {
            // state_id/district_id/country_id already have FK indexes from constrained().
            $table->index('dob', 'candidate_profiles_dob_idx');
            $table->index('gender', 'candidate_profiles_gender_idx');
            $table->index('has_passport', 'candidate_profiles_has_passport_idx');
            $table->index('passport_expiry', 'candidate_profiles_passport_expiry_idx');
        });

        // FULLTEXT keyword search on the name is a MySQL-only feature; SQLite has no FULLTEXT.
        // Search endpoints fall back to LIKE on SQLite (guarded by driver at query time).
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE candidate_profiles ADD FULLTEXT candidate_profiles_full_name_fulltext (full_name)');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE candidate_profiles DROP INDEX candidate_profiles_full_name_fulltext');
        }

        Schema::table('candidate_profiles', function (Blueprint $table) {
            $table->dropIndex('candidate_profiles_dob_idx');
            $table->dropIndex('candidate_profiles_gender_idx');
            $table->dropIndex('candidate_profiles_has_passport_idx');
            $table->dropIndex('candidate_profiles_passport_expiry_idx');
        });
    }
};
