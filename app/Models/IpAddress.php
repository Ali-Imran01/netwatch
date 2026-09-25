<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\IpStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['subnet_id', 'address', 'status', 'device_id', 'dns_name'])]
class IpAddress extends Model
{
    use Auditable, HasFactory;

    protected static function booted(): void
    {
        static::saving(fn (IpAddress $ip) => $ip->address_long = ip2long($ip->address));
    }

    protected function casts(): array
    {
        return ['status' => IpStatus::class];
    }

    /** @return BelongsTo<Subnet, $this> */
    public function subnet(): BelongsTo
    {
        return $this->belongsTo(Subnet::class);
    }

    /** @return BelongsTo<Device, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
