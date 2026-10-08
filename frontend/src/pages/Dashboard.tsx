import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { duration, listIncidents, transitionIncident } from '../api/incidents'
import type { Row } from '../api/inventory'
import { Icon, type IconName } from '../components/Icon'
import { SeverityChip, StateChip } from '../components/Chip'
import { MonitorRowSkeleton } from '../components/Skeleton'
import { useAuth } from '../context/AuthContext'
import { useLiveMonitors, type Connection } from '../hooks/useLiveMonitors'

type Filter = 'all' | 'down' | 'up'

const order: Record<string, number> = { down: 0, maintenance: 1, unknown: 2, up: 3 }
// A monitor under planned maintenance is shown as such, whatever its raw state.
const shown = (m: Row) => (m.in_maintenance ? 'maintenance' : m.state)

const connectionText: Record<Connection, string> = { connecting: 'Connecting…', live: 'Live', offline: 'Offline, showing last data' }

const avatar: Record<string, { icon: IconName; tone: string }> = {
  up: { icon: 'checkCircle', tone: 'bg-success-container text-success' },
  down: { icon: 'errorCircle', tone: 'bg-error-container text-error' },
  maintenance: { icon: 'build', tone: 'bg-primary-container text-primary' },
  unknown: { icon: 'errorCircle', tone: 'bg-neutral-container text-on-surface-variant' },
}

/** "13d 8h" for long spells, otherwise the usual "2h 5m" / "35s". */
function since(iso: string | null | undefined): string {
  if (!iso) return ''
  const s = Math.max(0, Math.floor((Date.now() - new Date(iso).getTime()) / 1000))
  return s >= 86400 ? `${Math.floor(s / 86400)}d ${Math.floor((s % 86400) / 3600)}h` : duration(s)
}

function StatusChip({ m }: { m: Row }) {
  const s = shown(m)
  if (s === 'down') return <span className="chip bg-error-container text-on-error-container">Down{m.state_changed_at ? ` · ${since(m.state_changed_at)}` : ''}</span>
  if (s === 'maintenance') return <span className="chip bg-primary-container text-on-primary-container">Maintenance</span>
  if (s === 'up') return <span className="chip border border-outline-variant">Up</span>
  return <span className="chip bg-neutral-container">Unknown</span>
}

function Tile({ name, value, tone }: { name: string; value: number | null; tone: string }) {
  return (
    <div className={`rounded-2xl px-6 py-5 ${tone}`}>
      <p className="text-xs font-medium tracking-wide uppercase">{name}</p>
      {value === null ? <div aria-hidden="true" className="mt-2 h-12 w-14 rounded-lg bg-black/10 motion-safe:animate-pulse" /> : <p className="mt-2 text-5xl leading-none">{value}</p>}
    </div>
  )
}

function MonitorRow({ m }: { m: Row }) {
  const a = avatar[shown(m)] ?? avatar.unknown
  return (
    <li>
      <Link to={`/monitoring/monitors/${m.id}`} className={`flex min-h-18 items-center gap-4 border-t border-neutral-container px-4 py-2 hover:bg-surface sm:px-6 ${shown(m) === 'down' ? 'bg-error-container/30' : ''}`}>
        <span className={`flex size-10 shrink-0 items-center justify-center rounded-full ${a.tone}`}>
          <Icon name={a.icon} />
        </span>
        <span className="min-w-0 flex-1">
          <span className="block truncate">{m.name}</span>
          <span className="block truncate font-mono text-xs text-on-surface-variant">{m.type} · {m.target}{m.port ? `:${m.port}` : ''}</span>
        </span>
        <span className="hidden w-24 text-right font-mono text-xs text-on-surface-variant sm:block">{m.last_latency_ms === null || m.last_latency_ms === undefined ? '—' : `${m.last_latency_ms} ms`}</span>
        <StatusChip m={m} />
      </Link>
    </li>
  )
}

