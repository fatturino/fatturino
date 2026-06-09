<?php

namespace App\Http\Controllers;

use App\Contracts\SdiProvider;
use App\Models\Contact;
use App\Models\FiscalDocument;
use App\Services\PostHogTelemetryService;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $fiscalYear = session('fiscal_year', now()->year);
        $stats = app(ReportService::class)->getDashboardStats($fiscalYear);
        app(PostHogTelemetryService::class)->capture('dashboard_viewed', [
            'fiscal_year' => (int) $fiscalYear,
        ], $request->user());

        return Inertia::render('Dashboard', array_merge($stats, [
            'fiscalYear' => $fiscalYear,
            'isCurrentYear' => $fiscalYear === now()->year,
            'hasInvoices' => FiscalDocument::whereYear('date', $fiscalYear)->exists(),
            'hasSdi' => app(SdiProvider::class)->isActivated(),
            'hasContacts' => Contact::exists(),
        ]));
    }
}
