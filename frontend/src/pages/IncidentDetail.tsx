import { useCallback, useEffect, useState, type FormEvent } from 'react'
import { Link, useParams } from 'react-router-dom'
import { apiError } from '../api/inventory'
import { addIncidentNote, assignIncident, downloadRfo, duration, getIncident, listAssignees, saveIncident, stateLabel, transitionIncident, type Assignee, type Incident } from '../api/incidents'
import { SeverityChip, StateChip } from '../components/Chip'
import { Icon } from '../components/Icon'
import { Skeleton } from '../components/Skeleton'
import { useAuth } from '../context/AuthContext'

const rfoFields = [
  { key: 'rfo_summary', label: 'What happened' },
  { key: 'rfo_root_cause', label: 'Root cause' },
  { key: 'rfo_corrective_action', label: 'Corrective action' },
] as const

// What the person is expected to do next, so they never have to work it out from the state names.
const guidance: Record<string, string> = {
  detected: 'Nobody has picked this up yet. Acknowledge it to take ownership, or resolve it if it was a false alarm.',
  acknowledged: 'Someone owns this. Start investigating, or resolve it if the service has recovered.',
  investigating: 'Escalate to the provider if the fault is on their side, or resolve it once the service is back.',
  escalated: 'Waiting on the provider. Move to Monitoring when they report a fix.',
  monitoring: 'Fix applied. This will not resolve itself: confirm the service is stable, then resolve it.',
  resolved: 'Service is restored. Fill in the RFO below, then close the incident.',
}

