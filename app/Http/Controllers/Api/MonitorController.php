<?php

namespace App\Http\Controllers\Api;

use App\Enums\MonitorType;
use App\Models\Device;
use App\Models\Monitor;
use App\Services\CheckRunner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class MonitorController extends InventoryController
{
    private const HOSTNAME = '/^(?=.{1,253}$)[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?(\.[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?)*$/';

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
        return Monitor::query()->with('monitorable');
    }

    protected function present(Model $record): array
    {
        return [
            ...$record->only(['id', 'name', 'type', 'target', 'port', 'interval_s', 'timeout_ms', 'enabled', 'state',
                'next_check_at', 'last_checked_at', 'last_success', 'last_latency_ms']),
            'type' => $record->type->value,
            'device_id' => $record->monitorable_id,
            'device' => $record->monitorable?->only(['id', 'name']),
        ];
    }

    protected function attributes(array $validated): array
    {
        $device = $validated['device_id'] ?? null;
        unset($validated['device_id']);

        return [...$validated, 'monitorable_type' => $device ? Device::class : null, 'monitorable_id' => $device];
    }

    protected function rules(?Model $record, array $input = []): array
    {
        $type = MonitorType::tryFrom((string) ($input['type'] ?? ''));

        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(MonitorType::class)],
            // Targets go straight to ping/sockets, so accept only a plain host or, for HTTP, an http(s) URL.
            'target' => ['required', 'string', 'max:255', $type === MonitorType::Http
                ? 'url:http,https'
                : fn ($attr, $value, $fail) => filter_var($value, FILTER_VALIDATE_IP) || preg_match(self::HOSTNAME, (string) $value)
                    ? null : $fail('The target must be a hostname or IP address.')],
            'port' => [$type === MonitorType::Tcp ? 'required' : 'nullable', 'integer', 'between:1,65535'],
            'interval_s' => ['required', 'integer', 'between:30,86400'],
            'timeout_ms' => ['required', 'integer', 'between:100,30000', fn ($attr, $value, $fail) => ($value / 1000) < ($input['interval_s'] ?? 0)
                ? null : $fail('The timeout must be shorter than the interval.')],
            'device_id' => ['nullable', 'integer', 'exists:devices,id'],
            'enabled' => ['sometimes', 'boolean'],
        ];
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
