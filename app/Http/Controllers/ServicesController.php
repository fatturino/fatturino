<?php

namespace App\Http\Controllers;

use App\Services\PostHogTelemetryService;
use App\Settings\BackupSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ServicesController extends Controller
{
    public function index(BackupSettings $backup): Response
    {
        return Inertia::render('Settings/Services', [
            'backup' => $backup->toArray(),
            'backupManagedByEnv' => (bool) config('backup.managed_by_env'),
            'frequencyOptions' => [
                ['value' => 'daily', 'label' => 'Giornaliero'],
                ['value' => 'weekly', 'label' => 'Settimanale'],
                ['value' => 'monthly', 'label' => 'Mensile'],
            ],
        ]);
    }

    public function updateBackup(Request $request, BackupSettings $settings): RedirectResponse
    {
        $rules = [
            'enabled' => 'boolean',
            'frequency' => 'required|in:daily,weekly,monthly',
            'time' => 'required',
            'day_of_week' => 'required_if:frequency,weekly|integer|between:0,6',
            'day_of_month' => 'required_if:frequency,monthly|integer|between:1,28',
            'aws_endpoint' => 'nullable|url',
            'aws_use_path_style_endpoint' => 'boolean',
        ];

        if ($request->boolean('enabled')) {
            $rules['aws_access_key_id'] = 'required|string';
            $rules['aws_secret_access_key'] = 'required|string';
            $rules['aws_default_region'] = 'required|string';
            $rules['aws_bucket'] = 'required|string';
        }

        $validated = $request->validate($rules);

        $settings->fill($validated);
        $settings->save();

        return redirect()->route('settings.services');
    }

    public function testConnection(Request $request): RedirectResponse
    {
        try {
            $disk = Storage::build([
                'driver' => 's3',
                'key' => $request->input('aws_access_key_id'),
                'secret' => $request->input('aws_secret_access_key'),
                'region' => $request->input('aws_default_region'),
                'bucket' => $request->input('aws_bucket'),
                'endpoint' => $request->input('aws_endpoint'),
                'use_path_style_endpoint' => $request->boolean('aws_use_path_style_endpoint'),
            ]);

            $disk->files('/');
            app(PostHogTelemetryService::class)->capture('service_connection_tested', [
                'service' => 's3',
                'success' => true,
            ], $request->user());

            return back()->with('success', 'Connessione S3 riuscita.');
        } catch (\Exception $e) {
            app(PostHogTelemetryService::class)->capture('service_connection_tested', [
                'service' => 's3',
                'success' => false,
            ], $request->user());

            return back()->withErrors(['s3' => $e->getMessage()]);
        }
    }
}
