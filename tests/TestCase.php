<?php

namespace Tests;

use Database\Seeders\CompanySettingsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Setup the test environment
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Seed company settings only if settings table exists
        // (i.e., only for tests using RefreshDatabase)
        if (\Schema::hasTable('settings')) {
            $this->seed(CompanySettingsSeeder::class);
        }
    }
}
