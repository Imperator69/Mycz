<?php

namespace App\Models\Concerns;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Schreibt Anlage, Aenderung und Loeschung in das Aktivitaetslog.
 */
trait LogsActivity
{
    protected static array $activityHidden = ['updated_at', 'created_at', 'iban'];

    public static function bootLogsActivity(): void
    {
        static::created(fn (Model $model) => static::writeActivity($model, 'created', $model->getAttributes()));
        static::updated(function (Model $model) {
            $changes = collect($model->getChanges())->except(static::$activityHidden)->all();
            if ($changes !== []) {
                static::writeActivity($model, 'updated', $changes);
            }
        });
        static::deleted(fn (Model $model) => static::writeActivity($model, 'deleted', null));
    }

    protected static function writeActivity(Model $model, string $action, ?array $changes): void
    {
        if ($changes !== null) {
            $changes = collect($changes)->except(static::$activityHidden)->all();
        }

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'subject_type' => $model::class,
            'subject_id' => $model->getKey(),
            'subject_label' => method_exists($model, 'activityLabel') ? $model->activityLabel() : null,
            'changes' => $changes,
            'created_at' => now(),
        ]);
    }
}
