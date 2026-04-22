<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Production seeders:
     * - SequenceSeeder: Essential invoice numbering sequences
     *
     * Development seeders (only in non-production):
     * - DevelopmentSeeder: Sample users, contacts, and products
     */
    public function run(): void
    {
        // Always run production seeders - safe for all environments
        $this->call([
            SequenceSeeder::class,
            BackupSettingsSeeder::class,
        ]);
    }
}
