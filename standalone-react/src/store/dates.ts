/**
 * Les dates de GPV sont des chaînes « AAAA-MM-JJ ».
 *
 * Une échéance est un jour du calendrier, pas un instant : la stocker en
 * horodatage la ferait changer de jour d'un fuseau à l'autre, et un rendez-vous
 * du 6 novembre deviendrait le 5 au soir pour quelqu'un à l'ouest.
 */

const pad = (n: number): string => String(n).padStart(2, '0')

export const iso = (date: Date): string =>
  `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`

export const today = (): string => iso(new Date())

/** Minuit local pour une chaîne « AAAA-MM-JJ ». */
export function parseDay(day: string): Date {
  const [y, m, d] = day.split('-').map(Number)

  return new Date(y, (m ?? 1) - 1, d ?? 1)
}

export function addDays(day: string, count: number): string {
  const date = parseDay(day)
  date.setDate(date.getDate() + count)

  return iso(date)
}

/** Combien de jours séparent aujourd'hui de cette échéance. Négatif si elle est passée. */
export function daysUntil(day: string): number | null {
  if (!day) return null

  const now = new Date()
  now.setHours(0, 0, 0, 0)

  return Math.round((parseDay(day).getTime() - now.getTime()) / 86400000)
}

export const shortDate = (day: string): string =>
  parseDay(day).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' })

export const longDate = (day: string): string =>
  parseDay(day).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'short' })

/** « 1 h 35 » plutôt que « 95 min » dès qu'on dépasse l'heure. */
export function formatMinutes(minutes: number): string {
  const m = Math.round(minutes || 0)

  return m >= 60 ? `${Math.floor(m / 60)} h ${pad(m % 60)}` : `${m} min`
}

export function formatClock(seconds: number): string {
  const s = Math.max(0, Math.round(seconds))
  const h = Math.floor(s / 3600)
  const m = Math.floor((s % 3600) / 60)

  return `${h ? `${h}:${pad(m)}` : pad(m)}:${pad(s % 60)}`
}
