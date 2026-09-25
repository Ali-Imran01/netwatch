import { fireEvent, render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { transitionIncident } from '../api/incidents'
import IncidentDetail from './IncidentDetail'

const role = vi.hoisted(() => ({ current: 'engineer' }))

vi.mock('../context/AuthContext', () => ({
  useAuth: () => ({ user: { id: 1, name: 'T', email: 't@x', role: role.current } }),
}))

vi.mock('../api/incidents', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api/incidents')>()),
  transitionIncident: vi.fn().mockResolvedValue({}),
  getIncident: vi.fn().mockResolvedValue({
    id: 5,
    title: 'core-1 is down',
    state: 'detected',
    severity: 'major',
    monitor: { id: 2, name: 'core-1' },
    circuit: null,
    opened_at: '2026-09-27T10:00:00Z',
    time_to_acknowledge_s: null,
    time_to_resolve_s: null,
    allowed_next: ['acknowledged', 'resolved'],
    events: [{ id: 1, from_state: null, to_state: 'detected', user: null, note: 'Monitor went Down.', created_at: '2026-09-27T10:00:00Z' }],
  }),
}))

const renderPage = () =>
  render(
    <MemoryRouter initialEntries={['/incidents/5']}>
      <Routes>
        <Route path="/incidents/:id" element={<IncidentDetail />} />
      </Routes>
    </MemoryRouter>,
  )

describe('IncidentDetail', () => {
  beforeEach(() => vi.clearAllMocks())

  it('offers only the legal next states and sends the chosen one with the note', async () => {
    role.current = 'engineer'
    renderPage()

    expect(await screen.findByText('Monitor went Down.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Acknowledged' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Resolved' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /closed/i })).not.toBeInTheDocument()

    fireEvent.change(screen.getByPlaceholderText(/note for the timeline/i), { target: { value: 'on it' } })
    fireEvent.click(screen.getByRole('button', { name: 'Acknowledged' }))
    expect(transitionIncident).toHaveBeenCalledWith(5, 'acknowledged', 'on it')
  })

  it('shows no actions to viewers', async () => {
    role.current = 'viewer'
    renderPage()

    expect(await screen.findByText('Monitor went Down.')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Acknowledged' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Save' })).not.toBeInTheDocument()
  })
})
