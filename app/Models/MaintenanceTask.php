<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class MaintenanceTask extends Model
{
    use LogsActivity;

    protected $guarded = [];

    protected $casts = [
        'last_done_at' => 'date',
        'next_due_at' => 'date',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class)->withTrashed();
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** Erledigt markieren und naechsten Termin aus dem Intervall berechnen. */
    public function markDone(?\DateTimeInterface $on = null): void
    {
        $on = $on ? Carbon::instance($on) : today();
        $this->last_done_at = $on;
        $this->next_due_at = $this->interval_months ? $on->copy()->addMonthsNoOverflow($this->interval_months) : null;
        $this->save();
    }

    public function activityLabel(): string
    {
        return $this->title;
    }
}
