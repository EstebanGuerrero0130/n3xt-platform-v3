import { describe, it, expect, vi, afterEach } from 'vitest'
import { effectScope, nextTick, reactive } from 'vue'
import { calcQuoteEstimate, normalizeRules } from '../services/costCalculator'
import { summaryLines } from '../utils/quoteSummary'
import { useLeadTime, LEAD_TIME_DEBOUNCE_MS, type LeadTimeQuery } from '../composables/useLeadTime'

const cfg = {
  costPerKg: 85000,
  infra: { luz_hr: 926, depr_hr: 400, mant_hr: 700, etiquetas: 500, load_factor: 0.4 },
  prep: { mano_obra_hr: 1000, prep_time_pct: 10 },
  oper: { transporte: 5, ganancia: 30, marketing: 5, fallos: 10 },
  margin: { iva: 19 },
}

describe('resumen de precio', () => {
  it('las líneas suman el subtotal con redondeo, mano de obra, volumen, cupón y urgente', () => {
    const rules = normalizeRules({ round_to: 1000, labor_per_piece_min: 6, rush_pct: 20, volume_tiers: [{ min_qty: 3, discount_pct: 5 }] })
    for (const qty of [1, 3, 7]) {
      const r = calcQuoteEstimate({
        ...cfg, technology: 'FDM', bed: { x: 256, y: 256, z: 256 }, bedHours: 0.29, bedWeightG: 3, rules, urgent: true, discountPct: 10, extrasCost: 1234.5,
        models: [{ id: 1, weightG: 6.2, hours: 0.31, qty, footprint: { x: 20, y: 20 }, heightMm: 20 }],
      })
      const s = summaryLines(r)
      const sum = s.costs.reduce((a, l) => a + l.amount, 0) + s.adjustments.reduce((a, x) => a + x.amount, 0) - s.discount
      expect(sum).toBe(r.subtotal)
      expect(s.adjustments.map(a => a.type)).toContain('rounding')
      // La mano de obra (incluida la de cada pieza) tiene su línea
      const labor = s.costs.find(l => l.key === 'labor')!.amount
      expect(Math.abs(labor - Math.round(r.laborCost + cfg.infra.etiquetas))).toBeLessThanOrEqual(qty + 2)
    }
  })

  it('pedido mínimo también cuadra', () => {
    const r = calcQuoteEstimate({ ...cfg, rules: normalizeRules({ min_order_total: 90000 }), models: [{ id: 1, weightG: 5, hours: 0.5, qty: 1 }] })
    const s = summaryLines(r)
    expect(s.costs.reduce((a, l) => a + l.amount, 0) + s.adjustments.reduce((a, x) => a + x.amount, 0) - s.discount).toBe(90000)
  })
})

describe('fecha de entrega (lead-time)', () => {
  afterEach(() => { vi.useRealTimers() })

  const setup = () => {
    vi.useFakeTimers()
    const q = reactive<LeadTimeQuery>({ shown: true, technology: 'FDM', hours: 2, urgent: false })
    const fetcher = vi.fn(async (p: URLSearchParams) => ({ label: p.get('urgent') === '1' ? 'lunes' : 'jueves' }))
    const scope = effectScope()
    const lt = scope.run(() => useLeadTime(() => ({ ...q }), fetcher))!
    return { q, fetcher, lt, scope }
  }
  const flush = async () => { await vi.advanceTimersByTimeAsync(LEAD_TIME_DEBOUNCE_MS); await nextTick() }

  it('espera a que el precio se quede quieto y no repite la misma consulta', async () => {
    const { q, fetcher, lt } = setup()
    for (const h of [2.01, 2.02, 2.03]) { q.hours = h; await nextTick() }
    expect(fetcher).not.toHaveBeenCalled()
    await flush()
    expect(fetcher).toHaveBeenCalledTimes(1)
    expect(lt.label.value).toBe('jueves')
    // 2.03 → 2.1 h: misma consulta, no se repite
    q.hours = 2.04
    await nextTick(); await flush()
    expect(fetcher).toHaveBeenCalledTimes(1)
    expect(lt.label.value).toBe('jueves')
  })

  it('no consulta sin precio a la vista', async () => {
    const { q, fetcher, lt } = setup()
    q.shown = false
    await nextTick(); await flush()
    expect(fetcher).not.toHaveBeenCalled()
    expect(lt.label.value).toBe('')
  })

  it('al pasar a urgente borra la fecha vieja y marca la nueva como urgente', async () => {
    const { q, lt } = setup()
    await flush()
    expect(lt.label.value).toBe('jueves')
    expect(lt.urgent.value).toBe(false)
    q.urgent = true
    await nextTick()
    expect(lt.label.value).toBe('')
    await flush()
    expect(lt.label.value).toBe('lunes')
    expect(lt.urgent.value).toBe(true)
  })

  it('descarta una respuesta vieja que llega tarde', async () => {
    vi.useFakeTimers()
    const q = reactive<LeadTimeQuery>({ shown: true, technology: 'FDM', hours: 2, urgent: false })
    const resolvers: ((v: any) => void)[] = []
    const fetcher = vi.fn(() => new Promise(res => { resolvers.push(res) }))
    const lt = effectScope().run(() => useLeadTime(() => ({ ...q }), fetcher))!
    await flush()
    q.hours = 5
    await nextTick(); await flush()
    resolvers[1]({ label: 'viernes' })
    resolvers[0]({ label: 'jueves' })
    await vi.advanceTimersByTimeAsync(0)
    expect(lt.label.value).toBe('viernes')
  })
})
