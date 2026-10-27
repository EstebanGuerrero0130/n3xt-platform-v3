import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import PriceSimulator from '../components/admin/PriceSimulator.vue'

const settings = (rules: Record<string, unknown> = {}) => ({
  infra: { luz_hr: 500, depr_hr: 300, mant_hr: 200, etiquetas: 1000, load_factor: 0.4 },
  prep: { mano_obra_hr: 10000, prep_time_pct: 10 },
  oper: { transporte: 5, marketing: 10, fallos: 5, ganancia: 30 },
  margin: { iva: 19 },
  rules,
})
const materials = [{ id: 'PLA', name: 'PLA', type: 'material', unit: 'g', cost_per_kg: 80000, waste_pct: 10, failure_pct: 12 }]

// Abre el simulador con 20 g y 1 h por cama, 2 camas de 5 piezas
async function simulate(rules?: Record<string, unknown>) {
  const w = mount(PriceSimulator, {
    props: { visible: false, settings: settings(rules), materials },
    global: { stubs: { AdminModal: { template: '<div><slot /><slot name="footer" /></div>' } } },
  })
  await w.setProps({ visible: true })
  await w.find('#sim-material').setValue('PLA')
  await w.find('#sim-weight').setValue(20)
  await w.find('#sim-time').setValue('1:00')
  await w.find('#sim-beds').setValue(2)
  await w.find('#sim-pieces').setValue(5)
  return w
}
const total = (w: Awaited<ReturnType<typeof simulate>>) => (w.vm as any).result.total as number

describe('simulador de precios', () => {
  it('usa el desperdicio y los fallos del material', async () => {
    const w = await simulate()
    expect((w.find('#sim-fail').element as HTMLInputElement).value).toBe('12')
    expect(w.text()).toContain('44 g') // 20 g × 2 camas + 10 %
  })

  it('aplica el pedido mínimo, el descuento por volumen y el urgente como el sitio', async () => {
    const base = total(await simulate())
    expect(total(await simulate({ min_order_total: 10_000_000 }))).toBe(Math.round(10_000_000 * 1.19))

    const volume = await simulate({ volume_tiers: [{ min_qty: 10, discount_pct: 10 }] })
    expect(total(volume)).toBeLessThan(base)
    expect(volume.text()).toContain('Descuento por volumen')

    const rush = await simulate({ rush_pct: 50 })
    expect(rush.text()).toContain('Urgente')
    await rush.find('input[type="checkbox"]').setValue(true)
    expect(total(rush)).toBeGreaterThan(base)
  })

  it('el descuento manual tiene el mismo tope del 50 %', async () => {
    const w = await simulate()
    const base = (w.vm as any).result.subtotal as number
    await w.find('#sim-disc').setValue(90)
    expect((w.vm as any).result.subtotal).toBeGreaterThanOrEqual(Math.floor(base / 2))
    expect(w.text()).toContain('no pasan del 50 %')
  })
})
