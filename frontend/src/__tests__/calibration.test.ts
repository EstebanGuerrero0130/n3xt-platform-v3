import { describe, it, expect } from 'vitest'
import { deviationText, missingSamples, factorText } from '../utils/calibration'

describe('calibración', () => {
  it('describe cuánto se desvía del cálculo', () => {
    expect(deviationText(1.12)).toBe('Tarda 12 % más de lo calculado')
    expect(deviationText(0.92)).toBe('Tarda 8 % menos de lo calculado')
    expect(deviationText(1.002)).toBe('Tarda lo calculado')
    expect(deviationText(null)).toBe('Sin datos')
  })

  it('cuenta las impresiones que faltan', () => {
    expect(missingSamples(2)).toBe(3)
    expect(missingSamples(7)).toBe(0)
  })

  it('formatea el factor con coma decimal', () => {
    expect(factorText(1.1)).toBe('×1,10')
    expect(factorText(null)).toBe('—')
  })
})
