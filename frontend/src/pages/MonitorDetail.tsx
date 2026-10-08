import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { Bar, CartesianGrid, ComposedChart, Legend, Line, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import { apiError, type Row } from '../api/inventory'
import { apiClient } from '../api/client'
import { fetchHistory, type HistoryPoint, type Range } from '../api/monitors'
import { Icon } from '../components/Icon'
import { Skeleton } from '../components/Skeleton'

const ranges: { value: Range; label: string; note: string }[] = [
  { value: '1h', label: '1 hour', note: 'every check' },
  { value: '24h', label: '24 hours', note: '5-minute buckets' },
  { value: '7d', label: '7 days', note: 'hourly buckets' },
]

export default function MonitorDetail() {
  const { id } = useParams()
  const [monitor, setMonitor] = useState<Row | null>(null)
  const [range, setRange] = useState<Range>('1h')
  const [points, setPoints] = useState<HistoryPoint[] | null>(null)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    apiClient.get<Row>(`/api/monitors/${id}`).then((r) => setMonitor(r.data)).catch((e) => setError(apiError(e).message))
  }, [id])

  useEffect(() => {
    fetchHistory(Number(id), range).then(setPoints).catch((e) => setError(apiError(e).message))
  }, [id, range])

  const fmt = (t: string) => {
    const d = new Date(t)
    return range === '7d' ? d.toLocaleString([], { weekday: 'short', hour: '2-digit' }) : d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
  }
  const failed = points?.reduce((n, p) => n + p.failures, 0) ?? 0
  const total = points?.reduce((n, p) => n + p.checks, 0) ?? 0

  return (
    <div className="mx-auto max-w-6xl space-y-6">
      <div>
        <Link to="/" className="btn btn-text -ml-3">
          <Icon name="back" className="size-5" />
          Status
        </Link>
        {monitor ? <h1 className="mt-2 text-[28px] leading-9">{monitor.name}</h1> : <Skeleton className="mt-2 h-9 w-64 max-w-full" />}
        {monitor && (
          <p className="mt-1 text-sm text-on-surface-variant">
            <span className="font-mono">{monitor.type} · {monitor.target}{monitor.port ? `:${monitor.port}` : ''}</span>
            <span className={`chip ml-3 uppercase ${monitor.state === 'down' ? 'bg-error-container text-on-error-container' : monitor.state === 'up' ? 'bg-success-container text-on-success-container' : 'bg-neutral-container'}`}>{monitor.state}</span>
          </p>
        )}
      </div>
      {error && <p className="rounded-xl bg-error-container p-3 text-sm text-on-error-container">{error}</p>}

      <div className="flex flex-wrap items-center gap-3">
        <div role="group" aria-label="Time range" className="inline-flex h-10 overflow-hidden rounded-full border border-outline text-sm font-medium">
          {ranges.map((r, i) => (
            <button
              key={r.value}
              aria-pressed={range === r.value}
              onClick={() => {
                setPoints(null)
                setRange(r.value)
              }}
              className={`px-4 ${i > 0 ? 'border-l border-outline' : ''} ${range === r.value ? 'bg-primary-container text-on-primary-container' : 'hover:bg-surface-container'}`}
            >
              {r.label}
            </button>
          ))}
        </div>
        <span className="text-xs text-on-surface-variant">{ranges.find((r) => r.value === range)?.note}</span>
      </div>

      <div className="card h-80 p-4">
        {points === null && !error && (
          <div role="status" aria-label="Loading chart" className="h-full">
            <Skeleton className="h-full w-full rounded-xl" />
          </div>
        )}
        {points && points.length === 0 && <p className="text-sm text-on-surface-variant">No data for this range yet. Rollups appear once a 5-minute bucket has closed.</p>}
        {points && points.length > 0 && (
          <ResponsiveContainer width="100%" height="100%">
            <ComposedChart data={points}>
              <CartesianGrid strokeDasharray="3 3" stroke="#e1e3e1" />
              <XAxis dataKey="t" tickFormatter={fmt} minTickGap={40} fontSize={12} />
              <YAxis unit=" ms" fontSize={12} width={64} />
              <YAxis yAxisId="failed" orientation="right" allowDecimals={false} fontSize={12} width={32} />
              <Tooltip labelFormatter={(t) => new Date(String(t)).toLocaleString()} />
              <Legend />
              <Bar yAxisId="failed" dataKey="failures" name="Failed checks" fill="#b3261e" />
              <Line type="monotone" dataKey="avg" name="Average" stroke="#0b57d0" strokeWidth={2} dot={false} connectNulls />
              {range !== '1h' && <Line type="monotone" dataKey="p95" name="p95" stroke="#b26a00" strokeWidth={2} dot={false} connectNulls />}
            </ComposedChart>
          </ResponsiveContainer>
        )}
      </div>
      {points && total > 0 && (
        <p className="text-sm text-on-surface-variant">
          {failed} failed of {total} checks in this range ({(((total - failed) / total) * 100).toFixed(2)}% success).
        </p>
      )}
    </div>
  )
}
