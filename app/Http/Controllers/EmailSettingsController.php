<?php

namespace App\Http\Controllers;

use App\Services\DocumentMailer;
use App\Settings\EmailSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailSettingsController extends Controller
{
    public function index(EmailSettings $settings): Response
    {
        return Inertia::render('Settings/Email', [
            'settings' => $settings->toArray(),
            'smtpManagedByEnv' => config('email.managed_by_env', false),
            'encryptionOptions' => [
                ['value' => '', 'label' => 'Nessuna'],
                ['value' => 'tls', 'label' => 'TLS'],
                ['value' => 'ssl', 'label' => 'SSL'],
            ],
        ]);
    }

    public function update(Request $request, EmailSettings $settings): RedirectResponse
    {
        $rules = [
            'from_address' => 'nullable|email',
            'from_name' => 'nullable|string',
            'template_sales_subject' => 'nullable|string',
            'template_sales_body' => 'nullable|string',
            'auto_send_sales' => 'boolean',
            'template_proforma_subject' => 'nullable|string',
            'template_proforma_body' => 'nullable|string',
            'auto_send_proforma' => 'boolean',
        ];

        if (! config('email.managed_by_env', false)) {
            $rules = array_merge($rules, [
                'smtp_host' => 'nullable|string',
                'smtp_port' => 'nullable|string',
                'smtp_username' => 'nullable|string',
                'smtp_password' => 'nullable|string',
                'smtp_encryption' => 'nullable|string',
            ]);
        }

        $validated = $request->validate($rules);

        $settings->fill($validated);
        $settings->save();

        return redirect()->route('settings.email');
    }

    public function testConnection(Request $request): RedirectResponse
    {
        try {
            $config = [
                'host' => $request->input('smtp_host'),
                'port' => $request->input('smtp_port'),
                'username' => $request->input('smtp_username'),
                'password' => $request->input('smtp_password'),
                'encryption' => $request->input('smtp_encryption'),
            ];

            $mailer = app(DocumentMailer::class);
            $mailer->testConnection($config);

            return back()->with('success', 'Connessione SMTP riuscita.');
        } catch (\Exception $e) {
            return back()->withErrors(['smtp' => $e->getMessage()]);
        }
    }
}
