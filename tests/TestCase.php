<?php

namespace Tests;

use Database\Seeders\CompanySettingsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;

abstract class TestCase extends BaseTestCase
{
    /**
     * Setup the test environment
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->ensurePassportKeys();

        // Seed company settings only if settings table exists
        // (i.e., only for tests using RefreshDatabase)
        if (\Schema::hasTable('settings')) {
            $this->seed(CompanySettingsSeeder::class);
        }
    }

    private function ensurePassportKeys(): void
    {
        $privateKey = storage_path('oauth-private.key');
        $publicKey = storage_path('oauth-public.key');

        if (is_file($privateKey) && is_file($publicKey)) {
            return;
        }

        Artisan::call('passport:keys', ['--force' => true]);
    }
}
