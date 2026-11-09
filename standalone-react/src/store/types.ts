/**
 * Le format de données de GPV.
 *
 * Il reprend exactement celui de la version autonome écrite à la main : mêmes
 * noms de champs, même clé de stockage. Ouvrir cette version-ci reprend donc
 * les données déjà saisies, et les deux fichiers lisent les mêmes exports.
 */

export type Status = 'todo' | 'doing' | 'wait' | 'done'
export type Priority = 'haute' | 'normale' | 'basse'
export type Recurrence = '' | 'daily' | 'weekdays' | 'weekly' | 'monthly'
export type Template = 'tache' | 'rdv' | 'cours' | 'formation' | 'programme' | 'habitude' | 'demarche'
export type ViewName = 'domaines' | 'kanban' | 'liste' | 'calendrier'
export type Theme = 'auto' | 'light' | 'dark'

export interface Domain {
  id: string
  name: string
  hue: number
  goal: string
  notes: string
  order: number
}

export interface SubDomain {
  id: string
  domainId: string
  /** Vide au premier niveau. Renseigné, il crée Famille › Parents › Maman. */
  parentId: string
  name: string
  tpl: Template
  notes: string
  order: number
}

export interface Subtask {
  id: string
  t: string
  done: boolean
}

export interface Item {
  id: string
  domainId: string
  subId: string
  title: string
  status: Status
  priority: Priority
  /** Échéance au format AAAA-MM-JJ. Une chaîne, pas une date : c'est un jour du calendrier. */
  due: string
  time: string
  recur: Recurrence
  /** Progression manuelle, utilisée seulement sans sous-étapes. */
  progress: number
  subtasks: Subtask[]
  fields: Record<string, string>
  notes: string
  timeSpent: number
  example: boolean
  createdAt: string
  doneAt?: string | null
  /** Vrai dès que cet élément a engendré son occurrence suivante. */
  spawned?: boolean
  /** Quantième de référence d'une récurrence mensuelle. */
  anchorDay?: number | null
  seedKey?: string
}

export interface AccessLink {
  id: string
  label: string
  url: string
  login: string
  domainId: string
  subId: string
  order?: number
}

export interface Shift {
  id: string
  date: string
  start: string
  end: string
  place: string
  example?: boolean
}

export interface InboxEntry {
  id: string
  text: string
  src: string
  /** « d:<id> » ou « s:<id> », vide tant que la ligne n'est pas classée. */
  where: string
  due: string
  time: string
  auto: boolean
}

export interface FocusEntry {
  /** Jour local, AAAA-MM-JJ. */
  d: string
  domainId: string
  min: number
}

export interface PomodoroSettings {
  work: number
  short: number
  long: number
  auto: boolean
}

export interface Settings {
  theme: Theme
  pomo: PomodoroSettings
  ambPreset: string
  ambVol: number
  ambMix: Record<string, number> | null
  ambMixes: Record<string, Record<string, number> & { _img?: string }>
  ambVisual: boolean
  ambDuck: boolean
  ambIntensity: number
  wall: boolean
  openOver: boolean
  lastExport: string | null
  view: ViewName
  seedV: number
}

export interface State {
  v: number
  domains: Domain[]
  subs: SubDomain[]
  items: Item[]
  links: AccessLink[]
  shifts: Shift[]
  inbox: InboxEntry[]
  freeNotes: string
  focusLog: FocusEntry[]
  settings: Settings
}

export const STATUSES: { key: Status; label: string }[] = [
  { key: 'todo', label: 'À faire' },
  { key: 'doing', label: 'En cours' },
  { key: 'wait', label: 'En attente' },
  { key: 'done', label: 'Terminé' },
]

export const PRIORITIES: { key: Priority; label: string }[] = [
  { key: 'haute', label: 'Haute' },
  { key: 'normale', label: 'Normale' },
  { key: 'basse', label: 'Basse' },
]

export const RECURRENCES: { key: Recurrence; label: string }[] = [
  { key: '', label: 'Aucune' },
  { key: 'daily', label: 'Chaque jour' },
  { key: 'weekdays', label: 'Jours ouvrés' },
  { key: 'weekly', label: 'Chaque semaine' },
  { key: 'monthly', label: 'Chaque mois' },
]

/** Les champs que chaque gabarit ajoute à un élément. */
export const TEMPLATES: Record<Template, { name: string; fields: [string, string][] }> = {
  tache: { name: 'Tâche simple', fields: [] },
  rdv: {
    name: 'Rendez-vous',
    fields: [
      ['praticien', 'Praticien'],
      ['lieu', 'Lieu'],
      ['docs', 'Documents à apporter'],
    ],
  },
  cours: {
    name: 'Cours',
    fields: [
      ['intervenant', 'Intervenant'],
      ['lieu', 'Lieu ou lien'],
    ],
  },
  formation: {
    name: 'Formation',
    fields: [
      ['module', 'Module'],
      ['nature', 'Nature (rendu, examen…)'],
    ],
  },
  programme: {
    name: 'Programme',
    fields: [
      ['objectif', 'Objectif chiffré'],
      ['resultat', 'Résultat obtenu'],
    ],
  },
  habitude: { name: 'Habitude', fields: [] },
  demarche: {
    name: 'Démarche',
    fields: [
      ['organisme', 'Organisme'],
      ['reference', 'Référence / dossier'],
      ['montant', 'Montant'],
      ['contact', 'Contact'],
    ],
  },
}

export const statusLabel = (key: Status): string =>
  STATUSES.find((s) => s.key === key)?.label ?? 'À faire'

export const priorityLabel = (key: Priority): string =>
  PRIORITIES.find((p) => p.key === key)?.label ?? 'Normale'
