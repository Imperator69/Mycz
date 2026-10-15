<?php

namespace App\Models;

use App\Enums\DepositType;
use App\Enums\IndexType;
use App\Enums\PropertyStatus;
use App\Models\Concerns\LogsActivity;
use App\Services\RentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Lease extends Model
{
    use LogsActivity, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'charges_from' => 'date',
        'deposit_returned_at' => 'date',
        'index_base_month' => 'date',
        'index_last_adjusted_at' => 'date',
        'is_fixed_term' => 'boolean',
        'base_rent' => 'decimal:2',
        'operating_costs' => 'decimal:2',
        'heating_costs' => 'decimal:2',
        'other_costs' => 'decimal:2',
        'vat_rate' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'index_base_value' => 'decimal:2',
        'index_threshold_percent' => 'decimal:2',
        'deposit_type' => DepositType::class,
        'index_type' => IndexType::class,
    ];

    protected static function booted(): void
    {
        // Soll-Mieten sofort anlegen, egal von wo der Vertrag gespeichert wird.
        static::saved(function (Lease $lease) {
            app(RentService::class)->generateForLease($lease, today()->startOfMonth());

            if ($lease->wasRecentlyCreated && $lease->isActive() && $lease->property?->status === PropertyStatus::Vacant) {
                $lease->property->update(['status' => PropertyStatus::Rented]);
            }
        });
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function rentCharges(): HasMany
    {
        return $this->hasMany(RentCharge::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /** Vertraege, die heute (oder am Stichtag) laufen. */
    public function scopeActive(Builder $query, ?Carbon $on = null): Builder
    {
        $on ??= today();

        return $query->whereDate('start_date', '<=', $on)
            ->where(fn (Builder $q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $on));
    }

    public function monthlyNet(): float
    {
        return round((float) $this->base_rent + (float) $this->operating_costs + (float) $this->heating_costs + (float) $this->other_costs, 2);
    }

    public function monthlyVat(): float
    {
        return round($this->monthlyNet() * (float) $this->vat_rate / 100, 2);
    }

    public function monthlyGross(): float
    {
        return round($this->monthlyNet() + $this->monthlyVat(), 2);
    }

    public function isActive(?Carbon $on = null): bool
    {
        $on ??= today();

        return $this->start_date->lte($on) && ($this->end_date === null || $this->end_date->gte($on));
    }

    public function activityLabel(): string
    {
        return ($this->property?->name ?? 'Objekt').' / '.($this->tenant?->display_name ?? 'Mieter');
    }
}
