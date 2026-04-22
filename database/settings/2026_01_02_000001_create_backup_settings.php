<?php

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Use insertOrIgnore so this migration is safe to run on an existing database
        // where BackupSettingsSeeder may have already populated these rows.
        $defaults = [
            'enabled'                     => false,
            'frequency'                   => 'daily',
            'time'                        => '02:00',
            'day_of_week'                 => 1,
            'day_of_month'                => 1,
            'aws_access_key_id'           => null,
            'aws_secret_access_key'       => null,
            'aws_default_region'          => null,
            'aws_bucket'                  => null,
            'aws_endpoint'                => null,
            'aws_use_path_style_endpoint' => false,
        ];

        foreach ($defaults as $name => $value) {
            DB::table('settings')->insertOrIgnore([
                'group'      => 'backup',
                'name'       => $name,
                'locked'     => false,
                'payload'    => json_encode($value),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
