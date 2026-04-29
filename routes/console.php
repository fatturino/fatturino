<?php

use App\Settings\BackupSettings;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command("inspire", function () {
    $this->comment(Inspiring::quote());
})->purpose("Display an inspiring quote");

// Daily check for gaps in invoice numbering sequences. Reports to Sentry when gaps found.
Schedule::command('invoices:check-sequences')->dailyAt('04:00');

// Register backup schedule only when: not managed by env (self-hosted) and enabled in settings.
// Guard with try/catch so the console still works during first-run migrations.
try {
    if (! config('backup.managed_by_env')) {
        $backup = app(BackupSettings::class);

        if ($backup->enabled) {
            $backupSchedule = Schedule::command('backup:run --disable-notifications');

            match ($backup->frequency) {
                'weekly'  => $backupSchedule->weeklyOn($backup->day_of_week, $backup->time),
                'monthly' => $backupSchedule->monthlyOn($backup->day_of_month, $backup->time),
                default   => $backupSchedule->dailyAt($backup->time),
            };

            Schedule::command('backup:clean --disable-notifications')->dailyAt('03:30');
        }
    }
} catch (\Throwable) {
    // Settings table not yet created (first migration run) — skip silently.
}
