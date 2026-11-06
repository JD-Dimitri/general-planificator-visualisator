/** Les formes renvoyées par l'API Symfony, groupe de sérialisation « read ». */

export interface User {
  id: string
  email: string
  displayName: string
  phone: string | null
  plan: 'free' | 'connected'
  verified: boolean
  roles: string[]
  settings: Record<string, unknown>
  createdAt: string
}

/**
 * Une demande de code en cours. L'API ne renvoie jamais le code lui-même,
 * seulement de quoi afficher l'écran de saisie : par où il est parti, vers
 * quelle adresse masquée, et combien de temps il reste.
 *
 * `devCode` n'existe qu'en développement, quand aucun e-mail ne part vraiment.
 */
export interface Challenge {
  challengeId: string
  channel: 'email' | 'sms'
  destination: string
  expiresIn: number
  sent: boolean
  devCode?: string
}

export interface ChallengeResponse {
  status: 'code_sent'
  message: string
  challenge: Challenge
}

export interface Domain {
  id: string
  name: string
  hue: number
  goal: string
  notes: string
  position: number
  createdAt: string
  updatedAt: string
}

export type SubDomainTemplate =
  | 'tache'
  | 'rdv'
  | 'cours'
  | 'formation'
  | 'programme'
  | 'habitude'
  | 'demarche'

export interface SubDomain {
  id: string
  name: string
  domainId: string
  parentId: string | null
  depth: number
  template: SubDomainTemplate
  notes: string
  position: number
  createdAt: string
  updatedAt: string
}

export const TEMPLATE_LABELS: Record<SubDomainTemplate, string> = {
  tache: 'Tâche simple',
  rdv: 'Rendez-vous',
  cours: 'Cours',
  formation: 'Formation',
  programme: 'Programme',
  habitude: 'Habitude',
  demarche: 'Démarche',
}

export interface DomainInput {
  name: string
  hue: number
  goal: string
  notes: string
  position: number
}

export interface SubDomainInput {
  name: string
  domainId: string
  parentId: string | null
  template: SubDomainTemplate
  notes: string
  position: number
}

/** Un sous-domaine et ses enfants, reconstruit côté client à partir de la liste plate. */
export interface SubDomainNode extends SubDomain {
  children: SubDomainNode[]
}
