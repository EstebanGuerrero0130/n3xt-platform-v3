import logger from '../utils/logger'
import { supabase } from './supabase'

const hostname = window.location.hostname || '127.0.0.1'
const isLocal = hostname === 'localhost' || hostname === '127.0.0.1'
const API_BASE_URL = import.meta.env.VITE_API_URL || (isLocal ? '/api' : '/api')
const STORAGE_BASE_URL = import.meta.env.VITE_STORAGE_URL || (isLocal ? '/storage' : '/storage')
const BASE_URL = API_BASE_URL.replace('/api', '')

/**
 * N3XT API Client — Sanctum SPA mode.
 *
 * Auth is handled via httpOnly session cookies (no localStorage tokens).
 * The SPA fetches a CSRF cookie before every mutating request.
 */

let csrfPromise: Promise<void> | null = null

/** Fetch a fresh CSRF token from Sanctum */
const ensureCsrf = async () => {
  if (csrfPromise) return csrfPromise
  csrfPromise = fetch(`${BASE_URL}/sanctum/csrf-cookie`, {
    credentials: 'include',
  }).then(() => {
    csrfPromise = null
  }).catch(err => {
    csrfPromise = null
    logger.error('CSRF token fetch failed:', err)
  })
  return csrfPromise
}

interface RequestOptions {
  method: string
  credentials: RequestCredentials
  headers: Record<string, string>
  body?: BodyInit | null
}

/** Build base fetch options for Sanctum SPA */
const baseOptions = async (method: string, body?: any, isFormData?: boolean): Promise<RequestOptions> => {
  const opts: RequestOptions = {
    method,
    credentials: 'include', // Send cookies (session + XSRF)
    headers: {
      'Accept': 'application/json',
      'X-N3XT-SECURE-NONCE': btoa(`${Date.now()}_${Math.random()}`),
    },
  }

  const { data } = await supabase.auth.getSession()
  if (data?.session?.access_token) {
    opts.headers['Authorization'] = `Bearer ${data.session.access_token}`
  }

  if (isFormData) {
    opts.body = body as BodyInit
  } else if (body !== undefined) {
    opts.headers['Content-Type'] = 'application/json'
    opts.body = JSON.stringify(body)
  }
  return opts
}

/**
 * Cache en memoria para respuestas GET.
 * Evita re-fetching al navegar entre vistas.
 */
const CACHE_TTL = 5 * 60 * 1000 // 5 minutos
interface CacheEntry {
  data: any
  timestamp: number
}
const apiCache = new Map<string, CacheEntry>()

const getCached = (key: string): any | null => {
  const entry = apiCache.get(key)
  if (!entry) return null
  if (Date.now() - entry.timestamp > CACHE_TTL) {
    apiCache.delete(key)
    return null
  }
  return entry.data
}

const setCached = (key: string, data: any): void => {
  apiCache.set(key, { data, timestamp: Date.now() })
}

/** Limpia endpoints relacionados a datos mutables */
const invalidateDataCache = () => {
  for (const key of apiCache.keys()) {
    // Mantener cache de auth, limpiar datos de configuración
    if (!key.includes('/user') && !key.includes('/auth')) {
      apiCache.delete(key)
    }
  }
}

export const api = {
  baseUrl: API_BASE_URL,
  storageUrl: STORAGE_BASE_URL,

  /**
   * Check if the current session is authenticated.
   * @returns {Promise<{authenticated: boolean, user: object|null, role: string|null}>}
   */
  async checkAuth() {
    try {
      const res = await fetch(`${API_BASE_URL}/auth/status`, {
        credentials: 'include',
        headers: { 'Accept': 'application/json' },
      })
      if (!res.ok) return { authenticated: false, user: null, role: null }
      const data = await res.json()
      return { authenticated: data.authenticated, user: data.user, role: data.role }
    } catch {
      return { authenticated: false, user: null, role: null }
    }
  },

  async get(endpoint: string): Promise<any> {
    // Revisar cache primero
    const cached = getCached(endpoint)
    if (cached !== null) return cached

    const options = await baseOptions('GET')
    const res: Response = await fetch(`${API_BASE_URL}${endpoint}`, options)
    const data = await this.handleResponse(res, endpoint)
    
    // Solo cachear si la respuesta fue exitosa
    if (data !== undefined) {
      setCached(endpoint, data)
    }
    return data
  },

  async post(endpoint: string, body?: any): Promise<any> {
    // Auto-detect FormData — no manual isFormData flag needed
    const isFormData: boolean = body instanceof FormData
    // Mutating requests need CSRF protection
    await ensureCsrf()
    const options = await baseOptions('POST', body, isFormData)
    const res: Response = await fetch(`${API_BASE_URL}${endpoint}`, options)
    const data = await this.handleResponse(res, endpoint)
    // POST muta datos → limpiar cache
    invalidateDataCache()
    return data
  },

  async patch(endpoint: string, body?: any): Promise<any> {
    await ensureCsrf()
    const options = await baseOptions('PATCH', body)
    const res: Response = await fetch(`${API_BASE_URL}${endpoint}`, options)
    const data = await this.handleResponse(res, endpoint)
    invalidateDataCache()
    return data
  },

  async put(endpoint: string, body?: any): Promise<any> {
    await ensureCsrf()
    const options = await baseOptions('PUT', body)
    const res: Response = await fetch(`${API_BASE_URL}${endpoint}`, options)
    const data = await this.handleResponse(res, endpoint)
    invalidateDataCache()
    return data
  },


  async delete(endpoint: string): Promise<any> {
    await ensureCsrf()
    const options = await baseOptions('DELETE')
    const res: Response = await fetch(`${API_BASE_URL}${endpoint}`, options)
    const data = await this.handleResponse(res, endpoint)
    // DELETE muta datos → limpiar cache
    invalidateDataCache()
    return data
  },

  async handleResponse(res: Response, endpoint: string = ''): Promise<any> {
    if (res.status === 204) return { success: true }

    const contentType: string | null = res.headers.get('content-type')
    if (!contentType || !contentType.includes('application/json')) {
      const text: string = await res.text()
      logger.error('Respuesta no es JSON:', text.substring(0, 100))
      throw new Error('El servidor no respondió con JSON válido.')
    }

    let data: any
    try {
      data = await res.json()
    } catch {
      data = { message: 'Error al procesar respuesta del servidor' }
    }

    if (!res.ok) {
      if (res.status === 401) {
        logger.error(`[AUTH] Sesión expirada en ${endpoint}.`)
        throw new Error(data.message || 'Sesión no autenticada. Inicia sesión primero.')
      }
      throw new Error(data.message || `Error del servidor (${res.status})`)
    }
    return data.data !== undefined ? data.data : data
  },

  /** Limpiar toda la cache forzadamente */
  clearCache() {
    apiCache.clear()
  },
}
