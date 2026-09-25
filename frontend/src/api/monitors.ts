import { apiClient } from './client'

export type Range = '1h' | '24h' | '7d'

export interface HistoryPoint {
  t: string
  checks: number
  failures: number
  avg: number | null
  p95: number | null
}

export async function fetchHistory(id: number, range: Range): Promise<HistoryPoint[]> {
  const { data } = await apiClient.get<{ points: HistoryPoint[] }>(`/api/monitors/${id}/history`, { params: { range } })
  return data.points
}
