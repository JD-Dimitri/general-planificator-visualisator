import { createContext, use, useCallback, useEffect, useMemo, useState } from 'react'
import type { ReactNode } from 'react'
import { ApiError, api, setAccessToken } from '../api/client'
import type { Challenge, ChallengeResponse, User } from '../api/types'

interface RegisterPayload {
  email: string
  password: string
  displayName: string
  phone?: string
  channel?: 'email' | 'sms'
}

interface AuthState {
  user: User | null
  loading: boolean
  /** Étape 1 : vérifie le mot de passe et déclenche l'envoi du code. */
  login: (email: string, password: string, channel?: 'email' | 'sms') => Promise<Challenge>
  register: (payload: RegisterPayload) => Promise<Challenge>
  /** Étape 2 : le code. C'est elle qui ouvre vraiment la session. */
  submitCode: (purpose: 'login' | 'register', challengeId: string, code: string) => Promise<void>
  resendCode: (challengeId: string, channel?: 'email' | 'sms') => Promise<Challenge>
  forgotPassword: (email: string) => Promise<{ message: string; devToken?: string }>
  resetPassword: (token: string, password: string) => Promise<void>
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

  const login = useCallback(async (email: string, password: string, channel: 'email' | 'sms' = 'email') => {
    const response = await api.post<ChallengeResponse>('/auth/login', { email, password, channel })

    return response.challenge
  }, [])

  const register = useCallback(async (payload: RegisterPayload) => {
    const response = await api.post<ChallengeResponse>('/auth/register', payload)

    return response.challenge
  }, [])

  const submitCode = useCallback(
    async (purpose: 'login' | 'register', challengeId: string, code: string) => {
      const path = purpose === 'login' ? '/auth/login/verify' : '/auth/verify'
      setUser(await api.post<User>(path, { challengeId, code }))
    },
    [],
  )

  const resendCode = useCallback(async (challengeId: string, channel?: 'email' | 'sms') => {
    const response = await api.post<ChallengeResponse>('/auth/code/resend', { challengeId, channel })

    return response.challenge
  }, [])

  const forgotPassword = useCallback(async (email: string) => {
    return api.post<{ message: string; devToken?: string }>('/auth/password/forgot', { email })
  }, [])

  const resetPassword = useCallback(async (token: string, password: string) => {
    // L'API ouvre la session dans la foulée : avoir reçu le lien dans sa boîte
    // mail prouve déjà l'accès au second facteur.
    setUser(await api.post<User>('/auth/password/reset', { token, password }))
  }, [])

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
    () => ({ user, loading, login, register, submitCode, resendCode, forgotPassword, resetPassword, logout }),
    [user, loading, login, register, submitCode, resendCode, forgotPassword, resetPassword, logout],
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
