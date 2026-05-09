<?php

namespace App\Livewire;

use App\Contracts\SdiProvider;
use App\Models\Contact;
use App\Models\Invoice;
use App\Services\ReportService;
use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
        $fiscalYear = session('fiscal_year', now()->year);
        $stats = app(ReportService::class)->getDashboardStats($fiscalYear);

        return view('livewire.dashboard', array_merge($stats, [
            'fiscalYear' => $fiscalYear,
            'isCurrentYear' => $fiscalYear === now()->year,
            'hasInvoices' => Invoice::whereYear('date', $fiscalYear)->exists(),
            'hasSdi' => app(SdiProvider::class)->isConfigured(),
            'hasContacts' => Contact::exists(),
        ]));
    }
}
