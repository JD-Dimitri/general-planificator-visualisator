import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { buildTree, useDomains, useSubDomains } from '../api/hooks'
import type { Domain } from '../api/types'
import { DomainForm } from '../components/DomainForm'
import { Gauge } from '../components/Gauge'

export function DashboardPage() {
  const navigate = useNavigate()
  const domains = useDomains()
  const subDomains = useSubDomains()
  const [creating, setCreating] = useState(false)

  if (domains.isPending) {
    return <p className="empty">Chargement de tes domaines…</p>
  }

  if (domains.isError) {
    return <p className="alert">{(domains.error as Error).message}</p>
  }

  return (
    <>
      <div className="crumbs">
        <h1>Domaines de vie</h1>
      </div>

      <div className="domains">
        {domains.data.map((domain) => (
          <DomainCard
            key={domain.id}
            domain={domain}
            subCount={(subDomains.data ?? []).filter((s) => s.domainId === domain.id).length}
            chips={buildTree(subDomains.data ?? [], domain.id).slice(0, 4)}
            onOpen={() => navigate(`/domaines/${domain.id}`)}
          />
        ))}

        <button type="button" className="domain-card add" onClick={() => setCreating(true)}>
          + Nouveau domaine
        </button>
      </div>

      {creating && <DomainForm onClose={() => setCreating(false)} />}
    </>
  )
}

interface DomainCardProps {
  domain: Domain
  subCount: number
  chips: { id: string; name: string }[]
  onOpen: () => void
}

function DomainCard({ domain, subCount, chips, onOpen }: DomainCardProps) {
  return (
    <button
      type="button"
      className="domain-card"
      style={{ '--h': domain.hue } as React.CSSProperties}
      onClick={onOpen}
    >
      <h3>{domain.name}</h3>
      <div className="goal">{domain.goal || ' '}</div>

      {/* La progression viendra des éléments : l'entité Item existe déjà en base,
          son contrôleur est la prochaine tranche à écrire. */}
      <Gauge value={null} label={`Progression de ${domain.name}`} />

      <div className="muted">
        {subCount === 0
          ? 'Aucun sous-domaine'
          : `${subCount} sous-domaine${subCount > 1 ? 's' : ''}`}
      </div>

      {chips.length > 0 && (
        <div className="chips">
          {chips.map((chip) => (
            <span className="chip" key={chip.id}>
              {chip.name}
            </span>
          ))}
        </div>
      )}
    </button>
  )
}
