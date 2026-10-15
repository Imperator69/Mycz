<?php

namespace App\Models;

use App\Enums\StatementStatus;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Statement extends Model
{
    use LogsActivity;

    protected $guarded = [];

    protected $casts = [
        'status' => StatementStatus::class,
        'period_from' => 'date',
        'period_to' => 'date',
        'total_costs' => 'decimal:2',
        'reserve_contribution' => 'decimal:2',
        'reserve_balance' => 'decimal:2',
        'prepayments' => 'decimal:2',
        'balance' => 'decimal:2',
        'warnings' => 'array',
        'raw' => 'array',
        'booked_at' => 'datetime',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class)->withTrashed();
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StatementLine::class)->orderBy('sort');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function linesTotal(): float
    {
        return round((float) $this->lines()->sum('amount'), 2);
    }

    public function activityLabel(): string
    {
        return trim(($this->issuer ?? 'Abrechnung').' '.$this->year);
    }
}
