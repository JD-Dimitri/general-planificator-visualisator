import { Route, Routes } from 'react-router-dom'
import { useAuth } from './auth/AuthContext'
import { DashboardPage } from './routes/DashboardPage'
import { DomainPage } from './routes/DomainPage'
import { LoginPage } from './routes/LoginPage'

export function App() {
  const { user, loading, logout } = useAuth()

  if (loading) {
    return <p className="empty" style={{ padding: 24 }}>Ouverture de GPV…</p>
  }

  if (!user) {
    return <LoginPage />
  }

  return (
    <div className="app">
      <header className="topbar">
        <div className="brand">
          <b>GPV</b>
          <span>General Planificator Visualisator</span>
        </div>
        <span className="spacer" />
        <span className="plan" title="Offre du compte">
          {user.plan === 'connected' ? 'Offre connectée' : 'Offre gratuite'}
        </span>
        <span className="muted">{user.displayName}</span>
        <button type="button" className="btn" onClick={() => void logout()}>
          Se déconnecter
        </button>
      </header>

      <main>
        <Routes>
          <Route path="/" element={<DashboardPage />} />
          <Route path="/domaines/:domainId" element={<DomainPage />} />
          <Route path="*" element={<DashboardPage />} />
        </Routes>
      </main>
    </div>
  )
}
