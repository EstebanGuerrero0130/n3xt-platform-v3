/**
 * Calibración del tiempo de impresión: textos del panel a partir de las
 * estadísticas de GET /admin/calibration (razón real / estimado).
 */

export interface CalibrationRow {
  samples: number
  median_ratio: number | null
  suggested_factor: number | null
}

/** "tarda 12 % más de lo calculado" · "tarda 8 % menos…" · "tarda lo calculado" */
export function deviationText(ratio: number | null): string {
  if (ratio === null || !Number.isFinite(ratio)) return 'Sin datos'
  const pct = Math.round((ratio - 1) * 100)
  if (pct === 0) return 'Tarda lo calculado'
  return `Tarda ${Math.abs(pct)} % ${pct > 0 ? 'más' : 'menos'} de lo calculado`
}

/** Impresiones que faltan para sugerir un ajuste (0 si ya alcanza). */
export function missingSamples(samples: number, min = 5): number {
  return Math.max(0, min - samples)
}

/** Factor en texto (×1,12), con coma decimal de Colombia. */
export function factorText(factor: number | null | undefined): string {
  if (factor === null || factor === undefined) return '—'
  return `×${factor.toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}
