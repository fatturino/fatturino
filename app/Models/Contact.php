<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    use HasFactory;

    protected $guarded = [];

    // Invoices linked to this contact
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Check if contact is Italian
     * Priority: use 'country' field if set, fallback to 'country_code'
     */
    public function isItalian(): bool
    {
        $countryToCheck = $this->country ?? $this->country_code;

        return $countryToCheck === 'IT';
    }

    /**
     * Check if contact is in EU
     * Priority: use 'country' field if set, fallback to 'country_code'
     */
    public function isEU(): bool
    {
        $euCountries = [
            'AT', 'BE', 'BG', 'CY', 'HR', 'DK', 'EE', 'FI', 'FR',
            'DE', 'GR', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
            'PL', 'PT', 'CZ', 'RO', 'SK', 'SI', 'ES', 'SE', 'HU',
        ];

        $code = $this->country ?? $this->country_code;

        return in_array($code, $euCountries);
    }

    /**
     * Get SDI code for XML generation
     * Returns appropriate value based on country
     */
    public function getSdiCodeForXml(): string
    {
        // For Italian customers, use provided SDI code or default
        if ($this->isItalian()) {
            return $this->sdi_code ?? '0000000';
        }

        // For foreign customers, use XXXXXXX as per SDI specs
        return 'XXXXXXX';
    }

    /**
     * Get postal code formatted for SDI XML
     * Italian: as-is, Foreign: 00000
     */
    public function getPostalCodeForXml(): string
    {
        if ($this->isItalian()) {
            return $this->postal_code ?? '';
        }

        return '00000';
    }

    /**
     * Get province/state code for SDI XML
     * Italian: as-is (e.g., MI, RM), Foreign: EE (Estero)
     */
    public function getProvinceForXml(): string
    {
        if ($this->isItalian()) {
            return $this->province ?? '';
        }

        return 'EE';
    }

    /**
     * Get VAT number cleaned for XML (without country prefix)
     */
    public function getVatNumberClean(): string
    {
        if (! $this->vat_number) {
            return '';
        }

        // Remove common prefixes (IT, DE, FR, etc.)
        $cleaned = preg_replace('/^[A-Z]{2}/', '', $this->vat_number);

        return $cleaned ?? $this->vat_number;
    }
}
