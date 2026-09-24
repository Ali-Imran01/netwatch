import { NavLink, Outlet } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'
import { entities } from '../inventory/entities'

const linkClass = ({ isActive }: { isActive: boolean }) =>
  `block rounded px-3 py-2 text-sm ${isActive ? 'bg-gray-900 text-white' : 'text-gray-700 hover:bg-gray-200'}`

export default function Layout() {
  const { user, logout } = useAuth()

  return (
    <div className="flex min-h-screen bg-gray-50">
      <nav className="flex w-56 shrink-0 flex-col border-r border-gray-200 bg-white p-4">
        <p className="mb-6 text-lg font-semibold text-gray-900">NetWatch</p>
        <div className="space-y-1">
          <NavLink to="/" end className={linkClass}>
            Dashboard
          </NavLink>
          <p className="px-3 pt-4 pb-1 text-xs font-medium uppercase text-gray-400">Inventory</p>
          {entities.map((e) => (
            <NavLink key={e.path} to={`/inventory/${e.path}`} className={linkClass}>
              {e.title}
            </NavLink>
          ))}
        </div>
        <div className="mt-auto border-t border-gray-200 pt-4 text-sm">
          <p className="font-medium text-gray-900">{user?.name}</p>
          <p className="text-gray-500">{user?.role}</p>
          <button onClick={() => logout()} className="mt-2 text-gray-600 hover:text-gray-900">
            Sign out
          </button>
        </div>
      </nav>
      <main className="min-w-0 flex-1 p-8">
        <Outlet />
      </main>
    </div>
  )
}
