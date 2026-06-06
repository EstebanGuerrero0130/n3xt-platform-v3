import { describe, it, expect } from 'vitest'

describe('logger', () => {
  it('should export error and warn functions', async () => {
    const logger = await import('../utils/logger')
    expect(typeof logger.default.error).toBe('function')
    expect(typeof logger.default.warn).toBe('function')
  })
})
