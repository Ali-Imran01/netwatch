import { useCallback, useEffect, useState, type FormEvent } from 'react'
import {
  apiError,
  deleteRow,
  importCsv,
  listAll,
  listRows,
  saveRow,
  type FieldErrors,
  type ImportResult,
  type Page,
  type Row,
} from '../api/inventory'
import { useAuth } from '../context/AuthContext'
import type { Entity } from '../inventory/entities'

const inputClass = 'mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm'

function RowForm({ entity, row, onDone, onCancel }: { entity: Entity; row: Row | null; onDone: () => void; onCancel: () => void }) {
  const [values, setValues] = useState<Record<string, unknown>>(() =>
    Object.fromEntries(entity.fields.map((f) => [f.key, row?.[f.key] ?? ''])),
  )
  const [choices, setChoices] = useState<Record<string, Row[]>>({})
  const [error, setError] = useState<{ message: string; fields: FieldErrors } | null>(null)
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    entity.fields.forEach((f) => {
      if (f.ref) listAll(f.ref.path).then((rows) => setChoices((c) => ({ ...c, [f.key]: rows })))
    })
  }, [entity])

  async function submit(e: FormEvent) {
    e.preventDefault()
    setSaving(true)
    setError(null)
    // Empty inputs are sent as null so optional fields can be cleared.
    const payload = Object.fromEntries(Object.entries(values).map(([k, v]) => [k, v === '' ? null : v]))
    try {
      await saveRow(entity.path, payload, row?.id)
      onDone()
    } catch (err) {
      setError(apiError(err))
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="fixed inset-0 z-10 flex items-center justify-center bg-black/40 p-4">
      <form onSubmit={submit} className="max-h-full w-full max-w-md overflow-y-auto rounded-lg bg-white p-6 shadow-xl">
        <h2 className="text-lg font-semibold">{row ? `Edit ${entity.singular}` : `New ${entity.singular}`}</h2>
        {error && !Object.keys(error.fields).length && <p className="mt-2 text-sm text-red-600">{error.message}</p>}

        {entity.fields.map((f) => (
          <label key={f.key} className="mt-4 block text-sm font-medium text-gray-700">
            {f.label}
            {f.required && ' *'}
            {f.type === 'select' || f.type === 'ref' ? (
              <select
                className={inputClass}
                value={String(values[f.key] ?? '')}
                onChange={(e) => setValues({ ...values, [f.key]: e.target.value })}
              >
                <option value="">{f.required ? 'Select…' : '— none —'}</option>
                {f.type === 'select'
                  ? f.options?.map((o) => <option key={o}>{o}</option>)
                  : choices[f.key]?.map((c) => (
                      <option key={c.id} value={c.id}>
                        {f.ref!.label(c)}
                      </option>
                    ))}
              </select>
            ) : (
              <input
                className={inputClass}
                type={f.type === 'number' ? 'number' : 'text'}
                step={f.type === 'number' ? 'any' : undefined}
                value={String(values[f.key] ?? '')}
                onChange={(e) => setValues({ ...values, [f.key]: e.target.value })}
              />
            )}
            {error?.fields[f.key]?.map((m) => (
              <span key={m} className="mt-1 block text-xs font-normal text-red-600">
                {m}
              </span>
            ))}
          </label>
        ))}

        <div className="mt-6 flex justify-end gap-3">
          <button type="button" onClick={onCancel} className="rounded px-4 py-2 text-sm text-gray-600 hover:bg-gray-100">
            Cancel
          </button>
          <button disabled={saving} className="rounded bg-gray-900 px-4 py-2 text-sm text-white hover:bg-gray-700 disabled:opacity-50">
            {saving ? 'Saving…' : 'Save'}
          </button>
        </div>
      </form>
    </div>
  )
}

