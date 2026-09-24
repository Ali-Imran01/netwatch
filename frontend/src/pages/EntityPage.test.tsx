import { render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { entities } from '../inventory/entities'
import EntityPage from './EntityPage'

const role = vi.hoisted(() => ({ current: 'viewer' }))

vi.mock('../context/AuthContext', () => ({
  useAuth: () => ({ user: { id: 1, name: 'T', email: 't@x', role: role.current } }),
}))

vi.mock('../api/inventory', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api/inventory')>()),
  listRows: vi.fn().mockResolvedValue({
    current_page: 1,
    last_page: 1,
    total: 1,
    data: [{ id: 1, site: { code: 'KL-HQ' }, cidr: '10.1.30.0/25', description: 'Customer pool', gateway: '10.1.30.1', used_count: 95, usable_hosts: 126, utilization: 75.4 }],
  }),
}))

const subnets = entities.find((e) => e.path === 'subnets')!

describe('EntityPage', () => {
  it('shows rows with a utilization bar and hides write actions for viewers', async () => {
    role.current = 'viewer'
    render(<EntityPage entity={subnets} />)

    expect(await screen.findByText('10.1.30.0/25')).toBeInTheDocument()
    expect(screen.getByRole('progressbar')).toHaveAttribute('aria-valuenow', '75.4')
    expect(screen.queryByRole('button', { name: /new subnet/i })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /import csv/i })).not.toBeInTheDocument()
  })

  it('offers create and import to engineers', async () => {
    role.current = 'engineer'
    render(<EntityPage entity={subnets} />)

    expect(await screen.findByRole('button', { name: /new subnet/i })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /import csv/i })).toBeInTheDocument()
  })
})
