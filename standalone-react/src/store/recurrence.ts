import { addDays, iso, parseDay, today } from './dates'
import type { Item, Recurrence } from './types'

/**
 * Récurrence des éléments.
 *
 * Deux exigences guident le calcul :
 *
 * - **Pas de date passée.** Une tâche oubliée pendant deux ans repart
 *   aujourd'hui. Le rattrapage se calcule d'un coup, jamais pas à pas.
 * - **Pas de dérive.** « Le 31 de chaque mois » ramené au 28 février doit
 *   revenir au 31 en mars. Le quantième de départ est donc gardé sur
 *   l'élément et transmis d'occurrence en occurrence.
 */

const lastDayOfMonth = (year: number, month: number): number =>
  new Date(year, month + 1, 0).getDate()

/** Le quantième voulu dans le mois visé, ramené au dernier jour si le mois est trop court. */
function anchored(year: number, month: number, anchorDay: number): string {
  return iso(new Date(year, month, Math.min(anchorDay, lastDayOfMonth(year, month))))
}

function nextMonthly(from: string, floor: string, anchorDay: number): string {
  const start = parseDay(from)
  let candidate = anchored(start.getFullYear(), start.getMonth() + 1, anchorDay)

  if (candidate < floor) {
    const limit = parseDay(floor)
    const current = parseDay(candidate)
    const months =
      (limit.getFullYear() - current.getFullYear()) * 12 + (limit.getMonth() - current.getMonth())

    candidate = anchored(current.getFullYear(), current.getMonth() + Math.max(0, months), anchorDay)
  }

  // Le mois courant peut rester trop tôt : le 5 alors qu'on est le 20.
  if (candidate < floor) {
    const current = parseDay(candidate)
    candidate = anchored(current.getFullYear(), current.getMonth() + 1, anchorDay)
  }

  return candidate
}

function nextWeekly(from: string, floor: string): string {
  let candidate = addDays(from, 7)

  if (candidate < floor) {
    const missed = Math.round(
      (parseDay(floor).getTime() - parseDay(candidate).getTime()) / 86400000,
    )
    candidate = addDays(candidate, Math.ceil(missed / 7) * 7)
  }

  return candidate
}

/** Avance jusqu'au prochain jour ouvré, en restant sur place si c'en est déjà un. */
function toWeekday(day: string): string {
  let candidate = day

  while ([0, 6].includes(parseDay(candidate).getDay())) {
    candidate = addDays(candidate, 1)
  }

  return candidate
}

/**
 * La première occurrence qui soit à la fois après `from` et aujourd'hui au plus tôt.
 * Rend une chaîne vide quand l'élément ne se répète pas.
 */
export function nextDate(from: string, recurrence: Recurrence, anchorDay?: number | null): string {
  if (!recurrence) return ''

  const floor = today()
  const start = from || floor
  const anchor = anchorDay ?? parseDay(start).getDate()
  const later = (candidate: string): string => (candidate >= floor ? candidate : floor)

  switch (recurrence) {
    case 'daily':
      return later(addDays(start, 1))
    case 'weekly':
      return nextWeekly(start, floor)
    case 'weekdays':
      return toWeekday(later(addDays(start, 1)))
    case 'monthly':
      return nextMonthly(start, floor, anchor)
  }
}

/** Copie l'élément sur sa date suivante, étapes décochées et compteurs remis à zéro. */
export function spawnNext(item: Item, makeId: () => string): Item | null {
  if (!item.recur) return null

  const due = nextDate(item.due || today(), item.recur, item.anchorDay)

  if (!due) return null

  return {
    ...item,
    id: makeId(),
    due,
    status: 'todo',
    progress: 0,
    doneAt: null,
    timeSpent: 0,
    spawned: false,
    example: item.example,
    createdAt: new Date().toISOString(),
    subtasks: item.subtasks.map((step) => ({ id: makeId(), t: step.t, done: false })),
  }
}
