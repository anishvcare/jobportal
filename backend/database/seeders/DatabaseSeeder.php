<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeds lookup lists (idempotent).
     */
    public function run(): void
    {
        $this->call(LookupSeeder::class);
    }
}
