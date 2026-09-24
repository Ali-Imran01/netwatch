<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\DeviceType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['site_id', 'name', 'type', 'vendor', 'model', 'mgmt_ip_id', 'serial'])]
class Device extends Model
{
    use Auditable, HasFactory;

    protected function casts(): array
    {
        return ['type' => DeviceType::class];
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function mgmtIp()
    {
        return $this->belongsTo(IpAddress::class, 'mgmt_ip_id');
    }
}
