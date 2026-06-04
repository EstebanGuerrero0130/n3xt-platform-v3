import { describe, it, expect } from 'vitest'

describe('costCalculator', () => {
  it('should export required functions', async () => {
    const cc = await import('../services/costCalculator')
    expect(typeof cc.calcProductionCost).toBe('function')
    expect(typeof cc.calcFinalPrice).toBe('function')
    expect(typeof cc.calcExtraCost).toBe('function')
    expect(typeof cc.calcOrderDetailBreakdown).toBe('function')
  })
})
