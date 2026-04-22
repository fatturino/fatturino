<?php

namespace App\Enums;

enum SdiStatus: string
{
    case Sent = 'sent';                 // Inviata, in attesa risposta SDI
    case Rejected = 'rejected';         // NS - Notifica di Scarto
    case Delivered = 'delivered';       // RC - Ricevuta di Consegna
    case NotDelivered = 'not_delivered'; // MC - Mancata Consegna
    case Expired = 'expired';           // DT - Decorrenza Termini
    case Accepted = 'accepted';         // NE esito positivo / AT
    case Refused = 'refused';           // NE esito negativo / EC
    case Error = 'error';               // Errore tecnico di invio
    case Received = 'received';         // Fattura passiva ricevuta da SDI

    /**
     * Translated label for display
     */
    public function label(): string
    {
        return match ($this) {
            self::Sent        => __('app.invoices.sdi_sent'),
            self::Rejected    => __('app.invoices.sdi_rejected'),
            self::Delivered   => __('app.invoices.sdi_delivered'),
            self::NotDelivered => __('app.invoices.sdi_not_delivered'),
            self::Expired     => __('app.invoices.sdi_expired'),
            self::Accepted    => __('app.invoices.sdi_accepted'),
            self::Refused     => __('app.invoices.sdi_refused'),
            self::Error       => __('app.invoices.sdi_error'),
            self::Received    => __('app.invoices.sdi_received'),
        };
    }

    /**
     * Badge color class for Mary UI
     */
    public function color(): string
    {
        return match ($this) {
            self::Sent        => 'badge-soft badge-info',
            self::Rejected    => 'badge-soft badge-error',
            self::Delivered   => 'badge-soft badge-success',
            self::NotDelivered => 'badge-soft badge-warning',
            self::Expired     => 'badge-soft badge-success',
            self::Accepted    => 'badge-soft badge-success',
            self::Refused     => 'badge-soft badge-error',
            self::Error       => 'badge-soft badge-error',
            self::Received    => 'badge-soft badge-accent',
        };
    }

    /**
     * Icon for Mary UI badge
     */
    public function icon(): string
    {
        return match ($this) {
            self::Sent        => 'o-clock',
            self::Rejected    => 'o-x-circle',
            self::Delivered   => 'o-check-circle',
            self::NotDelivered => 'o-exclamation-triangle',
            self::Expired     => 'o-check-circle',
            self::Accepted    => 'o-check-circle',
            self::Refused     => 'o-x-circle',
            self::Error       => 'o-exclamation-circle',
            self::Received    => 'o-inbox-arrow-down',
        };
    }

    /**
     * Icon color class (Tailwind text-* utility) matching the status severity
     */
    public function iconColorClass(): string
    {
        return match ($this) {
            self::Sent                                     => 'text-info',
            self::Rejected, self::Refused, self::Error     => 'text-error',
            self::Delivered, self::Accepted, self::Expired => 'text-success',
            self::NotDelivered                             => 'text-warning',
            self::Received                                 => 'text-accent',
        };
    }

    /**
     * Background tint class for the icon container
     */
    public function iconBgClass(): string
    {
        return match ($this) {
            self::Sent                                     => 'bg-info/15',
            self::Rejected, self::Refused, self::Error     => 'bg-error/15',
            self::Delivered, self::Accepted, self::Expired => 'bg-success/15',
            self::NotDelivered                             => 'bg-warning/15',
            self::Received                                 => 'bg-accent/15',
        };
    }

    /**
     * Timeline connector color for DaisyUI timeline component
     */
    public function timelineColor(): string
    {
        return match ($this) {
            self::Sent                                => 'bg-info',
            self::Rejected, self::Refused, self::Error => 'bg-error',
            self::Delivered, self::Accepted, self::Expired => 'bg-success',
            self::NotDelivered                        => 'bg-warning',
            self::Received                            => 'bg-accent',
        };
    }

    /**
     * Whether the invoice can still be edited with this status.
     * Only null (not sent), rejected (NS), and error allow editing.
     */
    public function isEditable(): bool
    {
        return match ($this) {
            self::Rejected, self::Error => true,
            default                     => false,
        };
    }

    /**
     * Map SDI notification type code to SdiStatus
     */
    public static function fromNotificationType(string $type): ?self
    {
        return match (strtoupper($type)) {
            'NS' => self::Rejected,
            'RC' => self::Delivered,
            'MC' => self::NotDelivered,
            'DT' => self::Expired,
            'NE' => self::Accepted,
            'AT' => self::Accepted,
            'EC' => self::Refused,
            default => null,
        };
    }
}
