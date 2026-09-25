import type { ReactNode } from 'react'
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import Layout from '../components/Layout'
import { useAuth } from '../context/AuthContext'
import { entities, entityUrl } from '../inventory/entities'
import Dashboard from '../pages/Dashboard'
import EntityPage from '../pages/EntityPage'
import IncidentDetail from '../pages/IncidentDetail'
import Incidents from '../pages/Incidents'
import Login from '../pages/Login'
import MonitorDetail from '../pages/MonitorDetail'

function ProtectedRoute({ children }: { children: ReactNode }) {
  const { user, loading } = useAuth()

  if (loading) return null
  if (!user) return <Navigate to="/login" replace />

  return <>{children}</>
}

export default function AppRouter() {
  return (
    <BrowserRouter>
      <Routes>
        <Route path="/login" element={<Login />} />
        <Route
          element={
            <ProtectedRoute>
              <Layout />
            </ProtectedRoute>
          }
        >
          <Route path="/" element={<Dashboard />} />
          <Route path="/monitoring/monitors/:id" element={<MonitorDetail />} />
          <Route path="/incidents" element={<Incidents />} />
          <Route path="/incidents/:id" element={<IncidentDetail />} />
          {entities.map((e) => (
            // key forces a fresh page (page number, open dialogs) when switching entity.
            <Route key={e.path} path={entityUrl(e)} element={<EntityPage key={e.path} entity={e} />} />
          ))}
        </Route>
      </Routes>
    </BrowserRouter>
  )
}
