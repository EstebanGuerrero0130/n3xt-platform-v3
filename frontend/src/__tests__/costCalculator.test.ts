import { describe, it, expect } from 'vitest'
import { calcProductionCost, calcFinalPrice, calcExtraCost, calcOrderDetailBreakdown } from '../services/costCalculator'

describe('calcProductionCost', () => {
  const baseInfra = { luz_hr: 926, depr_hr: 400, mant_hr: 700, etiquetas: 500, load_factor: 0.4 }
  const basePrep = { mano_obra_hr: 1000, prep_time_pct: 10 }

  it('returns zero for empty inputs', () => {
    const r = calcProductionCost({})
    expect(r.material).toBe(0)
    expect(r.luz).toBe(0)
    expect(r.labor).toBe(0)
    expect(r.depr).toBe(0)
    expect(r.mant).toBe(0)
    expect(r.etiquetas).toBe(0)
    expect(r.extras).toBe(0)
    expect(r.total).toBe(0)
  })

  it('calculates material cost correctly', () => {
    const r = calcProductionCost({ weightG: 500, costPerKg: 80000 })
    expect(r.material).toBe(40000) // 500g / 1000 * 80000
  })

  it('calculates infrastructure costs with load factor', () => {
    const r = calcProductionCost({
      totalHours: 5,
      infra: { luz_hr: 926, depr_hr: 400, mant_hr: 700, etiquetas: 500, load_factor: 0.4 },
      prep: { mano_obra_hr: 1000, prep_time_pct: 10 },
    })
    // luz = 5 * 0.4 * 926 = 1852
    expect(r.luz).toBe(1852)
    // labor = (5 * 0.1) * 1000 = 500
    expect(r.labor).toBe(500)
    // depr = 5 * 400 = 2000
    expect(r.depr).toBe(2000)
    // mant = 5 * 700 = 3500
    expect(r.mant).toBe(3500)
    // etiquetas = 500 (fixed per order)
    expect(r.etiquetas).toBe(500)
  })

  it('includes extras cost in total', () => {
    const r = calcProductionCost({ extrasCost: 15000, weightG: 500, costPerKg: 80000 })
    expect(r.extras).toBe(15000)
    expect(r.total).toBe(40000 + 15000)
  })

  it('uses default load_factor of 0.4 when not provided', () => {
    const r = calcProductionCost({
      totalHours: 5,
      infra: { luz_hr: 926 },
      prep: { mano_obra_hr: 1000 },
    })
    expect(r.luz).toBe(5 * 0.4 * 926)
  })

  it('uses default prep_time_pct of 10% when not provided', () => {
    const r = calcProductionCost({
      totalHours: 5,
      prep: { mano_obra_hr: 1000 },
    })
    expect(r.labor).toBe(5 * 0.1 * 1000)
  })

  it('returns percentage breakdown that sums to ~100%', () => {
    const r = calcProductionCost({
      weightG: 250, costPerKg: 80000, totalHours: 3,
      infra: baseInfra, prep: basePrep,
    })
    const totalPct = Object.values(r.pcts).reduce((s, v) => s + v, 0)
    expect(totalPct).toBeCloseTo(100, 0)
  })
})

