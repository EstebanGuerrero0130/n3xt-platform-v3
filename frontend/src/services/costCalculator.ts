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
  margin?: { iva?: number }
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
  const total = subtotal + iva

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
export function calcExtraCost(costPerKg: number, unit: string, qty: number): number {
  const isWeightOrVolume = ['g', 'ml', 'kg', 'l'].includes((unit || '').toLowerCase())
  return isWeightOrVolume ? costPerKg * (qty / 1000) : costPerKg * qty
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

  // Productos del catálogo: costo de cada producto guardado al comprar
  const isCatalog = order.technology === 'CATALOGO'
  const material = isCatalog
    ? (Array.isArray(order.models) ? order.models : []).reduce((sum: number, m: any) => sum + (Number(m.unit_cost) || 0) * (Number(m.qty) || 1), 0)
    : (weightG / 1000) * matCostPerKg
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
  // Sin precio (pedido en $0) no hay margen que mostrar
  const profitMarginPct = totalPrice > 0 ? (operatingMargin / totalPrice) * 100 : 0

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

// ─── Cotizador: espejo EXACTO de QuotePricingService (backend) ───
// Mismas operaciones en el mismo orden; tests/Fixtures/quote_core_vectors.json
// se comprueba en las dos pruebas (Vitest y PHPUnit).

export type Axis = 'x' | 'y' | 'z'
export interface Dims3 { x: number; y: number; z: number }
export interface Footprint { x: number; y: number }
export type Technology = 'FDM' | 'SLA'

/** FDM: preparación (h) y purga (g) de cada cama, no de cada pieza */
export const FDM_PREP_H = 0.233
export const FDM_PURGE_G = 3.0
/** Separación entre piezas (mm) y parte de la cama que se aprovecha */
export const BED_SPACING_MM = 5
export const BED_PACKING = 0.7
/** Tope de cupón + descuento por volumen (%) */
export const MAX_DISCOUNT_PCT = 50
export const DEFAULT_BEDS: Record<Technology, Dims3> = {
  FDM: { x: 256, y: 256, z: 256 },
  SLA: { x: 218, y: 123, z: 250 },
}

export interface VolumeTier { min_qty: number; discount_pct: number }
export interface QuoteRules {
  min_order_total: number
  volume_tiers: VolumeTier[]
  round_to: number
  labor_per_piece_min: number
  rush_pct: number
}
export interface Calibration {
  time_factor: Record<Technology, number>
  by_material: Record<string, number>
}
export interface MaterialParams { speed_factor: number; waste_pct: number; failure_pct: number | null }

const isObj = (v: unknown): v is Record<string, any> => typeof v === 'object' && v !== null && !Array.isArray(v)

/** Número finito (número o texto numérico) o null */
export function finiteNum(v: unknown): number | null {
  if (typeof v === 'number') return Number.isFinite(v) ? v : null
  if (typeof v === 'string' && v.trim() !== '') {
    const n = Number(v.trim())
    return Number.isFinite(n) ? n : null
  }
  return null
}

export function normalizeBed(raw: unknown, def: Dims3): Dims3 {
  const pick = (k: Axis) => {
    const v = isObj(raw) ? finiteNum(raw[k]) : null
    return v !== null && v > 0 ? v : def[k]
  }
  return { x: pick('x'), y: pick('y'), z: pick('z') }
}

/** Camas por tecnología (settings `beds` o GET /quote/capacity) */
export function normalizeBeds(raw: unknown): Record<Technology, Dims3> {
  return {
    FDM: normalizeBed(isObj(raw) ? raw.FDM : null, DEFAULT_BEDS.FDM),
    SLA: normalizeBed(isObj(raw) ? raw.SLA : null, DEFAULT_BEDS.SLA),
  }
}

/** Reglas comerciales (settings `rules`). Sin configurar no cambian el precio. */
export function normalizeRules(raw: unknown): QuoteRules {
  const r = isObj(raw) ? raw : {}
  const pos = (v: unknown, max = Infinity) => {
    const n = finiteNum(v)
    return n !== null && n > 0 ? Math.min(n, max) : 0
  }
  const tiers: VolumeTier[] = []
  for (const t of Array.isArray(r.volume_tiers) ? r.volume_tiers : []) {
    if (!isObj(t)) continue
    const min = finiteNum(t.min_qty)
    const pct = finiteNum(t.discount_pct)
    if (min === null || pct === null || Math.floor(min) < 1 || pct <= 0) continue
    tiers.push({ min_qty: Math.floor(min), discount_pct: Math.min(pct, MAX_DISCOUNT_PCT) })
  }
  tiers.sort((a, b) => a.min_qty - b.min_qty)
  return {
    min_order_total: pos(r.min_order_total),
    volume_tiers: tiers,
    round_to: pos(r.round_to),
    labor_per_piece_min: pos(r.labor_per_piece_min),
    rush_pct: pos(r.rush_pct, 200),
  }
}

/**
 * Calibración del tiempo (settings `calibration`). Solo valen factores entre 0.3 y 3:
 * por tecnología, si no, 1; por material, si no, se ignora (cae al de la tecnología).
 */
export function normalizeCalibration(raw: unknown): Calibration {
  const ok = (v: unknown) => {
    const n = finiteNum(v)
    return n !== null && n >= 0.3 && n <= 3 ? n : null
  }
  const tf = isObj(raw) && isObj(raw.time_factor) ? raw.time_factor : {}
  const byMaterial: Record<string, number> = {}
  if (isObj(raw) && isObj(raw.by_material)) {
    for (const [id, v] of Object.entries(raw.by_material)) {
      const f = ok(v)
      if (f !== null) byMaterial[id] = f
    }
  }
  return { time_factor: { FDM: ok(tf.FDM) ?? 1, SLA: ok(tf.SLA) ?? 1 }, by_material: byMaterial }
}

/** by_material[id] ?? time_factor[tecnología] ?? 1 */
export function calibrationFactor(cal: Calibration, technology: Technology, materialId: string): number {
  return cal.by_material[materialId] ?? cal.time_factor[technology] ?? 1
}

/** Velocidad (× tiempo), desperdicio (% extra de material) y fallos propios del material */
export function materialParams(mat: Record<string, any> | null | undefined): MaterialParams {
  const speed = finiteNum(mat?.speed_factor)
  const waste = finiteNum(mat?.waste_pct)
  const fail = finiteNum(mat?.failure_pct)
  return {
    speed_factor: speed !== null && speed > 0 ? Math.min(speed, 10) : 1,
    waste_pct: waste !== null && waste > 0 ? Math.min(waste, 100) : 0,
    failure_pct: fail !== null && fail >= 0 ? Math.min(fail, 100) : null,
  }
}

/** Horas de impresión FDM de `weightG` gramos (mismo flujo volumétrico que /process-stl) */
export function fdmPrintHours(weightG: number, density: number, layerHeight: number): number {
  if (!(density > 0)) density = 1.25
  if (!(layerHeight > 0)) layerHeight = 0.2
  return (((weightG / density) * 1000) / 15500) * (0.2 / layerHeight)
}

/**
 * Eje vertical con menos soporte: área hacia abajo menos la que apoya en la cama.
 * Gana 'y' (la orientación del visor) salvo que otro eje ahorre más de 1 mm².
 */
export function chooseOrientation(down: Partial<Dims3>, contact?: Partial<Dims3> | null): { axis: Axis; supportArea: number } {
  const support = (k: Axis) => Math.max(0, (Number(down[k]) || 0) - (Number(contact?.[k]) || 0))
  let axis: Axis = 'y'
  let best = support('y')
  for (const k of ['z', 'x'] as Axis[]) {
    const a = support(k)
    if (a < best - 1.0) {
      axis = k
      best = a
    }
  }
  return { axis, supportArea: best }
}

/** Huella en la cama con `axis` vertical (los otros dos lados del bbox) */
export function footprint(bbox: Dims3 | null | undefined, axis: Axis): Footprint | null {
  if (!bbox) return null
  const rest = (['x', 'y', 'z'] as Axis[]).filter(k => k !== axis).map(k => bbox[k])
  return { x: rest[0], y: rest[1] }
}

/** Piezas de esa huella que caben en una cama (separación + factor de aprovechamiento) */
export function piecesPerBed(fp: Footprint | null | undefined, bed: Partial<Dims3> | null | undefined): number {
  const bx = Number(bed?.x) || 0
  const by = Number(bed?.y) || 0
  const fx = Number(fp?.x) || 0
  const fy = Number(fp?.y) || 0
  if (bx <= 0 || by <= 0 || fx <= 0 || fy <= 0) return 1
  // Una pieza más grande que la cama ocupa una cama entera (ver bedsPerPiece)
  if (!((fx <= bx && fy <= by) || (fx <= by && fy <= bx))) return 1
  const sp = BED_SPACING_MM
  const area = Math.floor((bx * by * BED_PACKING) / ((fx + sp) * (fy + sp)))
  // Por área no basta: también deben caber en filas (con la pieza girada o no)
  const grid = Math.max(
    Math.floor((bx + sp) / (fx + sp)) * Math.floor((by + sp) / (fy + sp)),
    Math.floor((bx + sp) / (fy + sp)) * Math.floor((by + sp) / (fx + sp)),
  )
  return Math.max(1, Math.min(area, grid))
}

/**
 * Camas que ocupa UNA pieza: 1 si cabe; si no, se imprime en partes y cada eje que
 * se pasa de la cama multiplica por ceil(lado / cama) (la huella puede girarse).
 */
export function bedsPerPiece(fp: Footprint | null | undefined, heightMm: number, bed: Partial<Dims3> | null | undefined): number {
  const bx = Number(bed?.x) || 0
  const by = Number(bed?.y) || 0
  const bz = Number(bed?.z) || 0
  const fx = Number(fp?.x) || 0
  const fy = Number(fp?.y) || 0
  let xy = 1
  if (bx > 0 && by > 0 && fx > 0 && fy > 0) {
    xy = Math.min(Math.ceil(fx / bx) * Math.ceil(fy / by), Math.ceil(fx / by) * Math.ceil(fy / bx))
  }
  const z = bz > 0 && heightMm > bz ? Math.ceil(heightMm / bz) : 1
  return Math.max(1, xy) * z
}

/** Huella y altura en la cama con `axis` vertical (bbox del archivo, ya escalado) */
export function placement(bbox: Dims3 | null | undefined, axis: Axis): { footprint: Footprint | null; heightMm: number } {
  return { footprint: footprint(bbox, axis), heightMm: bbox ? bbox[axis] : 0 }
}

interface BedSlot { height: number; pieces: number; used: number; items: number[] }

/**
 * Reparte las piezas en camas: de la más alta a la más baja, llenando cada cama.
 * Una pieza que no cabe ocupa `parts` camas enteras, cada una con la altura de su parte.
 */
function packBeds(models: { qty: number; heightMm: number }[], ppb: number[], parts: number[], zParts: number[]): BedSlot[] {
  const order = models.map((_, i) => i)
  order.sort((a, b) => (models[b].heightMm - models[a].heightMm) || (a - b))
  const beds: BedSlot[] = []
  let cur = -1
  for (const i of order) {
    if (parts[i] > 1) {
      const items = models.map((_, k) => (k === i ? 1 : 0))
      for (let p = 0; p < models[i].qty * parts[i]; p++) {
        beds.push({ height: models[i].heightMm / zParts[i], pieces: 1, used: 1, items: [...items] })
        cur++
      }
      continue
    }
    const need = 1 / ppb[i]
    for (let p = 0; p < models[i].qty; p++) {
      if (cur < 0 || beds[cur].used + need > 1 + 1e-9) {
        beds.push({ height: models[i].heightMm, pieces: 0, used: 0, items: models.map(() => 0) })
        cur++
      }
      beds[cur].used += need
      beds[cur].pieces++
      beds[cur].items[i]++
    }
  }
  return beds
}

export interface QuoteModelInput {
  /** Identificador del modelo (para el desglose por modelo) */
  id: number | string
  /**
   * Peso de UNA pieza en gramos. Sin `technology`: todo incluido (cálculo histórico);
   * con FDM: sin purga (va por cama).
   */
  weightG: number
  /** Horas de UNA pieza. Sin `technology`: impresión + preparación; FDM: solo impresión; SLA: no se usa */
  hours: number
  /** Cantidad de piezas de este modelo (entero >= 1) */
  qty: number
  /** Huella en la cama (mm, ya orientada); sin ella, una pieza por cama */
  footprint?: Footprint | null
  /** Altura de la pieza en la cama (mm); SLA: define el tiempo de la cama */
  heightMm?: number
}

export interface QuoteAdjustment {
  type: 'rush' | 'rounding' | 'volume' | 'min_order'
  label: string
  pct: number | null
  amount: number
}

export interface QuoteInput {
  models: QuoteModelInput[]
  costPerKg: number
  infra?: ProductionInput['infra']
  prep?: ProductionInput['prep']
  oper?: PricingInput['oper']
  margin?: PricingInput['margin']
  /** Costo total de extras/consumibles del pedido */
  extrasCost?: number
  /** Descuento del cupón en porcentaje (10 = 10 %) */
  discountPct?: number
  /** Con tecnología las piezas comparten cama; sin ella, cada pieza es un trabajo aparte */
  technology?: Technology | null
  bed?: Partial<Dims3> | null
  /** FDM: horas (preparación + purga) y gramos de purga de cada cama */
  bedHours?: number
  bedWeightG?: number
  /** SLA: multiplica el tiempo de cada cama (velocidad del material × calibración) */
  bedTimeFactor?: number
  /** Reserva por fallos del material (null: la global oper.fallos) */
  failurePct?: number | null
  rules?: QuoteRules
  urgent?: boolean
}

export interface QuoteResult {
  pieces: number
  weight: number
  duration: number
  beds: number
  piecesPerBed: number
  matCost: number
  infraCost: number
  laborCost: number
  utilityCost: number
  productionCost: number
  marginCost: number
  unitPrice: number
  /** Subtotal antes de descuentos */
  grossSubtotal: number
  volumeDiscount: number
  /** Descuento del cupón */
  discount: number
  /** Subtotal después de descuentos y pedido mínimo (base del IVA) */
  subtotal: number
  iva: number
  ivaPct: number
  total: number
  urgent: boolean
  adjustments: QuoteAdjustment[]
  /** Por modelo: piezas por cama y peso/horas de una pieza con su parte de la cama */
  models: { qty: number; piecesPerBed: number; weightG: number; hours: number }[]
  /** Total estimado (IVA incluido) atribuido a cada modelo; suma exactamente `total`. */
  perModel: { id: number | string; total: number }[]
}

const adjustment = (type: QuoteAdjustment['type'], label: string, pct: number | null, amount: number): QuoteAdjustment =>
  ({ type, label, pct, amount: Math.trunc(amount) })

/**
 * Precio estimado de un pedido con varios modelos (cada uno con su cantidad).
 * Sin `technology` da exactamente el cálculo histórico del cotizador (peso × qty,
 * márgenes sobre el costo unitario y luego × qty). Con `technology` las piezas
 * comparten cama (ver QuotePricingService::estimate). Es solo un estimado: el
 * precio definitivo lo calcula el servidor.
 */
export function calcQuoteEstimate(input: QuoteInput): QuoteResult {
  const tech = input.technology === 'FDM' || input.technology === 'SLA' ? input.technology : null
  const models = input.models.map(m => ({
    ...m,
    qty: Math.max(1, Math.floor(Number(m.qty)) || 1),
    weightG: Number(m.weightG) || 0,
    hours: Number(m.hours) || 0,
    heightMm: Number(m.heightMm) || 0,
  }))
  const pieces = models.reduce((s, m) => s + m.qty, 0)
  let weight = models.reduce((s, m) => s + m.weightG * m.qty, 0)
  let duration = models.reduce((s, m) => s + m.hours * m.qty, 0)
  const extrasCost = input.extrasCost ?? 0
  const rules = input.rules ?? normalizeRules(null)

  // Camas: cuántas piezas caben y en qué cama va cada una
  const ppb = models.map(m => (tech ? piecesPerBed(m.footprint, input.bed) : 1))
  const parts = models.map(m => (tech ? bedsPerPiece(m.footprint, m.heightMm, input.bed) : 1))
  const zParts = models.map(m => (tech ? bedsPerPiece(null, m.heightMm, input.bed) : 1))
  const beds = tech ? packBeds(models, ppb, parts, zParts) : []
  const bedCount = tech ? beds.length : pieces
  const maxPerBed = beds.reduce((s, b) => Math.max(s, b.pieces), 1)

  const unitW = models.map(m => m.weightG)
  const unitH = models.map(m => m.hours)
  if (tech === 'FDM' && pieces > 0) {
    const bedW = Number(input.bedWeightG) || 0
    const bedH = Number(input.bedHours) || 0
    weight += bedW * bedCount
    duration += bedH * bedCount
    models.forEach((m, i) => {
      unitW[i] = m.weightG + (bedW * bedCount) / pieces
      unitH[i] = m.hours + (bedH * bedCount) / pieces
    })
  } else if (tech === 'SLA') {
    const f = Number(input.bedTimeFactor) || 1
    duration = 0
    const share = models.map(() => 0)
    for (const b of beds) {
      const t = Math.max(0.5, b.height / 25) * f
      duration += t
      b.items.forEach((c, i) => { if (c > 0) share[i] += t * c / b.pieces })
    }
    models.forEach((m, i) => { unitH[i] = share[i] / m.qty })
  }

  const manoObra = input.prep?.mano_obra_hr ?? 0
  const prod = calcProductionCost({
    weightG: weight,
    totalHours: duration,
    costPerKg: input.costPerKg,
    infra: input.infra,
    prep: input.prep,
    extrasCost,
  })
  // Mano de obra de posproceso por pieza
  const laborPieces = pieces * (rules.labor_per_piece_min / 60) * manoObra
  const production = prod.total + laborPieces

  const unitCost = pieces > 0 ? production / pieces : 0
  const failPct = input.failurePct ?? input.oper?.fallos ?? 0
  const perUnit = calcFinalPrice({ productionCost: unitCost, oper: { ...input.oper, fallos: failPct }, margin: input.margin })
  let unitPrice = perUnit.subtotal

  const adjustments: QuoteAdjustment[] = []
  const urgent = !!input.urgent && rules.rush_pct > 0
  if (urgent) {
    const withRush = Math.round(unitPrice * (1 + rules.rush_pct / 100))
    adjustments.push(adjustment('rush', 'Urgente', rules.rush_pct, (withRush - unitPrice) * pieces))
    unitPrice = withRush
  }
  if (rules.round_to > 0) {
    const rounded = Math.ceil(unitPrice / rules.round_to) * rules.round_to
    if (rounded !== unitPrice) adjustments.push(adjustment('rounding', 'Redondeo', null, (rounded - unitPrice) * pieces))
    unitPrice = rounded
  }
  const rawSubtotal = unitPrice * pieces

  // Descuento por volumen (antes del cupón); juntos no pasan de MAX_DISCOUNT_PCT
  let volumePct = 0
  for (const t of rules.volume_tiers) if (pieces >= t.min_qty) volumePct = t.discount_pct
  const volumeAmount = volumePct > 0 ? rawSubtotal * (volumePct / 100) : 0
  const couponPct = Number(input.discountPct) || 0
  let couponAmount = couponPct ? (rawSubtotal - volumeAmount) * (couponPct / 100) : 0
  const maxOff = rawSubtotal * (MAX_DISCOUNT_PCT / 100)
  if (volumeAmount + couponAmount > maxOff) couponAmount = Math.max(0, maxOff - volumeAmount)
  if (volumePct > 0) adjustments.push(adjustment('volume', 'Descuento por volumen', -volumePct, -Math.round(volumeAmount)))
  let subtotal = Math.round(rawSubtotal - volumeAmount - couponAmount)

  const minTotal = Math.round(rules.min_order_total)
  if (minTotal > 0 && subtotal < minTotal) {
    adjustments.push(adjustment('min_order', 'Pedido mínimo', null, minTotal - subtotal))
    subtotal = minTotal
  }

  const ivaPct = input.margin?.iva ?? 19
  const iva = Math.round(subtotal * (ivaPct / 100))
  const total = subtotal + iva

  // Reparto por modelo según su costo variable (material + máquina); el último absorbe el redondeo
  const variable = models.map((m, i) => {
    const p = calcProductionCost({
      weightG: unitW[i] * m.qty,
      totalHours: unitH[i] * m.qty,
      costPerKg: input.costPerKg,
      infra: { ...input.infra, etiquetas: 0 },
      prep: input.prep,
    })
    return p.total + (rules.labor_per_piece_min / 60) * manoObra * m.qty
  })
  const variableSum = variable.reduce((s, v) => s + v, 0)
  let assigned = 0
  const perModel = models.map((m, i) => {
    if (i === models.length - 1) return { id: m.id, total: total - assigned }
    const share = variableSum > 0 ? variable[i] / variableSum : (pieces > 0 ? m.qty / pieces : 0)
    const t = Math.round(total * share)
    assigned += t
    return { id: m.id, total: t }
  })

  return {
    pieces,
    weight,
    duration,
    beds: bedCount,
    piecesPerBed: maxPerBed,
    matCost: prod.material,
    infraCost: prod.luz + prod.depr + prod.mant,
    laborCost: prod.labor + laborPieces,
    utilityCost: extrasCost,
    productionCost: production,
    marginCost: (perUnit.logistics + perUnit.marketing + perUnit.failures + perUnit.profit) * pieces,
    unitPrice,
    grossSubtotal: rawSubtotal,
    volumeDiscount: Math.round(volumeAmount),
    discount: Math.round(couponAmount),
    subtotal,
    iva,
    ivaPct,
    total,
    urgent,
    adjustments,
    models: models.map((m, i) => ({ qty: m.qty, piecesPerBed: ppb[i], weightG: unitW[i], hours: unitH[i] })),
    perModel,
  }
}
