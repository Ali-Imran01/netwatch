<?php

namespace App\Http\Controllers\Api;

use App\Enums\IpStatus;
use App\Models\IpAddress;
use App\Models\Subnet;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class IpAddressController extends InventoryController
{
    protected function model(): string
    {
        return IpAddress::class;
    }

    protected function filters(): array
    {
        return ['subnet_id', 'status', 'device_id'];
    }

    protected function query(): Builder
    {
        return IpAddress::query()->with('device:id,name');
    }

    protected function rules(?Model $record): array
    {
        return [
            'subnet_id' => ['required', 'integer', 'exists:subnets,id'],
            'address' => [
                'required', 'ip:ipv4',
                Rule::unique('ip_addresses', 'address')->where('subnet_id', request('subnet_id'))->ignore($record),
                function (string $attribute, mixed $value, Closure $fail) {
                    $subnet = Subnet::find(request('subnet_id'));
                    if (! $subnet) {
                        return;
                    }
                    $long = ip2long($value);
                    if ($long < $subnet->network_start || $long > $subnet->network_end) {
                        return $fail("The address must be inside {$subnet->cidr}.");
                    }
                    // Network and broadcast addresses are not usable hosts, except in /31 and /32.
                    if ($subnet->prefix < 31 && ($long === $subnet->network_start || $long === $subnet->network_end)) {
                        $fail('The network and broadcast addresses cannot be assigned.');
                    }
                },
            ],
            'status' => ['required', Rule::enum(IpStatus::class)],
            'device_id' => ['nullable', 'integer', 'exists:devices,id'],
            'dns_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
