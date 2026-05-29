<?php

namespace App\Http\Middleware;

use App\Models\FiscalDocument;
use App\Settings\CompanySettings;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $fiscalYear = (int) session('fiscal_year', now()->year);

        $minDate = FiscalDocument::withoutGlobalScopes()->min('date');
        $minYear = $minDate ? (int) Carbon::parse($minDate)->year : now()->year;
        $availableYears = range(now()->year, $minYear);
        $fiscalRegime = null;
        $rf19SelfInvoicesEnabled = false;

        try {
            $companySettings = app(CompanySettings::class);
            $fiscalRegime = $companySettings->company_fiscal_regime;
            $rf19SelfInvoicesEnabled = $companySettings->rf19_self_invoices_enabled;
        } catch (\Throwable) {
            // Company settings may be unavailable before initial setup/migrations.
        }

        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'auth.user' => fn () => $request->user() ? $request->user()->only('name', 'email') : null,
            'fiscalYear' => $fiscalYear,
            'availableYears' => $availableYears,
            'fiscalRegime' => $fiscalRegime,
            'rf19SelfInvoicesEnabled' => $rf19SelfInvoicesEnabled,
            'title' => fn () => $request->route()?->defaults['title'] ?? null,
            'breadcrumbs' => fn () => $request->route()?->defaults['breadcrumbs'] ?? [],
            'flash' => [
                'toast' => fn () => $request->session()->get('toast'),
            ],
        ];
    }
}
