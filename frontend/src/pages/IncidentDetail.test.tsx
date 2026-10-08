import { cleanup, fireEvent, render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { addIncidentNote, assignIncident, transitionIncident } from '../api/incidents'
import IncidentDetail from './IncidentDetail'

const role = vi.hoisted(() => ({ current: 'engineer' }))

vi.mock('../context/AuthContext', () => ({
  useAuth: () => ({ user: { id: 1, name: 'T', email: 't@x', role: role.current } }),
}))

vi.mock('../api/incidents', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api/incidents')>()),
  transitionIncident: vi.fn().mockResolvedValue({}),
  assignIncident: vi.fn().mockResolvedValue({}),
  addIncidentNote: vi.fn().mockResolvedValue({}),
  listAssignees: vi.fn().mockResolvedValue([{ id: 1, name: 'T' }, { id: 2, name: 'Aisha' }]),
  getIncident: vi.fn().mockResolvedValue({
    id: 5,
    title: 'core-1 is down',
    state: 'detected',
    severity: 'major',
    monitor: { id: 2, name: 'core-1' },
    circuit: null,
    assignee: null,
    opened_at: '2026-09-27T10:00:00Z',
    time_to_acknowledge_s: null,
    time_to_resolve_s: null,
    allowed_next: ['acknowledged', 'resolved'],
    events: [{ id: 1, type: 'state', from_state: null, to_state: 'detected', user: null, note: 'Monitor went Down.', created_at: '2026-09-27T10:00:00Z' }],
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
  afterEach(cleanup)

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

  it('assigns the incident to someone, or to the current user in one click', async () => {
    role.current = 'engineer'
    renderPage()

    expect(await screen.findByText('Unassigned', { selector: 'span span' })).toBeInTheDocument()
    fireEvent.click(await screen.findByRole('button', { name: 'Assign to me' }))
    expect(assignIncident).toHaveBeenCalledWith(5, 1)

    fireEvent.change(await screen.findByLabelText('Assign to'), { target: { value: '2' } })
    expect(assignIncident).toHaveBeenCalledWith(5, 2)
  })

  it('adds a note to the timeline and keeps the button off until there is text', async () => {
    role.current = 'engineer'
    renderPage()

    const add = await screen.findByRole('button', { name: 'Add note' })
    expect(add).toBeDisabled()
    fireEvent.change(screen.getByPlaceholderText(/what did you find/i), { target: { value: '  Called the provider.  ' } })
    fireEvent.click(add)
    expect(addIncidentNote).toHaveBeenCalledWith(5, 'Called the provider.')
  })

  it('shows viewers the owner but no way to change it or add notes', async () => {
    role.current = 'viewer'
    renderPage()

    expect(await screen.findByText('Monitor went Down.')).toBeInTheDocument()
    expect(screen.queryByLabelText('Assign to')).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Add note' })).not.toBeInTheDocument()
  })
})
