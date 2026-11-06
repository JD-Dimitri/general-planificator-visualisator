import { useState } from 'react'
import { ApiError } from '../api/client'
import { useCreateDomain, useUpdateDomain } from '../api/hooks'
import type { Domain } from '../api/types'
import { Sheet } from './Sheet'

interface DomainFormProps {
  domain?: Domain
  onClose: () => void
}

export function DomainForm({ domain, onClose }: DomainFormProps) {
  const [name, setName] = useState(domain?.name ?? '')
  const [goal, setGoal] = useState(domain?.goal ?? '')
  const [hue, setHue] = useState(domain?.hue ?? Math.floor(Math.random() * 360))
  const [error, setError] = useState<ApiError | null>(null)

  const create = useCreateDomain()
  const update = useUpdateDomain()
  const saving = create.isPending || update.isPending

  async function submit(event: React.FormEvent) {
    event.preventDefault()
    setError(null)

    try {
      if (domain) {
        await update.mutateAsync({ id: domain.id, name, goal, hue })
      } else {
        await create.mutateAsync({ name, goal, hue })
      }
      onClose()
    } catch (caught) {
      setError(caught instanceof ApiError ? caught : new ApiError(0, 'error', 'Enregistrement impossible.'))
    }
  }

  return (
    <Sheet title={domain ? 'Modifier le domaine' : 'Nouveau domaine'} onClose={onClose}>
      <form onSubmit={submit} noValidate>
        <div className="field">
          <label htmlFor="domain-name">Nom</label>
          <input
            id="domain-name"
            value={name}
            onChange={(event) => setName(event.target.value)}
            placeholder="Santé, Formation, Famille…"
          />
          {error?.fields.name && <span className="error">{error.fields.name[0]}</span>}
        </div>

        <div className="field">
          <label htmlFor="domain-goal">Objectif</label>
          <input
            id="domain-goal"
            value={goal}
            onChange={(event) => setGoal(event.target.value)}
            placeholder="Ce que tu veux atteindre dans ce domaine"
          />
          {error?.fields.goal && <span className="error">{error.fields.goal[0]}</span>}
        </div>

        <div className="field">
          <label htmlFor="domain-hue">Couleur</label>
          <div className="range-row" style={{ '--h': hue } as React.CSSProperties}>
            <input
              id="domain-hue"
              type="range"
              min={0}
              max={359}
              value={hue}
              onChange={(event) => setHue(Number(event.target.value))}
            />
            <span className="swatch" aria-hidden="true" />
          </div>
        </div>

        {error && !Object.keys(error.fields).length && <p className="alert">{error.message}</p>}

        <div className="actions">
          <button type="button" className="btn" onClick={onClose}>
            Annuler
          </button>
          <button type="submit" className="btn primary" disabled={saving}>
            {saving ? 'Enregistrement…' : 'Enregistrer'}
          </button>
        </div>
      </form>
    </Sheet>
  )
}
