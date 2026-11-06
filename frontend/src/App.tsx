import { Navigate, Route, Routes } from 'react-router-dom'
import { useAuth } from './auth/AuthContext'
import { DashboardPage } from './routes/DashboardPage'
import { DomainPage } from './routes/DomainPage'
import { LoginPage } from './routes/LoginPage'
import { ResetPasswordPage } from './routes/ResetPasswordPage'

export function App() {
  const { user, loading, logout } = useAuth()

  if (loading) {
    return (
      <p className="empty" style={{ padding: 24 }}>
        Ouverture de GPV…
      </p>
    )
  }

  // Le lien de réinitialisation s'ouvre sans être connecté : c'est justement
  // la situation de quelqu'un qui ne peut plus entrer.
  if (!user) {
    return (
      <Routes>
        <Route path="/reinitialiser/:token" element={<ResetPasswordPage />} />
        <Route path="*" element={<LoginPage />} />
      </Routes>
    )
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
          <Route path="/reinitialiser/:token" element={<Navigate to="/" replace />} />
          <Route path="*" element={<DashboardPage />} />
        </Routes>
      </main>
    </div>
  )
}
