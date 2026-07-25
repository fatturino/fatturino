<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\SdiStatus;
use Parental\HasParent;

class SalesInvoice extends FiscalDocument
{
    use HasParent;

    protected $appends = [
        'net_due',
        'is_sdi_editable',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'due_date' => 'date:Y-m-d',
        'withholding_tax_enabled' => 'boolean',
        'split_payment' => 'boolean',
        'status' => InvoiceStatus::class,
        'payment_status' => PaymentStatus::class,
        'sdi_status' => SdiStatus::class,
        'metadata' => 'array',
    ];

    public function isSdiEditable(): bool
    {
        if ($this->sdi_status === null) {
            return true;
        }

        return $this->sdi_status->isEditable();
    }

    public function getIsSdiEditableAttribute(): bool
    {
        return $this->isSdiEditable();
    }
}
