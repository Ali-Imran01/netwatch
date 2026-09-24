<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Generic CRUD for inventory records. Subclasses supply the model, validation rules and optional eager loads.
 */
abstract class InventoryController extends Controller
{
    /** @return class-string<Model> */
    abstract protected function model(): string;

    /** @return array<string, mixed> */
    abstract protected function rules(?Model $record): array;

    /** Query-string parameters that filter the index by exact column match. */
    protected function filters(): array
    {
        return [];
    }

    protected function query(): Builder
    {
        return $this->model()::query();
    }

    /** Shape a record for the API. */
    protected function present(Model $record): array
    {
        return $record->toArray();
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', $this->model());

        $query = $this->query()->orderBy('id');
        foreach ($this->filters() as $column) {
            if ($request->filled($column)) {
                $query->where($column, $request->input($column));
            }
        }

        $page = $query->paginate($request->integer('per_page', 50));

        return response()->json($page->through(fn (Model $record) => $this->present($record)));
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', $this->model());

        $record = $this->model()::create($request->validate($this->rules(null)));

        return response()->json($this->present($this->query()->findOrFail($record->getKey())), 201);
    }

    public function show(int $id): JsonResponse
    {
        $record = $this->query()->findOrFail($id);
        Gate::authorize('view', $record);

        return response()->json($this->present($record));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $record = $this->query()->findOrFail($id);
        Gate::authorize('update', $record);

        $record->update($request->validate($this->rules($record)));

        return response()->json($this->present($this->query()->findOrFail($id)));
    }

    public function destroy(int $id): JsonResponse
    {
        $record = $this->query()->findOrFail($id);
        Gate::authorize('delete', $record);

        try {
            $record->delete();
        } catch (QueryException) {
            return response()->json(['message' => 'Cannot delete: other records still reference this one.'], 409);
        }

        return response()->json(null, 204);
    }
}
