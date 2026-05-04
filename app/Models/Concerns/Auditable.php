<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Request;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            static::logAudit($model, 'created', [], $model->getAttributes());
        });

        static::updated(function ($model) {
            $dirty = $model->getDirty();
            $original = array_intersect_key($model->getOriginal(), $dirty);
            static::logAudit($model, 'updated', $original, $dirty);
        });

        static::deleted(function ($model) {
            $isSoftDelete = method_exists($model, 'trashed') && $model->trashed();
            static::logAudit($model, $isSoftDelete ? 'soft_deleted' : 'deleted', $model->getOriginal(), []);
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(function ($model) {
                static::logAudit($model, 'restored', [], $model->getAttributes());
            });
        }
    }

    private static function logAudit($model, string $event, array $old, array $new): void
    {
        $user = auth()->user();
        $hidden = $model->getHidden();

        $filter = fn (array $values) => collect($values)
            ->except(array_merge($hidden, ['remember_token', 'two_factor_secret']))
            ->toArray();

        AuditLog::create([
            'user_id'        => $user?->id,
            'user_name'      => $user?->name,
            'auditable_type' => get_class($model),
            'auditable_id'   => $model->getKey(),
            'event'          => $event,
            'old_values'     => $old ? $filter($old) : null,
            'new_values'     => $new ? $filter($new) : null,
        ]);
    }

    public function auditLogs()
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest('created_at');
    }
}
