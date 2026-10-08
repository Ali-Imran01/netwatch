import { useEffect, useState } from 'react'
import { NavLink, Outlet, useLocation } from 'react-router-dom'
import { incidentSummary } from '../api/incidents'
import { useAuth } from '../context/AuthContext'
import { entities, entityUrl } from '../inventory/entities'
import { Icon, type IconName } from './Icon'

const entityIcon: Record<string, IconName> = {
  sites: 'site',
  vlans: 'layers',
  subnets: 'subnet',
  'ip-addresses': 'globe',
  devices: 'computer',
  providers: 'business',
  circuits: 'link',
  'maintenance-windows': 'build',
  monitors: 'monitor',
  'alert-channels': 'bell',
}

const linkClass = ({ isActive }: { isActive: boolean }) =>
  `flex h-14 items-center gap-3 rounded-full px-4 text-sm font-medium ${isActive ? 'bg-primary-container text-on-primary-container' : 'text-on-surface-variant hover:bg-surface-container'}`

export default function Layout() {
  const { user, logout } = useAuth()
  const { pathname } = useLocation()
  const [menuOpen, setMenuOpen] = useState(false)
  const [openIncidents, setOpenIncidents] = useState(0)

  // The badge only needs to be right when the person moves around, so refresh it on navigation.
  useEffect(() => {
    incidentSummary().then((s) => setOpenIncidents(s.open)).catch(() => setOpenIncidents(0))
  }, [pathname])

  const closeMenu = () => setMenuOpen(false)

  return (
    <div className="min-h-screen lg:flex">
      <header className="sticky top-0 z-20 flex h-16 items-center gap-2 bg-surface px-2 lg:hidden">
        <button onClick={() => setMenuOpen(true)} aria-label="Open menu" className="flex size-12 items-center justify-center rounded-full text-on-surface-variant hover:bg-surface-container">
          <Icon name="menu" />
        </button>
        <span className="text-xl font-medium">NetWatch</span>
      </header>

      {menuOpen && <div onClick={closeMenu} className="fixed inset-0 z-30 bg-black/40 lg:hidden" aria-hidden="true" />}

      <nav
        aria-label="Main"
        className={`fixed inset-y-0 left-0 z-40 flex w-72 shrink-0 flex-col overflow-y-auto bg-surface p-3 transition-transform lg:sticky lg:top-0 lg:h-screen lg:translate-x-0 ${menuOpen ? 'translate-x-0 shadow-xl' : '-translate-x-full max-lg:invisible'}`}
      >
        <div className="flex h-16 items-center justify-between px-4">
          <div className="flex items-center gap-3">
            <Icon name="globe" className="size-7 text-primary" />
            <span className="text-[22px] font-medium">NetWatch</span>
          </div>
          <button onClick={closeMenu} aria-label="Close menu" className="flex size-10 items-center justify-center rounded-full hover:bg-surface-container lg:hidden">
            <Icon name="close" />
          </button>
        </div>

        <div className="mt-1 space-y-0.5">
          <NavLink to="/" end onClick={closeMenu} className={linkClass}>
            <Icon name="dashboard" />
            Status
          </NavLink>
          {(['Inventory', 'Carriers', 'Monitoring', 'Incidents'] as const).map((group) => (
            <div key={group} className="space-y-0.5">
              <p className="px-4 pt-5 pb-2 text-sm font-medium text-on-surface-variant">{group}</p>
              {group === 'Incidents' && (
                <NavLink to="/incidents" end onClick={closeMenu} className={linkClass}>
                  <Icon name="warning" />
                  <span className="flex-1">Incidents</span>
                  {openIncidents > 0 && <span className="flex h-6 min-w-6 items-center justify-center rounded-full bg-error-container px-1.5 text-sm font-medium text-on-error-container">{openIncidents}</span>}
                </NavLink>
              )}
              {entities
                .filter((e) => e.group === group && !(e.hideFromViewers && user?.role === 'viewer'))
                .map((e) => (
                  <NavLink key={e.path} to={entityUrl(e)} onClick={closeMenu} className={linkClass}>
                    <Icon name={entityIcon[e.path] ?? 'dashboard'} />
                    {e.title}
                  </NavLink>
                ))}
            </div>
          ))}
        </div>

        <div className="mt-auto flex items-center gap-3 px-4 pt-6 pb-2">
          <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary-container font-medium text-on-primary-container">{user?.name?.[0]?.toUpperCase() ?? '?'}</div>
          <div className="min-w-0 flex-1">
            <p className="truncate text-sm font-medium">{user?.name}</p>
            <p className="text-xs text-on-surface-variant">{user?.role}</p>
          </div>
          <button onClick={() => logout()} className="btn btn-text">
            Sign out
          </button>
        </div>
      </nav>

      <main className="min-w-0 flex-1 px-4 pt-2 pb-10 lg:px-8 lg:pt-6">
        <Outlet />
      </main>
    </div>
  )
}
