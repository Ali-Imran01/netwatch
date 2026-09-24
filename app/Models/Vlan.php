<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['site_id', 'vid', 'name'])]
class Vlan extends Model
{
    use Auditable, HasFactory;

    public function site()
    {
        return $this->belongsTo(Site::class);
    }
}
