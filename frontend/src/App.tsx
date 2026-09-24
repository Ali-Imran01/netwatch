import { AuthProvider } from './context/AuthContext'
import AppRouter from './router'

function App() {
  return (
    <AuthProvider>
      <AppRouter />
    </AuthProvider>
  )
}

export default App
