import { describe, it, expect } from 'vitest'
import { parsePrice, formatCOP } from '../utils/format'

// Intl usa espacio no separable entre "$" y el número
const norm = (s: string) => s.replace(/\s/g, ' ')

describe('parsePrice', () => {
  it('treats dot/comma groups of 3 as thousands', () => {
    expect(parsePrice('45.000')).toBe(45000)
    expect(parsePrice('$45,000')).toBe(45000)
    expect(parsePrice('1.250.000 COP')).toBe(1250000)
  })

  it('accepts numbers and plain digits', () => {
    expect(parsePrice(45000)).toBe(45000)
    expect(parsePrice('45000')).toBe(45000)
  })

  it('keeps short trailing decimals', () => {
    expect(parsePrice('45000.50')).toBe(45000.5)
  })

  it('returns null for non-numeric values', () => {
    expect(parsePrice('Consultar')).toBeNull()
    expect(parsePrice('')).toBeNull()
    expect(parsePrice(null)).toBeNull()
    expect(parsePrice(undefined)).toBeNull()
    expect(parsePrice(NaN)).toBeNull()
  })
})

describe('formatCOP', () => {
  it('formats as COP without decimals', () => {
    expect(norm(formatCOP('45.000'))).toBe('$ 45.000')
    expect(norm(formatCOP(45000))).toBe('$ 45.000')
  })

  it('falls back to "Consultar" for non-numeric or zero', () => {
    expect(formatCOP('Consultar')).toBe('Consultar')
    expect(formatCOP('')).toBe('Consultar')
    expect(formatCOP(0)).toBe('Consultar')
    expect(formatCOP(undefined, 'A cotizar')).toBe('A cotizar')
  })
})
