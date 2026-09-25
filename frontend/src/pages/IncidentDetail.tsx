import { useCallback, useEffect, useState, type FormEvent } from 'react'
import { Link, useParams } from 'react-router-dom'
import { apiError } from '../api/inventory'
import { downloadRfo, duration, getIncident, saveIncident, stateLabel, transitionIncident, type Incident } from '../api/incidents'
import { useAuth } from '../context/AuthContext'

const rfoFields = [
  { key: 'rfo_summary', label: 'What happened' },
  { key: 'rfo_root_cause', label: 'Root cause' },
  { key: 'rfo_corrective_action', label: 'Corrective action' },
] as const

const area = 'mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm'

export default function IncidentDetail() {
  const { id } = useParams()
  const incidentId = Number(id)
  const { user } = useAuth()
  const canWrite = user?.role === 'admin' || user?.role === 'engineer'

  const [incident, setIncident] = useState<Incident | null>(null)
  const [note, setNote] = useState('')
  const [form, setForm] = useState<Record<string, string>>({})
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  const load = useCallback(() => {
    getIncident(incidentId)
      .then((i) => {
        setIncident(i)
        setForm({ provider_ticket: i.provider_ticket ?? '', rfo_summary: i.rfo_summary ?? '', rfo_root_cause: i.rfo_root_cause ?? '', rfo_corrective_action: i.rfo_corrective_action ?? '' })
      })
      .catch((e) => setError(apiError(e).message))
  }, [incidentId])

  useEffect(load, [load])

  async function run(action: () => Promise<unknown>, done?: string) {
    setBusy(true)
    setError(null)
    setNotice(null)
    try {
      await action()
      if (done) setNotice(done)
      load()
    } catch (e) {
      const { message, fields } = apiError(e)
      setError(Object.values(fields)[0]?.[0] ?? message)
    } finally {
      setBusy(false)
    }
  }

  const move = (to: string) => run(async () => {
    await transitionIncident(incidentId, to, note)
    setNote('')
  })

  const saveRfo = (e: FormEvent) => {
    e.preventDefault()
    return run(() => saveIncident(incidentId, form), 'Saved.')
  }

  if (!incident) return <p className="text-sm text-gray-500">{error ?? 'Loading…'}</p>

  const closed = incident.state === 'closed'
  const resolvedOrLater = incident.state === 'resolved' || closed

  return (
    <div className="max-w-4xl">
      <Link to="/incidents" className="text-sm text-gray-500 hover:text-gray-900">← Incidents</Link>
      <h1 className="mt-2 text-2xl font-semibold text-gray-900">
        #{incident.id} {incident.title}
      </h1>
      <p className="mt-1 text-sm text-gray-500">
        {stateLabel[incident.state]} · {incident.severity} · opened {new Date(incident.opened_at).toLocaleString()}
        {incident.monitor && <> · <Link className="underline" to={`/monitoring/monitors/${incident.monitor.id}`}>{incident.monitor.name}</Link></>}
        {incident.circuit && <> · {incident.circuit.name}</>}
      </p>
      <p className="mt-1 text-sm text-gray-500">
        To acknowledge {duration(incident.time_to_acknowledge_s)} · to resolve {duration(incident.time_to_resolve_s)}
      </p>

      {error && <p className="mt-4 rounded bg-red-50 p-3 text-sm text-red-700">{error}</p>}
      {notice && <p className="mt-4 rounded bg-emerald-50 p-3 text-sm text-emerald-700">{notice}</p>}

      {canWrite && incident.allowed_next.length > 0 && (
        <div className="mt-6 rounded border border-gray-200 bg-white p-4">
          <p className="text-sm font-medium text-gray-700">Move to</p>
          <input className={area} placeholder="Note for the timeline (optional)" value={note} onChange={(e) => setNote(e.target.value)} maxLength={1000} />
          <div className="mt-3 flex flex-wrap gap-2">
            {incident.allowed_next.map((s) => (
              <button key={s} disabled={busy} onClick={() => move(s)} className="rounded bg-gray-900 px-3 py-2 text-sm text-white hover:bg-gray-700 disabled:opacity-50">
                {stateLabel[s]}
              </button>
            ))}
          </div>
          {incident.allowed_next.includes('closed') && <p className="mt-2 text-xs text-gray-500">Closing needs the RFO summary below.</p>}
        </div>
      )}

      <h2 className="mt-8 text-lg font-semibold text-gray-900">Timeline</h2>
      <ol className="mt-3 space-y-3 border-l border-gray-200 pl-4">
        {incident.events?.map((e) => (
          <li key={e.id} className="text-sm">
            <p className="font-medium text-gray-900">
              {e.from_state ? `${stateLabel[e.from_state]} → ` : ''}
              {stateLabel[e.to_state]}
            </p>
            <p className="text-xs text-gray-500">
              {new Date(e.created_at).toLocaleString()} · {e.user ?? 'System'}
            </p>
            {e.note && <p className="mt-1 text-gray-700">{e.note}</p>}
          </li>
        ))}
      </ol>

      <div className="mt-8 flex items-center justify-between">
        <h2 className="text-lg font-semibold text-gray-900">Reason for outage (RFO)</h2>
        {resolvedOrLater && (
          <button disabled={busy} onClick={() => run(() => downloadRfo(incidentId))} className="rounded border border-gray-300 px-3 py-2 text-sm hover:bg-gray-100 disabled:opacity-50">
            Download PDF{closed ? '' : ' (draft)'}
          </button>
        )}
      </div>

      <form onSubmit={saveRfo} className="mt-3 space-y-4 rounded border border-gray-200 bg-white p-4">
        <label className="block text-sm font-medium text-gray-700">
          Provider ticket no.
          <input className={area} value={form.provider_ticket ?? ''} disabled={!canWrite || closed} onChange={(e) => setForm({ ...form, provider_ticket: e.target.value })} />
        </label>
        {rfoFields.map((f) => (
          <label key={f.key} className="block text-sm font-medium text-gray-700">
            {f.label}
            <textarea rows={3} className={area} value={form[f.key] ?? ''} disabled={!canWrite || closed} onChange={(e) => setForm({ ...form, [f.key]: e.target.value })} />
          </label>
        ))}
        {canWrite && !closed && (
          <button disabled={busy} className="rounded bg-gray-900 px-4 py-2 text-sm text-white hover:bg-gray-700 disabled:opacity-50">
            Save
          </button>
        )}
        {closed && <p className="text-xs text-gray-500">This incident is closed and can no longer be edited.</p>}
      </form>
    </div>
  )
}
