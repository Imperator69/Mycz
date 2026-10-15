<?php

namespace App\Models;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    use LogsActivity;

    protected $guarded = [];

    protected $casts = [
        'status' => TicketStatus::class,
        'priority' => TicketPriority::class,
        'reported_at' => 'date',
        'resolved_at' => 'date',
        'cost_estimate' => 'decimal:2',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class)->withTrashed();
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function insuranceClaim(): BelongsTo
    {
        return $this->belongsTo(InsuranceClaim::class);
    }

    public function activityLabel(): string
    {
        return $this->title;
    }
}
