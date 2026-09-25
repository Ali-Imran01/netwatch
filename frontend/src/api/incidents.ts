import { apiClient } from './client'
import type { Page, Row } from './inventory'

export const stateLabel: Record<string, string> = {
  detected: 'Detected',
  acknowledged: 'Acknowledged',
  investigating: 'Investigating',
  escalated: 'Escalated to provider',
  monitoring: 'Monitoring',
  resolved: 'Resolved',
  closed: 'Closed',
}

export interface IncidentEvent {
  id: number
  from_state: string | null
  to_state: string
  user: string | null
  note: string | null
  created_at: string
}

export interface Incident extends Row {
  title: string
  state: string
  severity: string
  monitor: { id: number; name: string } | null
  circuit: { id: number; name: string } | null
  allowed_next: string[]
  events?: IncidentEvent[]
}

export interface IncidentSummary {
  open: number
  last_30d: number
  mtta_s: number | null
  mttr_s: number | null
}

export async function listIncidents(openOnly: boolean, page = 1): Promise<Page> {
  const { data } = await apiClient.get<Page>('/api/incidents', { params: { page, ...(openOnly ? { state: 'open' } : {}) } })
  return data
}

export async function getIncident(id: number): Promise<Incident> {
  return (await apiClient.get<Incident>(`/api/incidents/${id}`)).data
}

export async function transitionIncident(id: number, to: string, note: string): Promise<Incident> {
  return (await apiClient.post<Incident>(`/api/incidents/${id}/transition`, { to, note: note || null })).data
}

export async function saveIncident(id: number, values: Record<string, unknown>): Promise<Incident> {
  return (await apiClient.put<Incident>(`/api/incidents/${id}`, values)).data
}

export async function incidentSummary(): Promise<IncidentSummary> {
  return (await apiClient.get<IncidentSummary>('/api/incidents/summary')).data
}

/** Fetch the PDF with the session cookie, then hand it to the browser as a download. */
export async function downloadRfo(id: number): Promise<void> {
  const { data } = await apiClient.get<Blob>(`/api/incidents/${id}/rfo`, { responseType: 'blob' })
  const url = URL.createObjectURL(data)
  const a = document.createElement('a')
  a.href = url
  a.download = `RFO-INC-${id}.pdf`
  a.click()
  URL.revokeObjectURL(url)
}

export async function testChannel(id: number): Promise<string> {
  return (await apiClient.post<{ message: string }>(`/api/alert-channels/${id}/test`)).data.message
}

/** "2h 5m", "4m 10s", "35s". */
export function duration(seconds: number | null | undefined): string {
  if (seconds === null || seconds === undefined) return '—'
  if (seconds >= 3600) return `${Math.floor(seconds / 3600)}h ${Math.floor((seconds % 3600) / 60)}m`
  if (seconds >= 60) return `${Math.floor(seconds / 60)}m ${seconds % 60}s`
  return `${seconds}s`
}
