<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

trait HasUuid
{
    public function initializeHasUuid(): void
    {
        if (empty($this->attributes['uuid'] ?? null)) {
            $this->attributes['uuid'] = (string) Str::uuid();
        }
    }

    protected static function bootHasUuid(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
