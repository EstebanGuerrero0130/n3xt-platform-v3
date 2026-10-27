import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import {
  calcQuoteEstimate, normalizeRules, normalizeCalibration, calibrationFactor, normalizeBeds, materialParams,
  chooseOrientation, piecesPerBed, bedsPerPiece, fdmPrintHours, finiteNum, footprint, placement, type QuoteInput,
} from '../services/costCalculator'

// Mismo archivo que comprueba QuoteCoreParityTest (PHPUnit): paridad navegador ↔ servidor
const fixture = JSON.parse(readFileSync(resolve(__dirname, '../../../backend/tests/Fixtures/quote_core_vectors.json'), 'utf8'))

const helpers: Record<string, (...a: any[]) => unknown> = {
  normalizeRules,
  normalizeCalibration,
  calibrationFactor: (raw: unknown, t: any, id: string) => calibrationFactor(normalizeCalibration(raw), t, id),
  normalizeBeds,
  materialParams,
  chooseOrientation,
  piecesPerBed,
  fdmPrintHours,
  bedsPerPiece,
  finiteNum,
}

describe('paridad con QuotePricingService (quote_core_vectors.json)', () => {
  for (const c of fixture.cases) {
    it(c.name, () => {
      const { perModel, ...result } = calcQuoteEstimate(c.input)
      expect(result).toEqual(c.expected)
      expect(perModel.reduce((s, m) => s + m.total, 0)).toBe(result.total)
    })
  }

  fixture.helpers.forEach((h: any, i: number) => {
    it(`${h.fn} #${i}`, () => {
      expect(helpers[h.fn](...h.args)).toEqual(h.expected)
    })
  })
})

const cfg = {
  costPerKg: 85000,
  infra: { luz_hr: 926, depr_hr: 400, mant_hr: 700, etiquetas: 500, load_factor: 0.4 },
  prep: { mano_obra_hr: 1000, prep_time_pct: 10 },
  oper: { transporte: 5, ganancia: 30, marketing: 5, fallos: 10 },
  margin: { iva: 19 },
}
const bed = { x: 256, y: 256, z: 256 }
const fdm = (qty: number, extra: Partial<QuoteInput> = {}): QuoteInput => ({
  ...cfg, technology: 'FDM', bed, bedHours: 0.29, bedWeightG: 3,
  models: [{ id: 1, weightG: 6.2, hours: 0.31, qty, footprint: { x: 20, y: 20 }, heightMm: 20 }],
  ...extra,
})

describe('camas', () => {
  it('FDM: preparación y purga una vez por cama, no por pieza', () => {
    const one = calcQuoteEstimate(fdm(1))
    const ten = calcQuoteEstimate(fdm(10))
    expect(ten.beds).toBe(1)
    expect(ten.weight).toBeCloseTo(6.2 * 10 + 3, 9)
    expect(ten.duration).toBeCloseTo(0.31 * 10 + 0.29, 9)
    // Sin compartir cama serían 10 preparaciones
    expect(ten.total).toBeLessThan(one.total * 10)
  })

  it('FDM: más piezas que las que caben abren otra cama', () => {
    const per = piecesPerBed({ x: 20, y: 20 }, bed)
    const r = calcQuoteEstimate(fdm(per + 1))
    expect(r.beds).toBe(2)
    expect(r.models[0].piecesPerBed).toBe(per)
  })

  it('SLA: el tiempo es por cama según la pieza más alta', () => {
    const r = calcQuoteEstimate({
      ...cfg, technology: 'SLA', bed: { x: 218, y: 123, z: 250 },
      models: [{ id: 1, weightG: 3, hours: 0, qty: 6, footprint: { x: 20, y: 20 }, heightMm: 50 }],
    })
    expect(r.beds).toBe(1)
    expect(r.duration).toBe(2) // 50 / 25, no × 6
  })

  it('piezas por cama: no solo por área, también en filas', () => {
    expect(piecesPerBed({ x: 130, y: 130 }, bed)).toBe(1)
    expect(calcQuoteEstimate(fdm(2, { models: [{ id: 1, weightG: 120, hours: 6, qty: 2, footprint: { x: 130, y: 130 }, heightMm: 20 }] })).beds).toBe(2)
  })

  it('pieza más grande que la cama: se cobra una cama por parte', () => {
    expect(bedsPerPiece({ x: 400, y: 400 }, 400, bed)).toBe(8)
    const r = calcQuoteEstimate(fdm(1, { models: [{ id: 1, weightG: 2000, hours: 60, qty: 1, footprint: { x: 400, y: 400 }, heightMm: 400 }] }))
    expect(r.beds).toBe(8)
    expect(r.duration).toBeCloseTo(60 + 0.29 * 8, 9)
  })

  it('sin huella (datos viejos) cada pieza ocupa su cama', () => {
    const r = calcQuoteEstimate({ ...fdm(3), models: [{ id: 1, weightG: 6.2, hours: 0.31, qty: 3 }] })
    expect(r.beds).toBe(3)
  })
})