export default function IncidentDetail() {
  const { id } = useParams()
  const incidentId = Number(id)
  const { user } = useAuth()
  const canWrite = user?.role === 'admin' || user?.role === 'engineer'

  const [incident, setIncident] = useState<Incident | null>(null)
  const [note, setNote] = useState('')
  const [noteText, setNoteText] = useState('')
  const [assignees, setAssignees] = useState<Assignee[]>([])
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

  useEffect(() => {
    if (canWrite) listAssignees().then(setAssignees).catch(() => setAssignees([]))
  }, [canWrite])

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

  const assign = (assigneeId: number | null) => run(() => assignIncident(incidentId, assigneeId))

  const addNote = (e: FormEvent) => {
    e.preventDefault()
    return run(async () => {
      await addIncidentNote(incidentId, noteText.trim())
      setNoteText('')
    })
  }

  const saveRfo = (e: FormEvent) => {
    e.preventDefault()
    return run(() => saveIncident(incidentId, form), 'Saved.')
  }

  if (!incident && error) return <p className="rounded-xl bg-error-container p-3 text-sm text-on-error-container">{error}</p>
  if (!incident) {
    return (
      <div role="status" aria-label="Loading incident" className="mx-auto max-w-4xl space-y-6">
        <Skeleton className="h-9 w-3/4" />
        <div className="flex gap-2">
          <Skeleton className="h-7 w-24" />
          <Skeleton className="h-7 w-16" />
        </div>
        <Skeleton className="h-40 w-full rounded-2xl" />
        <div className="space-y-4">
          {[0, 1, 2].map((n) => (
            <Skeleton key={n} className="h-10 w-2/3" />
          ))}
        </div>
      </div>
    )
  }

  const closed = incident.state === 'closed'
  const resolvedOrLater = incident.state === 'resolved' || closed

  return (
    <div className="mx-auto max-w-4xl space-y-6">
      <div>
        <Link to="/incidents" className="btn btn-text -ml-3">
          <Icon name="back" className="size-5" />
          Incidents
        </Link>
        <h1 className="mt-2 text-[28px] leading-9">
          #{incident.id} {incident.title}
        </h1>
        <div className="mt-3 flex flex-wrap items-center gap-2">
          <StateChip state={incident.state} />
          <SeverityChip severity={incident.severity} />
          <span className="text-sm text-on-surface-variant">
            Opened {new Date(incident.opened_at).toLocaleString()}
            {incident.monitor && <> · <Link className="text-primary hover:underline" to={`/monitoring/monitors/${incident.monitor.id}`}>{incident.monitor.name}</Link></>}
            {incident.circuit && <> · {incident.circuit.name}</>}
          </span>
        </div>
        <p className="mt-2 text-sm text-on-surface-variant">
          To acknowledge {duration(incident.time_to_acknowledge_s)} · to resolve {duration(incident.time_to_resolve_s)}
        </p>
        <div className="mt-3 flex flex-wrap items-center gap-3">
          <span className="text-sm">
            Owner: <span className="font-medium">{incident.assignee?.name ?? 'Unassigned'}</span>
          </span>
          {canWrite && !closed && (
            <>
              <select aria-label="Assign to" className="field mt-0 w-auto" value={incident.assignee?.id ?? ''} disabled={busy} onChange={(e) => assign(e.target.value ? Number(e.target.value) : null)}>
                <option value="">Unassigned</option>
                {assignees.map((a) => (
                  <option key={a.id} value={a.id}>
                    {a.name}
                  </option>
                ))}
              </select>
              {user && incident.assignee?.id !== user.id && (
                <button disabled={busy} onClick={() => assign(user.id)} className="btn btn-text">
                  Assign to me
                </button>
              )}
            </>
          )}
        </div>
      </div>

      {error && <p className="rounded-xl bg-error-container p-3 text-sm text-on-error-container">{error}</p>}
      {notice && <p className="rounded-xl bg-success-container p-3 text-sm text-on-success-container">{notice}</p>}

      {canWrite && incident.allowed_next.length > 0 && (
        <section className="rounded-2xl bg-primary-container/50 p-5">
          <h2 className="text-base font-medium text-on-primary-container">Move to</h2>
          {guidance[incident.state] && <p className="mt-1 text-sm text-on-surface-variant">{guidance[incident.state]}</p>}
          <input className="field bg-white" placeholder="Note for the timeline (optional)" aria-label="Note for the timeline" value={note} onChange={(e) => setNote(e.target.value)} maxLength={1000} />
          <div className="mt-3 flex flex-wrap gap-2">
            {incident.allowed_next.map((s) => (
              <button key={s} disabled={busy} onClick={() => move(s)} className={`btn ${s === 'resolved' || s === 'closed' ? 'btn-filled' : 'btn-tonal'}`}>
                {stateLabel[s]}
              </button>
            ))}
          </div>
          {incident.allowed_next.includes('closed') && <p className="mt-3 text-xs text-on-surface-variant">Closing needs the RFO summary below.</p>}
        </section>
      )}

      <section>
        <h2 className="text-[22px] leading-7">Timeline</h2>
        {canWrite && !closed && (
          <form onSubmit={addNote} className="card mt-4 p-4">
            <label className="block text-sm font-medium text-on-surface-variant">
              Add a note
              <textarea rows={2} className="field" maxLength={2000} placeholder="What did you find, or who did you speak to?" value={noteText} onChange={(e) => setNoteText(e.target.value)} />
            </label>
            <button disabled={busy || !noteText.trim()} className="btn btn-tonal mt-3">
              Add note
            </button>
          </form>
        )}
        <ol className="mt-5 space-y-5">
          {incident.events?.map((e) => (
            <li key={e.id} className={`relative pl-8 text-sm before:absolute before:top-1.5 before:left-1 before:size-3 before:rounded-full ${e.type === 'state' ? 'before:bg-primary' : 'before:bg-outline'} after:absolute after:top-5 after:bottom-[-1.25rem] after:left-[0.6rem] after:w-0.5 after:bg-outline-variant last:after:hidden`}>
              <p className="font-medium">
                {e.type === 'note' ? 'Note' : e.type === 'assignment' ? 'Owner changed' : `${e.from_state ? `${stateLabel[e.from_state]} → ` : ''}${stateLabel[e.to_state]}`}
              </p>
              <p className="text-xs text-on-surface-variant">
                {new Date(e.created_at).toLocaleString()} · {e.user ?? 'System'}
              </p>
              {e.note && <p className={`mt-1 whitespace-pre-wrap ${e.type === 'note' ? 'text-on-surface' : 'text-on-surface-variant'}`}>{e.note}</p>}
            </li>
          ))}
        </ol>
      </section>

      <section>
        <div className="flex flex-wrap items-center gap-3">
          <h2 className="flex-1 text-[22px] leading-7">Reason for outage (RFO)</h2>
          {resolvedOrLater && (
            <button disabled={busy} onClick={() => run(() => downloadRfo(incidentId))} className="btn btn-outlined">
              <Icon name="download" className="size-5" />
              Download PDF{closed ? '' : ' (draft)'}
            </button>
          )}
        </div>

        <form onSubmit={saveRfo} className="card mt-3 space-y-4 p-5">
          <label className="block text-sm font-medium text-on-surface-variant">
            Provider ticket no.
            <input className="field" value={form.provider_ticket ?? ''} disabled={!canWrite || closed} onChange={(e) => setForm({ ...form, provider_ticket: e.target.value })} />
          </label>
          {rfoFields.map((f) => (
            <label key={f.key} className="block text-sm font-medium text-on-surface-variant">
              {f.label}
              <textarea rows={3} className="field" value={form[f.key] ?? ''} disabled={!canWrite || closed} onChange={(e) => setForm({ ...form, [f.key]: e.target.value })} />
            </label>
          ))}
          {canWrite && !closed && (
            <button disabled={busy} className="btn btn-filled">
              Save
            </button>
          )}
          {closed && <p className="text-xs text-on-surface-variant">This incident is closed and can no longer be edited.</p>}
        </form>
      </section>
    </div>
  )
}
