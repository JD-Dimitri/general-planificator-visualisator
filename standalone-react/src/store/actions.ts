import { today } from './dates'
import { spawnNext } from './recurrence'
import { descendantIds, parseWhere, subById } from './selectors'
import { getState, makeId, update } from './store'
import type { AccessLink, Domain, InboxEntry, Item, Shift, Status, SubDomain } from './types'

/** Toutes les écritures passent par ici : un seul endroit à relire pour savoir ce qui change. */

export function saveItem(draft: Item, where: string): Item {
  const place = parseWhere(getState(), where)
  const item: Item = { ...draft, domainId: place.domainId, subId: place.subId, example: false }

  // Le quantième de référence se fixe à la première occurrence mensuelle,
  // et s'efface dès que l'élément cesse de se répéter ainsi.
  item.anchorDay =
    item.recur === 'monthly' ? (item.anchorDay ?? (item.due ? Number(item.due.slice(8)) : null)) : null

  update((state) => {
    const existing = state.items.findIndex((i) => i.id === item.id)
    state.items = existing >= 0 ? state.items.map((i) => (i.id === item.id ? item : i)) : [...state.items, item]
  })

  return item
}

export function newItem(where = '', patch: Partial<Item> = {}): Item {
  const place = parseWhere(getState(), where)

  return {
    id: makeId(),
    domainId: place.domainId,
    subId: place.subId,
    title: '',
    status: 'todo',
    priority: 'normale',
    due: '',
    time: '',
    recur: '',
    progress: 0,
    subtasks: [],
    fields: {},
    notes: '',
    timeSpent: 0,
    example: false,
    createdAt: new Date().toISOString(),
    ...patch,
  }
}

/**
 * Change le statut d'un élément. Terminer une tâche récurrente engendre son
 * occurrence suivante — une seule fois, même si on la rouvre puis la retermine.
 *
 * @returns la date de la prochaine occurrence si elle vient d'être créée
 */
export function setStatus(id: string, status: Status): string | null {
  let spawnedDue: string | null = null

  update((state) => {
    const item = state.items.find((i) => i.id === id)
    if (!item) return

    const wasDone = item.status === 'done'
    const next: Item = {
      ...item,
      status,
      doneAt: status === 'done' ? new Date().toISOString() : null,
    }

    state.items = state.items.map((i) => (i.id === id ? next : i))

    if (!wasDone && status === 'done' && next.recur && !next.spawned) {
      const copy = spawnNext(next, makeId)

      if (copy) {
        next.spawned = true
        spawnedDue = copy.due
        state.items = [...state.items, copy]
      }
    }
  })

  return spawnedDue
}

export const toggleDone = (item: Item): string | null =>
  setStatus(item.id, item.status === 'done' ? 'todo' : 'done')

export function toggleSubtask(itemId: string, subtaskId: string): void {
  update((state) => {
    state.items = state.items.map((item) =>
      item.id === itemId
        ? {
            ...item,
            subtasks: item.subtasks.map((s) => (s.id === subtaskId ? { ...s, done: !s.done } : s)),
          }
        : item,
    )
  })
}

export function deleteItem(id: string): void {
  update((state) => {
    state.items = state.items.filter((i) => i.id !== id)
    state.focusLog = state.focusLog
  })
}

export function addFocusMinutes(itemId: string | null, minutes: number, domainId: string): void {
  if (minutes < 1) return

  update((state) => {
    state.focusLog = [...state.focusLog, { d: today(), domainId, min: Math.round(minutes) }].slice(-3000)

    if (itemId) {
      state.items = state.items.map((i) =>
        i.id === itemId ? { ...i, timeSpent: (i.timeSpent || 0) + Math.round(minutes) } : i,
      )
    }
  })
}

// ---------- domaines ----------

export function saveDomain(domain: Domain): void {
  update((state) => {
    const exists = state.domains.some((d) => d.id === domain.id)
    state.domains = exists ? state.domains.map((d) => (d.id === domain.id ? domain : d)) : [...state.domains, domain]
  })
}

export const newDomain = (): Domain => ({
  id: makeId(),
  name: '',
  hue: Math.floor(Math.random() * 360),
  goal: '',
  notes: '',
  order: getState().domains.length + 1,
})

/**
 * Supprime un domaine. Sans `moveTo`, tout ce qu'il contient part avec lui ;
 * avec, éléments et accès rejoignent le domaine indiqué plutôt que d'être perdus.
 */
export function deleteDomain(id: string, moveTo: string): void {
  update((state) => {
    const subIds = state.subs.filter((s) => s.domainId === id).map((s) => s.id)

    if (moveTo) {
      state.items = state.items.map((i) => (i.domainId === id ? { ...i, domainId: moveTo, subId: '' } : i))
      state.links = state.links.map((l) => (l.domainId === id ? { ...l, domainId: moveTo, subId: '' } : l))
    } else {
      state.items = state.items.filter((i) => i.domainId !== id)
      state.links = state.links.filter((l) => l.domainId !== id)
    }

    state.subs = state.subs.filter((s) => !subIds.includes(s.id))
    state.domains = state.domains.filter((d) => d.id !== id)
  })
}

