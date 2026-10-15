<?php

namespace App\Models;

use App\Enums\InterestType;
use App\Models\Concerns\LogsActivity;
use App\Services\LoanSchedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Loan extends Model
{
    use LogsActivity;

    protected $guarded = [];

    protected $casts = [
        'interest_type' => InterestType::class,
        'start_date' => 'date',
        'fixed_until' => 'date',
        'principal' => 'decimal:2',
        'interest_rate' => 'decimal:3',
        'monthly_payment' => 'decimal:2',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class)->withTrashed();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function schedule(): LoanSchedule
    {
        return new LoanSchedule($this);
    }

    public function activityLabel(): string
    {
        return $this->bank.' '.$this->contract_number;
    }
}
