import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'

describe('cache store', () => {
  beforeEach(() => {
    localStorage.clear()
  })

  it('should set and get values', async () => {
    const store = (await import('../utils/cache')).default
    store.set('test_key', { hello: 'world' })
    const val = store.get('test_key')
    expect(val).toEqual({ hello: 'world' })
  })

  it('should return null for expired entries', async () => {
    const store = (await import('../utils/cache')).default
    vi.useFakeTimers()
    store.set('expired', 'data', 100) // 100ms TTL
    vi.advanceTimersByTime(200)
    expect(store.get('expired')).toBeNull()
    vi.useRealTimers()
  })

  it('should remove entries', async () => {
    const store = (await import('../utils/cache')).default
    store.set('temp', 'value')
    store.remove('temp')
    expect(store.get('temp')).toBeNull()
  })
})
