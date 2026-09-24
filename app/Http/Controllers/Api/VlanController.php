<?php

namespace App\Http\Controllers\Api;

use App\Models\Site;
use App\Models\Vlan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class VlanController extends InventoryController
{
    protected function model(): string
    {
        return Vlan::class;
    }

    protected function filters(): array
    {
        return ['site_id'];
    }

    protected function query(): Builder
    {
        return Vlan::query()->with('site:id,code');
    }

    protected function csvColumns(): array
    {
        return ['site_code', 'vid', 'name'];
    }

    protected function fromCsv(array $row): array
    {
        return [
            'site_id' => $this->ref('site_code', Site::class, 'code', $row['site_code']),
            'vid' => $row['vid'],
            'name' => $row['name'],
        ];
    }

    protected function rules(?Model $record, array $input = []): array
    {
        return [
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'vid' => [
                'required', 'integer', 'between:1,4094',
                Rule::unique('vlans', 'vid')->where('site_id', $input['site_id'] ?? null)->ignore($record),
            ],
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
