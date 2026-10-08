import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { apiError, type Page } from '../api/inventory'
import { duration, incidentSummary, listIncidents, type IncidentSummary } from '../api/incidents'
import { SeverityChip, StateChip } from '../components/Chip'
import { Skeleton } from '../components/Skeleton'

function Stat({ name, value }: { name: string; value: string | number }) {
  return (
    <div className="rounded-2xl bg-primary-container/60 px-5 py-4 text-on-primary-container">
      <p className="text-xs font-medium tracking-wide uppercase">{name}</p>
      <p className="mt-1 text-3xl">{value}</p>
    </div>
  )
}

export default function Incidents() {
  const [openOnly, setOpenOnly] = useState(true)
  const [pageNo, setPageNo] = useState(1)
  const [page, setPage] = useState<Page | null>(null)
  const [summary, setSummary] = useState<IncidentSummary | null | undefined>(undefined)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    listIncidents(openOnly, pageNo).then(setPage).catch((e) => setError(apiError(e).message))
  }, [openOnly, pageNo])

  useEffect(() => {
    incidentSummary().then(setSummary).catch(() => setSummary(null))
  }, [])

  return (
    <div className="mx-auto max-w-6xl space-y-6">
      <div className="flex flex-wrap items-center gap-4">
        <h1 className="flex-1 text-[28px] leading-9">Incidents</h1>
        <div role="group" aria-label="Filter incidents" className="inline-flex h-10 overflow-hidden rounded-full border border-outline text-sm font-medium">
          {[true, false].map((only) => (
            <button
              key={String(only)}
              aria-pressed={openOnly === only}
              onClick={() => {
                setPage(null)
                setPageNo(1)
                setOpenOnly(only)
              }}
              className={`px-5 ${only ? '' : 'border-l border-outline'} ${openOnly === only ? 'bg-primary-container text-on-primary-container' : 'hover:bg-surface-container'}`}
            >
              {only ? 'Open' : 'All'}
            </button>
          ))}
        </div>
      </div>

      {summary === undefined && (
        <div role="status" aria-label="Loading summary" className="grid grid-cols-2 gap-4 lg:grid-cols-4">
          {[0, 1, 2, 3].map((n) => (
            <Skeleton key={n} className="h-[88px] rounded-2xl" />
          ))}
        </div>
      )}
      {summary && (
        <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
          <Stat name="Open now" value={summary.open} />
          <Stat name="Last 30 days" value={summary.last_30d} />
          <Stat name="Mean time to acknowledge" value={duration(summary.mtta_s)} />
          <Stat name="Mean time to resolve" value={duration(summary.mttr_s)} />
        </div>
      )}

      {error && <p className="rounded-xl bg-error-container p-3 text-sm text-on-error-container">{error}</p>}

      <div className="card overflow-x-auto">
        <table className="w-full min-w-3xl text-left text-sm">
          <thead className="bg-surface text-xs tracking-wide text-on-surface-variant uppercase">
            <tr>
              {['#', 'Incident', 'State', 'Severity', 'Owner', 'Opened', 'Ack', 'Resolve'].map((h) => (
                <th key={h} className="px-4 py-3 font-medium">
                  {h}
                </th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-container">
            {page?.data.map((i) => (
              <tr key={i.id} className="hover:bg-surface">
                <td className="px-4 py-3 font-mono text-xs text-on-surface-variant">{i.id}</td>
                <td className="px-4 py-3">
                  <Link to={`/incidents/${i.id}`} className="font-medium text-primary hover:underline">
                    {i.title}
                  </Link>
                  {i.circuit && <span className="ml-2 text-xs text-on-surface-variant">{i.circuit.name}</span>}
                </td>
                <td className="px-4 py-3">
                  <StateChip state={i.state} />
                </td>
                <td className="px-4 py-3">
                  <SeverityChip severity={i.severity} />
                </td>
                <td className="px-4 py-3">{i.assignee?.name ?? <span className="text-on-surface-variant">Unassigned</span>}</td>
                <td className="px-4 py-3">{new Date(i.opened_at).toLocaleString()}</td>
                <td className="px-4 py-3">{duration(i.time_to_acknowledge_s)}</td>
                <td className="px-4 py-3">{duration(i.time_to_resolve_s)}</td>
              </tr>
            ))}
            {!page && !error && [0, 1, 2, 3, 4].map((n) => (
              <tr key={n} aria-hidden="true">
                <td colSpan={8} className="px-4 py-3">
                  <Skeleton className="h-7 w-full" />
                </td>
              </tr>
            ))}
            {page?.data.length === 0 && (
              <tr>
                <td colSpan={8} className="px-4 py-10 text-center text-on-surface-variant">
                  {openOnly ? 'No open incidents. All quiet.' : 'No incidents yet.'}
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
    </div>
  )
}
