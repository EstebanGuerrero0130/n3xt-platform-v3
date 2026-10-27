/**
 * Calendario de producción: qué imprime cada máquina y cuándo termina.
 *
 * Lo que está en impresión empieza en `printing_started_at`; la cola (pedidos por
 * revisar) se reparte, en orden de llegada, a la máquina libre más pronto de la
 * misma tecnología. Es una estimación con las horas calculadas de cada pedido.
 */

export interface SchedulePrinter { id: number | string; name: string; technology?: string; status?: string }
export interface ScheduleOrder {
  id: number | string
  status?: string
  technology?: string
  printer_id?: number | string | null
  estimated_duration_h?: number | string | null
  printing_started_at?: string | null
  updated_at?: string | null
  created_at?: string | null
  [key: string]: unknown
}

export interface ScheduleSlot {
  order: ScheduleOrder
  start: Date
  end: Date
  /** printing: ya está en la máquina · queued: estimado de la cola */
  state: 'printing' | 'queued'
  /** Ya debió terminar según las horas estimadas */
  overdue: boolean
}

export interface PrinterSchedule {
  printer: SchedulePrinter
  slots: ScheduleSlot[]
  /** Cuándo queda libre después de todo lo asignado */
  freeAt: Date
  /** En mantenimiento o apagada: no recibe trabajos de la cola */
  inMaintenance: boolean
  offline: boolean
}

export interface ProductionSchedule {
  rows: PrinterSchedule[]
  /** Pedidos sin máquina compatible (o en impresión sin máquina asignada) */
  unscheduled: ScheduleOrder[]
}

const HOUR = 3_600_000
const tech = (v?: string) => (v || '').toUpperCase()
const hoursOf = (o: ScheduleOrder) => Math.max(Number(o.estimated_duration_h) || 0, 0)
const time = (v?: string | null) => (v ? new Date(v).getTime() : NaN)

export function buildSchedule(printers: SchedulePrinter[], orders: ScheduleOrder[], now = new Date()): ProductionSchedule {
  const rows: PrinterSchedule[] = printers.map(p => ({
    printer: p, slots: [], freeAt: now, inMaintenance: p.status === 'maintenance', offline: p.status === 'offline',
  }))
  const byId = new Map(rows.map(r => [String(r.printer.id), r]))
  const unscheduled: ScheduleOrder[] = []

  // 1. Lo que ya se está imprimiendo
  for (const o of orders.filter(o => o.status === 'printing')) {
    const row = o.printer_id != null ? byId.get(String(o.printer_id)) : undefined
    if (!row) { unscheduled.push(o); continue }
    const startMs = [time(o.printing_started_at), time(o.updated_at)].find(t => !Number.isNaN(t)) ?? now.getTime()
    const start = new Date(startMs)
    const end = new Date(startMs + hoursOf(o) * HOUR)
    row.slots.push({ order: o, start, end, state: 'printing', overdue: end < now })
    if (end > row.freeAt) row.freeAt = end
  }

  // 2. La cola, en orden de llegada, a la máquina compatible libre más pronto
  const queue = orders
    .filter(o => o.status === 'pending' && tech(o.technology) !== 'CATALOGO')
    .sort((a, b) => (time(a.created_at) || 0) - (time(b.created_at) || 0))

  for (const o of queue) {
    const candidates = rows.filter(r => !r.inMaintenance && !r.offline && tech(r.printer.technology) === tech(o.technology))
    const preferred = o.printer_id != null ? candidates.find(r => String(r.printer.id) === String(o.printer_id)) : undefined
    const row = preferred ?? candidates.sort((a, b) => a.freeAt.getTime() - b.freeAt.getTime() || a.slots.length - b.slots.length)[0]
    if (!row) { unscheduled.push(o); continue }
    const start = row.freeAt
    const end = new Date(start.getTime() + hoursOf(o) * HOUR)
    row.slots.push({ order: o, start, end, state: 'queued', overdue: false })
    row.freeAt = end
  }

  return { rows, unscheduled }
}
