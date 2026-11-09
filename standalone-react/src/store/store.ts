import { useCallback, useSyncExternalStore } from 'react'
import { mergeSeed } from './seed'
import type { Settings, State } from './types'

/**
 * L'état de GPV, tenu hors de React et diffusé aux composants.
 *
 * Un magasin externe plutôt qu'un contexte : l'écran reste ouvert des heures,
 * et chaque frappe dans un bloc-notes écrit dans l'état. Avec un contexte,
 * tout l'arbre se redessinerait à chaque caractère.
 */

const KEY = 'gpv.standalone.v1'
const SEED_VERSION = 2

export const makeId = (): string =>
  Date.now().toString(36) + Math.random().toString(36).slice(2, 8)

const defaultSettings = (): Settings => ({
  theme: 'auto',
  pomo: { work: 25, short: 5, long: 15, auto: true },
  ambPreset: 'pluie',
  ambVol: 0.6,
  ambMix: null,
  ambMixes: {},
  ambVisual: true,
  ambDuck: true,
  ambIntensity: 0.45,
  wall: false,
  openOver: true,
  lastExport: null,
  view: 'domaines',
  seedV: 0,
})

const emptyState = (): State => ({
  v: 1,
  domains: [],
  subs: [],
  items: [],
  links: [],
  shifts: [],
  inbox: [],
  freeNotes: '',
  focusLog: [],
  settings: defaultSettings(),
})

/**
 * Remet d'aplomb ce qui sort du stockage : un fichier importé, ou des données
 * écrites par une version plus ancienne, peuvent manquer de champs entiers.
 */
export function normalize(raw: unknown): State {
  const input = (raw && typeof raw === 'object' ? raw : {}) as Partial<State>
  const state = { ...emptyState(), ...input }

  for (const key of ['domains', 'subs', 'items', 'links', 'shifts', 'inbox', 'focusLog'] as const) {
    if (!Array.isArray(state[key])) {
      ;(state as Record<string, unknown>)[key] = []
    }
  }

  state.settings = {
    ...defaultSettings(),
    ...(input.settings ?? {}),
    pomo: { ...defaultSettings().pomo, ...(input.settings?.pomo ?? {}) },
    ambMixes: input.settings?.ambMixes ?? {},
  }

  state.freeNotes = typeof state.freeNotes === 'string' ? state.freeNotes : ''

  state.subs = state.subs.map((sub) => ({ ...sub, parentId: sub.parentId || '' }))
  state.items = state.items.map((item) => ({
    ...item,
    subtasks: Array.isArray(item.subtasks) ? item.subtasks : [],
    fields: item.fields && typeof item.fields === 'object' ? item.fields : {},
    status: ['todo', 'doing', 'wait', 'done'].includes(item.status) ? item.status : 'todo',
  }))

  return state
}

let storageWorks = true

function read(): State {
  try {
    const raw = localStorage.getItem(KEY)

    return raw ? normalize(JSON.parse(raw)) : normalize({})
  } catch {
    // Navigation privée, site bloqué, JSON abîmé : on repart propre plutôt
    // que de laisser l'écran vide sur une exception.
    storageWorks = false

    return normalize({})
  }
}

export const storageAvailable = (): boolean => storageWorks

let state: State = read()

if ((state.settings.seedV || 0) < SEED_VERSION) {
  mergeSeed(state, makeId, true)
  state.settings.seedV = SEED_VERSION
}

const listeners = new Set<() => void>()
let writeTimer: number | undefined

function persist(): void {
  try {
    localStorage.setItem(KEY, JSON.stringify(state))
    storageWorks = true
  } catch {
    storageWorks = false
  }
}

/** Écriture différée : taper dans un bloc-notes ne doit pas sérialiser à chaque lettre. */
function schedulePersist(): void {
  window.clearTimeout(writeTimer)
  writeTimer = window.setTimeout(persist, 250)
}

export function flushWrites(): void {
  window.clearTimeout(writeTimer)
  persist()
}

window.addEventListener('beforeunload', flushWrites)

export function getState(): State {
  return state
}

function emit(): void {
  for (const listener of listeners) listener()
}

function subscribe(listener: () => void): () => void {
  listeners.add(listener)

  return () => listeners.delete(listener)
}

/**
 * Modifie l'état. Le brouillon est muté librement puis remplacé d'un bloc :
 * les composants voient toujours un état cohérent, jamais un à moitié écrit.
 */
export function update(mutate: (draft: State) => void): void {
  const draft: State = { ...state }
  mutate(draft)
  state = draft
  schedulePersist()
  emit()
}

/** Remplace tout, pour un import de sauvegarde. */
export function replaceState(next: State): void {
  state = normalize(next)
  flushWrites()
  emit()
}

export function useStore<T>(select: (state: State) => T): T {
  return useSyncExternalStore(
    subscribe,
    useCallback(() => select(state), [select]),
    useCallback(() => select(state), [select]),
  )
}

export function useGpv(): State {
  return useSyncExternalStore(subscribe, getState, getState)
}

export function addSeed(withExamples: boolean): number {
  let added = 0
  update((draft) => {
    draft.domains = [...draft.domains]
    draft.subs = [...draft.subs]
    draft.items = [...draft.items]
    draft.links = [...draft.links]
    draft.shifts = [...draft.shifts]
    added = mergeSeed(draft, makeId, withExamples)
  })

  return added
}

export function clearExamples(): number {
  let removed = 0
  update((draft) => {
    removed = draft.items.filter((i) => i.example).length + draft.shifts.filter((s) => s.example).length
    draft.items = draft.items.filter((i) => !i.example)
    draft.shifts = draft.shifts.filter((s) => !s.example)
  })

  return removed
}

export function setSettings(patch: Partial<Settings>): void {
  update((draft) => {
    draft.settings = { ...draft.settings, ...patch }
  })
}
