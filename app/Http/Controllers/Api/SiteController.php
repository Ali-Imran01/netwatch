<?php

namespace App\Http\Controllers\Api;

use App\Models\Site;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class SiteController extends InventoryController
{
    protected function model(): string
    {
        return Site::class;
    }

    protected function rules(?Model $record): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', Rule::unique('sites', 'code')->ignore($record)],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'size:2'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
