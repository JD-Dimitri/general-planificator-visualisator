import { createContext, use, useCallback, useEffect, useMemo, useState } from 'react'
import type { ReactNode } from 'react'
import { ApiError, api, setAccessToken } from '../api/client'
import type { User } from '../api/types'

interface AuthState {
  user: User | null
  loading: boolean
  login: (email: string, password: string) => Promise<void>
  register: (email: string, password: string, displayName: string) => Promise<void>
  logout: () => Promise<void>
}

const AuthContext = createContext<AuthState | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  const [loading, setLoading] = useState(true)

  // Au chargement, on demande qui est connecté. Un 401 est une réponse
  // normale ici — il veut dire « personne » — et non une panne.
  useEffect(() => {
    let cancelled = false

    api
      .get<User>('/auth/me')
      .then((me) => {
        if (!cancelled) setUser(me)
      })
      .catch(() => {
        if (!cancelled) setUser(null)
      })
      .finally(() => {
        if (!cancelled) setLoading(false)
      })

    return () => {
      cancelled = true
    }
  }, [])

  const login = useCallback(async (email: string, password: string) => {
    setUser(await api.post<User>('/auth/login', { email, password }))
  }, [])

  const register = useCallback(
    async (email: string, password: string, displayName: string) => {
      await api.post<User>('/auth/register', { email, password, displayName })
      setUser(await api.post<User>('/auth/login', { email, password }))
    },
    [],
  )

  const logout = useCallback(async () => {
    try {
      await api.post<void>('/auth/logout')
    } catch (error) {
      // Une session déjà expirée côté serveur ne doit pas bloquer la déconnexion ici.
      if (!(error instanceof ApiError)) throw error
    }
    setAccessToken(null)
    setUser(null)
  }, [])

  const value = useMemo(
    () => ({ user, loading, login, register, logout }),
    [user, loading, login, register, logout],
  )

  return <AuthContext value={value}>{children}</AuthContext>
}

export function useAuth(): AuthState {
  const context = use(AuthContext)

  if (!context) {
    throw new Error("useAuth doit être utilisé à l'intérieur d'un AuthProvider.")
  }

  return context
}
