import { useState } from 'react'
import { ApiError } from '../api/client'
import { useCreateSubDomain, useUpdateSubDomain, pathOf } from '../api/hooks'
import { TEMPLATE_LABELS } from '../api/types'
import type { SubDomain, SubDomainTemplate } from '../api/types'
import { Sheet } from './Sheet'

interface SubDomainFormProps {
  domainId: string
  /** Sous-domaines du même domaine, pour proposer un parent. */
  siblings: SubDomain[]
  subDomain?: SubDomain
  defaultParentId?: string | null
  onClose: () => void
}

export function SubDomainForm({
  domainId,
  siblings,
  subDomain,
  defaultParentId,
  onClose,
}: SubDomainFormProps) {
  const [name, setName] = useState(subDomain?.name ?? '')
  const [parentId, setParentId] = useState<string>(
    subDomain?.parentId ?? defaultParentId ?? '',
  )
  const [template, setTemplate] = useState<SubDomainTemplate>(subDomain?.template ?? 'tache')
  const [error, setError] = useState<ApiError | null>(null)

  const create = useCreateSubDomain()
  const update = useUpdateSubDomain()
  const saving = create.isPending || update.isPending

  // On ne se propose pas soi-même comme parent, ni sa propre descendance :
  // l'API refuse le cycle, autant ne pas l'offrir.
  const forbidden = new Set<string>()
  if (subDomain) {
    const collect = (id: string) => {
      forbidden.add(id)
      siblings.filter((s) => s.parentId === id).forEach((child) => collect(child.id))
    }
    collect(subDomain.id)
  }

  const candidates = siblings.filter((s) => !forbidden.has(s.id))

  async function submit(event: React.FormEvent) {
    event.preventDefault()
    setError(null)

    const payload = {
      name,
      domainId,
      parentId: parentId === '' ? null : parentId,
      template,
    }

    try {
      if (subDomain) {
        await update.mutateAsync({ id: subDomain.id, ...payload })
      } else {
        await create.mutateAsync(payload)
      }
      onClose()
    } catch (caught) {
      setError(
        caught instanceof ApiError ? caught : new ApiError(0, 'error', 'Enregistrement impossible.'),
      )
    }
  }

  return (
    <Sheet
      title={subDomain ? 'Modifier le sous-domaine' : 'Nouveau sous-domaine'}
      onClose={onClose}
    >
      <form onSubmit={submit} noValidate>
        <div className="field">
          <label htmlFor="sub-name">Nom</label>
          <input
            id="sub-name"
            value={name}
            onChange={(event) => setName(event.target.value)}
            placeholder="Rendez-vous médicaux, Parents, Maman…"
          />
          {error?.fields.name && <span className="error">{error.fields.name[0]}</span>}
        </div>

        <div className="row">
          <div className="field">
            <label htmlFor="sub-parent">Placé dans</label>
            <select
              id="sub-parent"
              value={parentId}
              onChange={(event) => setParentId(event.target.value)}
            >
              <option value="">Directement dans le domaine</option>
              {candidates.map((candidate) => (
                <option key={candidate.id} value={candidate.id}>
                  {pathOf(candidate, siblings)
                    .map((node) => node.name)
                    .join(' › ')}
                </option>
              ))}
            </select>
          </div>

          <div className="field">
            <label htmlFor="sub-template">Gabarit</label>
            <select
              id="sub-template"
              value={template}
              onChange={(event) => setTemplate(event.target.value as SubDomainTemplate)}
            >
              {Object.entries(TEMPLATE_LABELS).map(([value, label]) => (
                <option key={value} value={value}>
                  {label}
                </option>
              ))}
            </select>
          </div>
        </div>

        <p className="muted">
          Choisis un sous-domaine dans « Placé dans » pour créer un niveau de plus, comme Famille ›
          Parents › Maman.
        </p>

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
