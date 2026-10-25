import { describe, it, expect } from 'vitest'
import { calcQuoteEstimate, calcProductionCost, calcFinalPrice } from '../services/costCalculator'

const cfg = {
  infra: { luz_hr: 926, depr_hr: 400, mant_hr: 700, etiquetas: 500, load_factor: 0.4 },
  prep: { mano_obra_hr: 1000, prep_time_pct: 10 },
  oper: { transporte: 5, ganancia: 30, marketing: 5, fallos: 10 },
  margin: { iva: 19 },
}

/** Cálculo histórico del cotizador (un modelo × qty) para comparar. */
function legacy(weightG: number, hours: number, qty: number, costPerKg: number, discount = 0) {
  const prod = calcProductionCost({ weightG: weightG * qty, totalHours: hours * qty, costPerKg, infra: cfg.infra, prep: cfg.prep })
  const unit = calcFinalPrice({ productionCost: prod.total / qty, oper: cfg.oper, margin: cfg.margin })
  const subtotal = unit.subtotal * qty
  const finalSubtotal = Math.round(subtotal - subtotal * discount)
  const iva = Math.round(finalSubtotal * 0.19)
  return finalSubtotal + iva
}

describe('calcQuoteEstimate', () => {
  it('con un solo modelo coincide con el cálculo histórico', () => {
    const r = calcQuoteEstimate({ models: [{ id: 1, weightG: 40, hours: 3, qty: 3 }], costPerKg: 80000, ...cfg })
    expect(r.total).toBe(legacy(40, 3, 3, 80000))
    expect(r.pieces).toBe(3)
    expect(r.weight).toBe(120)
    expect(r.perModel).toEqual([{ id: 1, total: r.total }])
  })

  it('aplica el cupón en porcentaje igual que antes', () => {
    const r = calcQuoteEstimate({ models: [{ id: 1, weightG: 40, hours: 3, qty: 1 }], costPerKg: 80000, discountPct: 10, ...cfg })
    expect(r.total).toBe(legacy(40, 3, 1, 80000, 0.1))
    expect(r.discount).toBeGreaterThan(0)
  })

  it('suma varios modelos, cada uno por su cantidad', () => {
    const a = { id: 1, weightG: 40, hours: 3, qty: 2 }
    const b = { id: 2, weightG: 10, hours: 1, qty: 5 }
    const r = calcQuoteEstimate({ models: [a, b], costPerKg: 80000, ...cfg })
    expect(r.pieces).toBe(7)
    expect(r.weight).toBe(40 * 2 + 10 * 5)
    expect(r.duration).toBe(3 * 2 + 1 * 5)
    // Mismo resultado que un pedido equivalente de peso/tiempo agregados
    const prod = calcProductionCost({ weightG: 130, totalHours: 11, costPerKg: 80000, infra: cfg.infra, prep: cfg.prep })
    const unit = calcFinalPrice({ productionCost: prod.total / 7, oper: cfg.oper, margin: cfg.margin })
    const subtotal = Math.round(unit.subtotal * 7)
    expect(r.total).toBe(subtotal + Math.round(subtotal * 0.19))
    // Más modelos → más caro que cualquiera por separado
    const onlyA = calcQuoteEstimate({ models: [a], costPerKg: 80000, ...cfg })
    expect(r.total).toBeGreaterThan(onlyA.total)
  })

  it('el desglose por modelo suma el total', () => {
    const r = calcQuoteEstimate({
      models: [{ id: 1, weightG: 33.3, hours: 2.1, qty: 1 }, { id: 2, weightG: 12.7, hours: 0.9, qty: 3 }, { id: 3, weightG: 5, hours: 0.5, qty: 2 }],
      costPerKg: 95000,
      ...cfg,
    })
    expect(r.perModel.reduce((s, m) => s + m.total, 0)).toBe(r.total)
    expect(r.perModel.every(m => m.total > 0)).toBe(true)
  })

  it('normaliza cantidades no válidas a 1', () => {
    const r = calcQuoteEstimate({ models: [{ id: 1, weightG: 10, hours: 1, qty: 0 }], costPerKg: 80000, ...cfg })
    expect(r.pieces).toBe(1)
  })
})
