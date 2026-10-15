<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Manuell angelegte Frist / Erinnerung. */
class Deadline extends Model
{
    use LogsActivity;

    protected $guarded = [];

    protected $casts = [
        'due_date' => 'date',
        'done_at' => 'date',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class)->withTrashed();
    }

    /** Erledigen; bei Wiederholung wird der naechste Termin gesetzt statt abgeschlossen. */
    public function complete(): void
    {
        if ($this->repeat_months) {
            $this->due_date = $this->due_date->copy()->addMonthsNoOverflow($this->repeat_months);
        } else {
            $this->done_at = today();
        }
        $this->save();
    }

    public function activityLabel(): string
    {
        return $this->title;
    }
}
