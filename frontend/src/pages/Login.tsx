import { isAxiosError } from 'axios'
import { useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext'

// Public demo: the seeded account is read-only, so its credentials are shown on the sign-in page. Off unless the build sets VITE_DEMO_LOGIN=true.
const showDemo = import.meta.env.VITE_DEMO_LOGIN === 'true'
const demo = { email: 'demo@netwatch.example', password: 'read-only-demo' }

export default function Login() {
  const { login } = useAuth()
  const navigate = useNavigate()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  async function handleSubmit(event: FormEvent) {
    event.preventDefault()
    setError(null)
    setSubmitting(true)
    try {
      await login(email, password)
      navigate('/')
    } catch (err) {
      setError(isAxiosError(err) && err.response?.status === 429 ? 'Too many attempts. Wait a minute and try again.' : 'Invalid credentials.')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-gray-50">
      <form onSubmit={handleSubmit} className="w-full max-w-sm space-y-4 rounded-lg border border-gray-200 bg-white p-8 shadow-sm">
        <h1 className="text-xl font-semibold text-gray-900">Sign in to NetWatch</h1>

        {error && <p className="text-sm text-red-600">{error}</p>}

        <div>
          <label htmlFor="email" className="block text-sm font-medium text-gray-700">Email</label>
          <input
            id="email"
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
            className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
          />
        </div>

        <div>
          <label htmlFor="password" className="block text-sm font-medium text-gray-700">Password</label>
          <input
            id="password"
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
            className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
          />
        </div>

        <button
          type="submit"
          disabled={submitting}
          className="w-full rounded-md bg-gray-900 px-3 py-2 text-sm font-medium text-white disabled:opacity-50"
        >
          {submitting ? 'Signing in…' : 'Sign in'}
        </button>

        {showDemo && (
          <div className="rounded-md bg-sky-50 p-3 text-sm text-sky-900">
            <p className="font-medium">Public demo (read-only)</p>
            <p className="mt-1">Simulated carrier network; nothing here is real.</p>
            <button
              type="button"
              onClick={() => {
                setEmail(demo.email)
                setPassword(demo.password)
              }}
              className="mt-2 underline"
            >
              Fill in the demo account
            </button>
          </div>
        )}
      </form>
    </div>
  )
}
