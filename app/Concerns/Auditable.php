<?php

namespace App\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(fn (Model $model) => static::recordAudit('created', $model, null, $model->getAttributes()));

        static::updated(fn (Model $model) => static::recordAudit('updated', $model, $model->getOriginal(), $model->getChanges()));

        static::deleted(fn (Model $model) => static::recordAudit('deleted', $model, $model->getOriginal(), null));
    }

    protected static function recordAudit(string $action, Model $model, ?array $before, ?array $after): void
    {
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'model' => static::class,
            'model_id' => $model->getKey(),
            'before' => $before,
            'after' => $after,
        ]);
    }
}
