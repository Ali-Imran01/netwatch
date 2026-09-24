<?php

namespace App\Http\Controllers\Api;

use App\Enums\IpStatus;
use App\Models\Subnet;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class SubnetController extends InventoryController
{
    protected function model(): string
    {
        return Subnet::class;
    }

    protected function filters(): array
    {
        return ['site_id', 'vlan_id'];
    }

    protected function query(): Builder
    {
        return Subnet::query()->withCount(['ipAddresses as used_count' => fn ($q) => $q->where('status', '!=', IpStatus::Free->value)]);
    }

    protected function present(Model $record): array
    {
        return $record->toArray() + [
            'usable_hosts' => $record->usableHosts(),
            'utilization' => $record->utilization(),
        ];
    }

    protected function rules(?Model $record): array
    {
        $range = fn (string $cidr) => Subnet::range($cidr);

        return [
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            // The VLAN must belong to the same site as the subnet.
            'vlan_id' => ['nullable', 'integer', Rule::exists('vlans', 'id')->where('site_id', request('site_id'))],
            'cidr' => [
                'required', 'string',
                function (string $attribute, mixed $value, Closure $fail) use ($range, $record) {
                    if (($r = $range($value)) === null || $r[2] < 8) {
                        return $fail('The CIDR must be a valid IPv4 network with a prefix between /8 and /32.');
                    }
                    $exists = Subnet::where('site_id', request('site_id'))
                        ->where('network_start', $r[0])->where('prefix', $r[2])
                        ->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))
                        ->exists();
                    if ($exists) {
                        $fail('This subnet already exists at the site.');
                    }
                },
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'gateway' => [
                'nullable', 'ip:ipv4',
                function (string $attribute, mixed $value, Closure $fail) use ($range) {
                    $r = $range((string) request('cidr'));
                    if ($r && (ip2long($value) < $r[0] || ip2long($value) > $r[1])) {
                        $fail('The gateway must be inside the subnet.');
                    }
                },
            ],
        ];
    }
}
