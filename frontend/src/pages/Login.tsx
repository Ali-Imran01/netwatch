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
    <div className="flex min-h-screen items-center justify-center px-4">
      <form onSubmit={handleSubmit} className="card w-full max-w-sm space-y-4 p-8">
        <h1 className="text-2xl">Sign in to NetWatch</h1>

        {error && <p className="rounded-xl bg-error-container p-3 text-sm text-on-error-container">{error}</p>}

        <div>
          <label htmlFor="email" className="block text-sm font-medium text-on-surface-variant">Email</label>
          <input
            id="email"
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
            className="field"
          />
        </div>

        <div>
          <label htmlFor="password" className="block text-sm font-medium text-on-surface-variant">Password</label>
          <input
            id="password"
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
            className="field"
          />
        </div>

        <button
          type="submit"
          disabled={submitting}
          className="btn btn-filled w-full"
        >
          {submitting ? 'Signing in…' : 'Sign in'}
        </button>

        {showDemo && (
          <div className="rounded-xl bg-primary-container/60 p-3 text-sm text-on-primary-container">
            <p className="font-medium">Public demo (read-only)</p>
            <p className="mt-1">Simulated carrier network; nothing here is real.</p>
            <button
              type="button"
              onClick={() => {
                setEmail(demo.email)
                setPassword(demo.password)
              }}
              className="mt-2 text-primary underline"
            >
              Fill in the demo account
            </button>
          </div>
        )}
      </form>
    </div>
  )
}
