import { fireEvent, render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { runMonitor } from '../api/inventory'
import { entities } from '../inventory/entities'
import EntityPage from './EntityPage'

vi.mock('../context/AuthContext', () => ({
  useAuth: () => ({ user: { id: 1, name: 'T', email: 't@x', role: 'engineer' } }),
}))

vi.mock('../api/inventory', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api/inventory')>()),
  runMonitor: vi.fn().mockResolvedValue({}),
  listRows: vi.fn().mockResolvedValue({
    current_page: 1,
    last_page: 1,
    total: 2,
    data: [
      { id: 7, name: 'Core ping', type: 'ping', target: '192.0.2.10', port: null, enabled: true, state: 'down', last_success: false, last_latency_ms: null, last_checked_at: null, device: { name: 'rtr-kl-01' } },
      { id: 8, name: 'Web', type: 'tcp', target: '192.0.2.20', port: 443, enabled: true, state: 'up', last_success: true, last_latency_ms: 12, last_checked_at: '2026-09-25T10:00:00Z', device: null },
    ],
  }),
}))

const monitors = entities.find((e) => e.path === 'monitors')!

describe('Monitors page', () => {
  it('shows last-check status and latency, and runs a check on demand', async () => {
    render(<EntityPage entity={monitors} />)

    expect(await screen.findByText('Core ping')).toBeInTheDocument()
    expect(screen.getByText('Down')).toBeInTheDocument()
    expect(screen.getByText('Up')).toBeInTheDocument()
    expect(screen.getByText('192.0.2.20:443')).toBeInTheDocument()
    expect(screen.getByText('12 ms')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /import csv/i })).not.toBeInTheDocument()

    fireEvent.click(screen.getAllByRole('button', { name: /run now/i })[0])
    expect(runMonitor).toHaveBeenCalledWith(7)
  })
})
