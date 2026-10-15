<?php

namespace App\Models;

use App\Enums\TenantType;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use LogsActivity, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'type' => TenantType::class,
        'birth_date' => 'date',
    ];

    protected $appends = ['display_name'];

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class);
    }

    public function getDisplayNameAttribute(): string
    {
        $person = trim($this->first_name.' '.$this->last_name);

        if ($this->company) {
            return $person !== '' ? $this->company.' ('.$person.')' : $this->company;
        }

        return $person !== '' ? $person : 'Mieter #'.$this->id;
    }

    public function activityLabel(): string
    {
        return $this->display_name;
    }
}
