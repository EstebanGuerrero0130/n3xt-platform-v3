/**
 * N3XT Cache Utility — localStorage-based cache with TTL.
 *
 * Features:
 * - TTL-based expiration (default 5 minutes)
 * - Automatic stale data cleanup
 * - Graceful fallback if localStorage is full/unavailable
 * - Type-safe get/set with JSON parsing
 */

const DEFAULT_TTL_MS: number = 5 * 60 * 1000 // 5 minutes

interface CacheEntry<T = any> {
  data: T
  expiresAt: number
}

interface CacheMeta {
  expiresAt: number | null
  remainingMs: number | null
}

interface CacheStore {
  set<T>(key: string, value: T, ttlMs?: number): void
  get<T = any>(key: string): T | null
  remove(key: string): void
  clear(): void
  meta(key: string): CacheMeta | null
}

const store: CacheStore = {
  /**
   * Set a value in cache with optional TTL.
   * @param {string} key - Cache key (will be prefixed with 'n3xt_')
   * @param {*} value - Any JSON-serializable value
   * @param {number} [ttlMs] - Time-to-live in milliseconds
   */
  set<T>(key: string, value: T, ttlMs: number = DEFAULT_TTL_MS): void {
    try {
      const entry: CacheEntry<T> = {
        data: value,
        expiresAt: Date.now() + ttlMs,
      }
      localStorage.setItem('n3xt_' + key, JSON.stringify(entry))
    } catch (_e: unknown) {
      // localStorage full or unavailable — silently fail
    }
  },

  /**
   * Get a value from cache. Returns null if expired or missing.
   * @param {string} key - Cache key (without prefix)
   * @returns {*|null}
   */
  get<T = any>(key: string): T | null {
    try {
      const raw: string | null = localStorage.getItem('n3xt_' + key)
      if (!raw) return null

      const entry: CacheEntry<T> = JSON.parse(raw)
      if (Date.now() > entry.expiresAt) {
        localStorage.removeItem('n3xt_' + key)
        return null
      }
      return entry.data
    } catch (_e: unknown) {
      return null
    }
  },

  /**
   * Remove a single cache entry.
   */
  remove(key: string): void {
    try { localStorage.removeItem('n3xt_' + key) } catch (_e: unknown) {/* Silently ignore */}
  },

  /**
   * Clear all N3XT cache entries.
   */
  clear(): void {
    try {
      const keys: string[] = Object.keys(localStorage)
      keys.filter(k => k.startsWith('n3xt_')).forEach(k => localStorage.removeItem(k))
    } catch (_e: unknown) {/* Silently ignore */}
  },

  /**
   * Get cache entry metadata without returning the data.
   * @returns {{ expiresAt: number|null, remainingMs: number|null }|null}
   */
  meta(key: string): CacheMeta | null {
    try {
      const raw: string | null = localStorage.getItem('n3xt_' + key)
      if (!raw) return null
      const entry: CacheEntry = JSON.parse(raw)
      return {
        expiresAt: entry.expiresAt,
        remainingMs: Math.max(0, entry.expiresAt - Date.now()),
      }
    } catch (_e: unknown) { return null }
  }
}

export default store
