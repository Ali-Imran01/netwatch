<?php

namespace App\Models;

use App\Enums\IncidentState;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['incident_id', 'from_state', 'to_state', 'user_id', 'note', 'created_at'])]
class IncidentEvent extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['from_state' => IncidentState::class, 'to_state' => IncidentState::class, 'created_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
