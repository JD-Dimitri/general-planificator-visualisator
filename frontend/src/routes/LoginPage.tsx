import { useEffect, useState } from 'react'
import { ApiError } from '../api/client'
import type { Challenge } from '../api/types'
import { useAuth } from '../auth/AuthContext'
import { PasswordField } from '../components/PasswordField'

type Step = 'login' | 'register' | 'code' | 'forgot' | 'forgot-sent'

export function LoginPage() {
  const { login, register, submitCode, resendCode, forgotPassword } = useAuth()

  const [step, setStep] = useState<Step>('login')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [displayName, setDisplayName] = useState('')
  const [phone, setPhone] = useState('')
  const [code, setCode] = useState('')
  const [challenge, setChallenge] = useState<Challenge | null>(null)
  const [origin, setOrigin] = useState<'login' | 'register'>('login')
  const [notice, setNotice] = useState('')
  const [error, setError] = useState<ApiError | null>(null)
  const [busy, setBusy] = useState(false)

  function go(next: Step) {
    setStep(next)
    setError(null)
    setNotice('')
  }

  function fail(caught: unknown) {
    setError(
      caught instanceof ApiError
        ? caught
        : new ApiError(0, 'error', "Quelque chose n'a pas fonctionné. Réessaie."),
    )
  }

  async function run(action: () => Promise<void>) {
    setBusy(true)
    setError(null)
    try {
      await action()
    } catch (caught) {
      fail(caught)
    } finally {
      setBusy(false)
    }
  }

  const submitCredentials = (event: React.FormEvent) => {
    event.preventDefault()
    void run(async () => {
      const issued =
        step === 'register'
          ? await register({ email, password, displayName, phone: phone || undefined })
          : await login(email, password)

      setOrigin(step === 'register' ? 'register' : 'login')
      setChallenge(issued)
      setCode('')
      setStep('code')
      setNotice('')
    })
  }

  const submitTheCode = (event: React.FormEvent) => {
    event.preventDefault()
    if (!challenge) return
    void run(() => submitCode(origin, challenge.challengeId, code))
  }

  const askAgain = () =>
    void run(async () => {
      if (!challenge) return
      const issued = await resendCode(challenge.challengeId)
      setChallenge(issued)
      setCode('')
      setNotice('Nouveau code envoyé.')
    })

  const submitForgot = (event: React.FormEvent) => {
    event.preventDefault()
    void run(async () => {
      const result = await forgotPassword(email)
      setNotice(result.message + (result.devToken ? ` Lien de test : /reinitialiser/${result.devToken}` : ''))
      setStep('forgot-sent')
    })
  }

  return (
    <div className="login-screen">
      <div className="login-card">
        <h1>GPV</h1>
        <p className="tagline">
          Tout ce que tu as à faire, dans tous tes domaines, sur un seul écran.
        </p>

        {step === 'code' && challenge ? (
          <CodeStep
            challenge={challenge}
            code={code}
            setCode={setCode}
            onSubmit={submitTheCode}
            onResend={askAgain}
            onBack={() => go(origin === 'register' ? 'register' : 'login')}
            notice={notice}
            error={error}
            busy={busy}
          />
        ) : step === 'forgot' ? (
          <form onSubmit={submitForgot} noValidate>
            <div className="steps-head">
              <span className="kind">Mot de passe oublié</span>
              <span className="muted">
                Indique ton adresse : nous t'envoyons un lien pour en choisir un nouveau.
              </span>
            </div>

            <div className="field">
              <label htmlFor="forgot-email">Adresse e-mail</label>
              <input
                id="forgot-email"
                type="email"
                value={email}
                onChange={(event) => setEmail(event.target.value)}
                autoComplete="email"
                autoFocus
                required
              />
              {error?.fields.email && <span className="error">{error.fields.email[0]}</span>}
            </div>

            {error && !Object.keys(error.fields).length && <p className="alert">{error.message}</p>}

            <div className="actions">
              <button type="button" className="btn" onClick={() => go('login')}>
                Retour
              </button>
              <button type="submit" className="btn primary" disabled={busy}>
                {busy ? 'Envoi…' : 'Envoyer le lien'}
              </button>
            </div>
          </form>
        ) : step === 'forgot-sent' ? (
          <div>
            <p className="ok-note">{notice}</p>
            <div className="actions">
              <button type="button" className="btn primary" onClick={() => go('login')}>
                Revenir à la connexion
              </button>
            </div>
          </div>
        ) : (
          <form onSubmit={submitCredentials} noValidate>
            {step === 'register' && (
              <>
                <div className="field">
                  <label htmlFor="display-name">Ton nom</label>
                  <input
                    id="display-name"
                    value={displayName}
                    onChange={(event) => setDisplayName(event.target.value)}
                    autoComplete="name"
                  />
                </div>
                <div className="field">
                  <label htmlFor="phone">Téléphone (facultatif)</label>
                  <input
                    id="phone"
                    type="tel"
                    value={phone}
                    onChange={(event) => setPhone(event.target.value)}
                    placeholder="+33612345678"
                    autoComplete="tel"
                  />
                  {error?.fields.phone ? (
                    <span className="error">{error.fields.phone[0]}</span>
                  ) : (
                    <span className="muted">Pour recevoir le code par SMS plutôt que par e-mail.</span>
                  )}
                </div>
              </>
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

            <PasswordField
              label="Mot de passe"
              value={password}
              onChange={setPassword}
              autoComplete={step === 'register' ? 'new-password' : 'current-password'}
              hint={step === 'register' ? 'Au moins 10 caractères.' : undefined}
              error={error?.fields.password?.[0]}
            />

            {error && !Object.keys(error.fields).length && <p className="alert">{error.message}</p>}

            <div className="actions">
              <button type="submit" className="btn primary" disabled={busy}>
                {busy ? 'Un instant…' : step === 'register' ? 'Créer mon compte' : 'Se connecter'}
              </button>
            </div>

            <div className="link-row">
              <button type="button" className="linkish" onClick={() => go(step === 'login' ? 'register' : 'login')}>
                {step === 'login' ? "Pas encore de compte ? En créer un" : "J'ai déjà un compte"}
              </button>
              {step === 'login' && (
                <button type="button" className="linkish" onClick={() => go('forgot')}>
                  Mot de passe oublié ?
                </button>
              )}
            </div>
          </form>
        )}
      </div>
    </div>
  )
}

interface CodeStepProps {
  challenge: Challenge
  code: string
  setCode: (value: string) => void
  onSubmit: (event: React.FormEvent) => void
  onResend: () => void
  onBack: () => void
  notice: string
  error: ApiError | null
  busy: boolean
}

function CodeStep({ challenge, code, setCode, onSubmit, onResend, onBack, notice, error, busy }: CodeStepProps) {
  const [left, setLeft] = useState(challenge.expiresIn)

  useEffect(() => {
    setLeft(challenge.expiresIn)
    const timer = setInterval(() => setLeft((value) => Math.max(0, value - 1)), 1000)

    return () => clearInterval(timer)
  }, [challenge])

  const minutes = Math.floor(left / 60)
  const seconds = String(left % 60).padStart(2, '0')

  return (
    <form onSubmit={onSubmit} noValidate>
      <div className="steps-head">
        <span className="kind">Étape 2 sur 2</span>
        <span className="muted">
          {challenge.sent
            ? `Code envoyé ${challenge.channel === 'sms' ? 'par SMS au' : 'à'} ${challenge.destination}.`
            : `Le code n'a pas pu partir vers ${challenge.destination}. Demande-en un nouveau.`}
        </span>
      </div>

      {challenge.devCode && (
        <p className="dev-code">
          Développement : aucun e-mail n'est envoyé. Code <b>{challenge.devCode}</b>
        </p>
      )}

      {notice && <p className="ok-note">{notice}</p>}

      <div className="field">
        <label htmlFor="code">Code à six chiffres</label>
        <input
          id="code"
          className="code-input"
          value={code}
          onChange={(event) => setCode(event.target.value.replace(/\D/g, '').slice(0, 6))}
          inputMode="numeric"
          autoComplete="one-time-code"
          maxLength={6}
          autoFocus
          required
        />
        {error?.fields.code && <span className="error">{error.fields.code[0]}</span>}
      </div>

      {error && !Object.keys(error.fields).length && <p className="alert">{error.message}</p>}

      <p className="muted">
        {left > 0 ? `Ce code expire dans ${minutes} min ${seconds} s.` : 'Ce code a expiré.'}
      </p>

      <div className="actions">
        <button type="button" className="btn" onClick={onBack}>
          Retour
        </button>
        <button type="submit" className="btn primary" disabled={busy || code.length !== 6}>
          {busy ? 'Vérification…' : 'Valider'}
        </button>
      </div>

      <div className="link-row">
        <button type="button" className="linkish" onClick={onResend} disabled={busy}>
          Renvoyer un code
        </button>
      </div>
    </form>
  )
}
