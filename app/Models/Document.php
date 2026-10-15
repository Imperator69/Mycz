<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Document extends Model
{
    use LogsActivity;

    protected $guarded = [];

    protected $casts = [
        'doc_type' => DocumentType::class,
        'status' => DocumentStatus::class,
        'document_date' => 'date',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class)->withTrashed();
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function url(): ?string
    {
        if ($this->drive_web_link) {
            return $this->drive_web_link;
        }

        return $this->local_path ? route('documents.download', $this) : null;
    }

    public function activityLabel(): string
    {
        return $this->title;
    }
}
