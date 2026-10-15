<?php

namespace App\Models;

use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Property extends Model
{
    use LogsActivity, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'type' => PropertyType::class,
        'status' => PropertyStatus::class,
        'purchase_date' => 'date',
        'area_m2' => 'decimal:2',
        'land_area_m2' => 'decimal:2',
        'purchase_price' => 'decimal:2',
        'purchase_costs' => 'decimal:2',
        'assessed_value' => 'decimal:2',
        'market_value' => 'decimal:2',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'parent_id');
    }

    public function units(): HasMany
    {
        return $this->hasMany(Property::class, 'parent_id');
    }

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class);
    }

    public function rentCharges(): HasMany
    {
        return $this->hasMany(RentCharge::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function insurancePolicies(): HasMany
    {
        return $this->hasMany(InsurancePolicy::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function maintenanceTasks(): HasMany
    {
        return $this->hasMany(MaintenanceTask::class);
    }

    public function meters(): HasMany
    {
        return $this->hasMany(Meter::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function statements(): HasMany
    {
        return $this->hasMany(Statement::class);
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class);
    }

    public function activeLease(): ?Lease
    {
        return $this->leases()->active()->latest('start_date')->first();
    }

    public function totalInvestment(): float
    {
        return (float) $this->purchase_price + (float) $this->purchase_costs;
    }

    public function address(): string
    {
        return trim(implode(', ', array_filter([$this->street, trim($this->zip.' '.$this->city)])));
    }

    /** Ordnername im Drive, eindeutig durch die ID. */
    public function driveFolderName(): string
    {
        $name = preg_replace('/[\\\\\/:*?"<>|]+/', '-', $this->name);

        return trim($name).' #'.$this->id;
    }

    public function activityLabel(): string
    {
        return $this->name;
    }
}
