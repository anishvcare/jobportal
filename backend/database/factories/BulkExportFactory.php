<?php

namespace Database\Factories;

use App\Models\BulkExport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BulkExport>
 */
class BulkExportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'requested_by' => User::factory()->admin(),
            'candidate_ids' => [],
            'status' => BulkExport::STATUS_QUEUED,
            'progress' => 0,
            'disk' => 'documents',
            'path' => null,
            'error' => null,
            'expires_at' => null,
        ];
    }

    public function ready(): static
    {
        return $this->state(fn () => [
            'status' => BulkExport::STATUS_READY,
            'progress' => 100,
            'path' => 'exports/'.fake()->uuid().'.zip',
            'expires_at' => now()->addDay(),
        ]);
    }
}
