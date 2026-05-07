<?php

use App\Models\Contact;
use App\Models\Invoice;
use App\Models\PurchaseInvoice;
use App\Services\ReportService;
use Carbon\Carbon;

// Pin time to a known date so all tests are deterministic
beforeEach(function () {
    Carbon::setTestNow('2026-06-15');

    $this->service = app(ReportService::class);
    $this->contact = Contact::create(['name' => 'Client A']);
});

afterEach(function () {
    Carbon::setTestNow(null);
});

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Creates an invoice with pre-set totals (no lines needed for report tests).
 */
function makeInvoice(string $date, int $totalGross, array $extra = []): Invoice
{
    static $seq = 0;

    return Invoice::create(array_merge([
        'number' => 'TEST-'.++$seq,
        'date' => $date,
        'contact_id' => Contact::first()->id,
        'total_gross' => $totalGross,
        'total_net' => $totalGross,
        'total_vat' => 0,
    ], $extra));
}

// ---------------------------------------------------------------------------
// Revenue this month
// ---------------------------------------------------------------------------

test('revenue this month only counts invoices in current month', function () {
    makeInvoice('2026-06-01', 10000); // this month
    makeInvoice('2026-06-30', 5000);  // this month
    makeInvoice('2026-05-20', 9999);  // last month — must be excluded
    makeInvoice('2025-06-15', 8888);  // last year — must be excluded

    expect($this->service->revenueThisMonth())->toBe(15000);
});

// ---------------------------------------------------------------------------
// Revenue last month
// ---------------------------------------------------------------------------

test('revenue last month only counts invoices in previous month', function () {
    makeInvoice('2026-05-01', 20000); // last month
    makeInvoice('2026-05-31', 3000);  // last month
    makeInvoice('2026-06-10', 9999);  // this month — must be excluded

    expect($this->service->revenueLastMonth())->toBe(23000);
});

// ---------------------------------------------------------------------------
// Revenue YTD
// ---------------------------------------------------------------------------

test('revenue ytd sums all invoices since start of year', function () {
    makeInvoice('2026-01-01', 10000);
    makeInvoice('2026-03-15', 20000);
    makeInvoice('2026-06-15', 5000);
    makeInvoice('2025-12-31', 9999); // previous year — must be excluded

    expect($this->service->revenueYtd())->toBe(35000);
});

// ---------------------------------------------------------------------------
// Invoice counts
// ---------------------------------------------------------------------------

test('invoices this month counts only current month', function () {
    makeInvoice('2026-06-01', 1000);
    makeInvoice('2026-06-14', 1000);
    makeInvoice('2026-05-30', 1000); // last month — excluded

    expect($this->service->invoicesThisMonth())->toBe(2);
});

test('invoices ytd counts only invoices since start of year', function () {
    makeInvoice('2026-01-01', 1000);
    makeInvoice('2026-06-15', 1000);
    makeInvoice('2025-12-31', 1000); // previous year — excluded

    expect($this->service->invoicesYtd())->toBe(2);
});

// ---------------------------------------------------------------------------
// Active clients
// ---------------------------------------------------------------------------

test('active clients count deduplicates contacts with multiple invoices', function () {
    $clientB = Contact::create(['name' => 'Client B']);

    makeInvoice('2026-03-01', 1000); // $this->contact
    makeInvoice('2026-04-01', 1000); // $this->contact again — should not double-count
    makeInvoice('2026-05-01', 1000, ['contact_id' => $clientB->id]);

    expect($this->service->activeClientsCount())->toBe(2);
});

test('active clients excludes contacts whose invoices are from a previous year', function () {
    makeInvoice('2025-12-31', 1000); // last year

    expect($this->service->activeClientsCount())->toBe(0);
});

// ---------------------------------------------------------------------------
// Total contacts
// ---------------------------------------------------------------------------

test('total contacts count includes contacts without invoices', function () {
    Contact::create(['name' => 'No Invoices Client']);

    // $this->contact already exists + 1 new = 2
    expect($this->service->totalContactsCount())->toBe(2);
});

// ---------------------------------------------------------------------------
// Average invoice value YTD
// ---------------------------------------------------------------------------

test('average invoice value ytd returns zero when no invoices exist', function () {
    expect($this->service->averageInvoiceValueYtd())->toBe(0);
});

test('average invoice value ytd calculates integer average correctly', function () {
    makeInvoice('2026-01-10', 10000);
    makeInvoice('2026-03-20', 30000);

    // Average of 10000 and 30000 = 20000
    expect($this->service->averageInvoiceValueYtd())->toBe(20000);
});

// ---------------------------------------------------------------------------
// Month change percent
// ---------------------------------------------------------------------------

test('month change percent is positive when this month revenue is higher', function () {
    makeInvoice('2026-05-01', 10000); // last month
    makeInvoice('2026-06-01', 15000); // this month

    // Change: (15000 - 10000) / 10000 * 100 = +50%
    expect($this->service->monthChangePercent())->toBe(50.0);
});

test('month change percent is negative when this month revenue is lower', function () {
    makeInvoice('2026-05-01', 20000); // last month
    makeInvoice('2026-06-01', 10000); // this month

    // Change: (10000 - 20000) / 20000 * 100 = -50%
    expect($this->service->monthChangePercent())->toBe(-50.0);
});

test('month change percent is 100 when last month had no revenue', function () {
    makeInvoice('2026-06-01', 5000); // only this month

    expect($this->service->monthChangePercent())->toBe(100.0);
});

