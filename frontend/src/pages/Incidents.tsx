import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { apiError, type Page } from '../api/inventory'
import { duration, incidentSummary, listIncidents, stateLabel, type IncidentSummary } from '../api/incidents'

const stateTone: Record<string, string> = {
  detected: 'text-red-700',
  acknowledged: 'text-amber-700',
  investigating: 'text-amber-700',
  escalated: 'text-orange-700',
  monitoring: 'text-sky-700',
  resolved: 'text-emerald-700',
  closed: 'text-gray-500',
}

function Stat({ name, value }: { name: string; value: string | number }) {
  return (
    <div className="rounded border border-gray-200 bg-white px-4 py-3">
      <p className="text-2xl font-semibold text-gray-900">{value}</p>
      <p className="text-xs uppercase text-gray-500">{name}</p>
    </div>
  )
}

export default function Incidents() {
  const [openOnly, setOpenOnly] = useState(true)
  const [pageNo, setPageNo] = useState(1)
  const [page, setPage] = useState<Page | null>(null)
  const [summary, setSummary] = useState<IncidentSummary | null>(null)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    listIncidents(openOnly, pageNo).then(setPage).catch((e) => setError(apiError(e).message))
  }, [openOnly, pageNo])

  useEffect(() => {
    incidentSummary().then(setSummary).catch(() => setSummary(null))
  }, [])

  return (
    <div>
      <div className="mb-4 flex items-center justify-between">
        <h1 className="text-2xl font-semibold text-gray-900">Incidents</h1>
        <div className="flex gap-2 text-sm">
          {[true, false].map((only) => (
            <button
              key={String(only)}
              onClick={() => {
                setPage(null)
                setPageNo(1)
                setOpenOnly(only)
              }}
              className={`rounded px-3 py-1 ${openOnly === only ? 'bg-gray-900 text-white' : 'border border-gray-300 hover:bg-gray-100'}`}
            >
              {only ? 'Open' : 'All'}
            </button>
          ))}
        </div>
      </div>

      {summary && (
        <div className="mb-6 flex gap-3">
          <Stat name="Open now" value={summary.open} />
          <Stat name="Last 30 days" value={summary.last_30d} />
          <Stat name="Mean time to acknowledge" value={duration(summary.mtta_s)} />
          <Stat name="Mean time to resolve" value={duration(summary.mttr_s)} />
        </div>
      )}

      {error && <p className="mb-4 rounded bg-red-50 p-3 text-sm text-red-700">{error}</p>}

      <div className="overflow-x-auto rounded border border-gray-200 bg-white">
        <table className="w-full text-left text-sm">
          <thead className="bg-gray-50 text-xs uppercase text-gray-500">
            <tr>
              {['#', 'Incident', 'State', 'Severity', 'Opened', 'Ack', 'Resolve'].map((h) => (
                <th key={h} className="px-4 py-3 font-medium">
                  {h}
                </th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {page?.data.map((i) => (
              <tr key={i.id} className="hover:bg-gray-50">
                <td className="px-4 py-2 text-gray-500">{i.id}</td>
                <td className="px-4 py-2">
                  <Link to={`/incidents/${i.id}`} className="font-medium text-gray-900 hover:underline">
                    {i.title}
                  </Link>
                  {i.circuit && <span className="ml-2 text-xs text-gray-500">{i.circuit.name}</span>}
                </td>
                <td className={`px-4 py-2 font-medium ${stateTone[i.state] ?? ''}`}>{stateLabel[i.state] ?? i.state}</td>
                <td className="px-4 py-2">{i.severity}</td>
                <td className="px-4 py-2">{new Date(i.opened_at).toLocaleString()}</td>
                <td className="px-4 py-2">{duration(i.time_to_acknowledge_s)}</td>
                <td className="px-4 py-2">{duration(i.time_to_resolve_s)}</td>
              </tr>
            ))}
            {page?.data.length === 0 && (
              <tr>
                <td colSpan={7} className="px-4 py-8 text-center text-gray-500">
                  {openOnly ? 'No open incidents.' : 'No incidents yet.'}
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
    </div>
  )
}
