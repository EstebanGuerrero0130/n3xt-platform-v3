/**
 * Líneas del "Resumen de precio" del cotizador. Deben sumar exactamente el subtotal:
 * cada línea se redondea y "Mano de obra y empaque" absorbe lo que falta (mano de obra,
 * empaque y los pesos del redondeo de las demás).
 */

export interface SummaryAdjustment { type: string; label: string; pct: number | null; amount: number }
export interface SummaryBreakdown {
  matCost: number
  infraCost: number
  utilityCost: number
  marginCost: number
  discount: number
  subtotal: number
  adjustments?: SummaryAdjustment[]
}
export interface SummaryLine { key: string; label: string; amount: number }

export function summaryLines(b: SummaryBreakdown): { costs: SummaryLine[]; adjustments: SummaryAdjustment[]; discount: number } {
  const r = (n: unknown) => Math.round(Number(n) || 0)
  const adjustments = (b.adjustments ?? []).filter(a => r(a.amount) !== 0).map(a => ({ ...a, amount: r(a.amount) }))
  const discount = r(b.discount)
  const costs: SummaryLine[] = [
    { key: 'material', label: 'Material', amount: r(b.matCost) },
    { key: 'infra', label: 'Máquina y energía', amount: r(b.infraCost) },
    { key: 'extras', label: 'Extras', amount: r(b.utilityCost) },
    { key: 'margin', label: 'Operación y margen', amount: r(b.marginCost) },
  ]
  const shown = costs.reduce((s, l) => s + l.amount, 0) + adjustments.reduce((s, a) => s + a.amount, 0) - discount
  const labor = r(b.subtotal) - shown
  if (labor !== 0) costs.splice(2, 0, { key: 'labor', label: 'Mano de obra y empaque', amount: labor })
  return { costs, adjustments, discount }
}
