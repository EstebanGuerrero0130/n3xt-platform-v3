/**
 * Volumen de impresión: medidas de cada máquina y si una pieza cabe en ella.
 * La pieza puede girarse, así que se comparan las medidas ordenadas.
 */

export interface Dims { x: number; y: number; z: number }
export type Capacity = Partial<Record<'FDM' | 'SLA', Dims>>

const sorted = (d: Dims) => [d.x, d.y, d.z].map(v => Number(v) || 0).sort((a, b) => a - b)

/** Cabe en alguna orientación (girando la pieza en ángulos rectos) */
export function fitsIn(piece: Dims, volume: Dims): boolean {
  const p = sorted(piece)
  const v = sorted(volume)
  return p.every((side, i) => side <= v[i])
}

const TECH_NAME: Record<string, string> = { FDM: 'filamento', SLA: 'resina' }

/** Aviso para el cliente cuando la pieza no cabe; null si cabe o no hay datos. */
export function fitNotice(piece: Dims, technology: string, capacity: Capacity | null): string | null {
  const tech = technology.toUpperCase() as 'FDM' | 'SLA'
  const volume = capacity?.[tech]
  if (!volume || !(piece.x > 0 && piece.y > 0 && piece.z > 0) || fitsIn(piece, volume)) return null
  const size = [piece.x, piece.y, piece.z].map(v => Math.round(v).toLocaleString('es-CO')).join(' × ')
  const plan = tech === 'SLA' ? 'la imprimiremos en partes o con filamento' : 'la imprimiremos en partes'
  return `Esta pieza mide ${size} mm y no cabe en nuestras máquinas de ${TECH_NAME[tech]}; ${plan}`
}

/** Medida del formulario del panel: entero de 1 a 2000 mm, o null si se deja vacía */
export function toBuildMm(v: unknown): number | null {
  if (v === '' || v === null || v === undefined) return null
  const n = Math.round(Number(v))
  return Number.isFinite(n) && n >= 1 && n <= 2000 ? n : null
}
