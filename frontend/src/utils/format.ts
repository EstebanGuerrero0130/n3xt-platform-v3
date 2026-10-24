/**
 * Utilidades de formato de precios (COP).
 * Un único parser para que catálogo y detalle muestren lo mismo.
 */

const copFormatter = new Intl.NumberFormat('es-CO', {
  style: 'currency',
  currency: 'COP',
  maximumFractionDigits: 0,
})

/**
 * Convierte un precio (número o texto como "45.000", "$45,000", "45000 COP")
 * a número. Devuelve null si no hay un valor numérico ("Consultar", "", null).
 * Los puntos/comas seguidos de exactamente 3 dígitos se tratan como separadores de miles.
 */
export function parsePrice(value: unknown): number | null {
  if (typeof value === 'number') return Number.isFinite(value) ? value : null
  if (typeof value !== 'string') return null

  const cleaned = value.replace(/[^\d.,-]/g, '')
  if (!/\d/.test(cleaned)) return null

  let normalized: string
  if (/^-?\d{1,3}([.,]\d{3})+$/.test(cleaned)) {
    // "45.000" / "1.250.000" / "45,000" → miles
    normalized = cleaned.replace(/[.,]/g, '')
  } else {
    // Decimal opcional al final ("45000.50" / "45000,5"), el resto son separadores
    const m = cleaned.match(/^(-?[\d.,]*?)[.,](\d{1,2})$/)
    normalized = m ? `${m[1].replace(/[.,]/g, '')}.${m[2]}` : cleaned.replace(/[.,]/g, '')
  }

  const n = Number(normalized)
  return Number.isFinite(n) ? n : null
}

/** Formatea un precio en COP; si no es numérico o es <= 0 devuelve el fallback ("Consultar"). */
export function formatCOP(value: unknown, fallback = 'Consultar'): string {
  const n = parsePrice(value)
  if (n === null || n <= 0) return fallback
  return copFormatter.format(n)
}
