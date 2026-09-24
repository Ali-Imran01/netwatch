<?php

namespace App\Http\Controllers\Api;

use App\Enums\IpStatus;
use App\Models\Device;
use App\Models\IpAddress;
use App\Models\Site;
use App\Models\Subnet;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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

    protected function csvColumns(): array
    {
        return ['site_code', 'subnet_cidr', 'address', 'status', 'device_name', 'dns_name'];
    }

    protected function fromCsv(array $row): array
    {
        $siteId = $this->ref('site_code', Site::class, 'code', $row['site_code']);
        $range = Subnet::range((string) $row['subnet_cidr']);
        $subnetId = $range === null
            ? throw ValidationException::withMessages(['subnet_cidr' => "Invalid subnet_cidr '{$row['subnet_cidr']}'."])
            : Subnet::where(['site_id' => $siteId, 'network_start' => $range[0], 'prefix' => $range[2]])->value('id');
        if ($subnetId === null) {
            throw ValidationException::withMessages(['subnet_cidr' => "Unknown subnet_cidr '{$row['subnet_cidr']}' at this site."]);
        }

        return [
            'subnet_id' => $subnetId,
            'address' => $row['address'],
            'status' => $row['status'] ?? IpStatus::Free->value,
            'device_id' => $this->ref('device_name', Device::class, 'name', $row['device_name'], ['site_id' => $siteId], optional: true),
            'dns_name' => $row['dns_name'],
        ];
    }

    protected function rules(?Model $record, array $input = []): array
    {
        return [
            'subnet_id' => ['required', 'integer', 'exists:subnets,id'],
            'address' => [
                'required', 'ip:ipv4',
                Rule::unique('ip_addresses', 'address')->where('subnet_id', $input['subnet_id'] ?? null)->ignore($record),
                function (string $attribute, mixed $value, Closure $fail) use ($input) {
                    $subnet = Subnet::find($input['subnet_id'] ?? null);
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