test('month change percent is 0 when both months have no revenue', function () {
    expect($this->service->monthChangePercent())->toBe(0.0);
});

// ---------------------------------------------------------------------------
// Withholding tax YTD
// ---------------------------------------------------------------------------

test('withholding tax ytd only sums invoices with withholding enabled', function () {
    makeInvoice('2026-02-01', 10000, [
        'withholding_tax_enabled' => true,
        'withholding_tax_amount' => 2000,
    ]);
    makeInvoice('2026-03-01', 10000, [
        'withholding_tax_enabled' => false, // excluded
        'withholding_tax_amount' => 9999,
    ]);
    makeInvoice('2026-04-01', 10000, [
        'withholding_tax_enabled' => true,
        'withholding_tax_amount' => 3000,
    ]);

    expect($this->service->withholdingTaxYtd())->toBe(5000);
});

// ---------------------------------------------------------------------------
// VAT collected YTD
// ---------------------------------------------------------------------------

test('vat collected ytd sums total vat from all invoices this year', function () {
    makeInvoice('2026-01-15', 12200, ['total_vat' => 2200]);
    makeInvoice('2026-05-10', 12200, ['total_vat' => 2200]);
    makeInvoice('2025-12-01', 12200, ['total_vat' => 9999]); // previous year — excluded

    expect($this->service->vatCollectedYtd())->toBe(4400);
});

// ---------------------------------------------------------------------------
// Top clients
// ---------------------------------------------------------------------------

test('top clients returns contacts ranked by revenue descending', function () {
    $clientB = Contact::create(['name' => 'Client B']);
    $clientC = Contact::create(['name' => 'Client C']);

    makeInvoice('2026-01-01', 5000, ['contact_id' => $clientB->id]);
    makeInvoice('2026-01-02', 30000, ['contact_id' => $clientC->id]);
    makeInvoice('2026-01-03', 20000); // $this->contact

    $top = $this->service->topClients(3);

    expect($top)->toHaveCount(3);
    expect($top->first()->contact_id)->toBe($clientC->id);   // 30000
    expect($top->get(1)->contact_id)->toBe($this->contact->id); // 20000
    expect($top->last()->contact_id)->toBe($clientB->id);    // 5000
});

test('top clients respects the limit parameter', function () {
    for ($i = 1; $i <= 6; $i++) {
        $contact = Contact::create(['name' => "Client $i"]);
        makeInvoice("2026-0{$i}-01", $i * 1000, ['contact_id' => $contact->id]);
    }

    expect($this->service->topClients(3))->toHaveCount(3);
});

// ---------------------------------------------------------------------------
// Recent invoices
// ---------------------------------------------------------------------------

test('recent invoices returns most recent first', function () {
    $inv1 = makeInvoice('2026-01-01', 1000);
    $inv2 = makeInvoice('2026-06-15', 1000);
    $inv3 = makeInvoice('2026-03-10', 1000);

    $recent = $this->service->recentInvoices();

    expect($recent->first()->id)->toBe($inv2->id);
    expect($recent->last()->id)->toBe($inv1->id);
});

test('recent invoices respects the limit parameter', function () {
    for ($i = 1; $i <= 10; $i++) {
        makeInvoice("2026-0{$i}-01", 1000);
    }

    expect($this->service->recentInvoices(5))->toHaveCount(5);
});

// ---------------------------------------------------------------------------
// VAT on purchases
// ---------------------------------------------------------------------------

/**
 * Helper to create a PurchaseInvoice with the minimum required fields.
 */
function makePurchaseInvoice(string $date, int $totalVat, array $extra = []): PurchaseInvoice
{
    static $seq = 0;
    $contact = Contact::firstOrCreate(['name' => 'Supplier Test']);

    return PurchaseInvoice::create(array_merge([
        'contact_id' => $contact->id,
        'number' => 'ACQ-'.++$seq,
        'date' => $date,
        'total_vat' => $totalVat,
        'total_net' => $totalVat * 5,
        'total_gross' => $totalVat * 6,
    ], $extra));
}

test('vatOnPurchasesYtd returns zero when no purchase invoices exist', function () {
    expect($this->service->vatOnPurchasesYtd(2026))->toBe(0);
});

test('vatOnPurchasesYtd sums purchase invoice VAT for the given year', function () {
    makePurchaseInvoice('2026-03-15', 22000);
    makePurchaseInvoice('2026-05-10', 11000);

    expect($this->service->vatOnPurchasesYtd(2026))->toBe(33000);
});

test('vatOnPurchasesYtd excludes purchase invoices outside the given year', function () {
    makePurchaseInvoice('2025-11-01', 5000);

    expect($this->service->vatOnPurchasesYtd(2026))->toBe(0);
});

test('getDashboardStats includes vatOnPurchasesYtd and vatBalanceYtd', function () {
    makeInvoice('2026-03-01', 12200, ['total_vat' => 2200]);
    makePurchaseInvoice('2026-03-15', 1000);

    $stats = $this->service->getDashboardStats(2026);

    expect($stats)->toHaveKeys(['vatOnPurchasesYtd', 'vatBalanceYtd']);
    expect($stats['vatCollectedYtd'])->toBe(2200);
    expect($stats['vatOnPurchasesYtd'])->toBe(1000);
    expect($stats['vatBalanceYtd'])->toBe(1200);
});
