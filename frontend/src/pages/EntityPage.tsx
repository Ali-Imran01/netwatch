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
import { Icon } from '../components/Icon'
import { Skeleton } from '../components/Skeleton'
import { useAuth } from '../context/AuthContext'
import type { Entity, RowAction } from '../inventory/entities'

/** ISO string from the API → value for a datetime-local input, in the user's local time. */
const toLocalInput = (iso: unknown) => {
  if (!iso) return ''
  const d = new Date(String(iso))
  return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 16)
}

function RowForm({ entity, row, onDone, onCancel }: { entity: Entity; row: Row | null; onDone: () => void; onCancel: () => void }) {
  const [values, setValues] = useState<Record<string, unknown>>(() =>
    Object.fromEntries(
      entity.fields.map((f) => [f.key, f.type === 'datetime' ? toLocalInput(row?.[f.key]) : (row?.[f.key] ?? f.default ?? (f.type === 'checkbox' ? false : ''))]),
    ),
  )
  const [choices, setChoices] = useState<Record<string, Row[]>>({})
  const [error, setError] = useState<{ message: string; fields: FieldErrors } | null>(null)
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    entity.fields.forEach((f) => {
      if (f.ref) listAll(f.ref.path).then((rows) => setChoices((c) => ({ ...c, [f.key]: rows })))
    })
  }, [entity])

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => e.key === 'Escape' && onCancel()
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [onCancel])

  async function submit(e: FormEvent) {
    e.preventDefault()
    setSaving(true)
    setError(null)
    // Empty inputs are sent as null so optional fields can be cleared.
    const isDate = (k: string) => entity.fields.find((f) => f.key === k)?.type === 'datetime'
    const payload = Object.fromEntries(Object.entries(values).map(([k, v]) => [k, v === '' ? null : isDate(k) ? new Date(String(v)).toISOString() : v]))
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
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <form onSubmit={submit} role="dialog" aria-modal="true" aria-labelledby="row-form-title" className="max-h-full w-full max-w-md overflow-y-auto rounded-[28px] bg-white p-6 shadow-xl">
        <h2 id="row-form-title" className="text-2xl">{row ? `Edit ${entity.singular}` : `New ${entity.singular}`}</h2>
        {error && !Object.keys(error.fields).length && <p className="mt-3 rounded-xl bg-error-container p-3 text-sm text-on-error-container">{error.message}</p>}

        {entity.fields.map((f) => (
          <label key={f.key} className="mt-4 block text-sm font-medium text-on-surface-variant">
            {f.label}
            {f.required && ' *'}
            {f.type === 'checkbox' ? (
              <input
                type="checkbox"
                className="ml-3 size-5 align-middle accent-primary"
                checked={Boolean(values[f.key])}
                onChange={(e) => setValues({ ...values, [f.key]: e.target.checked })}
              />
            ) : f.type === 'select' || f.type === 'ref' ? (
              <select
                className="field"
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
                className="field"
                type={f.type === 'number' ? 'number' : f.type === 'datetime' ? 'datetime-local' : 'text'}
                step={f.type === 'number' ? 'any' : undefined}
                value={String(values[f.key] ?? '')}
                onChange={(e) => setValues({ ...values, [f.key]: e.target.value })}
              />
            )}
            {error?.fields[f.key]?.map((m) => (
              <span key={m} className="mt-1 block text-xs font-normal text-error">
                {m}
              </span>
            ))}
          </label>
        ))}

        <div className="mt-6 flex justify-end gap-2">
          <button type="button" onClick={onCancel} className="btn btn-text">
            Cancel
          </button>
          <button disabled={saving} className="btn btn-filled">
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
    <div className="card p-5 text-sm">
      <p className="text-base font-medium">Import CSV</p>
      <p className="mt-1 text-on-surface-variant">
        Required columns: <code className="rounded bg-neutral-container px-1 font-mono">{entity.csvHeader}</code>. Valid rows are imported; bad rows are listed below.
      </p>
      <input
        type="file"
        accept=".csv,text/csv"
        disabled={busy}
        className="mt-3 block text-sm file:mr-3 file:rounded-full file:border-0 file:bg-primary-container file:px-4 file:py-2 file:text-sm file:font-medium file:text-on-primary-container"
        onChange={(e) => {
          upload(e.target.files?.[0])
          e.target.value = ''
        }}
      />
      {busy && <p className="mt-2 text-on-surface-variant">Importing…</p>}
      {error && <p className="mt-2 text-error">{error}</p>}
      {result && (
        <div className="mt-3">
          <p>
            <span className="font-medium text-success">{result.imported} imported</span>,{' '}
            <span className={result.failed ? 'font-medium text-error' : ''}>{result.failed} failed</span>
          </p>
          {result.failed > 0 && (
            <ul className="mt-2 max-h-48 space-y-1 overflow-y-auto text-error">
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
  const [notice, setNotice] = useState<string | null>(null)
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

  async function act(action: RowAction, row: Row) {
    setNotice(null)
    setError(null)
    try {
      const message = await action.run(row)
      if (typeof message === 'string') setNotice(message)
      load()
    } catch (err) {
      setError(apiError(err).message)
    }
  }

  async function remove(row: Row) {
    if (!window.confirm(`Delete this ${entity.singular}?`)) return
    try {
      await deleteRow(entity.path, row.id)
      load()
    } catch (err) {
      setError(apiError(err).message)
    }
  }

  const cols = entity.columns.length + (canWrite ? 1 : 0)

  return (
    <div className="mx-auto max-w-6xl space-y-6">
      <div className="flex flex-wrap items-center gap-3">
        <h1 className="flex-1 text-[28px] leading-9">{entity.title}</h1>
        {canWrite && (
          <div className="flex gap-2">
            {entity.csvHeader && (
              <button onClick={() => setShowImport(!showImport)} className="btn btn-outlined">
                Import CSV
              </button>
            )}
            <button onClick={() => setEditing('new')} className="btn btn-filled">
              <Icon name="add" className="size-5" />
              New {entity.singular}
            </button>
          </div>
        )}
      </div>

      {showImport && canWrite && entity.csvHeader && <ImportPanel entity={entity} onImported={load} />}
      {error && <p className="rounded-xl bg-error-container p-3 text-sm text-on-error-container">{error}</p>}
      {notice && <p className="rounded-xl bg-success-container p-3 text-sm text-on-success-container">{notice}</p>}

      <div className="card overflow-x-auto">
        <table className="w-full text-left text-sm">
          <thead className="bg-surface text-xs tracking-wide text-on-surface-variant uppercase">
            <tr>
              {entity.columns.map((c) => (
                <th key={c.header} className="px-4 py-3 font-medium">
                  {c.header}
                </th>
              ))}
              {canWrite && <th className="px-4 py-3" />}
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-container">
            {!page && !error && [0, 1, 2, 3, 4].map((n) => (
              <tr key={n} aria-hidden="true">
                {Array.from({ length: cols }, (_, i) => (
                  <td key={i} className="px-4 py-3">
                    <Skeleton className="h-5 w-full max-w-40" />
                  </td>
                ))}
              </tr>
            ))}
            {page?.data.map((row) => (
              <tr key={row.id} className="hover:bg-surface">
                {entity.columns.map((c) => (
                  <td key={c.header} className="px-4 py-3">
                    {c.render(row) ?? <span className="text-outline-variant">—</span>}
                  </td>
                ))}
                {canWrite && (
                  <td className="px-4 py-2 text-right whitespace-nowrap">
                    {entity.actions?.map((a) => (
                      <button key={a.label} onClick={() => act(a, row)} className="btn btn-text min-h-9">
                        {a.label}
                      </button>
                    ))}
                    <button onClick={() => setEditing(row)} className="btn btn-text min-h-9">
                      Edit
                    </button>
                    <button onClick={() => remove(row)} className="btn min-h-9 px-3 text-error hover:bg-error-container/50">
                      Delete
                    </button>
                  </td>
                )}
              </tr>
            ))}
            {page?.data.length === 0 && (
              <tr>
                <td colSpan={cols} className="px-4 py-12 text-center text-on-surface-variant">
                  No {entity.title.toLowerCase()} yet.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {page && page.last_page > 1 && (
        <div className="flex items-center justify-between text-sm text-on-surface-variant">
          <span>
            Page {page.current_page} of {page.last_page} · {page.total} total
          </span>
          <div className="flex gap-2">
            <button disabled={pageNo <= 1} onClick={() => setPageNo(pageNo - 1)} className="btn btn-outlined">
              Previous
            </button>
            <button disabled={pageNo >= page.last_page} onClick={() => setPageNo(pageNo + 1)} className="btn btn-outlined">
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