export function setDomainNotes(id: string, notes: string): void {
  update((state) => {
    state.domains = state.domains.map((d) => (d.id === id ? { ...d, notes } : d))
  })
}

// ---------- sous-domaines ----------

export const newSubDomain = (domainId: string, parentId = ''): SubDomain => ({
  id: makeId(),
  domainId,
  parentId,
  name: '',
  tpl: 'tache',
  notes: '',
  order: getState().subs.filter((s) => s.domainId === domainId && s.parentId === parentId).length + 1,
})

export function saveSubDomain(sub: SubDomain): void {
  update((state) => {
    const previous = state.subs.find((s) => s.id === sub.id)
    const exists = Boolean(previous)
    state.subs = exists ? state.subs.map((s) => (s.id === sub.id ? sub : s)) : [...state.subs, sub]

    // Déplacé dans un autre domaine, il emmène sa descendance, ses éléments et ses accès.
    if (previous && previous.domainId !== sub.domainId) {
      const branch = descendantIds(state, sub.id)
      state.subs = state.subs.map((s) => (branch.includes(s.id) ? { ...s, domainId: sub.domainId } : s))
      state.items = state.items.map((i) => (branch.includes(i.subId) ? { ...i, domainId: sub.domainId } : i))
      state.links = state.links.map((l) => (branch.includes(l.subId) ? { ...l, domainId: sub.domainId } : l))
    }
  })
}

/** Un sous-domaine ne peut pas être rangé dans lui-même ni dans sa descendance. */
export function wouldCycle(subId: string, parentId: string): boolean {
  return Boolean(parentId) && descendantIds(getState(), subId).includes(parentId)
}

/** À la suppression, le contenu remonte d'un niveau plutôt que de disparaître. */
export function deleteSubDomain(id: string): void {
  update((state) => {
    const up = subById(state, id)?.parentId ?? ''

    state.subs = state.subs.filter((s) => s.id !== id).map((s) => (s.parentId === id ? { ...s, parentId: up } : s))
    state.items = state.items.map((i) => (i.subId === id ? { ...i, subId: up } : i))
    state.links = state.links.map((l) => (l.subId === id ? { ...l, subId: up } : l))
  })
}

export function setSubNotes(id: string, notes: string): void {
  update((state) => {
    state.subs = state.subs.map((s) => (s.id === id ? { ...s, notes } : s))
  })
}

// ---------- accès ----------

export const newLink = (where = ''): AccessLink => {
  const place = parseWhere(getState(), where)

  return { id: makeId(), label: '', url: 'https://', login: '', ...place, order: getState().links.length + 1 }
}

export function saveLink(link: AccessLink, where: string): void {
  const place = parseWhere(getState(), where)

  update((state) => {
    const next = { ...link, ...place }
    const exists = state.links.some((l) => l.id === link.id)
    state.links = exists ? state.links.map((l) => (l.id === link.id ? next : l)) : [...state.links, next]
  })
}

export function deleteLink(id: string): void {
  update((state) => {
    state.links = state.links.filter((l) => l.id !== id)
  })
}

// ---------- vacations ----------

export const newShift = (): Shift => ({
  id: makeId(),
  date: today(),
  start: '19:00',
  end: '07:00',
  place: '',
})

export function saveShift(shift: Shift): void {
  update((state) => {
    const next = { ...shift, example: false }
    const exists = state.shifts.some((s) => s.id === shift.id)
    state.shifts = exists ? state.shifts.map((s) => (s.id === shift.id ? next : s)) : [...state.shifts, next]
  })
}

export function deleteShift(id: string): void {
  update((state) => {
    state.shifts = state.shifts.filter((s) => s.id !== id)
  })
}

// ---------- boîte de dépôt ----------

export function addInboxEntries(entries: InboxEntry[]): void {
  update((state) => {
    state.inbox = [...entries, ...state.inbox]
  })
}

export function updateInboxEntry(id: string, patch: Partial<InboxEntry>): void {
  update((state) => {
    state.inbox = state.inbox.map((e) => (e.id === id ? { ...e, ...patch } : e))
  })
}

export function deleteInboxEntry(id: string): void {
  update((state) => {
    state.inbox = state.inbox.filter((e) => e.id !== id)
  })
}

export function clearInbox(): void {
  update((state) => {
    state.inbox = []
  })
}

/** Range une ligne : elle devient un élément et quitte la boîte. */
export function fileInboxEntry(entry: InboxEntry): void {
  const place = parseWhere(getState(), entry.where)

  update((state) => {
    state.items = [
      ...state.items,
      {
        ...newItem(''),
        id: makeId(),
        domainId: place.domainId,
        subId: place.subId,
        title: entry.text,
        due: entry.due,
        time: entry.time,
        notes: entry.src ? `Importé de ${entry.src}` : '',
      },
    ]
    state.inbox = state.inbox.filter((e) => e.id !== entry.id)
  })
}

export function fileAllClassified(): number {
  const ready = getState().inbox.filter((e) => e.where)

  for (const entry of ready) fileInboxEntry(entry)

  return ready.length
}

// ---------- notes en vrac ----------

export function setFreeNotes(notes: string): void {
  update((state) => {
    state.freeNotes = notes
  })
}
