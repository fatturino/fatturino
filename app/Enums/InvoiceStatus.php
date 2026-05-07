<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Generated = 'generated';
    case XmlValidated = 'xml_validated';
    case Sent = 'sent';

    /**
     * Translated label for display
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => __('app.invoices.invoice_status_draft'),
            self::Generated => __('app.invoices.invoice_status_generated'),
            self::XmlValidated => __('app.invoices.invoice_status_xml_validated'),
            self::Sent => __('app.invoices.invoice_status_sent'),
        };
    }

    /**
     * Badge color class for Mary UI / DaisyUI
     */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'badge-soft badge-warning',
            self::Generated => 'badge-soft badge-info',
            self::XmlValidated => 'badge-soft badge-accent',
            self::Sent => 'badge-soft badge-success',
        };
    }

    /**
     * Icon for Mary UI badge
     */
    public function icon(): string
    {
        return match ($this) {
            self::Draft => 'o-pencil-square',
            self::Generated => 'o-document-check',
            self::XmlValidated => 'o-shield-check',
            self::Sent => 'o-paper-airplane',
        };
    }

    /**
     * Whether XML validation can be triggered from this status
     */
    public function canValidateXml(): bool
    {
        return $this === self::Draft;
    }

    /**
     * Whether sending to SDI is allowed from this status
     */
    public function canSendToSdi(): bool
    {
        return $this === self::XmlValidated;
    }
}
