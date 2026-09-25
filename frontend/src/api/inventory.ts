import { isAxiosError } from 'axios'
import { apiClient } from './client'

// Rows are loosely typed on purpose: one generic CRUD screen serves all five inventory entities.
// eslint-disable-next-line @typescript-eslint/no-explicit-any
export type Row = { id: number } & Record<string, any>

export interface Page {
  data: Row[]
  current_page: number
  last_page: number
  total: number
}

export interface ImportResult {
  imported: number
  failed: number
  errors: { row: number; errors: Record<string, string[]> }[]
}

export type FieldErrors = Record<string, string[]>

export async function listRows(path: string, page = 1, perPage = 25): Promise<Page> {
  const { data } = await apiClient.get<Page>(`/api/${path}`, { params: { page, per_page: perPage } })
  return data
}

/** Everything for a dropdown. Fine at this scale; swap for a search box if a table grows past a few thousand rows. */
export async function listAll(path: string): Promise<Row[]> {
  return (await listRows(path, 1, 1000)).data
}

export async function saveRow(path: string, values: Record<string, unknown>, id?: number): Promise<Row> {
  const { data } = id
    ? await apiClient.put<Row>(`/api/${path}/${id}`, values)
    : await apiClient.post<Row>(`/api/${path}`, values)
  return data
}

export async function deleteRow(path: string, id: number): Promise<void> {
  await apiClient.delete(`/api/${path}/${id}`)
}

export async function runMonitor(id: number): Promise<Row> {
  const { data } = await apiClient.post<{ monitor: Row }>(`/api/monitors/${id}/run`)
  return data.monitor
}

export async function importCsv(path: string, file: File): Promise<ImportResult> {
  const body = new FormData()
  body.append('file', file)
  const { data } = await apiClient.post<ImportResult>(`/api/${path}/import`, body)
  return data
}

/** Pull Laravel's `{message, errors}` shape out of a failed request. */
export function apiError(err: unknown): { message: string; fields: FieldErrors } {
  if (isAxiosError(err)) {
    return { message: err.response?.data?.message ?? err.message, fields: err.response?.data?.errors ?? {} }
  }
  return { message: 'Something went wrong.', fields: {} }
}
