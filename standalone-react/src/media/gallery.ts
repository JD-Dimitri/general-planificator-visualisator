import biblio from './biblio.jpg'
import blanc from './blanc.jpg'
import brun from './brun.jpg'
import bureau from './bureau.jpg'
import cafe from './cafe.jpg'
import cheminee from './cheminee.jpg'
import mer from './mer.jpg'
import nature from './nature.jpg'
import pluie from './pluie.jpg'
import rose from './rose.jpg'

/**
 * La galerie des ambiances.
 *
 * Chaque ambiance est livrée avec une image, et accepte autant d'images et de
 * vidéos que tu veux y ajouter. Elles vivent dans IndexedDB et non dans le
 * fichier : une vidéo pèse des dizaines de mégaoctets, bien au-delà de ce que
 * le stockage de clés-valeurs accepte, et bien au-delà de ce qu'on veut
 * embarquer dans un HTML qu'on s'envoie par mail.
 */

export type MediaKind = 'image' | 'video'

export interface MediaItem {
  id: string
  ambience: string
  kind: MediaKind
  name: string
  /** Vrai pour l'image livrée avec GPV : elle ne se supprime pas. */
  builtin: boolean
  url: string
  addedAt?: string
}

export const BUILTIN: Record<string, string> = {
  pluie,
  mer,
  nature,
  cheminee,
  biblio,
  bureau,
  cafe,
  blanc,
  rose,
  brun,
}

const DB_NAME = 'gpv-media'
const STORE = 'media'
/** Au-delà, le navigateur refusera souvent d'enregistrer, et l'attente devient pénible. */
export const MAX_BYTES = 150 * 1024 * 1024

let db: IDBDatabase | null = null
let opening: Promise<IDBDatabase | null> | null = null

function open(): Promise<IDBDatabase | null> {
  if (db) return Promise.resolve(db)
  if (opening) return opening

  opening = new Promise((resolve) => {
    try {
      const request = indexedDB.open(DB_NAME, 2)

      request.onupgradeneeded = () => {
        const base = request.result

        if (!base.objectStoreNames.contains(STORE)) {
          const store = base.createObjectStore(STORE, { keyPath: 'id' })
          store.createIndex('ambience', 'ambience')
        }
      }

      request.onsuccess = () => {
        db = request.result
        resolve(db)
      }
      // Navigation privée, stockage bloqué : la galerie se réduit aux images
      // livrées, et le reste de GPV continue de fonctionner.
      request.onerror = () => resolve(null)
    } catch {
      resolve(null)
    }
  })

  return opening
}

interface StoredMedia {
  id: string
  ambience: string
  kind: MediaKind
  name: string
  blob: Blob
  addedAt: string
}

const urls = new Map<string, string>()

function toItem(record: StoredMedia): MediaItem {
  let url = urls.get(record.id)

  if (!url) {
    url = URL.createObjectURL(record.blob)
    urls.set(record.id, url)
  }

  return {
    id: record.id,
    ambience: record.ambience,
    kind: record.kind,
    name: record.name,
    builtin: false,
    url,
    addedAt: record.addedAt,
  }
}

export async function listStored(): Promise<MediaItem[]> {
  const base = await open()

  if (!base) return []

  return new Promise((resolve) => {
    try {
      const request = base.transaction(STORE).objectStore(STORE).getAll()
      request.onsuccess = () => resolve((request.result as StoredMedia[]).map(toItem))
      request.onerror = () => resolve([])
    } catch {
      resolve([])
    }
  })
}

export async function addMedia(ambience: string, file: File): Promise<MediaItem> {
  const base = await open()

  if (!base) throw new Error('indisponible')

  if (!/^(image|video)\//.test(file.type)) throw new Error('format')
  if (file.size > MAX_BYTES) throw new Error('taille')

  const record: StoredMedia = {
    id: `${Date.now().toString(36)}${Math.random().toString(36).slice(2, 8)}`,
    ambience,
    kind: file.type.startsWith('video') ? 'video' : 'image',
    name: file.name,
    blob: file,
    addedAt: new Date().toISOString(),
  }

  await new Promise<void>((resolve, reject) => {
    const tx = base.transaction(STORE, 'readwrite')
    tx.objectStore(STORE).put(record)
    tx.oncomplete = () => resolve()
    // Quota dépassé : le navigateur refuse, et on le dit plutôt que de
    // laisser croire que le fichier est enregistré.
    tx.onerror = tx.onabort = () => reject(new Error('quota'))
  })

  return toItem(record)
}

export async function removeMedia(id: string): Promise<void> {
  const url = urls.get(id)

  if (url) {
    URL.revokeObjectURL(url)
    urls.delete(id)
  }

  const base = await open()

  if (!base) return

  await new Promise<void>((resolve) => {
    try {
      const tx = base.transaction(STORE, 'readwrite')
      tx.objectStore(STORE).delete(id)
      tx.oncomplete = tx.onerror = () => resolve()
    } catch {
      resolve()
    }
  })
}

/** Place occupée par la galerie, pour l'afficher avant que le navigateur ne refuse. */
export async function usage(): Promise<{ used: number; quota: number } | null> {
  try {
    const estimate = await navigator.storage?.estimate?.()

    return estimate ? { used: estimate.usage ?? 0, quota: estimate.quota ?? 0 } : null
  } catch {
    return null
  }
}

export function formatBytes(bytes: number): string {
  if (bytes < 1024) return `${bytes} o`
  if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} ko`

  return `${(bytes / 1024 / 1024).toFixed(bytes < 10 * 1024 * 1024 ? 1 : 0)} Mo`
}