describe('calcFinalPrice', () => {
  const baseOper = { transporte: 5, marketing: 10, fallos: 5, ganancia: 50 }
  const baseMargin = { iva: 19 }

  it('calculates price with margins and IVA', () => {
    const r = calcFinalPrice({
      productionCost: 100000,
      oper: baseOper,
      margin: baseMargin,
    })
    // logistics = 100000 * 0.05 = 5000
    expect(r.logistics).toBe(5000)
    // marketing = 100000 * 0.10 = 10000
    expect(r.marketing).toBe(10000)
    // failures = 100000 * 0.05 = 5000
    expect(r.failures).toBe(5000)
    // profit = 100000 * 0.50 = 50000
    expect(r.profit).toBe(50000)
    // subtotal = 100000 + 5000 + 10000 + 5000 + 50000 = 170000
    expect(r.subtotal).toBe(170000)
    // iva = 170000 * 0.19 = 32300
    expect(r.iva).toBe(32300)
    // total = 170000 + 32300 = 202300
    expect(r.total).toBe(202300)
  })

  it('applies discount correctly', () => {
    const r = calcFinalPrice({
      productionCost: 100000,
      oper: { ganancia: 50 },
      margin: { iva: 19 },
      overrides: { discountPct: 10 },
    })
    // subtotal sin desc = 100000 + 50000 = 150000
    // desc = 150000 * 0.1 = 15000
    // subtotal = 150000 - 15000 = 135000
    expect(r.discount).toBe(15000)
    expect(r.subtotal).toBe(135000)
  })

  it('uses overrides over settings values', () => {
    const r = calcFinalPrice({
      productionCost: 100000,
      oper: { transporte: 5 },
      margin: { iva: 19 },
      overrides: { transportePct: 10 },
    })
    expect(r.logistics).toBe(10000) // 10% instead of 5%
  })

  it('returns zero for zero production cost', () => {
    const r = calcFinalPrice({ productionCost: 0 })
    expect(r.total).toBe(0)
  })

  it('calculates profit margin percentage correctly', () => {
    const r = calcFinalPrice({
      productionCost: 100000,
      oper: { ganancia: 50 },
      margin: { iva: 0 },
    })
    // subtotal = 100000 + 50000 = 150000
    // margin = (150000 - 100000) / 150000 * 100 = 33.33%
    expect(r.profitMarginPct).toBeCloseTo(33.3, 0)
  })
})

describe('calcExtraCost', () => {
  it('calculates by weight/volume for g/ml units', () => {
    expect(calcExtraCost(80000, 'g', 100)).toBe(8000) // 80000 * 100/1000
    expect(calcExtraCost(80000, 'ml', 50)).toBe(4000)  // 80000 * 50/1000
  })

  it('calculates by unit for non-weight units', () => {
    expect(calcExtraCost(5000, 'unid', 3)).toBe(15000)
    expect(calcExtraCost(20000, 'servicio', 1)).toBe(20000)
  })
})

describe('calcOrderDetailBreakdown', () => {
  it('calculates breakdown from order and settings', () => {
    const order = {
      estimated_duration_h: '4',
      estimated_weight_g: '300',
      extras_cost: '10000',
      total_price: '250000',
    }
    const settings = {
      infra: { luz_hr: 926, depr_hr: 400, mant_hr: 700, etiquetas: 500, load_factor: 0.4 },
      prep: { mano_obra_hr: 1000, prep_time_pct: 10 },
    }

    const r = calcOrderDetailBreakdown(order, settings, 80000)
    // material = 300/1000 * 80000 = 24000
    expect(r.material).toBe(24000)
    // luz = 4 * 0.4 * 926 = 1481.6 → 1482
    expect(r.luz).toBe(1482)
    // labor = (4 * 0.1) * 1000 = 400
    expect(r.labor).toBe(400)
    // depr = 4 * 400 = 1600
    expect(r.depr).toBe(1600)
    // mant = 4 * 700 = 2800
    expect(r.mant).toBe(2800)
    // etiquetas = 500
    expect(r.etiquetas).toBe(500)
    // extras = 10000
    expect(r.extras).toBe(10000)
    // total_cost = 24000 + 1482 + 400 + 1600 + 2800 + 500 + 10000 = 40782
    expect(r.total_cost).toBe(40782)
    // margin = 250000 - 40782 = 209218
    expect(r.margin).toBe(209218)
  })

  it('handles missing settings gracefully', () => {
    const r = calcOrderDetailBreakdown({ total_price: '0' }, {}, 0)
    expect(r.total_cost).toBe(0)
    expect(r.margin).toBe(0)
  })
})
