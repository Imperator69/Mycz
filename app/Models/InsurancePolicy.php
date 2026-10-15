<?php

namespace App\Models;

use App\Enums\InsuranceLine;
use App\Enums\PaymentInterval;
use App\Models\Concerns\HasRenewalTerm;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class InsurancePolicy extends Model
{
    use HasRenewalTerm, LogsActivity;

    protected $guarded = [];

    protected $casts = [
        'line' => InsuranceLine::class,
        'payment_interval' => PaymentInterval::class,
        'start_date' => 'date',
        'end_date' => 'date',
        'auto_renews' => 'boolean',
        'coverage_amount' => 'decimal:2',
        'deductible' => 'decimal:2',
        'premium' => 'decimal:2',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class)->withTrashed();
    }

    public function claims(): HasMany
    {
        return $this->hasMany(InsuranceClaim::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function annualPremium(): float
    {
        return annualize((float) $this->premium, $this->payment_interval);
    }

    public function activityLabel(): string
    {
        return $this->insurer.' '.$this->policy_number;
    }
}
