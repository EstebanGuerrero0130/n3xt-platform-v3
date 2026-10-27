import { describe, it, expect } from 'vitest'
import { buildSchedule } from '../utils/productionSchedule'

const now = new Date('2026-10-28T10:00:00Z')
const printers = [
  { id: 1, name: 'Ender', technology: 'FDM', status: 'printing' },
  { id: 2, name: 'Bambu', technology: 'FDM', status: 'idle' },
  { id: 3, name: 'Saturn', technology: 'SLA', status: 'maintenance' },
]

describe('buildSchedule', () => {
  it('ubica lo que se imprime desde su hora de inicio', () => {
    const { rows } = buildSchedule(printers, [
      { id: 10, status: 'printing', printer_id: 1, estimated_duration_h: 4, printing_started_at: '2026-10-28T08:00:00Z' },
    ], now)
    const slot = rows[0].slots[0]
    expect(slot.state).toBe('printing')
    expect(slot.end.toISOString()).toBe('2026-10-28T12:00:00.000Z')
    expect(rows[0].freeAt.toISOString()).toBe('2026-10-28T12:00:00.000Z')
  })

  it('reparte la cola a la máquina compatible libre más pronto, en orden de llegada', () => {
    const { rows } = buildSchedule(printers, [
      { id: 10, status: 'printing', printer_id: 1, estimated_duration_h: 4, printing_started_at: '2026-10-28T08:00:00Z' },
      { id: 21, status: 'pending', technology: 'FDM', estimated_duration_h: 3, created_at: '2026-10-27T09:00:00Z' },
      { id: 20, status: 'pending', technology: 'fdm', estimated_duration_h: 5, created_at: '2026-10-27T08:00:00Z' },
    ], now)
    // El 20 llegó primero: va a la Bambu (libre ya); el 21 a la Ender, libre a las 12
    expect(rows[1].slots.map(s => s.order.id)).toEqual([20])
    expect(rows[1].slots[0].start.toISOString()).toBe(now.toISOString())
    expect(rows[0].slots.map(s => s.order.id)).toEqual([10, 21])
    expect(rows[0].slots[1].start.toISOString()).toBe('2026-10-28T12:00:00.000Z')
  })

  it('los urgentes van primero y luego en orden de llegada', () => {
    const { rows } = buildSchedule(printers, [
      { id: 10, status: 'printing', printer_id: 1, estimated_duration_h: 4, printing_started_at: '2026-10-28T08:00:00Z' },
      { id: 20, status: 'pending', technology: 'FDM', estimated_duration_h: 5, created_at: '2026-10-27T08:00:00Z' },
      { id: 22, status: 'pending', technology: 'FDM', estimated_duration_h: 2, created_at: '2026-10-27T10:00:00Z', is_urgent: 1 },
      { id: 21, status: 'pending', technology: 'FDM', estimated_duration_h: 3, created_at: '2026-10-27T09:00:00Z', is_urgent: true },
    ], now)
    // 21 (urgente más antiguo) a la Bambu ya; 22 a la Ender a las 12; el 20 espera a la Bambu (13:00)
    expect(rows[1].slots.map(s => s.order.id)).toEqual([21, 20])
    expect(rows[0].slots.map(s => s.order.id)).toEqual([10, 22])
    expect(rows[0].slots[1].start.toISOString()).toBe('2026-10-28T12:00:00.000Z')
    expect(rows[1].slots[1].start.toISOString()).toBe('2026-10-28T13:00:00.000Z')
  })

  it('con dos máquinas libres a la vez, reparte entre ambas', () => {
    const { rows } = buildSchedule(printers, [
      { id: 50, status: 'pending', technology: 'FDM', estimated_duration_h: 0, created_at: '2026-10-27T08:00:00Z' },
      { id: 51, status: 'pending', technology: 'FDM', estimated_duration_h: 0, created_at: '2026-10-27T09:00:00Z' },
    ], now)
    expect(rows[0].slots.map(s => s.order.id)).toEqual([50])
    expect(rows[1].slots.map(s => s.order.id)).toEqual([51])
  })

  it('no agenda en máquinas en mantenimiento ni pedidos del catálogo', () => {
    const { rows, unscheduled } = buildSchedule(printers, [
      { id: 30, status: 'pending', technology: 'SLA', estimated_duration_h: 2 },
      { id: 31, status: 'pending', technology: 'CATALOGO' },
      { id: 32, status: 'printing', printer_id: null, estimated_duration_h: 1 },
    ], now)
    expect(rows[2].slots).toEqual([])
    expect(unscheduled.map(o => o.id)).toEqual([32, 30])
  })

  it('marca como atrasado lo que ya debió terminar', () => {
    const { rows } = buildSchedule(printers, [
      { id: 40, status: 'printing', printer_id: 2, estimated_duration_h: 1, printing_started_at: '2026-10-28T06:00:00Z' },
    ], now)
    expect(rows[1].slots[0].overdue).toBe(true)
    expect(rows[1].freeAt.toISOString()).toBe(now.toISOString())
  })
})
