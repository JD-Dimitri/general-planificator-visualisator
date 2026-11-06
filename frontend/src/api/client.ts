/**
 * Client HTTP de GPV.
 *
 * Deux modes d'authentification, comme côté Symfony :
 * - le web envoie le cookie de session (`credentials: 'include'`) ;
 * - une application mobile ou de bureau pose un jeton avec `setAccessToken`,
 *   envoyé en en-tête Authorization.
 *
 * Le jeton vit en mémoire, jamais dans localStorage : un script injecté dans
 * la page pourrait l'y lire, alors qu'il ne peut pas lire un cookie HttpOnly.
 */

const BASE = import.meta.env.VITE_API_BASE ?? '/api'

let accessToken: string | null = null

export function setAccessToken(token: string | null): void {
  accessToken = token
}

/** Erreur d'API avec le code et les messages par champ renvoyés par Symfony. */
export class ApiError extends Error {
  readonly status: number
  readonly code: string
  readonly fields: Record<string, string[]>

  constructor(status: number, code: string, message: string, fields: Record<string, string[]> = {}) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.code = code
    this.fields = fields
  }

  get isUnauthenticated(): boolean {
    return this.status === 401
  }
}

interface ErrorBody {
  error?: string
  message?: string
  fields?: Record<string, string[]>
}

async function request<T>(method: string, path: string, body?: unknown): Promise<T> {
  const headers: Record<string, string> = { Accept: 'application/json' }

  if (body !== undefined) {
    headers['Content-Type'] = 'application/json'
  }
  if (accessToken) {
    headers.Authorization = `Bearer ${accessToken}`
  }

  let response: Response
  try {
    response = await fetch(`${BASE}${path}`, {
      method,
      headers,
      credentials: 'include',
      body: body === undefined ? undefined : JSON.stringify(body),
    })
  } catch {
    throw new ApiError(0, 'network', "Le serveur ne répond pas. Vérifie ta connexion.")
  }

  if (response.status === 204) {
    return undefined as T
  }

  const text = await response.text()
  let payload: unknown = null

  if (text) {
    try {
      payload = JSON.parse(text)
    } catch {
      payload = null
    }
  }

  if (!response.ok) {
    const error = (payload ?? {}) as ErrorBody
    throw new ApiError(
      response.status,
      error.error ?? 'error',
      error.message ?? `Erreur ${response.status}.`,
      error.fields ?? {},
    )
  }

  return payload as T
}

export const api = {
  get: <T>(path: string) => request<T>('GET', path),
  post: <T>(path: string, body?: unknown) => request<T>('POST', path, body ?? {}),
  patch: <T>(path: string, body: unknown) => request<T>('PATCH', path, body),
  delete: <T>(path: string) => request<T>('DELETE', path),
}
