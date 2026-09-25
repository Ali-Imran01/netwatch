<?php

namespace App\Http\Controllers\Api;

use App\Enums\CircuitType;
use App\Models\Circuit;
use App\Services\SlaCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CircuitController extends InventoryController
{
    protected function model(): string
    {
        return Circuit::class;
    }

    protected function filters(): array
    {
        return ['provider_id', 'type'];
    }

    protected function query(): Builder
    {
        return Circuit::query()->with(['provider:id,name', 'aSite:id,code', 'zSite:id,code']);
    }

    protected function present(Model $record): array
    {
        return [...$record->toArray(), 'type' => $record->type->value, 'sla_30d' => app(SlaCalculator::class)->forCircuit($record, now()->subDays(30), now())];
    }

    protected function rules(?Model $record, array $input = []): array
    {
        return [
            'provider_id' => ['required', 'integer', 'exists:providers,id'],
            'a_site_id' => ['nullable', 'integer', 'exists:sites,id'],
            'z_site_id' => ['nullable', 'integer', 'exists:sites,id'],
            'circuit_ref' => ['required', 'string', 'max:100', Rule::unique('circuits', 'circuit_ref')->where('provider_id', $input['provider_id'] ?? null)->ignore($record)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(CircuitType::class)],
            'bandwidth_mbps' => ['nullable', 'integer', 'min:1'],
            'sla_target' => ['sometimes', 'numeric', 'between:90,100'],
        ];
    }

    /** Availability for an arbitrary period (default: the current calendar month), maintenance windows excluded. */
    public function sla(Request $request, int $id, SlaCalculator $sla): JsonResponse
    {
        $circuit = $this->query()->findOrFail($id);
        Gate::authorize('view', $circuit);

        $input = $request->validate(['from' => ['sometimes', 'date'], 'to' => ['sometimes', 'date', 'after:from']]);
        $from = CarbonImmutable::parse($input['from'] ?? now()->startOfMonth());
        $to = CarbonImmutable::parse($input['to'] ?? now());

        return response()->json($sla->forCircuit($circuit, $from, $to));
    }
}
