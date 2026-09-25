<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'type', 'target', 'enabled'])]
class AlertChannel extends Model
{
    use Auditable, HasFactory;

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
