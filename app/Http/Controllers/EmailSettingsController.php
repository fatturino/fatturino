<?php

namespace App\Http\Controllers;

use App\Services\DocumentMailer;
use App\Settings\CompanySettings;
use App\Settings\EmailSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailSettingsController extends Controller
{
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
                'mail_provider' => 'required|in:smtp,scaleway_tem',
                'smtp_host' => 'nullable|string',
                'smtp_port' => 'nullable|string',
                'smtp_username' => 'nullable|string',
                'smtp_password' => 'nullable|string',
                'smtp_encryption' => 'nullable|string',
                'scaleway_tem_region' => 'nullable|string',
                'scaleway_tem_project_id' => 'nullable|string',
                'scaleway_tem_secret_key' => 'nullable|string',
            ]);
        }

        $validated = $request->validate($rules);

        $settings->fill($validated);
        $settings->save();

        return redirect()->route('settings.email');
    }

    public function testConnection(Request $request, EmailSettings $settings): RedirectResponse
    {
        try {
            if (! config('email.managed_by_env', false)) {
                $settings->fill($request->validate([
                    'mail_provider' => 'required|in:smtp,scaleway_tem',
                    'smtp_host' => 'nullable|string',
                    'smtp_port' => 'nullable|string',
                    'smtp_username' => 'nullable|string',
                    'smtp_password' => 'nullable|string',
                    'smtp_encryption' => 'nullable|string',
                    'scaleway_tem_region' => 'nullable|string',
                    'scaleway_tem_project_id' => 'nullable|string',
                    'scaleway_tem_secret_key' => 'nullable|string',
                    'from_address' => 'nullable|email',
                    'from_name' => 'nullable|string',
                ]));
            }

            $error = (new DocumentMailer($settings, app(CompanySettings::class)))->testConnection();
            if ($error !== null) {
                throw new \RuntimeException($error);
            }

            return back()->with('success', 'Connessione email riuscita.');
        } catch (\Exception $e) {
            return back()->withErrors(['smtp' => $e->getMessage()]);
        }
    }
}
