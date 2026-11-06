import { useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import {
  buildTree,
  useDeleteSubDomain,
  useDomains,
  useSubDomains,
} from '../api/hooks'
import { TEMPLATE_LABELS } from '../api/types'
import type { SubDomain, SubDomainNode } from '../api/types'
import { DomainForm } from '../components/DomainForm'
import { Gauge } from '../components/Gauge'
import { SubDomainForm } from '../components/SubDomainForm'

export function DomainPage() {
  const { domainId = '' } = useParams()
  const navigate = useNavigate()
  const domains = useDomains()
  const subDomains = useSubDomains()
  const removeSubDomain = useDeleteSubDomain()

  const [editingDomain, setEditingDomain] = useState(false)
  const [subForm, setSubForm] = useState<{ parentId: string | null; editing?: SubDomain } | null>(
    null,
  )

  const domain = domains.data?.find((d) => d.id === domainId)

  if (domains.isPending || subDomains.isPending) {
    return <p className="empty">Chargement…</p>
  }

  if (!domain) {
    return (
      <div className="panel">
        <p className="empty">Ce domaine n'existe plus.</p>
        <button type="button" className="btn" onClick={() => navigate('/')}>
          Revenir aux domaines
        </button>
      </div>
    )
  }

  const scoped = (subDomains.data ?? []).filter((s) => s.domainId === domain.id)
  const tree = buildTree(scoped)

  return (
    <>
      <div className="crumbs">
        <button type="button" onClick={() => navigate('/')}>
          Domaines
        </button>
        <span className="muted">/</span>
        <h1>{domain.name}</h1>
      </div>

      <div className="detail" style={{ '--h': domain.hue } as React.CSSProperties}>
        <div className="detail-head">
          <div>
            <div className="kind">Domaine</div>
            <h1>{domain.name}</h1>
            {domain.goal && <p className="muted">{domain.goal}</p>}
          </div>
          <div className="actions-row">
            <button
              type="button"
              className="btn small primary"
              onClick={() => setSubForm({ parentId: null })}
            >
              + Sous-domaine
            </button>
            <button type="button" className="btn small" onClick={() => setEditingDomain(true)}>
              Modifier
            </button>
          </div>
        </div>

        <Gauge value={null} big label={`Progression de ${domain.name}`} />

        <div className="cols">
          <section>
            <h4>Sous-domaines ({scoped.length})</h4>
            {tree.length === 0 ? (
              <p className="empty">
                Aucun sous-domaine. Ajoute-en un pour organiser ce domaine, par exemple « Parents »
                puis « Maman » à l'intérieur.
              </p>
            ) : (
              <div className="tree">
                {tree.map((node) => (
                  <SubDomainBranch
                    key={node.id}
                    node={node}
                    hue={domain.hue}
                    onAddChild={(parentId) => setSubForm({ parentId })}
                    onEdit={(editing) => setSubForm({ parentId: editing.parentId, editing })}
                    onDelete={(id) => removeSubDomain.mutate(id)}
                  />
                ))}
              </div>
            )}
          </section>

          <section>
            <h4>Bloc-notes</h4>
            <div className="notes">
              {domain.notes || 'Aucune note. Clique sur Modifier pour en ajouter.'}
            </div>
          </section>
        </div>
      </div>

      {editingDomain && <DomainForm domain={domain} onClose={() => setEditingDomain(false)} />}

      {subForm && (
        <SubDomainForm
          domainId={domain.id}
          siblings={scoped}
          subDomain={subForm.editing}
          defaultParentId={subForm.parentId}
          onClose={() => setSubForm(null)}
        />
      )}
    </>
  )
}

interface BranchProps {
  node: SubDomainNode
  hue: number
  onAddChild: (parentId: string) => void
  onEdit: (subDomain: SubDomain) => void
  onDelete: (id: string) => void
}

function SubDomainBranch({ node, hue, onAddChild, onEdit, onDelete }: BranchProps) {
  const [confirming, setConfirming] = useState(false)

  return (
    <div>
      <div className="tree-node" style={{ '--h': hue } as React.CSSProperties}>
        <div className="kind">{TEMPLATE_LABELS[node.template]}</div>
        <h5>{node.name}</h5>
        <div className="actions-row">
          <button type="button" className="btn small" onClick={() => onAddChild(node.id)}>
            + Sous-niveau
          </button>
          <button type="button" className="btn small ghost" onClick={() => onEdit(node)}>
            Modifier
          </button>
          {confirming ? (
            <button
              type="button"
              className="btn small danger"
              onClick={() => {
                onDelete(node.id)
                setConfirming(false)
              }}
            >
              Confirmer : son contenu remonte d'un niveau
            </button>
          ) : (
            <button type="button" className="btn small ghost" onClick={() => setConfirming(true)}>
              Supprimer
            </button>
          )}
        </div>
      </div>

      {node.children.length > 0 && (
        <div className="tree-children">
          {node.children.map((child) => (
            <SubDomainBranch
              key={child.id}
              node={child}
              hue={hue}
              onAddChild={onAddChild}
              onEdit={onEdit}
              onDelete={onDelete}
            />
          ))}
        </div>
      )}
    </div>
  )
}
