<?php

namespace App\Http\Controllers\Api;

use App\Models\Provider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class ProviderController extends InventoryController
{
    protected function model(): string
    {
        return Provider::class;
    }

    protected function rules(?Model $record, array $input = []): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('providers', 'name')->ignore($record)],
            'noc_email' => ['nullable', 'email', 'max:255'],
            'noc_phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
