import { useState } from 'react'
import { ApiError } from '../api/client'
import { useAuth } from '../auth/AuthContext'

export function LoginPage() {
  const { login, register } = useAuth()
  const [mode, setMode] = useState<'login' | 'register'>('login')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [displayName, setDisplayName] = useState('')
  const [error, setError] = useState<ApiError | null>(null)
  const [busy, setBusy] = useState(false)

  async function submit(event: React.FormEvent) {
    event.preventDefault()
    setError(null)
    setBusy(true)

    try {
      if (mode === 'login') {
        await login(email, password)
      } else {
        await register(email, password, displayName)
      }
    } catch (caught) {
      setError(
        caught instanceof ApiError
          ? caught
          : new ApiError(0, 'error', 'Connexion impossible pour le moment.'),
      )
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="login-screen">
      <form className="login-card" onSubmit={submit} noValidate>
        <h1>GPV</h1>
        <p className="tagline">
          Tout ce que tu as à faire, dans tous tes domaines, sur un seul écran.
        </p>

        {mode === 'register' && (
          <div className="field">
            <label htmlFor="display-name">Ton nom</label>
            <input
              id="display-name"
              value={displayName}
              onChange={(event) => setDisplayName(event.target.value)}
              autoComplete="name"
            />
          </div>
        )}

        <div className="field">
          <label htmlFor="email">Adresse e-mail</label>
          <input
            id="email"
            type="email"
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            autoComplete="email"
            required
          />
          {error?.fields.email && <span className="error">{error.fields.email[0]}</span>}
        </div>

        <div className="field">
          <label htmlFor="password">Mot de passe</label>
          <input
            id="password"
            type="password"
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            autoComplete={mode === 'login' ? 'current-password' : 'new-password'}
            required
          />
          {error?.fields.password && <span className="error">{error.fields.password[0]}</span>}
          {mode === 'register' && !error?.fields.password && (
            <span className="muted">Au moins 10 caractères.</span>
          )}
        </div>

        {error && !Object.keys(error.fields).length && <p className="alert">{error.message}</p>}

        <div className="actions">
          <button type="submit" className="btn primary" disabled={busy}>
            {busy ? 'Un instant…' : mode === 'login' ? 'Se connecter' : 'Créer mon compte'}
          </button>
        </div>

        <button
          type="button"
          className="switch"
          onClick={() => {
            setMode(mode === 'login' ? 'register' : 'login')
            setError(null)
          }}
        >
          {mode === 'login'
            ? "Pas encore de compte ? En créer un"
            : "J'ai déjà un compte, me connecter"}
        </button>
      </form>
    </div>
  )
}
