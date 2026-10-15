<?php

namespace App\Models;

use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use LogsActivity;

    protected $guarded = [];

    protected $casts = [
        'type' => TransactionType::class,
        'source' => TransactionSource::class,
        'booked_at' => 'date',
        'amount_net' => 'decimal:2',
        'vat_rate' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'amount_gross' => 'decimal:2',
        'is_allocatable' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Brutto/USt konsistent halten, wenn nur Netto + Satz gesetzt wurden.
        static::saving(function (Transaction $t) {
            $rate = (float) $t->vat_rate;
            $fromGross = $t->amount_net === null
                || ($t->isDirty('amount_gross') && ! $t->isDirty('amount_net') && $t->amount_gross !== null);
            if ($fromGross) {
                $t->amount_net = round((float) $t->amount_gross / (1 + $rate / 100), 2);
                $t->vat_amount = round((float) $t->amount_gross - (float) $t->amount_net, 2);
            } else {
                $t->vat_amount = round((float) $t->amount_net * $rate / 100, 2);
                $t->amount_gross = round((float) $t->amount_net + (float) $t->vat_amount, 2);
            }
            if ($t->type === null && $t->category_id) {
                $t->type = Category::find($t->category_id)?->type;
            }
        });
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class)->withTrashed();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function rentCharge(): BelongsTo
    {
        return $this->belongsTo(RentCharge::class);
    }

    public function statement(): BelongsTo
    {
        return $this->belongsTo(Statement::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function scopeInYear(Builder $query, int $year): Builder
    {
        return $query->whereBetween('booked_at', ["$year-01-01", "$year-12-31"]);
    }

    public function activityLabel(): string
    {
        return $this->description;
    }
}
