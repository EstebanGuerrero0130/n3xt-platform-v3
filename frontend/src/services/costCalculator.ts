/**
 * N3XT Cost Calculator — Frontend Module
 *
 * Centraliza toda la lógica de cálculo de costos de producción.
 */

interface ProductionInput {
  weightG?: number
  totalHours?: number
  costPerKg?: number
  infra?: { luz_hr?: number; depr_hr?: number; mant_hr?: number; etiquetas?: number; load_factor?: number }
  prep?: { mano_obra_hr?: number; prep_time_pct?: number }
  extrasCost?: number
}

interface ProductionResult {
  material: number; luz: number; labor: number; depr: number; mant: number
  etiquetas: number; extras: number; total: number
  pcts: { material: number; luz: number; labor: number; depr: number; mant: number; etiquetas: number; extras: number }
}

interface PricingInput {
  productionCost?: number
  oper?: { transporte?: number; marketing?: number; fallos?: number; ganancia?: number }
  margin?: { iva?: number; min_price?: number }
  overrides?: { transportePct?: number; marketingPct?: number; fallosPct?: number; gananciaPct?: number; ivaPct?: number; discountPct?: number }
}

interface PricingResult {
  logistics: number; marketing: number; failures: number; profit: number
  discount: number; subtotal: number; iva: number; total: number; profitMarginPct: number
}

/**
 * Calcula el costo de producción base de una orden.
 */
export function calcProductionCost(params: ProductionInput = {}): ProductionResult {
  const {
    weightG = 0,
    totalHours = 0,
    costPerKg = 0,
    infra = {},
    prep = {},
    extrasCost = 0,
  } = params
  const loadFactor = infra.load_factor ?? 0.4
  const prepTimePct = (prep.prep_time_pct ?? 10) / 100

  // Material
  const matCost = (weightG / 1000) * costPerKg

  // Infraestructura
  const luz = totalHours * loadFactor * (infra.luz_hr ?? 0)
  const labor = (totalHours * prepTimePct) * (prep.mano_obra_hr ?? 0)
  const depr = totalHours * (infra.depr_hr ?? 0)
  const mant = totalHours * (infra.mant_hr ?? 0)
  const etiquetas = infra.etiquetas ?? 0

  const total = matCost + luz + labor + depr + mant + etiquetas + extrasCost

  // Porcentajes para visualización
  const subtotalForPct = total || 1
  const pcts = {
    material: (matCost / subtotalForPct) * 100,
    luz: (luz / subtotalForPct) * 100,
    labor: (labor / subtotalForPct) * 100,
    depr: (depr / subtotalForPct) * 100,
    mant: (mant / subtotalForPct) * 100,
    etiquetas: (etiquetas / subtotalForPct) * 100,
    extras: (extrasCost / subtotalForPct) * 100,
  }

  return { material: matCost, luz, labor, depr, mant, etiquetas, extras: extrasCost, total, pcts }
}

/**
 * Calcula el precio final aplicando márgenes operativos e IVA.
 */
export function calcFinalPrice(params: PricingInput = {}): PricingResult {
  const {
    productionCost = 0,
    oper = {},
    margin = {},
    overrides = {},
  } = params
  const transportePct = overrides.transportePct ?? oper.transporte ?? 0
  const marketingPct = overrides.marketingPct ?? oper.marketing ?? 0
  const fallosPct = overrides.fallosPct ?? oper.fallos ?? 0
  const gananciaPct = overrides.gananciaPct ?? oper.ganancia ?? 0
  const ivaRate = (overrides.ivaPct ?? margin.iva ?? 19) / 100

  const logistics = productionCost * (transportePct / 100)
  const marketing = productionCost * (marketingPct / 100)
  const failures = productionCost * (fallosPct / 100)
  const profit = productionCost * (gananciaPct / 100)

  let subtotal = productionCost + logistics + marketing + failures + profit

  const discountPct = overrides.discountPct ?? 0
  let discount = 0
  if (discountPct > 0) {
    discount = subtotal * (discountPct / 100)
    subtotal = Math.max(subtotal - discount, productionCost)
  }

  const iva = subtotal * ivaRate
  let total = subtotal + iva

  // --- PRECIO MÍNIMO (#5) ---
  // Si el admin configura un precio mínimo en settings.margin.min_price,
  // se aplica como piso para evitar cotizaciones por debajo del costo real de arranque.
  const minPrice = params.margin?.min_price ?? 0
  if (minPrice > 0 && total < minPrice) {
    total = minPrice
  }

  return {
    logistics: Math.round(logistics),
    marketing: Math.round(marketing),
    failures: Math.round(failures),
    profit: Math.round(profit),
    discount: Math.round(discount),
    subtotal: Math.round(subtotal),
    iva: Math.round(iva),
    total: Math.round(total),
    profitMarginPct: productionCost > 0 ? ((subtotal - productionCost) / subtotal) * 100 : 0,
  }
}