function ImportPanel({ entity, onImported }: { entity: Entity; onImported: () => void }) {
  const [result, setResult] = useState<ImportResult | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  async function upload(file: File | undefined) {
    if (!file) return
    setBusy(true)
    setResult(null)
    setError(null)
    try {
      setResult(await importCsv(entity.path, file))
      onImported()
    } catch (err) {
      const { message, fields } = apiError(err)
      setError(fields.file?.[0] ?? message)
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="mb-6 rounded border border-gray-200 bg-white p-4 text-sm">
      <p className="font-medium">Import CSV</p>
      <p className="mt-1 text-gray-500">
        Required columns: <code className="rounded bg-gray-100 px-1">{entity.csvHeader}</code>. Valid rows are imported; bad rows are listed below.
      </p>
      <input
        type="file"
        accept=".csv,text/csv"
        disabled={busy}
        className="mt-3 block text-sm"
        onChange={(e) => {
          upload(e.target.files?.[0])
          e.target.value = ''
        }}
      />
      {busy && <p className="mt-2 text-gray-500">Importing…</p>}
      {error && <p className="mt-2 text-red-600">{error}</p>}
      {result && (
        <div className="mt-3">
          <p>
            <span className="font-medium text-emerald-700">{result.imported} imported</span>,{' '}
            <span className={result.failed ? 'font-medium text-red-600' : ''}>{result.failed} failed</span>
          </p>
          {result.failed > 0 && (
            <ul className="mt-2 max-h-48 space-y-1 overflow-y-auto text-red-600">
              {result.errors.map((e) => (
                <li key={e.row}>
                  Row {e.row}: {Object.entries(e.errors).map(([col, msgs]) => `${col} — ${msgs.join(' ')}`).join('; ')}
                </li>
              ))}
            </ul>
          )}
        </div>
      )}
    </div>
  )
}

export default function EntityPage({ entity }: { entity: Entity }) {
  const { user } = useAuth()
  const canWrite = user?.role === 'admin' || user?.role === 'engineer'
  const [pageNo, setPageNo] = useState(1)
  const [page, setPage] = useState<Page | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [editing, setEditing] = useState<Row | 'new' | null>(null)
  const [showImport, setShowImport] = useState(false)

  const load = useCallback(() => {
    listRows(entity.path, pageNo)
      .then((p) => {
        setPage(p)
        setError(null)
      })
      .catch((err) => setError(apiError(err).message))
  }, [entity.path, pageNo])

  useEffect(load, [load])

  async function remove(row: Row) {
    if (!window.confirm(`Delete this ${entity.singular}?`)) return
    try {
      await deleteRow(entity.path, row.id)
      load()
    } catch (err) {
      setError(apiError(err).message)
    }
  }

  return (
    <div>
      <div className="mb-4 flex items-center justify-between">
        <h1 className="text-2xl font-semibold text-gray-900">{entity.title}</h1>
        {canWrite && (
          <div className="flex gap-2">
            <button onClick={() => setShowImport(!showImport)} className="rounded border border-gray-300 px-3 py-2 text-sm hover:bg-gray-100">
              Import CSV
            </button>
            <button onClick={() => setEditing('new')} className="rounded bg-gray-900 px-3 py-2 text-sm text-white hover:bg-gray-700">
              New {entity.singular}
            </button>
          </div>
        )}
      </div>

      {showImport && canWrite && <ImportPanel entity={entity} onImported={load} />}
      {error && <p className="mb-4 rounded bg-red-50 p-3 text-sm text-red-700">{error}</p>}

      <div className="overflow-x-auto rounded border border-gray-200 bg-white">
        <table className="w-full text-left text-sm">
          <thead className="bg-gray-50 text-xs uppercase text-gray-500">
            <tr>
              {entity.columns.map((c) => (
                <th key={c.header} className="px-4 py-3 font-medium">
                  {c.header}
                </th>
              ))}
              {canWrite && <th className="px-4 py-3" />}
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {page?.data.map((row) => (
              <tr key={row.id}>
                {entity.columns.map((c) => (
                  <td key={c.header} className="px-4 py-2">
                    {c.render(row) ?? <span className="text-gray-300">—</span>}
                  </td>
                ))}
                {canWrite && (
                  <td className="whitespace-nowrap px-4 py-2 text-right">
                    <button onClick={() => setEditing(row)} className="mr-3 text-gray-600 hover:text-gray-900">
                      Edit
                    </button>
                    <button onClick={() => remove(row)} className="text-red-600 hover:text-red-800">
                      Delete
                    </button>
                  </td>
                )}
              </tr>
            ))}
            {page?.data.length === 0 && (
              <tr>
                <td colSpan={entity.columns.length + 1} className="px-4 py-8 text-center text-gray-500">
                  No {entity.title.toLowerCase()} yet.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {page && page.last_page > 1 && (
        <div className="mt-4 flex items-center justify-between text-sm text-gray-600">
          <span>
            Page {page.current_page} of {page.last_page} · {page.total} total
          </span>
          <div className="flex gap-2">
            <button disabled={pageNo <= 1} onClick={() => setPageNo(pageNo - 1)} className="rounded border px-3 py-1 disabled:opacity-40">
              Previous
            </button>
            <button disabled={pageNo >= page.last_page} onClick={() => setPageNo(pageNo + 1)} className="rounded border px-3 py-1 disabled:opacity-40">
              Next
            </button>
          </div>
        </div>
      )}

      {editing && (
        <RowForm
          entity={entity}
          row={editing === 'new' ? null : editing}
          onCancel={() => setEditing(null)}
          onDone={() => {
            setEditing(null)
            load()
          }}
        />
      )}
    </div>
  )
}
