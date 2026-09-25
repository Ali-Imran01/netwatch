import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { incidentSummary } from '../api/incidents'
import type { Row } from '../api/inventory'
import { useLiveMonitors, type Connection } from '../hooks/useLiveMonitors'

const tone: Record<string, string> = {
  up: 'border-emerald-200 bg-emerald-50',
  down: 'border-red-300 bg-red-50',
  unknown: 'border-gray-200 bg-white',
  maintenance: 'border-sky-200 bg-sky-50',
}
const label: Record<string, string> = { up: 'text-emerald-700', down: 'text-red-700', unknown: 'text-gray-500', maintenance: 'text-sky-700' }
const order: Record<string, number> = { down: 0, maintenance: 1, unknown: 2, up: 3 }
// A monitor under planned maintenance is shown as such, whatever its raw state.
const shown = (m: Row) => (m.in_maintenance ? 'maintenance' : m.state)

const connectionText: Record<Connection, string> = { connecting: 'Connecting…', live: 'Live', offline: 'Offline — showing last data' }

function Count({ name, value, className }: { name: string; value: number; className: string }) {
  return (
    <div className="rounded border border-gray-200 bg-white px-4 py-3">
      <p className={`text-2xl font-semibold ${className}`}>{value}</p>
      <p className="text-xs uppercase text-gray-500">{name}</p>
    </div>
  )
}

function Tile({ m }: { m: Row }) {
  return (
    <Link to={`/monitoring/monitors/${m.id}`} className={`block rounded border p-3 hover:shadow ${tone[shown(m)] ?? tone.unknown}`}>
      <div className="flex items-center justify-between gap-2">
        <p className="truncate text-sm font-medium text-gray-900">{m.name}</p>
        <span className={`text-xs font-semibold uppercase ${label[shown(m)] ?? label.unknown}`}>{shown(m)}</span>
      </div>
      <p className="mt-1 truncate text-xs text-gray-500">{m.type} · {m.target}{m.port ? `:${m.port}` : ''}</p>
      <p className="mt-2 text-xs text-gray-600">{m.last_latency_ms === null || m.last_latency_ms === undefined ? '—' : `${m.last_latency_ms} ms`}</p>
    </Link>
  )
}

export default function Dashboard() {
  const { monitors, connection } = useLiveMonitors()
  const [openIncidents, setOpenIncidents] = useState(0)
  // Refreshed whenever the live feed reports a change in the number of Down monitors, and on load.
  const downCount = (monitors ?? []).filter((m) => m.enabled && m.state === 'down' && !m.in_maintenance).length
  useEffect(() => {
    incidentSummary().then((s) => setOpenIncidents(s.open)).catch(() => setOpenIncidents(0))
  }, [downCount])
  const enabled = (monitors ?? []).filter((m) => m.enabled)
  const count = (s: string) => enabled.filter((m) => shown(m) === s).length
  const sorted = [...enabled].sort((a, b) => (order[shown(a)] ?? 2) - (order[shown(b)] ?? 2) || a.name.localeCompare(b.name))

  return (
    <div>
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-2xl font-semibold text-gray-900">Status</h1>
        <span className={`text-sm ${connection === 'live' ? 'text-emerald-700' : 'text-gray-500'}`}>
          <span className={`mr-2 inline-block h-2 w-2 rounded-full ${connection === 'live' ? 'bg-emerald-500' : 'bg-gray-400'}`} />
          {connectionText[connection]}
        </span>
      </div>

      {openIncidents > 0 && (
        <Link to="/incidents" className="mb-6 block rounded border border-red-300 bg-red-50 p-3 text-sm font-medium text-red-800 hover:bg-red-100">
          {openIncidents} open incident{openIncidents === 1 ? '' : 's'} — view
        </Link>
      )}

      <div className="mb-6 flex gap-3">
        <Count name="Up" value={count('up')} className="text-emerald-700" />
        <Count name="Down" value={count('down')} className="text-red-700" />
        <Count name="Maintenance" value={count('maintenance')} className="text-sky-700" />
        <Count name="Unknown" value={count('unknown')} className="text-gray-600" />
      </div>

      {monitors && sorted.length === 0 && <p className="text-sm text-gray-500">No enabled monitors yet. Add one under Monitoring → Monitors.</p>}
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        {sorted.map((m) => (
          <Tile key={m.id} m={m} />
        ))}
      </div>
    </div>
  )
}
