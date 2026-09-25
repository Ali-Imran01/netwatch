import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { CartesianGrid, Legend, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import { apiError, type Row } from '../api/inventory'
import { apiClient } from '../api/client'
import { fetchHistory, type HistoryPoint, type Range } from '../api/monitors'

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
    <div>
      <Link to="/" className="text-sm text-gray-500 hover:text-gray-900">← Status</Link>
      <h1 className="mt-2 text-2xl font-semibold text-gray-900">{monitor?.name ?? 'Monitor'}</h1>
      {monitor && (
        <p className="mt-1 text-sm text-gray-500">
          {monitor.type} · {monitor.target}{monitor.port ? `:${monitor.port}` : ''} · <span className="font-medium uppercase">{monitor.state}</span>
        </p>
      )}
      {error && <p className="mt-4 rounded bg-red-50 p-3 text-sm text-red-700">{error}</p>}

      <div className="mt-6 flex items-center gap-2">
        {ranges.map((r) => (
          <button
            key={r.value}
            onClick={() => {
              setPoints(null)
              setRange(r.value)
            }}
            className={`rounded px-3 py-1 text-sm ${range === r.value ? 'bg-gray-900 text-white' : 'border border-gray-300 hover:bg-gray-100'}`}
          >
            {r.label}
          </button>
        ))}
        <span className="ml-2 text-xs text-gray-500">{ranges.find((r) => r.value === range)?.note}</span>
      </div>

      <div className="mt-4 h-80 rounded border border-gray-200 bg-white p-4">
        {points && points.length === 0 && <p className="text-sm text-gray-500">No data for this range yet. Rollups appear once a 5-minute bucket has closed.</p>}
        {points && points.length > 0 && (
          <ResponsiveContainer width="100%" height="100%">
            <LineChart data={points}>
              <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
              <XAxis dataKey="t" tickFormatter={fmt} minTickGap={40} fontSize={12} />
              <YAxis unit=" ms" fontSize={12} width={64} />
              <Tooltip labelFormatter={(t) => new Date(String(t)).toLocaleString()} />
              <Legend />
              <Line type="monotone" dataKey="avg" name="Average" stroke="#059669" dot={false} connectNulls />
              {range !== '1h' && <Line type="monotone" dataKey="p95" name="p95" stroke="#d97706" dot={false} connectNulls />}
            </LineChart>
          </ResponsiveContainer>
        )}
      </div>
      {points && total > 0 && (
        <p className="mt-3 text-sm text-gray-600">
          {failed} failed of {total} checks in this range ({(((total - failed) / total) * 100).toFixed(2)}% success).
        </p>
      )}
    </div>
  )
}