export default function Dashboard() {
  const { user } = useAuth()
  const canWrite = user?.role === 'admin' || user?.role === 'engineer'
  const { monitors, connection } = useLiveMonitors()
  const [open, setOpen] = useState<{ total: number; top?: Row }>({ total: 0 })
  const [recent, setRecent] = useState<Row[]>([])
  const [filter, setFilter] = useState<Filter>('all')
  const [query, setQuery] = useState('')

  // Refreshed whenever the live feed reports a change in the number of Down monitors, and on load.
  const downCount = (monitors ?? []).filter((m) => m.enabled && m.state === 'down' && !m.in_maintenance).length
  const load = useCallback(() => {
    listIncidents(true).then((p) => setOpen({ total: p.total, top: p.data[0] })).catch(() => setOpen({ total: 0 }))
    listIncidents(false).then((p) => setRecent(p.data.slice(0, 3))).catch(() => setRecent([]))
  }, [])
  useEffect(load, [load, downCount])

  const acknowledge = (id: number) => transitionIncident(id, 'acknowledged', '').then(load).catch(() => undefined)

  const ready = monitors !== null
  const enabled = (monitors ?? []).filter((m) => m.enabled)
  const count = (s: string) => enabled.filter((m) => shown(m) === s).length
  const q = query.trim().toLowerCase()
  const visible = enabled
    .filter((m) => filter === 'all' || shown(m) === filter)
    .filter((m) => !q || `${m.name} ${m.target}`.toLowerCase().includes(q))
    .sort((a, b) => (order[shown(a)] ?? 2) - (order[shown(b)] ?? 2) || a.name.localeCompare(b.name))

  const { total: openIncidents, top } = open
  const filters: { value: Filter; label: string; n: number }[] = [
    { value: 'all', label: 'All', n: enabled.length },
    { value: 'down', label: 'Down', n: count('down') },
    { value: 'up', label: 'Up', n: count('up') },
  ]

  return (
    <div className="mx-auto max-w-6xl space-y-6">
      <div className="flex flex-wrap items-center gap-4">
        <h1 className="flex-1 text-[28px] leading-9">Status</h1>
        <label className="flex h-12 w-full items-center gap-3 rounded-full bg-surface-container px-5 text-on-surface-variant sm:w-80">
          <Icon name="search" />
          <input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Search monitors" aria-label="Search monitors" className="w-full bg-transparent text-base text-on-surface outline-none placeholder:text-on-surface-variant" />
        </label>
        <span className="inline-flex h-8 items-center gap-2 rounded-lg border border-outline-variant px-3 text-sm font-medium">
          <span className={`size-2 rounded-full ${connection === 'live' ? 'bg-success' : 'bg-outline'}`} />
          {connectionText[connection]}
        </span>
      </div>

      {openIncidents > 0 && (
        <section className="flex flex-wrap items-center gap-4 rounded-2xl bg-error-container px-6 py-5 text-on-error-container">
          <Icon name="warning" className="size-8 text-error" />
          <div className="min-w-0 flex-1 basis-80">
            <p className="text-base font-medium">
              {openIncidents} open incident{openIncidents === 1 ? '' : 's'}
              {top ? ` · ${top.severity} · ${top.state}` : ''}
            </p>
            {top && <p className="text-sm">{top.title} · open for {since(top.opened_at)}{top.acknowledged_at ? '' : ' · not yet acknowledged'}</p>}
          </div>
          <div className="flex flex-wrap gap-2">
            {top && canWrite && top.allowed_next?.includes('acknowledged') && (
              <button onClick={() => acknowledge(top.id)} className="btn btn-error min-h-12">
                Acknowledge
              </button>
            )}
            <Link to={top ? `/incidents/${top.id}` : '/incidents'} className="btn btn-text min-h-12 text-on-error-container">
              View incident
            </Link>
          </div>
        </section>
      )}

      <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <Tile name="Up" value={ready ? count('up') : null} tone="bg-success-container text-on-success-container" />
        <Tile name="Down" value={ready ? count('down') : null} tone="bg-error-container text-on-error-container" />
        <Tile name="Maintenance" value={ready ? count('maintenance') : null} tone="bg-warning-container text-on-warning-container" />
        <Tile name="Unknown" value={ready ? count('unknown') : null} tone="bg-neutral-container text-on-surface" />
      </div>

      <section className="card overflow-hidden">
        <div className="flex flex-wrap items-center gap-4 px-4 pt-5 pb-4 sm:px-6">
          <h2 className="flex-1 text-[22px] leading-7">Monitors</h2>
          <div role="group" aria-label="Filter monitors" className="inline-flex h-10 overflow-hidden rounded-full border border-outline text-sm font-medium">
            {filters.map((f, i) => (
              <button key={f.value} onClick={() => setFilter(f.value)} aria-pressed={filter === f.value} className={`flex items-center gap-1.5 px-4 ${i > 0 ? 'border-l border-outline' : ''} ${filter === f.value ? 'bg-primary-container text-on-primary-container' : 'hover:bg-surface-container'}`}>
                {filter === f.value && <Icon name="check" className="size-4" />}
                {f.label}{ready ? ` ${f.n}` : ''}
              </button>
            ))}
          </div>
          {canWrite && (
            <Link to="/monitoring/monitors" className="inline-flex min-h-14 items-center gap-2 rounded-2xl bg-primary-container px-5 text-sm font-medium text-on-primary-container shadow-md hover:brightness-95">
              <Icon name="add" />
              Add monitor
            </Link>
          )}
        </div>
        {monitors && visible.length === 0 && (
          <p className="border-t border-neutral-container px-6 py-8 text-sm text-on-surface-variant">
            {enabled.length === 0 ? 'No enabled monitors yet. Add one under Monitoring → Monitors.' : 'No monitors match this filter.'}
          </p>
        )}
        {!ready && (
          <div role="status" aria-label="Loading monitors">
            {[0, 1, 2, 3, 4].map((n) => (
              <MonitorRowSkeleton key={n} />
            ))}
          </div>
        )}
        <ul>
          {visible.map((m) => (
            <MonitorRow key={m.id} m={m} />
          ))}
        </ul>
      </section>

      {recent.length > 0 && (
        <section className="space-y-4">
          <div className="flex items-center">
            <h2 className="flex-1 text-[22px] leading-7">Recent incidents</h2>
            <Link to="/incidents" className="btn btn-text">
              View all
            </Link>
          </div>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {recent.map((i) => (
              <div key={i.id} className="card flex flex-col gap-3 p-4">
                <div className="flex flex-wrap items-center gap-2">
                  <StateChip state={i.state} />
                  <SeverityChip severity={i.severity} />
                  <span className="ml-auto font-mono text-xs text-on-surface-variant">#{i.id}</span>
                </div>
                <p className="text-base font-medium">{i.title}</p>
                <p className="text-sm text-on-surface-variant">
                  Opened {new Date(i.opened_at).toLocaleString()}
                  <br />
                  {i.time_to_resolve_s !== null && i.time_to_resolve_s !== undefined ? `Resolved in ${duration(i.time_to_resolve_s)}` : 'Still open'}
                </p>
                <div className="mt-auto flex gap-2 pt-1">
                  {canWrite && i.allowed_next?.includes('acknowledged') && (
                    <button onClick={() => acknowledge(i.id)} className="btn btn-tonal">
                      Acknowledge
                    </button>
                  )}
                  <Link to={`/incidents/${i.id}`} className="btn btn-text">
                    View
                  </Link>
                </div>
              </div>
            ))}
          </div>
        </section>
      )}
    </div>
  )
}
