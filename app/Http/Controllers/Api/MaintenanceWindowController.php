<?php

namespace App\Http\Controllers\Api;

use App\Models\MaintenanceWindow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MaintenanceWindowController extends InventoryController
{
    protected function model(): string
    {
        return MaintenanceWindow::class;
    }

    protected function filters(): array
    {
        return ['circuit_id', 'monitor_id'];
    }

    protected function query(): Builder
    {
        return MaintenanceWindow::query()->with(['circuit:id,name,circuit_ref', 'monitor:id,name'])->latest('starts_at');
    }

    protected function present(Model $record): array
    {
        return [...$record->toArray(), 'status' => match (true) {
            $record->ends_at <= now() => 'done',
            $record->starts_at <= now() => 'active',
            default => 'scheduled',
        }];
    }

    protected function rules(?Model $record, array $input = []): array
    {
        return [
            // Exactly one target: a circuit (all its monitors) or a single monitor.
            'circuit_id' => ['nullable', 'integer', 'exists:circuits,id', 'required_without:monitor_id', 'prohibits:monitor_id'],
            'monitor_id' => ['nullable', 'integer', 'exists:monitors,id', 'required_without:circuit_id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'provider_ref' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
