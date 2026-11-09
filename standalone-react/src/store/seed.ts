import { addDays, today } from './dates'
import type { AccessLink, Domain, Item, Shift, State, SubDomain, Template } from './types'

/**
 * Ce qu'un GPV neuf contient : les domaines du cahier des charges, leurs
 * sous-domaines et quelques éléments d'exemple.
 *
 * Un écran vide ne montre pas ce qu'il sait faire. Les exemples portent le
 * drapeau `example` pour s'effacer tous d'un clic.
 */

type DomainSpec = [key: string, name: string, hue: number, goal: string]
type SubSpec = [key: string, domainKey: string, parentKey: string | null, name: string, tpl: Template]
type ItemSpec = {
  key: string
  real?: boolean
  domain: string
  sub: string
  title: string
  due: string
  time?: string
  priority?: 'haute' | 'normale' | 'basse'
  status?: 'todo' | 'doing'
  fields?: Record<string, string>
  steps?: [string, boolean?][]
  notes?: string
}

const DOMAINS: DomainSpec[] = [
  ['sante', 'Santé', 150, 'Suivre mes rendez-vous et traitements'],
  ['sport', 'Sport', 30, 'Tenir mon programme chaque semaine'],
  ['religion', 'Religion', 300, 'Suivre mes cours régulièrement'],
  ['spiritualite', 'Spiritualité', 195, 'Garder une pratique quotidienne'],
  ['formation', 'Formation', 250, 'Valider mes formations dans les délais'],
  ['famille', 'Famille', 345, 'Accompagner mes parents dans leurs démarches'],
  ['securite', 'Sécurité', 10, 'Organiser mes vacations et mes papiers'],
]

const SUBS: SubSpec[] = [
  ['rdv', 'sante', null, 'Rendez-vous médicaux', 'rdv'],
  ['traitements', 'sante', null, 'Traitements', 'tache'],
  ['programme', 'sport', null, 'Programme sportif', 'programme'],
  ['cours', 'religion', null, 'Cours', 'cours'],
  ['pratiques', 'spiritualite', null, 'Pratiques régulières', 'habitude'],
  ['lectures', 'spiritualite', null, 'Lectures', 'tache'],
  ['formation1', 'formation', null, 'Formation (exemple)', 'formation'],
  ['parents', 'famille', null, 'Parents', 'tache'],
  ['maman', 'famille', 'parents', 'Maman', 'demarche'],
  ['papa', 'famille', 'parents', 'Papa', 'demarche'],
  ['administratif', 'securite', null, 'Administratif', 'demarche'],
]

function itemSpecs(): ItemSpec[] {
  const t = today()

  return [
    {
      key: 'ex-rdv',
      domain: 'sante',
      sub: 'rdv',
      title: 'Rendez-vous médecin traitant',
      due: addDays(t, 4),
      time: '10:30',
      fields: { praticien: 'Médecin traitant', lieu: 'Cabinet médical', docs: 'Carte Vitale, ordonnances' },
    },
    {
      key: 'ex-course',
      domain: 'sport',
      sub: 'programme',
      title: 'Séance course 5 km',
      due: addDays(t, 1),
      time: '07:00',
      fields: { objectif: '5 km en moins de 30 min' },
    },
    { key: 'ex-cours', domain: 'religion', sub: 'cours', title: 'Cours hebdomadaire', due: addDays(t, 2), time: '14:00' },
    { key: 'ex-medit', domain: 'spiritualite', sub: 'pratiques', title: 'Méditation 10 minutes', due: t },
    {
      key: 'ex-rendu',
      domain: 'formation',
      sub: 'formation1',
      title: 'Rendu du module 2',
      due: addDays(t, 6),
      priority: 'haute',
      status: 'doing',
      fields: { module: 'Module 2', nature: 'Rendu' },
      steps: [['Relire le cours', true], ['Rédiger le devoir'], ['Déposer sur la plateforme']],
    },
    {
      key: 'ex-exam',
      domain: 'formation',
      sub: 'formation1',
      title: 'Examen final',
      due: addDays(t, 21),
      fields: { module: 'Tous les modules', nature: 'Examen' },
    },
    {
      key: 'ex-papa',
      domain: 'famille',
      sub: 'papa',
      title: "Prendre rendez-vous en mairie pour sa carte d'identité",
      due: addDays(t, 10),
      fields: { organisme: 'Mairie' },
    },
    {
      key: 'ex-carte',
      domain: 'securite',
      sub: 'administratif',
      title: 'Renouveler la carte professionnelle',
      due: addDays(t, -2),
      priority: 'haute',
    },
  ]
}

