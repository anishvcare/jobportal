<?php

namespace Database\Factories;

use App\Models\CandidateProfile;
use App\Models\DownloadAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DownloadAudit>
 */
class DownloadAuditFactory extends Factory
{
    public function definition(): array
    {
        return [
            'actor_user_id' => User::factory()->admin(),
            'candidate_profile_id' => CandidateProfile::factory(),
            'kind' => DownloadAudit::KIND_RESUME,
            'bulk_export_id' => null,
            'document_id' => null,
            'ip' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }
}
