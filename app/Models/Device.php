<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\DeviceType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['site_id', 'name', 'type', 'vendor', 'model', 'mgmt_ip_id', 'serial'])]
class Device extends Model
{
    use Auditable, HasFactory;

    protected function casts(): array
    {
        return ['type' => DeviceType::class];
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** @return BelongsTo<IpAddress, $this> */
    public function mgmtIp(): BelongsTo
    {
        return $this->belongsTo(IpAddress::class, 'mgmt_ip_id');
    }
}
