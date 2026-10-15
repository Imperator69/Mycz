<?php

namespace App\Models;

use App\Enums\ContractType;
use App\Enums\PaymentInterval;
use App\Models\Concerns\HasRenewalTerm;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Contract extends Model
{
    use HasRenewalTerm, LogsActivity;

    protected $guarded = [];

    protected $casts = [
        'type' => ContractType::class,
        'cost_interval' => PaymentInterval::class,
        'start_date' => 'date',
        'end_date' => 'date',
        'auto_renews' => 'boolean',
        'cost_amount' => 'decimal:2',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class)->withTrashed();
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function annualCost(): float
    {
        return annualize((float) $this->cost_amount, $this->cost_interval);
    }

    public function activityLabel(): string
    {
        return $this->provider;
    }
}
