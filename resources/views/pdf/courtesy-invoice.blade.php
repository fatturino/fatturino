<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $documentTitle }} {{ $invoice->number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #1a1a1a;
            line-height: 1.4;
        }

        .page {
            padding: 20px 25px;
        }

        /* ── Header ────────────────────────────────── */
        .header-table {
            width: 100%;
            margin-bottom: 20px;
        }

        .document-title {
            font-size: 20px;
            font-weight: bold;
            color: #1a1a1a;
            letter-spacing: 1px;
        }

        .logo-cell {
            text-align: right;
            vertical-align: top;
        }

        .logo-cell img {
            max-height: 50px;
            max-width: 160px;
        }

        /* ── Parties (company + customer) ──────────── */
        .parties-table {
            width: 100%;
            margin-bottom: 18px;
        }

        .company-block {
            font-size: 9px;
            color: #444;
            vertical-align: top;
            width: 48%;
        }

        .company-name {
            font-size: 11px;
            font-weight: bold;
            color: #1a1a1a;
            margin-bottom: 3px;
        }

        .section-label {
            font-size: 7.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #888;
            margin-bottom: 4px;
        }

        .customer-block {
            vertical-align: top;
            width: 48%;
            padding-left: 20px;
        }

        .customer-name {
            font-size: 11px;
            font-weight: bold;
            color: #1a1a1a;
            margin-bottom: 3px;
        }

        /* ── Invoice metadata row ───────────────────── */
        .meta-table {
            width: 100%;
            margin-bottom: 18px;
            border-top: 1px solid #e0e0e0;
            border-bottom: 1px solid #e0e0e0;
            padding: 8px 0;
        }

        .meta-cell {
            vertical-align: top;
            padding: 0 8px;
        }

        .meta-cell:first-child {
            padding-left: 0;
        }

        .meta-label {
            font-size: 7.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #888;
            margin-bottom: 2px;
        }

        .meta-value {
            font-size: 10px;
            font-weight: bold;
            color: #1a1a1a;
        }

        /* ── Line items table ───────────────────────── */
        .lines-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }

        .lines-table thead tr {
            background-color: #1a1a1a;
            color: #ffffff;
        }

        .lines-table thead th {
            padding: 6px 8px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .lines-table tbody tr:nth-child(even) {
            background-color: #f7f7f7;
        }

        .lines-table tbody td {
            padding: 5px 8px;
            border-bottom: 1px solid #eeeeee;
            vertical-align: top;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .col-description {
            width: 38%;
        }

        .col-qty {
            width: 8%;
            text-align: center;
        }

        .col-um {
            width: 7%;
            text-align: center;
        }

        .col-price {
            width: 13%;
            text-align: right;
        }

        .col-discount {
            width: 10%;
            text-align: right;
        }

        .col-vat {
            width: 9%;
            text-align: center;
        }

        .col-amount {
            width: 15%;
            text-align: right;
        }

        /* ── VAT summary ────────────────────────────── */
        .vat-summary-section {
            margin-bottom: 16px;
        }

        .section-title {
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #888;
            margin-bottom: 6px;
        }

        .vat-table {
            width: 100%;
            border-collapse: collapse;
        }

        .vat-table th {
            background-color: #f0f0f0;
            padding: 4px 8px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #555;
        }

        .vat-table td {
            padding: 4px 8px;
            border-bottom: 1px solid #eeeeee;
            font-size: 9px;
        }

        /* ── Totals block ───────────────────────────── */
        .bottom-section {
            width: 100%;
            margin-bottom: 16px;
        }

        .payment-block {
            vertical-align: top;
            width: 52%;
            padding-right: 15px;
        }

        .totals-block {
            vertical-align: top;
            width: 48%;
        }

        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }

        .totals-table td {
            padding: 3px 8px;
            font-size: 9px;
        }

        .totals-table tr.total-due {
            background-color: #1a1a1a;
            color: #ffffff;
        }

        .totals-table tr.total-due td {
            padding: 6px 8px;
            font-size: 10px;
            font-weight: bold;
        }

        .totals-label {
            color: #555;
        }

        .totals-amount {
            text-align: right;
            font-weight: bold;
        }

        /* ── Payment info ───────────────────────────── */
        .payment-row {
            margin-bottom: 4px;
            font-size: 9px;
        }

        .payment-key {
            color: #888;
            font-size: 8px;
        }


        /* ── Notes ──────────────────────────────────── */
        .notes-section {
            margin-bottom: 14px;
            padding: 8px;
            background-color: #f9f9f9;
            border-left: 3px solid #cccccc;
        }

        .notes-text {
            font-size: 9px;
            color: #444;
        }

        /* ── Footer disclaimer ──────────────────────── */
        .disclaimer {
            border-top: 1px solid #dddddd;
            padding-top: 8px;
            font-size: 7.5px;
            color: #999;
            text-align: center;
        }

        .divider {
            border: none;
            border-top: 1px solid #e0e0e0;
            margin: 14px 0;
        }

        .negative {
            color: #c00000;
        }

        .highlight-row {
            background-color: #f5f5f5;
        }
    </style>
</head>
<body>
<div class="page">

    {{-- ── Header: title + logo ─────────────────────────────────────── --}}
    <table class="header-table">
        <tr>
            <td>
                <div class="document-title">{{ $documentTitle }}</div>
            </td>
            @if ($logo)
                <td class="logo-cell">
                    <img src="{{ $logo }}" alt="Logo" />
                </td>
            @endif
        </tr>
    </table>

    {{-- ── Parties: company info + customer info ────────────────────── --}}
    <table class="parties-table">
        <tr>
            {{-- Supplier (company) --}}
            <td class="company-block">
                <div class="section-label">{{ __('app.pdf.supplier_section') }}</div>
                <div class="company-name">{{ $company->company_name }}</div>
                @if ($company->company_address)
                    <div>{{ $company->company_address }}</div>
                @endif
                @if ($company->company_city)
                    <div>
                        {{ $company->company_postal_code }} {{ $company->company_city }}
                        @if ($company->company_province) ({{ $company->company_province }}) @endif
                    </div>
                @endif
                @if ($company->company_vat_number)
                    <div>P.IVA: {{ $company->company_vat_number }}</div>
                @endif
                @if ($company->company_tax_code)
                    <div>C.F.: {{ $company->company_tax_code }}</div>
                @endif
                @if ($company->company_pec)
                    <div>PEC: {{ $company->company_pec }}</div>
                @endif
            </td>

            {{-- Customer --}}
            <td class="customer-block">
                <div class="section-label">{{ __('app.pdf.customer_section') }}</div>
                <div class="customer-name">{{ $invoice->contact->name }}</div>
                @if ($invoice->contact->address)
                    <div>{{ $invoice->contact->address }}</div>
                @endif
                @if ($invoice->contact->city)
                    <div>
                        {{ $invoice->contact->postal_code }} {{ $invoice->contact->city }}
                        @if ($invoice->contact->province) ({{ $invoice->contact->province }}) @endif
                    </div>
                @endif
                @if ($invoice->contact->vat_number)
                    <div>P.IVA: {{ $invoice->contact->vat_number }}</div>
                @endif
                @if ($invoice->contact->tax_code)
                    <div>C.F.: {{ $invoice->contact->tax_code }}</div>
                @endif
            </td>
        </tr>
    </table>

    {{-- ── Invoice metadata ─────────────────────────────────────────── --}}
    <table class="meta-table">
        <tr>
            <td class="meta-cell">
                <div class="meta-label">{{ __('app.pdf.invoice_number') }}</div>
                <div class="meta-value">{{ $invoice->number }}</div>
            </td>
            <td class="meta-cell">
                <div class="meta-label">{{ __('app.pdf.invoice_date') }}</div>
                <div class="meta-value">{{ $invoice->date->format('d/m/Y') }}</div>
            </td>
            @if ($invoice->due_date)
                <td class="meta-cell">
                    <div class="meta-label">{{ __('app.pdf.due_date') }}</div>
                    <div class="meta-value">{{ $invoice->due_date->format('d/m/Y') }}</div>
                </td>
            @endif
            @if ($invoice->document_type)
                <td class="meta-cell">
                    <div class="meta-label">{{ __('app.pdf.document_type') }}</div>
                    <div class="meta-value">{{ $invoice->document_type }}</div>
                </td>
            @endif
        </tr>
    </table>

    {{-- ── Line items ───────────────────────────────────────────────── --}}
    <table class="lines-table">
        <thead>
            <tr>
                <th class="col-description" style="text-align:left;">{{ __('app.pdf.line_description') }}</th>
                <th class="col-qty">{{ __('app.pdf.line_quantity') }}</th>
                <th class="col-um">{{ __('app.pdf.line_unit') }}</th>
                <th class="col-price">{{ __('app.pdf.line_unit_price') }}</th>
                <th class="col-discount">{{ __('app.pdf.line_discount') }}</th>
                <th class="col-vat">{{ __('app.pdf.line_vat') }}</th>
                <th class="col-amount">{{ __('app.pdf.line_amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->lines as $line)
                <tr>
                    <td class="col-description">{{ $line->description }}</td>
                    <td class="col-qty">{{ number_format($line->quantity, 2, ',', '.') }}</td>
                    <td class="col-um">{{ $line->unit_of_measure ?? '' }}</td>
                    <td class="col-price">{{ number_format($line->unit_price / 100, 2, ',', '.') }}</td>
                    <td class="col-discount">
                        @if ($line->discount_percent)
                            {{ number_format($line->discount_percent, 2, ',', '.') }}%
                        @elseif ($line->discount_amount)
                            -{{ number_format($line->discount_amount / 100, 2, ',', '.') }}&nbsp;&euro;
                        @else
                            &mdash;
                        @endif
                    </td>
                    <td class="col-vat">
                        @if ($line->vat_rate?->nature())
                            {{ $line->vat_rate?->nature() }}
                        @else
                            {{ number_format($line->vat_rate?->percent(), 0, ',', '.') }}%
                        @endif
                    </td>
                    <td class="col-amount">{{ number_format($line->total / 100, 2, ',', '.') }}&nbsp;&euro;</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- ── Bottom: payment info + totals ───────────────────────────── --}}
    <table class="bottom-section">
        <tr>
            {{-- Payment info --}}
            <td class="payment-block">
                {{-- VAT summary grouped by rate --}}
                @if (count($vatSummary) > 0)
                    <div class="section-title">{{ __('app.pdf.vat_summary') }}</div>
                    <table class="vat-table" style="margin-bottom:12px;">
                        <thead>
                            <tr>
                                <th style="text-align:left;">{{ __('app.pdf.vat_rate') }}</th>
                                <th style="text-align:right;">{{ __('app.pdf.taxable') }}</th>
                                <th style="text-align:right;">{{ __('app.pdf.vat_amount') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($vatSummary as $row)
                                <tr>
                                    <td>
                                        @if ($row['nature'])
                                            {{ $row['nature'] }}
                                        @else
                                            {{ number_format($row['rate'], 0, ',', '.') }}%
                                        @endif
                                    </td>
                                    <td style="text-align:right;">{{ number_format($row['taxable'] / 100, 2, ',', '.') }}&nbsp;&euro;</td>
                                    <td style="text-align:right;">{{ number_format($row['tax'] / 100, 2, ',', '.') }}&nbsp;&euro;</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                {{-- Payment details --}}
                @if ($invoice->payment_method)
                    <div class="section-title">{{ __('app.pdf.payment_info') }}</div>
                    <div class="payment-row">
                        <span class="payment-key">{{ __('app.pdf.payment_method') }}:</span>
                        {{ $invoice->payment_method->label() }}
                    </div>
                @endif
                @if ($invoice->bank_name)
                    <div class="payment-row">
                        <span class="payment-key">{{ __('app.pdf.bank') }}:</span>
                        {{ $invoice->bank_name }}
                    </div>
                @endif
                @if ($invoice->bank_iban)
                    <div class="payment-row">
                        <span class="payment-key">{{ __('app.pdf.iban') }}:</span>
                        {{ $invoice->bank_iban }}
                    </div>
                @endif
            </td>

            {{-- Totals --}}
            <td class="totals-block">
                <table class="totals-table">
                    <tr>
                        <td class="totals-label">{{ __('app.pdf.net_total') }}</td>
                        <td class="totals-amount">{{ number_format($invoice->total_net / 100, 2, ',', '.') }}&nbsp;&euro;</td>
                    </tr>

                    @if ($invoice->fund_enabled && $invoice->fund_amount > 0)
                        <tr>
                            <td class="totals-label">
                                {{ __('app.pdf.fund_contribution', ['percent' => number_format($invoice->fund_percent, 2, ',', '.')]) }}
                            </td>
                            <td class="totals-amount">{{ number_format($invoice->fund_amount / 100, 2, ',', '.') }}&nbsp;&euro;</td>
                        </tr>
                    @endif

                    <tr>
                        <td class="totals-label">{{ __('app.pdf.vat_total') }}</td>
                        <td class="totals-amount">{{ number_format($invoice->total_vat / 100, 2, ',', '.') }}&nbsp;&euro;</td>
                    </tr>

                    <tr class="highlight-row">
                        <td class="totals-label" style="font-weight:bold;">{{ __('app.pdf.gross_total') }}</td>
                        <td class="totals-amount" style="font-weight:bold;">{{ number_format($invoice->total_gross / 100, 2, ',', '.') }}&nbsp;&euro;</td>
                    </tr>

                    @if ($invoice->stamp_duty_applied && $invoice->stamp_duty_amount > 0)
                        <tr>
                            <td class="totals-label">{{ __('app.pdf.stamp_duty') }}</td>
                            <td class="totals-amount">{{ number_format($invoice->stamp_duty_amount / 100, 2, ',', '.') }}&nbsp;&euro;</td>
                        </tr>
                    @endif

                    @if ($invoice->withholding_tax_enabled && $invoice->withholding_tax_amount > 0)
                        <tr>
                            <td class="totals-label negative">
                                {{ __('app.pdf.withholding_tax', ['percent' => number_format($invoice->withholding_tax_percent, 2, ',', '.')]) }}
                            </td>
                            <td class="totals-amount negative">-{{ number_format($invoice->withholding_tax_amount / 100, 2, ',', '.') }}&nbsp;&euro;</td>
                        </tr>
                    @endif

                    @if ($invoice->split_payment)
                        <tr>
                            <td class="totals-label negative">{{ __('app.pdf.split_payment_deduction') }}</td>
                            <td class="totals-amount negative">-{{ number_format($invoice->total_vat / 100, 2, ',', '.') }}&nbsp;&euro;</td>
                        </tr>
                    @endif

                    @php
                        // Net due = gross + stamp duty - withholding - split payment VAT deduction
                        $netDue = $invoice->total_gross;
                        if ($invoice->stamp_duty_applied) {
                            $netDue += $invoice->stamp_duty_amount;
                        }
                        if ($invoice->withholding_tax_enabled) {
                            $netDue -= $invoice->withholding_tax_amount;
                        }
                        if ($invoice->split_payment) {
                            $netDue -= $invoice->total_vat;
                        }
                    @endphp

                    <tr class="total-due">
                        <td>
                            {{ __('app.pdf.net_due') }}
                            @if ($invoice->due_date)
                                <div style="font-size:8px; font-weight:normal; margin-top:3px; opacity:0.7;">
                                    {{ __('app.pdf.due_date') }}: {{ $invoice->due_date->format('d/m/Y') }}
                                </div>
                            @endif
                        </td>
                        <td class="text-right">{{ number_format($netDue / 100, 2, ',', '.') }}&nbsp;&euro;</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- ── Notes ────────────────────────────────────────────────────── --}}
    @if ($invoice->notes)
        <div class="notes-section">
            <div class="section-title">{{ __('app.pdf.notes') }}</div>
            <div class="notes-text">{{ $invoice->notes }}</div>
        </div>
    @endif

    {{-- ── Disclaimer (SDI invoices only) ──────────────────────────── --}}
    @if ($showSdiDisclaimer)
        <div class="disclaimer">
            {{ __('app.pdf.sdi_disclaimer') }}
        </div>
    @endif

</div>
</body>
</html>
