<?php

namespace App\Models;

use App\Enums\ClaimStatus;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsuranceClaim extends Model
{
    use LogsActivity;

    protected $guarded = [];

    protected $casts = [
        'status' => ClaimStatus::class,
        'incident_date' => 'date',
        'damage_amount' => 'decimal:2',
        'payout_amount' => 'decimal:2',
    ];

    public function policy(): BelongsTo
    {
        return $this->belongsTo(InsurancePolicy::class, 'insurance_policy_id');
    }

    public function activityLabel(): string
    {
        return 'Schaden '.$this->incident_date?->format('d.m.Y');
    }
}
