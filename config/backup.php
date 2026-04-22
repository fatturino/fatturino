<?php

use Spatie\DbDumper\Compressors\GzipCompressor;
use Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes;

return [

    // When true, the backup UI is hidden and the scheduler is disabled.
    // fatturino-cloud sets this to true and manages backups externally.
    'managed_by_env' => env('BACKUP_MANAGED_BY_ENV', false),

    'backup' => [
        // Use tenant slug as backup name for clean S3 paths
        'name' => env('BACKUP_NAME', env('APP_NAME', 'fatturino')),

        'source' => [
            'files' => [
                // Include persisted documents (XML and PDF) and the company logo
                'include' => [
                    storage_path('app/private/documents'),
                    storage_path('app/public'),
                ],
                'exclude' => [],
                'follow_links' => false,
                'ignore_unreadable_directories' => false,
                'relative_path' => null,
            ],

            // Back up only the SQLite database
            'databases' => ['sqlite'],
        ],

        'database_dump_compressor' => GzipCompressor::class,

        'database_dump_file_timestamp_format' => null,

        'database_dump_filename_base' => 'database',

        'database_dump_file_extension' => '',

        'destination' => [
            'compression_method' => ZipArchive::CM_DEFAULT,
            'compression_level' => 9,
            'filename_prefix' => '',

            // Store backups on S3
            'disks' => ['s3'],

            'continue_on_failure' => false,
        ],

        'temporary_directory' => storage_path('app/backup-temp'),

        'password' => env('BACKUP_ARCHIVE_PASSWORD'),

        'encryption' => 'default',

        'verify_backup' => false,

        'tries' => 1,

        'retry_delay' => 0,
    ],

    // Notifications disabled: tenants have no mail configuration
    'notifications' => [
        'notifications' => [
            \Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification::class => [],
            \Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification::class => [],
            \Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification::class => [],
            \Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification::class => [],
            \Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification::class => [],
            \Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification::class => [],
        ],

        'notifiable' => \Spatie\Backup\Notifications\Notifiable::class,

        'mail' => [
            'to' => env('BACKUP_MAIL_TO_ADDRESS', 'noreply@fatturino.it'),
            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'noreply@fatturino.it'),
                'name' => env('MAIL_FROM_NAME', 'Fatturino'),
            ],
        ],

        'slack' => [
            'webhook_url' => '',
            'channel' => null,
            'username' => null,
            'icon' => null,
        ],

        'discord' => [
            'webhook_url' => '',
            'username' => '',
            'avatar_url' => '',
        ],

        'webhook' => [
            'url' => '',
        ],
    ],

    'log_channel' => null,

    'monitor_backups' => [
        [
            'name' => env('BACKUP_NAME', env('APP_NAME', 'fatturino')),
            'disks' => ['s3'],
            'health_checks' => [
                MaximumAgeInDays::class => 1,
                MaximumStorageInMegabytes::class => 5000,
            ],
        ],
    ],

    'cleanup' => [
        'strategy' => DefaultStrategy::class,

        'default_strategy' => [
            'keep_all_backups_for_days' => 7,
            'keep_daily_backups_for_days' => 16,
            'keep_weekly_backups_for_weeks' => 8,
            'keep_monthly_backups_for_months' => 4,
            'keep_yearly_backups_for_years' => 2,
            'delete_oldest_backups_when_using_more_megabytes_than' => 5000,
        ],

        'tries' => 1,

        'retry_delay' => 0,
    ],

];
