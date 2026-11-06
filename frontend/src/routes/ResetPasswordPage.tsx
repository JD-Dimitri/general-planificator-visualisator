import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { ApiError, api } from '../api/client'
import { useAuth } from '../auth/AuthContext'
import { PasswordField } from '../components/PasswordField'

interface TokenCheck {
  valid: boolean
  email: string
  expiresIn: number
}

/**
 * Écran ouvert depuis le lien reçu par e-mail.
 *
 * La validité du lien est vérifiée avant d'afficher le formulaire : mieux vaut
 * l'annoncer tout de suite que laisser quelqu'un choisir un mot de passe pour
 * s'entendre dire, après l'avoir saisi deux fois, que le lien avait expiré.
 */
export function ResetPasswordPage() {
  const { token = '' } = useParams()
  const navigate = useNavigate()
  const { resetPassword } = useAuth()

  const [check, setCheck] = useState<TokenCheck | null>(null)
  const [checking, setChecking] = useState(true)
  const [password, setPassword] = useState('')
  const [confirm, setConfirm] = useState('')
  const [error, setError] = useState<ApiError | null>(null)
  const [mismatch, setMismatch] = useState('')
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    let cancelled = false

    api
      .get<TokenCheck>(`/auth/password/reset/${encodeURIComponent(token)}`)
      .then((result) => {
        if (!cancelled) setCheck(result)
      })
      .catch((caught) => {
        if (!cancelled) setError(caught instanceof ApiError ? caught : null)
      })
      .finally(() => {
        if (!cancelled) setChecking(false)
      })

    return () => {
      cancelled = true
    }
  }, [token])

  async function submit(event: React.FormEvent) {
    event.preventDefault()
    setError(null)
    setMismatch('')

    if (password !== confirm) {
      setMismatch('Les deux mots de passe ne sont pas identiques.')

      return
    }

    setBusy(true)
    try {
      await resetPassword(token, password)
      navigate('/', { replace: true })
    } catch (caught) {
      setError(caught instanceof ApiError ? caught : new ApiError(0, 'error', 'Changement impossible. Réessaie.'))
    } finally {
      setBusy(false)
    }
  }

  if (checking) {
    return (
      <div className="login-screen">
        <div className="login-card">
          <h1>GPV</h1>
          <p className="muted">Vérification du lien…</p>
        </div>
      </div>
    )
  }

  if (!check?.valid) {
    return (
      <div className="login-screen">
        <div className="login-card">
          <h1>GPV</h1>
          <p className="alert">
            {error?.message ?? "Ce lien n'est plus valable. Demandes-en un nouveau depuis l'écran de connexion."}
          </p>
          <div className="actions">
            <button type="button" className="btn primary" onClick={() => navigate('/', { replace: true })}>
              Revenir à la connexion
            </button>
          </div>
        </div>
      </div>
    )
  }

  return (
    <div className="login-screen">
      <form className="login-card" onSubmit={submit} noValidate>
        <h1>GPV</h1>
        <div className="steps-head">
          <span className="kind">Nouveau mot de passe</span>
          <span className="muted">Pour le compte {check.email}.</span>
        </div>

        <PasswordField
          label="Nouveau mot de passe"
          value={password}
          onChange={setPassword}
          autoComplete="new-password"
          hint="Au moins 10 caractères."
          error={error?.fields.password?.[0]}
          autoFocus
        />

        <PasswordField
          label="Confirme le mot de passe"
          value={confirm}
          onChange={setConfirm}
          autoComplete="new-password"
          error={mismatch || undefined}
        />

        {error && !Object.keys(error.fields).length && <p className="alert">{error.message}</p>}

        <p className="muted">
          Changer le mot de passe déconnecte tous les appareils déjà connectés à ce compte.
        </p>

        <div className="actions">
          <button type="submit" className="btn primary" disabled={busy}>
            {busy ? 'Enregistrement…' : 'Valider et me connecter'}
          </button>
        </div>
      </form>
    </div>
  )
}