describe('orientación', () => {
  it('elige el eje con menos voladizo y deja la cara de apoyo fuera', () => {
    expect(chooseOrientation({ x: 900, y: 1200, z: 300 }).axis).toBe('z')
    // Placa plana: toda su cara de abajo apoya en la cama
    expect(chooseOrientation({ x: 30, y: 1500, z: 50 }, { x: 30, y: 1500, z: 0 }).axis).toBe('y')
  })

  it('la huella son los otros dos lados', () => {
    expect(footprint({ x: 10, y: 20, z: 30 }, 'z')).toEqual({ x: 10, y: 20 })
    expect(footprint({ x: 10, y: 20, z: 30 }, 'y')).toEqual({ x: 10, y: 30 })
  })

  it('huella y altura salen del mismo eje vertical (FDM y SLA)', () => {
    // Placa 100×100×2 con Y vertical (visor): queda de pie, 100 mm de alto
    expect(placement({ x: 100, y: 100, z: 2 }, 'y')).toEqual({ footprint: { x: 100, y: 2 }, heightMm: 100 })
    expect(placement({ x: 100, y: 100, z: 2 }, 'z')).toEqual({ footprint: { x: 100, y: 100 }, heightMm: 2 })
    expect(placement(null, 'y')).toEqual({ footprint: null, heightMm: 0 })
  })

  it('SLA: placa de pie, 25 por cama y el tiempo de su altura real', () => {
    const r = calcQuoteEstimate({
      ...cfg, technology: 'SLA', bed: { x: 218, y: 123, z: 250 },
      models: [{ id: 1, weightG: 22, hours: 0, qty: 25, ...placement({ x: 100, y: 100, z: 2 }, 'y') }],
    })
    expect(r.beds).toBe(1)
    expect(r.duration).toBe(4) // 100 / 25, no 0.5 h de una placa acostada
  })
})

describe('reglas comerciales', () => {
  it('sin reglas el precio no cambia', () => {
    expect(calcQuoteEstimate(fdm(3, { rules: normalizeRules({}) })).total).toBe(calcQuoteEstimate(fdm(3)).total)
  })

  it('pedido mínimo antes de IVA', () => {
    const r = calcQuoteEstimate(fdm(1, { rules: normalizeRules({ min_order_total: 50000 }) }))
    expect(r.subtotal).toBe(50000)
    expect(r.adjustments.map(a => a.type)).toEqual(['min_order'])
  })

  it('descuento por volumen según piezas, y con cupón nunca más del 50 %', () => {
    const rules = normalizeRules({ volume_tiers: [{ min_qty: 5, discount_pct: 10 }, { min_qty: 20, discount_pct: 45 }] })
    const few = calcQuoteEstimate(fdm(4, { rules }))
    expect(few.volumeDiscount).toBe(0)
    const many = calcQuoteEstimate(fdm(25, { rules, discountPct: 20 }))
    expect(many.volumeDiscount).toBe(Math.round(many.grossSubtotal * 0.45))
    expect(many.subtotal).toBe(Math.round(many.grossSubtotal * 0.5))
  })

  it('redondeo del precio unitario hacia arriba', () => {
    const r = calcQuoteEstimate(fdm(3, { rules: normalizeRules({ round_to: 1000 }) }))
    expect(r.unitPrice % 1000).toBe(0)
    expect(r.unitPrice).toBeGreaterThanOrEqual(calcQuoteEstimate(fdm(3)).unitPrice)
  })

  it('mano de obra por pieza va al costo de producción', () => {
    const base = calcQuoteEstimate(fdm(3))
    const r = calcQuoteEstimate(fdm(3, { rules: normalizeRules({ labor_per_piece_min: 6 }) }))
    expect(r.productionCost - base.productionCost).toBeCloseTo(3 * 0.1 * 1000, 9)
  })

  it('urgente solo con recargo configurado', () => {
    const base = calcQuoteEstimate(fdm(3))
    expect(calcQuoteEstimate(fdm(3, { urgent: true })).total).toBe(base.total)
    const r = calcQuoteEstimate(fdm(3, { urgent: true, rules: normalizeRules({ rush_pct: 20 }) }))
    expect(r.urgent).toBe(true)
    expect(r.unitPrice).toBe(Math.round(base.unitPrice * 1.2))
  })
})

describe('material y calibración', () => {
  it('fallos propios del material reemplazan oper.fallos', () => {
    expect(calcQuoteEstimate(fdm(3, { failurePct: 0 })).total).toBeLessThan(calcQuoteEstimate(fdm(3)).total)
  })

  it('factor de calibración: material, si no tecnología, si no 1', () => {
    const cal = normalizeCalibration({ time_factor: { FDM: 1.2 }, by_material: { PLA: 0.9, MALO: 7 } })
    expect(calibrationFactor(cal, 'FDM', 'PLA')).toBe(0.9)
    expect(calibrationFactor(cal, 'FDM', 'MALO')).toBe(1.2)
    expect(calibrationFactor(cal, 'SLA', 'PLA')).toBe(0.9)
    expect(calibrationFactor(normalizeCalibration(null), 'SLA', 'X')).toBe(1)
  })

  it('parámetros del material con valores seguros', () => {
    expect(materialParams({ speed_factor: null, waste_pct: 'x', failure_pct: null })).toEqual({ speed_factor: 1, waste_pct: 0, failure_pct: null })
  })
})
