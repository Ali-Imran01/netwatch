<?php

namespace App\Http\Controllers\Api;

use App\Enums\DeviceType;
use App\Models\Device;
use App\Models\IpAddress;
use App\Models\Site;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DeviceController extends InventoryController
{
    protected function model(): string
    {
        return Device::class;
    }

    protected function filters(): array
    {
        return ['site_id', 'type'];
    }

    protected function query(): Builder
    {
        return Device::query()->with(['site:id,code', 'mgmtIp:id,address']);
    }

    protected function csvColumns(): array
    {
        return ['site_code', 'name', 'type', 'vendor', 'model', 'serial', 'mgmt_ip'];
    }

    protected function fromCsv(array $row): array
    {
        $siteId = $this->ref('site_code', Site::class, 'code', $row['site_code']);
        $mgmtIp = $row['mgmt_ip'] === null ? null : IpAddress::where('address', $row['mgmt_ip'])
            ->whereHas('subnet', fn ($q) => $q->where('site_id', $siteId))->value('id');
        if ($row['mgmt_ip'] !== null && $mgmtIp === null) {
            throw ValidationException::withMessages(['mgmt_ip' => "Unknown mgmt_ip '{$row['mgmt_ip']}' at this site."]);
        }

        return [
            'site_id' => $siteId,
            'name' => $row['name'],
            'type' => $row['type'],
            'vendor' => $row['vendor'],
            'model' => $row['model'],
            'serial' => $row['serial'],
            'mgmt_ip_id' => $mgmtIp,
        ];
    }

    protected function rules(?Model $record, array $input = []): array
    {
        return [
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('devices', 'name')->where('site_id', $input['site_id'] ?? null)->ignore($record),
            ],
            'type' => ['required', Rule::enum(DeviceType::class)],
            'vendor' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'mgmt_ip_id' => ['nullable', 'integer', 'exists:ip_addresses,id'],
            'serial' => ['nullable', 'string', 'max:255'],
        ];
    }
}
