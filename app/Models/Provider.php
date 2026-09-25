<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'noc_email', 'noc_phone', 'notes'])]
class Provider extends Model
{
    use Auditable, HasFactory;

    /** @return HasMany<Circuit, $this> */
    public function circuits(): HasMany
    {
        return $this->hasMany(Circuit::class);
    }
}
