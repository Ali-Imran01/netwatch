import { useAuth } from '../context/AuthContext'

export default function Dashboard() {
  const { user, logout } = useAuth()

  return (
    <div className="min-h-screen bg-gray-50 p-8">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold text-gray-900">NetWatch</h1>
        <button onClick={() => logout()} className="text-sm text-gray-600 hover:text-gray-900">
          Sign out
        </button>
      </div>

      <p className="mt-4 text-sm text-gray-600">
        Signed in as <span className="font-medium text-gray-900">{user?.name}</span> ({user?.role})
      </p>

      <p className="mt-8 text-sm text-gray-500">
        Dashboard content (status board, latency charts, uptime %) arrives in Week 4.
      </p>
    </div>
  )
}