/**
 * Calcula el costo de un extra/consumible basado en la unidad del material.
 */
export function calcExtraCost(costPerKg: number, unit: string, qty: number, unitPrice?: number): number {
  const u = (unit || '').toLowerCase().trim()
  // Unidades de servicio/unidad usan precio unitario directo (no por kg)
  if (['servicio', 'unidad', 'und', 'u', 'ud', 'pieza', 'pza'].includes(u)) {
    return (unitPrice ?? costPerKg) * qty
  }
  // Unidades de peso/volumen: costPerKg * qty(g o ml) / 1000
  const isWeightOrVolume = ['g', 'ml', 'kg', 'l'].includes(u)
  return isWeightOrVolume ? costPerKg * (qty / 1000) : (unitPrice ?? costPerKg) * qty
}

/**
 * Calcula el descuento automático por volumen (escala de cantidad). (#6)
 *
 * Tiers configurables:
 *  1–4   uds  →  0%  de descuento
 *  5–9   uds  →  5%  de descuento
 *  10–24  uds  → 10%  de descuento
 *  25+    uds  → 15%  de descuento (negociable en taller)
 *
 * @param qty  Cantidad de unidades del pedido
 * @param tiers  Opcional: array de { minQty, discountPct } para sobreescribir los defaults
 * @returns  Porcentaje de descuento aplicable (0–15)
 */
export function calcVolumeDiscount(
  qty: number,
  tiers?: Array<{ minQty: number; discountPct: number }>
): number {
  const defaultTiers = [
    { minQty: 25, discountPct: 15 },
    { minQty: 10, discountPct: 10 },
    { minQty: 5,  discountPct: 5  },
    { minQty: 1,  discountPct: 0  },
  ]
  const activeTiers = (tiers ?? defaultTiers).sort((a, b) => b.minQty - a.minQty)
  const matched = activeTiers.find(t => qty >= t.minQty)
  return matched ? matched.discountPct : 0
}

interface OrderBreakdown {
  material: number; luz: number; labor: number; depr: number; mant: number
  prep_pack: number; extras: number; production_cost: number; operating_margin: number; profit_margin_pct: number
}

/**
 * Calcula el breakdown completo para mostrar en el modal de detalles de orden.
 */
export function calcOrderDetailBreakdown(order: Record<string, any>, settings: Record<string, any>, matCostPerKg: number): OrderBreakdown {
  const totalHours = parseFloat(order.estimated_duration_h) || 0
  const weightG = parseFloat(order.estimated_weight_g) || 0
  const s = settings

  const infra = s.infra || {}
  const prep = s.prep || {}

  const loadFactor = infra.load_factor ?? 0.4
  const prepTimePct = (prep.prep_time_pct ?? 10) / 100

  const material = (weightG / 1000) * matCostPerKg
  const luz = totalHours * loadFactor * (infra.luz_hr || 0)
  const labor = (totalHours * prepTimePct) * (prep.mano_obra_hr || 0)
  const depr = totalHours * (infra.depr_hr || 0)
  const mant = totalHours * (infra.mant_hr || 0)
  const etiquetas = parseFloat(infra.etiquetas || 0)
  const extras = parseFloat(order.extras_cost) || 0

  const rMaterial = Math.round(material)
  const rLuz = Math.round(luz)
  const rLabor = Math.round(labor)
  const rDepr = Math.round(depr)
  const rMant = Math.round(mant)
  const rEtiquetas = Math.round(etiquetas)
  const rExtras = Math.round(extras)
  const rTotalCost = rMaterial + rLuz + rLabor + rDepr + rMant + rEtiquetas + rExtras

  const totalPrice = Number(order.total_price) || 0
  const operatingMargin = totalPrice - rTotalCost
  const profitMarginPct = totalPrice > 0 ? (operatingMargin / totalPrice) * 100 : (rTotalCost > 0 ? -100 : 0)

  return {
    material: rMaterial,
    luz: rLuz,
    labor: rLabor,
    depr: rDepr,
    mant: rMant,
    prep_pack: rEtiquetas,
    extras: rExtras,
    production_cost: rTotalCost,
    operating_margin: operatingMargin,
    profit_margin_pct: Math.round(profitMarginPct * 10) / 10,
  }
}
