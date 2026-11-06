import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from './client'
import type { Domain, DomainInput, SubDomain, SubDomainInput, SubDomainNode } from './types'

export const keys = {
  domains: ['domains'] as const,
  subDomains: ['sub-domains'] as const,
}

export function useDomains() {
  return useQuery({
    queryKey: keys.domains,
    queryFn: () => api.get<Domain[]>('/domains'),
  })
}

export function useSubDomains() {
  return useQuery({
    queryKey: keys.subDomains,
    queryFn: () => api.get<SubDomain[]>('/sub-domains'),
  })
}

export function useCreateDomain() {
  const client = useQueryClient()

  return useMutation({
    mutationFn: (input: Partial<DomainInput>) => api.post<Domain>('/domains', input),
    onSuccess: () => client.invalidateQueries({ queryKey: keys.domains }),
  })
}

export function useUpdateDomain() {
  const client = useQueryClient()

  return useMutation({
    mutationFn: ({ id, ...input }: Partial<DomainInput> & { id: string }) =>
      api.patch<Domain>(`/domains/${id}`, input),
    onSuccess: () => client.invalidateQueries({ queryKey: keys.domains }),
  })
}

export function useDeleteDomain() {
  const client = useQueryClient()

  return useMutation({
    // `moveTo` évite les orphelins : les éléments rejoignent un autre domaine
    // au lieu de disparaître avec celui qu'on supprime.
    mutationFn: ({ id, moveTo }: { id: string; moveTo?: string }) =>
      api.delete<void>(`/domains/${id}${moveTo ? `?moveTo=${moveTo}` : ''}`),
    onSuccess: () => {
      void client.invalidateQueries({ queryKey: keys.domains })
      void client.invalidateQueries({ queryKey: keys.subDomains })
    },
  })
}

export function useCreateSubDomain() {
  const client = useQueryClient()

  return useMutation({
    mutationFn: (input: Partial<SubDomainInput>) => api.post<SubDomain>('/sub-domains', input),
    onSuccess: () => client.invalidateQueries({ queryKey: keys.subDomains }),
  })
}

export function useUpdateSubDomain() {
  const client = useQueryClient()

  return useMutation({
    mutationFn: ({ id, ...input }: Partial<SubDomainInput> & { id: string }) =>
      api.patch<SubDomain>(`/sub-domains/${id}`, input),
    onSuccess: () => client.invalidateQueries({ queryKey: keys.subDomains }),
  })
}

export function useDeleteSubDomain() {
  const client = useQueryClient()

  return useMutation({
    mutationFn: (id: string) => api.delete<void>(`/sub-domains/${id}`),
    onSuccess: () => client.invalidateQueries({ queryKey: keys.subDomains }),
  })
}

/**
 * L'API renvoie une liste plate ; l'écran a besoin d'un arbre.
 * Les orphelins — un parent filtré ou supprimé entre deux requêtes — sont
 * rattachés à la racine plutôt que d'être perdus pour l'affichage.
 */
export function buildTree(subDomains: SubDomain[], domainId?: string): SubDomainNode[] {
  const scoped = domainId ? subDomains.filter((s) => s.domainId === domainId) : subDomains
  const nodes = new Map<string, SubDomainNode>()

  for (const sub of scoped) {
    nodes.set(sub.id, { ...sub, children: [] })
  }

  const roots: SubDomainNode[] = []

  for (const node of nodes.values()) {
    const parent = node.parentId ? nodes.get(node.parentId) : undefined
    if (parent) {
      parent.children.push(node)
    } else {
      roots.push(node)
    }
  }

  const sort = (list: SubDomainNode[]): SubDomainNode[] => {
    list.sort((a, b) => a.position - b.position || a.name.localeCompare(b.name, 'fr'))
    list.forEach((node) => sort(node.children))
    return list
  }

  return sort(roots)
}

/** Le chemin lisible d'un sous-domaine : « Parents › Maman ». */
export function pathOf(subDomain: SubDomain, all: SubDomain[]): SubDomain[] {
  const byId = new Map(all.map((s) => [s.id, s]))
  const path: SubDomain[] = []
  let current: SubDomain | undefined = subDomain
  let guard = 0

  while (current && guard++ < 32) {
    path.unshift(current)
    current = current.parentId ? byId.get(current.parentId) : undefined
  }

  return path
}
