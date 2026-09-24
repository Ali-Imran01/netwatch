<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'action', 'model', 'model_id', 'before', 'after'])]
class AuditLog extends Model
{
    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
