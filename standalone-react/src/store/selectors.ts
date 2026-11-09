import { daysUntil } from './dates'
import type { Domain, Item, State, SubDomain } from './types'

/** Tout ce qui se déduit de l'état sans le modifier. */

export const byOrder = <T extends { order: number; name: string }>(a: T, b: T): number =>
  (a.order ?? 99) - (b.order ?? 99) || a.name.localeCompare(b.name, 'fr')

export const domains = (s: State): Domain[] => [...s.domains].sort(byOrder)
export const domainById = (s: State, id: string): Domain | undefined => s.domains.find((d) => d.id === id)
export const subById = (s: State, id: string): SubDomain | undefined => s.subs.find((x) => x.id === id)
export const itemById = (s: State, id: string): Item | undefined => s.items.find((i) => i.id === id)

export const hueOf = (s: State, domainId: string): number => domainById(s, domainId)?.hue ?? 220

/** Les sous-domaines de premier niveau d'un domaine. */
export const rootSubs = (s: State, domainId: string): SubDomain[] =>
  s.subs.filter((x) => x.domainId === domainId && (!x.parentId || !subById(s, x.parentId))).sort(byOrder)

export const childSubs = (s: State, subId: string): SubDomain[] =>
  s.subs.filter((x) => x.parentId === subId).sort(byOrder)

/** Un sous-domaine et toute sa descendance : « Parents » rapporte Maman et Papa. */
export function descendantIds(s: State, subId: string): string[] {
  const out = [subId]

  for (let i = 0; i < out.length; i++) {
    for (const child of s.subs) {
      if (child.parentId === out[i] && !out.includes(child.id)) out.push(child.id)
    }
  }

  return out
}

/** Le chemin depuis la racine. La garde protège d'un cycle introduit par un import. */
export function subPath(s: State, sub: SubDomain): SubDomain[] {
  const path: SubDomain[] = []
  let current: SubDomain | undefined = sub
  let guard = 0

  while (current && guard++ < 32) {
    path.unshift(current)
    current = current.parentId ? subById(s, current.parentId) : undefined
  }

  return path
}

export const itemsOfDomain = (s: State, domainId: string): Item[] =>
  s.items.filter((i) => i.domainId === domainId)

export function itemsOfSub(s: State, subId: string): Item[] {
  const ids = descendantIds(s, subId)

  return s.items.filter((i) => ids.includes(i.subId))
}

export const isDone = (item: Item): boolean => item.status === 'done'

export const isLate = (item: Item): boolean =>
  !isDone(item) && Boolean(item.due) && (daysUntil(item.due) ?? 0) < 0

/** Avec des sous-étapes, la progression se calcule seule ; sans elles, le curseur décide. */
export function progressOf(item: Item): number {
  if (!item.subtasks.length) return isDone(item) ? 100 : item.progress || 0

  return Math.round((100 * item.subtasks.filter((x) => x.done).length) / item.subtasks.length)
}

export function averageProgress(items: Item[]): number | null {
  if (!items.length) return null

  return Math.round(items.reduce((sum, i) => sum + progressOf(i), 0) / items.length)
}

const priorityRank: Record<string, number> = { haute: 0, normale: 1, basse: 2 }

/** Terminé en bas, puis par échéance, puis par priorité, puis par heure. */
export function sortItems(a: Item, b: Item): number {
  if (isDone(a) !== isDone(b)) return isDone(a) ? 1 : -1

  const da = a.due ? (daysUntil(a.due) ?? 999) : 999
  const db = b.due ? (daysUntil(b.due) ?? 999) : 999
  if (da !== db) return da - db

  const pa = priorityRank[a.priority] ?? 1
  const pb = priorityRank[b.priority] ?? 1
  if (pa !== pb) return pa - pb

  return (a.time || '99').localeCompare(b.time || '99')
}

/** « Famille › Parents › Maman ». */
export function whereLabel(s: State, item: { domainId: string; subId: string }): string {
  const domain = domainById(s, item.domainId)

  if (!domain) return 'Sans domaine'

  const sub = item.subId ? subById(s, item.subId) : undefined

  return [domain.name, ...(sub ? subPath(s, sub).map((x) => x.name) : [])].join(' › ')
}

/** La valeur du sélecteur « où ranger » : « d:<id> » ou « s:<id> ». */
export const whereValue = (item: { domainId: string; subId: string }): string =>
  item.subId ? `s:${item.subId}` : item.domainId ? `d:${item.domainId}` : ''

export function parseWhere(s: State, value: string): { domainId: string; subId: string } {
  if (!value) return { domainId: '', subId: '' }

  const [kind, id] = value.split(':')

  if (kind === 's') {
    const sub = subById(s, id)

    return { domainId: sub?.domainId ?? '', subId: sub ? id : '' }
  }

  return { domainId: id, subId: '' }
}

export const openItems = (items: Item[]): Item[] => items.filter((i) => !isDone(i))

/** Ce qui réclame l'attention : prioritaire, ou dû sous trois jours. */
export function urgentItems(s: State): Item[] {
  return s.items
    .filter((i) => !isDone(i) && ((i.due && (daysUntil(i.due) ?? 99) <= 3) || i.priority === 'haute'))
    .sort(sortItems)
    .slice(0, 12)
}

export function globalStats(s: State): {
  open: number
  late: number
  soon: number
  done: number
  progress: number
} {
  const open = s.items.filter((i) => !isDone(i))
  const soon = open.filter((i) => {
    const d = i.due ? daysUntil(i.due) : null

    return d !== null && d >= 0 && d <= 3
  })

  return {
    open: open.length,
    late: open.filter(isLate).length,
    soon: soon.length,
    done: s.items.length - open.length,
    progress: averageProgress(s.items) ?? 0,
  }
}

export const focusMinutesOn = (s: State, day: string): number =>
  s.focusLog.filter((f) => f.d === day).reduce((sum, f) => sum + f.min, 0)

/** Seules http et https sont acceptées : un lien « javascript: » s'exécuterait au clic. */
export function safeUrl(url: string): string {
  const value = String(url ?? '').trim()

  return /^https?:\/\/[^\s]+$/i.test(value) ? value : ''
}
