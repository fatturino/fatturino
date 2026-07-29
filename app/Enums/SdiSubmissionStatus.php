<?php

namespace App\Enums;

enum SdiSubmissionStatus: string
{
    case Pending = 'pending';
    case ProviderAccepted = 'provider_accepted';
    case Completed = 'completed';
    case ProviderRejected = 'provider_rejected';
    case OutcomeUnknown = 'outcome_unknown';
    case LocalPersistFailed = 'local_persist_failed';
    case Recoverable = 'recoverable';

    public function blocksNewSubmission(): bool
    {
        return match ($this) {
            self::Pending,
            self::ProviderAccepted,
            self::OutcomeUnknown,
            self::LocalPersistFailed => true,
            self::Completed,
            self::ProviderRejected,
            self::Recoverable => false,
        };
    }

    public function needsReconciliation(): bool
    {
        return match ($this) {
            self::ProviderAccepted,
            self::OutcomeUnknown,
            self::LocalPersistFailed => true,
            self::Pending,
            self::Completed,
            self::ProviderRejected,
            self::Recoverable => false,
        };
    }
}
