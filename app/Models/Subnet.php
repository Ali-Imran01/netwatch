<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\IpStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['site_id', 'vlan_id', 'cidr', 'description', 'gateway'])]
class Subnet extends Model
{
    use Auditable, HasFactory;

    protected static function booted(): void
    {
        // Normalise the CIDR to its network address and derive the numeric range used for containment checks.
        static::saving(function (Subnet $subnet) {
            [$start, $end, $prefix] = static::range($subnet->cidr);

            $subnet->prefix = $prefix;
            $subnet->network_start = $start;
            $subnet->network_end = $end;
            $subnet->cidr = long2ip($start).'/'.$prefix;
        });
    }

    /**
     * Numeric [network, broadcast, prefix] for a CIDR such as "10.1.2.7/24", or null if it is not a valid IPv4 CIDR.
     *
     * @return array{int, int, int}|null
     */
    public static function range(string $cidr): ?array
    {
        if (! preg_match('#^(\d{1,3}(?:\.\d{1,3}){3})/(\d{1,2})$#', $cidr, $m) || ($long = ip2long($m[1])) === false || $m[2] > 32) {
            return null;
        }

        $prefix = (int) $m[2];
        $mask = $prefix === 0 ? 0 : (0xFFFFFFFF << (32 - $prefix)) & 0xFFFFFFFF;
        $start = $long & $mask;

        return [$start, $start | (~$mask & 0xFFFFFFFF), $prefix];
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** @return BelongsTo<Vlan, $this> */
    public function vlan(): BelongsTo
    {
        return $this->belongsTo(Vlan::class);
    }

    /** @return HasMany<IpAddress, $this> */
    public function ipAddresses(): HasMany
    {
        return $this->hasMany(IpAddress::class);
    }

    /** Usable host count: /31 has 2 (RFC 3021), /32 has 1, otherwise minus network and broadcast. */
    public function usableHosts(): int
    {
        $total = 2 ** (32 - $this->prefix);

        return $this->prefix >= 31 ? $total : $total - 2;
    }

    /** Percentage of usable addresses that are not free. Uses `used_count` when loaded via withCount. */
    public function utilization(): float
    {
        $used = $this->used_count ?? $this->ipAddresses()->where('status', '!=', IpStatus::Free->value)->count();

        return round($used / $this->usableHosts() * 100, 1);
    }
}
