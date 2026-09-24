<?php

namespace App\Http\Controllers\Api;

use App\Models\Vlan;
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

    protected function rules(?Model $record): array
    {
        return [
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'vid' => [
                'required', 'integer', 'between:1,4094',
                Rule::unique('vlans', 'vid')->where('site_id', request('site_id'))->ignore($record),
            ],
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
