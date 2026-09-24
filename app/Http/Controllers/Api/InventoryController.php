<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Generic CRUD for inventory records. Subclasses supply the model, validation rules and optional eager loads.
 */
abstract class InventoryController extends Controller
{
    /** @return class-string<Model> */
    abstract protected function model(): string;

    /** @return array<string, mixed> */
    abstract protected function rules(?Model $record, array $input): array;

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

        $record = $this->model()::create($request->validate($this->rules(null, $request->all())));

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

        $record->update($request->validate($this->rules($record, $request->all())));

        return response()->json($this->present($this->query()->findOrFail($id)));
    }

    /** CSV headers that must be present for this entity's import. */
    protected function csvColumns(): array
    {
        return array_keys($this->rules(null, []));
    }

    /**
     * Turn a CSV row (header => value|null) into model attributes. Override to resolve human-friendly
     * references (site code, subnet CIDR, ...) into ids; throw a ValidationException for unknown ones.
     */
    protected function fromCsv(array $row): array
    {
        return $row;
    }

    /** Look up the id of a referenced record by a natural key, or fail with a message keyed on the CSV column. */
    protected function ref(string $column, string $model, string $key, ?string $value, array $where = [], bool $optional = false): ?int
    {
        if ($value === null) {
            return $optional ? null : throw ValidationException::withMessages([$column => "The {$column} field is required."]);
        }

        $id = $model::where($key, $value)->where($where)->value('id');

        return $id ?? throw ValidationException::withMessages([$column => "Unknown {$column} '{$value}'."]);
    }

    /**
     * Import one CSV: valid rows are created, invalid rows are reported with their file row number
     * (the header is row 1). Uses the same validation rules as the create endpoint.
     */
    public function import(Request $request): JsonResponse
    {
        Gate::authorize('create', $this->model());
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $headers = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), fgetcsv($handle) ?: []);
        $missing = array_diff($this->csvColumns(), $headers);
        if ($missing) {
            throw ValidationException::withMessages(['file' => 'Missing CSV columns: '.implode(', ', $missing).'.']);
        }

        $imported = 0;
        $errors = [];
        $rowNumber = 1;
        while (($cells = fgetcsv($handle)) !== false) {
            $rowNumber++;
            if ($cells === [null]) {
                continue; // blank line
            }
            if ($rowNumber > 5001) {
                throw ValidationException::withMessages(['file' => 'A CSV can contain at most 5000 rows.']);
            }

            $cells = array_pad(array_slice($cells, 0, count($headers)), count($headers), null);
            $row = array_map(fn ($v) => ($v = trim((string) $v)) === '' ? null : $v, array_combine($headers, $cells));

            try {
                $data = $this->fromCsv($row);
                $this->model()::create(Validator::make($data, $this->rules(null, $data))->validate());
                $imported++;
            } catch (ValidationException $e) {
                $errors[] = ['row' => $rowNumber, 'errors' => $e->errors()];
            }
        }
        fclose($handle);

        return response()->json(['imported' => $imported, 'failed' => count($errors), 'errors' => $errors]);
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
