<?php

namespace App\Http\Controllers\Api;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentState;
use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Services\IncidentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IncidentController extends Controller
{
    private function present(Incident $i, bool $withEvents = false): array
    {
        $data = [
            'id' => $i->id,
            'title' => $i->title,
            'state' => $i->state->value,
            'severity' => $i->severity->value,
            'monitor' => $i->monitor?->only(['id', 'name']),
            'circuit' => $i->circuit?->only(['id', 'name']),
            'opened_at' => $i->opened_at,
            'acknowledged_at' => $i->acknowledged_at,
            'resolved_at' => $i->resolved_at,
            'closed_at' => $i->closed_at,
            'time_to_acknowledge_s' => $i->timeToAcknowledge(),
            'time_to_resolve_s' => $i->timeToResolve(),
            'provider_ticket' => $i->provider_ticket,
            'rfo_summary' => $i->rfo_summary,
            'rfo_root_cause' => $i->rfo_root_cause,
            'rfo_corrective_action' => $i->rfo_corrective_action,
            'allowed_next' => array_map(fn (IncidentState $s) => $s->value, $i->state->allowedNext()),
        ];

        if ($withEvents) {
            $data['events'] = $i->events()->with('user:id,name')->get()->map(fn ($e) => [
                'id' => $e->id, 'from_state' => $e->from_state?->value, 'to_state' => $e->to_state->value,
                'user' => $e->user?->name, 'note' => $e->note, 'created_at' => $e->created_at,
            ]);
        }

        return $data;
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Incident::class);
        $request->validate(['state' => ['sometimes', 'string'], 'monitor_id' => ['sometimes', 'integer']]);

        $query = Incident::with(['monitor:id,name', 'circuit:id,name'])->latest('opened_at')->latest('id');
        if ($request->input('state') === 'open') {
            $query->whereIn('state', IncidentState::openValues());
        } elseif ($request->filled('state')) {
            $query->where('state', $request->input('state'));
        }
        if ($request->filled('monitor_id')) {
            $query->where('monitor_id', $request->integer('monitor_id'));
        }

        return response()->json($query->paginate($request->integer('per_page', 25))->through(fn (Incident $i) => $this->present($i)));
    }

    public function show(int $id): JsonResponse
    {
        $incident = Incident::with(['monitor:id,name', 'circuit:id,name'])->findOrFail($id);
        Gate::authorize('view', $incident);

        return response()->json($this->present($incident, true));
    }

    /** Open count plus mean time to acknowledge / resolve over the last 30 days. */
    public function summary(): JsonResponse
    {
        Gate::authorize('viewAny', Incident::class);
        $recent = Incident::where('opened_at', '>=', now()->subDays(30));

        return response()->json([
            'open' => Incident::whereIn('state', IncidentState::openValues())->count(),
            'last_30d' => (clone $recent)->count(),
            'mtta_s' => $this->mean((clone $recent)->whereNotNull('acknowledged_at')->get(), fn (Incident $i) => $i->timeToAcknowledge()),
            'mttr_s' => $this->mean((clone $recent)->whereNotNull('resolved_at')->get(), fn (Incident $i) => $i->timeToResolve()),
        ]);
    }

    private function mean($incidents, callable $seconds): ?int
    {
        return $incidents->isEmpty() ? null : (int) round($incidents->avg($seconds));
    }

    public function transition(Request $request, int $id, IncidentService $service): JsonResponse
    {
        $incident = Incident::findOrFail($id);
        Gate::authorize('update', $incident);

        $input = $request->validate(['to' => ['required', Rule::enum(IncidentState::class)], 'note' => ['nullable', 'string', 'max:1000']]);
        $service->transition($incident, IncidentState::from($input['to']), $request->user(), $input['note'] ?? null);

        return $this->show($id);
    }

    /** Edit severity, the provider's ticket number and the RFO text. A closed incident is a record and stays as written. */
    public function update(Request $request, int $id): JsonResponse
    {
        $incident = Incident::findOrFail($id);
        Gate::authorize('update', $incident);

        if ($incident->state === IncidentState::Closed) {
            throw ValidationException::withMessages(['state' => 'A closed incident cannot be edited.']);
        }

        $incident->update($request->validate([
            'severity' => ['sometimes', Rule::enum(IncidentSeverity::class)],
            'provider_ticket' => ['nullable', 'string', 'max:100'],
            'rfo_summary' => ['nullable', 'string', 'max:5000'],
            'rfo_root_cause' => ['nullable', 'string', 'max:5000'],
            'rfo_corrective_action' => ['nullable', 'string', 'max:5000'],
        ]));

        return $this->show($id);
    }

    /** Reason-for-outage report as a PDF. Available once the incident is resolved; marked DRAFT until it is closed. */
    public function rfo(int $id)
    {
        $incident = Incident::with(['monitor', 'circuit.provider', 'circuit.aSite', 'circuit.zSite'])->findOrFail($id);
        Gate::authorize('view', $incident);

        if ($incident->state->isOpen()) {
            return response()->json(['message' => 'The RFO can be exported once the incident is resolved.'], 409);
        }

        return Pdf::loadView('rfo', ['incident' => $incident, 'events' => $incident->events()->with('user:id,name')->get(), 'generatedAt' => now()])
            ->download("RFO-INC-{$incident->id}.pdf");
    }
}
