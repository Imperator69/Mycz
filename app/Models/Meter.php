<?php

namespace App\Models;

use App\Enums\MeterType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Meter extends Model
{
    protected $guarded = [];

    protected $casts = [
        'type' => MeterType::class,
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class)->withTrashed();
    }

    public function readings(): HasMany
    {
        return $this->hasMany(MeterReading::class)->orderByDesc('read_at');
    }
}
