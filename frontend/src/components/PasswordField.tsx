import { useId, useState } from 'react'

interface PasswordFieldProps {
  label: string
  value: string
  onChange: (value: string) => void
  autoComplete?: 'current-password' | 'new-password'
  hint?: string
  error?: string
  autoFocus?: boolean
}

/**
 * Champ de mot de passe avec l'œil qui montre ou masque la saisie.
 *
 * Le bouton porte `aria-pressed` et change de libellé : un lecteur d'écran
 * annonce l'état, et pas seulement un symbole. Le champ reste `type="password"`
 * par défaut, pour que le mot de passe ne se lise pas par-dessus l'épaule.
 */
export function PasswordField({
  label,
  value,
  onChange,
  autoComplete = 'current-password',
  hint,
  error,
  autoFocus,
}: PasswordFieldProps) {
  const id = useId()
  const [shown, setShown] = useState(false)

  return (
    <div className="field">
      <label htmlFor={id}>{label}</label>
      <div className="password-row">
        <input
          id={id}
          type={shown ? 'text' : 'password'}
          value={value}
          onChange={(event) => onChange(event.target.value)}
          autoComplete={autoComplete}
          autoFocus={autoFocus}
          required
        />
        <button
          type="button"
          className="eye"
          onClick={() => setShown(!shown)}
          aria-pressed={shown}
          aria-label={shown ? 'Masquer le mot de passe' : 'Afficher le mot de passe'}
          title={shown ? 'Masquer le mot de passe' : 'Afficher le mot de passe'}
        >
          {shown ? <EyeOff /> : <Eye />}
        </button>
      </div>
      {error ? (
        <span className="error">{error}</span>
      ) : hint ? (
        <span className="muted">{hint}</span>
      ) : null}
    </div>
  )
}

function Eye() {
  return (
    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="1.8" aria-hidden="true">
      <path d="M2 12s3.6-6.5 10-6.5S22 12 22 12s-3.6 6.5-10 6.5S2 12 2 12z" />
      <circle cx="12" cy="12" r="3" />
    </svg>
  )
}

function EyeOff() {
  return (
    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" aria-hidden="true">
      <path d="M2 12s3.6-6.5 10-6.5c1.6 0 3 .4 4.2 1M22 12s-3.6 6.5-10 6.5c-1.6 0-3-.4-4.2-1" />
      <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" />
      <path d="M3 3l18 18" />
    </svg>
  )
}