const LINKS: [label: string, url: string, domain: string | null, sub: string | null][] = [
  ['Gmail', 'https://mail.google.com/', null, null],
  ['Outlook / Hotmail', 'https://outlook.live.com/mail/0/', null, null],
  ['Google Drive', 'https://drive.google.com/', null, null],
  ['Coffre Bitwarden', 'https://vault.bitwarden.com/', null, null],
  ['Doctolib', 'https://www.doctolib.fr/', 'sante', 'rdv'],
  ['Ameli', 'https://www.ameli.fr/', 'sante', 'rdv'],
]

const norm = (value: string): string =>
  value.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '')

/**
 * Ajoute ce qui manque sans jamais toucher à ce qui existe.
 *
 * Appelée à la première ouverture, et à la demande par le bouton « Ajouter les
 * exemples ». Un domaine déjà présent sous le même nom est réutilisé plutôt
 * que dupliqué.
 */
export function mergeSeed(state: State, makeId: () => string, withExamples: boolean): number {
  const domainsByKey: Record<string, Domain> = {}
  const subsByKey: Record<string, SubDomain> = {}
  let added = 0

  for (const [key, name, hue, goal] of DOMAINS) {
    let domain = state.domains.find((d) => norm(d.name) === norm(name))

    if (!domain) {
      domain = {
        id: makeId(),
        name,
        hue,
        goal,
        notes: '',
        order: state.domains.reduce((max, d) => Math.max(max, d.order || 0), 0) + 1,
      }
      state.domains.push(domain)
      added++
    }

    domainsByKey[key] = domain
  }

  for (const [key, domainKey, parentKey, name, tpl] of SUBS) {
    const domain = domainsByKey[domainKey]
    const parentId = parentKey ? subsByKey[parentKey].id : ''
    let sub = state.subs.find(
      (s) => s.domainId === domain.id && (s.parentId || '') === parentId && norm(s.name) === norm(name),
    )

    if (!sub) {
      sub = {
        id: makeId(),
        domainId: domain.id,
        parentId,
        name,
        tpl,
        notes: '',
        order: state.subs.filter((s) => s.domainId === domain.id && (s.parentId || '') === parentId).length + 1,
      }
      state.subs.push(sub)
      added++
    }

    subsByKey[key] = sub
  }

  for (const spec of itemSpecs()) {
    if (!spec.real && !withExamples) continue
    if (state.items.some((i) => i.seedKey === spec.key || (i.example && i.title === spec.title))) continue

    const item: Item = {
      id: makeId(),
      seedKey: spec.key,
      domainId: domainsByKey[spec.domain].id,
      subId: subsByKey[spec.sub]?.id ?? '',
      title: spec.title,
      status: spec.status ?? 'todo',
      priority: spec.priority ?? 'normale',
      due: spec.due,
      time: spec.time ?? '',
      recur: '',
      progress: 0,
      subtasks: (spec.steps ?? []).map(([t, done]) => ({ id: makeId(), t, done: Boolean(done) })),
      fields: spec.fields ?? {},
      notes: spec.notes ?? '',
      timeSpent: 0,
      example: !spec.real,
      createdAt: new Date().toISOString(),
    }

    state.items.push(item)
    added++
  }

  for (const [label, url, domainKey, subKey] of LINKS) {
    if (state.links.some((l) => l.url === url)) continue

    state.links.push({
      id: makeId(),
      label,
      url,
      login: '',
      domainId: domainKey ? domainsByKey[domainKey].id : '',
      subId: subKey ? subsByKey[subKey].id : '',
      order: state.links.length + 1,
    })
    added++
  }

  if (withExamples && !state.shifts.some((s) => s.example)) {
    const shift: Shift = {
      id: makeId(),
      date: today(),
      start: '19:00',
      end: '07:00',
      place: 'Vacation exemple',
      example: true,
    }
    state.shifts.push(shift)
    added++
  }

  return added
}

export const seedLinks: AccessLink[] = []
