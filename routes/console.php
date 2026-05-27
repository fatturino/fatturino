<?php

use App\Settings\BackupSettings;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily check for gaps in invoice numbering sequences. Reports to Sentry when gaps found.
Schedule::command('invoices:check-sequences')->dailyAt('04:00');

if (config('demo.enabled')) {
    $interval = max(1, min(60, (int) config('demo.reset_interval_minutes', 60)));
    Schedule::command('demo:refresh')
        ->cron("*/{$interval} * * * *")
        ->withoutOverlapping();
}

Schedule::command('openapi:reconcile')
    ->hourlyAt(15)
    ->withoutOverlapping()
    ->runInBackground();

// Register backup schedule only when: not managed by env (self-hosted) and enabled in settings.
// In managed mode backup cadence is controlled via env.
// Guard with try/catch so the console still works during first-run migrations.
try {
    if (! config('backup.managed_by_env')) {
        $backup = app(BackupSettings::class);

        if ($backup->enabled) {
            $backupSchedule = Schedule::command('backup:run --disable-notifications');

            match ($backup->frequency) {
                'weekly' => $backupSchedule->weeklyOn($backup->day_of_week, $backup->time),
                'monthly' => $backupSchedule->monthlyOn($backup->day_of_month, $backup->time),
                default => $backupSchedule->dailyAt($backup->time),
            };

            Schedule::command('backup:clean --disable-notifications')->dailyAt('03:30');
        }
    } else {
        $runAt = (string) env('BACKUP_RUN_AT', '02:00');
        $cleanAt = (string) env('BACKUP_CLEAN_AT', '03:30');

        Schedule::command('backup:run --disable-notifications')->dailyAt($runAt);
        Schedule::command('backup:clean --disable-notifications')->dailyAt($cleanAt);
    }
} catch (Throwable) {
    // Settings table not yet created (first migration run) — skip silently.
}
