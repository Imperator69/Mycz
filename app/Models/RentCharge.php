<?php

namespace App\Models;

use App\Enums\RentChargeStatus;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RentCharge extends Model
{
    use LogsActivity;

    protected $guarded = [];

    protected $casts = [
        'period' => 'date',
        'due_date' => 'date',
        'paid_at' => 'date',
        'amount_due' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'status' => RentChargeStatus::class,
    ];

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class)->withTrashed();
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class)->withTrashed();
    }

    public function transaction(): HasOne
    {
        return $this->hasOne(Transaction::class);
    }

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->whereIn('status', [RentChargeStatus::Open->value, RentChargeStatus::Partial->value]);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->unpaid()->whereDate('due_date', '<', today());
    }

    public function outstanding(): float
    {
        return round((float) $this->amount_due - (float) $this->amount_paid, 2);
    }

    public function activityLabel(): string
    {
        return 'Miete '.$this->period?->format('m/Y');
    }
}
