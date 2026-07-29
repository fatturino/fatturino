<?php

namespace App\Models;

use App\Enums\SdiSubmissionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SdiOutboundSubmission extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'status' => SdiSubmissionStatus::class,
        'provider_accepted_at' => 'datetime',
        'completed_at' => 'datetime',
        'reconciled_at' => 'datetime',
        'unlocked_at' => 'datetime',
    ];

    public function fiscalDocument(): BelongsTo
    {
        return $this->belongsTo(FiscalDocument::class);
    }

    public function unlockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unlocked_by_user_id');
    }
}
