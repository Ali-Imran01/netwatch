<?php

namespace App\Http\Controllers\Api;

use App\Enums\DeviceType;
use App\Models\Device;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

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
        return Device::query()->with('mgmtIp:id,address');
    }

    protected function rules(?Model $record): array
    {
        return [
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('devices', 'name')->where('site_id', request('site_id'))->ignore($record),
            ],
            'type' => ['required', Rule::enum(DeviceType::class)],
            'vendor' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'mgmt_ip_id' => ['nullable', 'integer', 'exists:ip_addresses,id'],
            'serial' => ['nullable', 'string', 'max:255'],
        ];
    }
}
