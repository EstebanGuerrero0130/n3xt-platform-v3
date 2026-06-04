/**
 * N3XT Cost Simulator Composable
 * Maneja la simulaci\u00f3n de costos de producci\u00f3n 3D
 */
import { reactive, computed, type Ref } from 'vue'
import { api } from '../services/api'

interface SimulatorState {
  customer_id: string
  job_name: string
  customer_name: string
  customer_company: string
  customer_id_document: string
  material_id: string
  weight_g: number
  time_str: string
  profit_pct: number
  customer_email: string
  customer_phone: string
  shipping_address: string
  shipping_city: string
  shipping_zip: string
  shipping_reference: string
  discount_pct: number
  pieces_per_batch: number
  transporte_pct: number
  marketing_pct: number
  fallos_pct: number
  etiquetas: number
  extra_items: Array<{ id: string; name: string; cost: number; unit: string; qty: number }>
  comments: string
}

const defaultSimulator: SimulatorState = {
  customer_id: '',
  job_name: '', customer_name: '', customer_company: '', customer_id_document: '',
  material_id: '', weight_g: 0, time_str: '0:00', profit_pct: 20,
  customer_email: '', customer_phone: '',
  shipping_address: '', shipping_city: '', shipping_zip: '', shipping_reference: '',
  discount_pct: 0, pieces_per_batch: 1, transporte_pct: 0, marketing_pct: 0, fallos_pct: 0, etiquetas: 400,
  extra_items: [],
  comments: ''
}

interface UseSimulatorOptions {
  inventoryData: Ref<Array<Record<string, any>>>
  settings: Ref<Record<string, any>>
  showNotify?: (msg: string, type?: string) => void
}

export function useSimulator({ inventoryData, settings, showNotify }: UseSimulatorOptions) {
  const simulator = reactive<SimulatorState>({ ...defaultSimulator })

  const simulatedResult = computed(() => {
    const mat = inventoryData.value.find((m: any) => m.id === simulator.material_id)
    if (!mat || !settings.value.infra) return { 
      material: 0, luz: 0, labor: 0, depr: 0, mant: 0, etiquetas: 0, extras: 0,
      production: 0, subtotal: 0, iva: 0, total: 0, unit_price: 0, discount: 0,
      isSafetyAlert: false,
      pcts: { material: 0, infra: 0, extras: 0, profit: 0 }
    }

    const qty = Math.max(1, simulator.pieces_per_batch || 1)
    const matCost = (simulator.weight_g / 1000) * mat.cost_per_kg

    const [hours, minutes] = simulator.time_str.split(':').map(Number)
    const totalHours = (hours || 0) + ((minutes || 0) / 60)

    const luz = totalHours * (settings.value.infra.load_factor || 0.4) * (settings.value.infra.luz_hr || 0)
    const labor = (totalHours * ((settings.value.prep?.prep_time_pct || 10) / 100)) * (settings.value.prep?.mano_obra_hr || 0)
    const depr = totalHours * (settings.value.infra.depr_hr || 0)
    const mant = totalHours * (settings.value.infra.mant_hr || 0)
    const etiquetas = Number(simulator.etiquetas || 0)

    const extrasCost = simulator.extra_items.reduce((acc: number, item) => acc + (item.cost * item.qty), 0)
    const productionCost = matCost + luz + labor + depr + mant + etiquetas + extrasCost

    const logistics = productionCost * (simulator.transporte_pct / 100)
    const marketing = productionCost * (simulator.marketing_pct / 100)
    const failures = productionCost * (simulator.fallos_pct / 100)
    const profitAmount = productionCost * (simulator.profit_pct / 100)

    const listPrice = productionCost + logistics + marketing + failures + profitAmount
    const discountAmount = listPrice * (simulator.discount_pct / 100)
    const subtotal = Math.max(listPrice - discountAmount, productionCost)
    const effectiveDiscount = listPrice - subtotal

    const iva = subtotal * (settings.value.margin?.iva / 100 || 0)
    const totalGrand = subtotal + iva

    return {
      material: matCost, luz, labor, depr, mant, etiquetas, extras: extrasCost,
      production: productionCost, logistics, marketing, failures, profit: profitAmount,
      subtotal: Math.round(subtotal), discount: Math.round(effectiveDiscount),
      iva: Math.round(iva), total: Math.round(totalGrand),
      unit_price: Math.round(totalGrand / qty), total_hours: totalHours,
      profit_margin_pct: productionCost > 0 ? ((subtotal - productionCost) / subtotal) * 100 : 0,
      isSafetyAlert: simulator.discount_pct === 100,
      pcts: {
        material: subtotal > 0 ? (matCost / subtotal) * 100 : 0,
        infra: subtotal > 0 ? ((luz + labor + depr + mant + etiquetas) / subtotal) * 100 : 0,
        extras: subtotal > 0 ? (extrasCost / subtotal) * 100 : 0,
        profit: subtotal > 0 ? (Math.max(0, subtotal - productionCost) / subtotal) * 100 : 0
      }
    }
  })

  const addSimulatorExtra = (event: any) => {
    const matId = event.target.value
    if (!matId) return
    const item = inventoryData.value.find((m: any) => m.id === matId)
    if (item) {
      const existing = simulator.extra_items.find(i => i.id === item.id)
      if (existing) {
        existing.qty++
      } else {
        simulator.extra_items.push({
          id: item.id, name: item.name, cost: item.cost_per_kg, unit: item.unit, qty: 1
        })
      }
    }
    event.target.value = ''
  }

  const removeSimulatorExtra = (index: number) => {
    simulator.extra_items.splice(index, 1)
  }

  const resetSimulator = () => {
    Object.assign(simulator, {
      customer_id: '', job_name: '', customer_name: '', customer_company: '',
      customer_id_document: '', material_id: '', weight_g: 0, time_str: '0:00',
      extra_items: [], comments: ''
    })
  }

  return {
    simulator,
    simulatedResult,
    addSimulatorExtra,
    removeSimulatorExtra,
    resetSimulator
  }
}
