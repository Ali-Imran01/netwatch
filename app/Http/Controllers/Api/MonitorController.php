<?php

namespace App\Http\Controllers\Api;

use App\Enums\MonitorType;
use App\Models\CheckRollup;
use App\Models\Circuit;
use App\Models\Device;
use App\Models\Incident;
use App\Models\MaintenanceWindow;
use App\Models\Monitor;
use App\Probes\SimulatorProbe;
use App\Services\CheckRunner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class MonitorController extends InventoryController
{
    private const HOSTNAME = '/^(?=.{1,253}$)[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?(\.[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?)*$/';

    /** Windows open right now, loaded once per query() so a list of monitors costs one extra query, not one each. */
    private ?Collection $activeWindows = null;

    protected function model(): string
    {
        return Monitor::class;
    }

    protected function filters(): array
    {
        return ['type', 'enabled'];
    }

    protected function query(): Builder
    {
        $this->activeWindows = MaintenanceWindow::active()->get();

        return Monitor::query()->with('monitorable');
    }

    protected function present(Model $record): array
    {
        return [
            ...$record->only(['id', 'name', 'type', 'target', 'port', 'interval_s', 'timeout_ms', 'enabled', 'state',
                'next_check_at', 'last_checked_at', 'last_success', 'last_latency_ms', 'state_changed_at', 'down_after', 'up_after']),
            'type' => $record->type->value,
            'state' => $record->state->value,
            'device_id' => $record->monitorable_type === Device::class ? $record->monitorable_id : null,
            'device' => $record->monitorable instanceof Device ? $record->monitorable->only(['id', 'name']) : null,
            'circuit_id' => $record->monitorable_type === Circuit::class ? $record->monitorable_id : null,
            'circuit' => $record->monitorable instanceof Circuit ? $record->monitorable->only(['id', 'name']) : null,
            'in_maintenance' => $record->inMaintenance($this->activeWindows),
        ];
    }

    protected function attributes(array $validated): array
    {
        $device = $validated['device_id'] ?? null;
        $circuit = $validated['circuit_id'] ?? null;
        unset($validated['device_id'], $validated['circuit_id']);

        return [...$validated,
            'monitorable_type' => $device ? Device::class : ($circuit ? Circuit::class : null),
            'monitorable_id' => $device ?? $circuit];
    }

    protected function rules(?Model $record, array $input = []): array
    {
        $type = MonitorType::tryFrom((string) ($input['type'] ?? ''));

        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(MonitorType::class)],
            // Targets go straight to ping/sockets, so accept only a plain host or, for HTTP, an http(s) URL.
            'target' => ['required', 'string', 'max:255', match ($type) {
                MonitorType::Http => 'url:http,https',
                MonitorType::Simulator => Rule::in(array_keys(SimulatorProbe::SCENARIOS)),
                default => fn ($attr, $value, $fail) => filter_var($value, FILTER_VALIDATE_IP) || preg_match(self::HOSTNAME, (string) $value)
                    ? null : $fail('The target must be a hostname or IP address.'),
            }],
            'port' => [$type === MonitorType::Tcp ? 'required' : 'nullable', 'integer', 'between:1,65535'],
            'interval_s' => ['required', 'integer', 'between:30,86400'],
            'timeout_ms' => ['required', 'integer', 'between:100,30000', fn ($attr, $value, $fail) => ($value / 1000) < ($input['interval_s'] ?? 0)
                ? null : $fail('The timeout must be shorter than the interval.')],
            'device_id' => ['nullable', 'integer', 'exists:devices,id', 'prohibits:circuit_id'],
            'circuit_id' => ['nullable', 'integer', 'exists:circuits,id'],
            'enabled' => ['sometimes', 'boolean'],
            'down_after' => ['sometimes', 'integer', 'between:1,10'],
            'up_after' => ['sometimes', 'integer', 'between:1,10'],
        ];
    }

    /** Incidents are the record of an outage (and its RFO), so a monitor that has any cannot be deleted. Say so, and offer the alternative. */
    public function destroy(int $id): JsonResponse
    {
        $monitor = Monitor::findOrFail($id);
        Gate::authorize('delete', $monitor);

        $incidents = Incident::where('monitor_id', $id)->count();
        if ($incidents > 0) {
            return response()->json([
                'message' => "This monitor has {$incidents} incident(s) on record, so it can't be deleted. Untick Enabled in Edit to stop checking it and hide it from the Status board.",
            ], 409);
        }

        return parent::destroy($id);
    }

    /** Latency/availability series for the chart: raw results for 1h, 5-minute rollups for 24h, hourly for 7d. */
    public function history(Request $request, int $id): JsonResponse
    {
        $monitor = $this->query()->findOrFail($id);
        Gate::authorize('view', $monitor);

        $range = $request->validate(['range' => ['sometimes', 'in:1h,24h,7d']])['range'] ?? '1h';

        if ($range === '1h') {
            $points = $monitor->results()->where('checked_at', '>=', now()->subHour())->orderBy('checked_at')->get()
                ->map(fn ($r) => ['t' => $r->checked_at->toIso8601String(), 'checks' => 1, 'failures' => $r->success ? 0 : 1,
                    'avg' => $r->latency_ms, 'p95' => $r->latency_ms]);
        } else {
            [$size, $since] = $range === '24h' ? [300, now()->subDay()] : [3600, now()->subDays(7)];
            $points = CheckRollup::where('monitor_id', $id)->where('bucket_size', $size)->where('bucket_start', '>=', $since)
                ->orderBy('bucket_start')->get()
                ->map(fn ($r) => ['t' => $r->bucket_start->toIso8601String(), 'checks' => $r->checks, 'failures' => $r->failures,
                    'avg' => $r->avg_latency_ms, 'p95' => $r->p95_latency_ms]);
        }

        return response()->json(['range' => $range, 'points' => $points->values()]);
    }

    /** Run the check now, synchronously, and return the refreshed monitor with the result. */
    public function run(int $id, CheckRunner $runner): JsonResponse
    {
        $monitor = $this->query()->findOrFail($id);
        Gate::authorize('update', $monitor);

        $result = $runner->run($monitor);

        return response()->json([
            'monitor' => $this->present($this->query()->findOrFail($id)),
            'result' => $result->only(['checked_at', 'success', 'latency_ms', 'detail']),
        ]);
    }
}
