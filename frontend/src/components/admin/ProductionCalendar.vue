<script setup lang="ts">
/**
 * Calendario de producción: una fila por máquina con lo que imprime y la cola
 * estimada (ver utils/productionSchedule). La ventana va de hoy a 48 h.
 */
import { computed, ref, onMounted, onUnmounted, type PropType } from 'vue'
import { buildSchedule, type SchedulePrinter, type ScheduleOrder, type ScheduleSlot } from '../../utils/productionSchedule'

const props = defineProps({
  printers: { type: Array as PropType<SchedulePrinter[]>, default: () => [] },
  orders: { type: Array as PropType<ScheduleOrder[]>, default: () => [] },
})
defineEmits<{ (e: 'view', order: ScheduleOrder): void }>()

const WINDOW_H = 48
const HOUR = 3_600_000

// Se refresca cada minuto para que la línea de "ahora" avance
const now = ref(new Date())
let timer: ReturnType<typeof setInterval> | undefined
onMounted(() => { timer = setInterval(() => { now.value = new Date() }, 60_000) })
onUnmounted(() => clearInterval(timer))

const schedule = computed(() => buildSchedule(props.printers, props.orders, now.value))

// La ventana empieza a la hora en punto anterior (o antes, si algo empezó ayer)
const windowStart = computed(() => {
  const d = new Date(now.value)
  d.setMinutes(0, 0, 0)
  d.setHours(d.getHours() - 2)
  return d
})
const windowEnd = computed(() => new Date(windowStart.value.getTime() + WINDOW_H * HOUR))

const pct = (d: Date) => Math.min(Math.max((d.getTime() - windowStart.value.getTime()) / (WINDOW_H * HOUR), 0), 1) * 100
const barStyle = (s: ScheduleSlot) => {
  const left = pct(s.start)
  return { left: `${left}%`, width: `${Math.max(pct(s.end) - left, 0.8)}%` }
}
const visible = (s: ScheduleSlot) => s.end > windowStart.value && s.start < windowEnd.value

const ticks = computed(() => Array.from({ length: WINDOW_H / 6 + 1 }, (_, i) => new Date(windowStart.value.getTime() + i * 6 * HOUR)))

const fmtTime = new Intl.DateTimeFormat('es-CO', { hour: 'numeric', minute: '2-digit' })
const fmtDay = new Intl.DateTimeFormat('es-CO', { weekday: 'short', day: 'numeric' })
const isToday = (d: Date) => d.toDateString() === now.value.toDateString()
const when = (d: Date) => (isToday(d) ? '' : fmtDay.format(d) + ', ') + fmtTime.format(d)

const label = (o: ScheduleOrder) => String(o.job_name || o.original_filename || `Pedido #${o.id}`)
const hours = (o: ScheduleOrder) => Math.round((Number(o.estimated_duration_h) || 0) * 10) / 10
</script>

<template>
  <section class="space-y-4" aria-labelledby="calendar-title">
    <div class="flex flex-wrap items-baseline justify-between gap-3">
      <h2 id="calendar-title" class="admin-h2">Calendario de producción</h2>
      <p class="text-sm text-muted">Próximas 48 h · la cola es un estimado con las horas de cada pedido</p>
    </div>

    <p v-if="!printers.length" class="admin-card py-10 text-center text-muted">Agrega tus máquinas en Maquinaria para ver el calendario.</p>

    <article v-for="row in schedule.rows" :key="row.printer.id" class="admin-card">
      <header class="flex flex-wrap items-center justify-between gap-2">
        <div class="min-w-0">
          <h3 class="font-semibold truncate">{{ row.printer.name }} <span class="text-sm font-normal text-muted">{{ row.printer.technology }}</span></h3>
        </div>
        <span v-if="row.inMaintenance" class="badge bg-warning/15 text-warning">En mantenimiento</span>
        <span v-else-if="row.offline" class="badge bg-surface-2 text-muted">Apagada</span>
        <span v-else-if="!row.slots.length" class="badge bg-success/10 text-success">Libre</span>
        <span v-else class="text-sm text-muted">Libre desde <strong class="text-ink tabular-nums">{{ when(row.freeAt) }}</strong></span>
      </header>

      <!-- Línea de tiempo (decorativa: la lista de abajo dice lo mismo en texto) -->
      <div class="mt-4 relative h-9 rounded-[var(--radius-control)] bg-surface-2 overflow-hidden" aria-hidden="true">
        <span v-for="t in ticks" :key="t.getTime()" class="absolute inset-y-0 w-px bg-line" :style="{ left: `${pct(t)}%` }" />
        <button
          v-for="s in row.slots.filter(visible)"
          :key="`${s.order.id}-${s.state}`"
          type="button"
          tabindex="-1"
          :class="['absolute inset-y-1 rounded-md px-2 text-left text-xs font-semibold truncate',
                   s.state === 'printing' ? (s.overdue ? 'bg-danger text-white' : 'bg-primary text-white') : 'bg-primary/20 text-ink border border-dashed border-primary/60']"
          :style="barStyle(s)"
          :title="`${label(s.order)} · ${when(s.start)} → ${when(s.end)}`"
          @click="$emit('view', s.order)"
        >{{ label(s.order) }}</button>
        <span class="absolute inset-y-0 w-0.5 bg-danger" :style="{ left: `${pct(now)}%` }" />
      </div>
      <div class="mt-1 relative h-4 text-[11px] text-muted tabular-nums" aria-hidden="true">
        <span v-for="(t, i) in ticks" :key="t.getTime()" class="absolute -translate-x-1/2 whitespace-nowrap" :class="i === 0 ? 'translate-x-0' : i === ticks.length - 1 ? '-translate-x-full' : ''" :style="{ left: `${pct(t)}%` }">
          {{ t.getHours() === 0 ? fmtDay.format(t) : fmtTime.format(t) }}
        </span>
      </div>

      <ol v-if="row.slots.length" class="mt-3 divide-y divide-line text-sm" :aria-label="`Trabajos de ${row.printer.name}`">
        <li v-for="s in row.slots" :key="`${s.order.id}-${s.state}`" class="py-2 flex flex-wrap items-center gap-x-3 gap-y-1">
          <button type="button" class="font-semibold hover:underline text-left min-w-0 truncate" @click="$emit('view', s.order)">{{ label(s.order) }}</button>
          <span v-if="s.state === 'printing' && s.overdue" class="badge bg-danger/10 text-danger">Atrasado</span>
          <span v-else-if="s.state === 'printing'" class="badge bg-primary/15 text-brand">Imprimiendo</span>
          <span v-else class="badge bg-surface-2 text-muted">En cola</span>
          <span v-if="hours(s.order)" class="ml-auto text-muted tabular-nums">{{ when(s.start) }} → {{ when(s.end) }} · {{ hours(s.order) }} h</span>
          <span v-else class="ml-auto text-muted">Sin horas estimadas</span>
        </li>
      </ol>
    </article>

    <section v-if="schedule.unscheduled.length" class="admin-card" aria-labelledby="unscheduled-title">
      <h3 id="unscheduled-title" class="font-semibold">Sin máquina</h3>
      <p class="text-sm text-muted">No hay una máquina disponible de su tecnología, o está en impresión sin máquina asignada.</p>
      <ul class="mt-2 divide-y divide-line text-sm">
        <li v-for="o in schedule.unscheduled" :key="o.id" class="py-2 flex flex-wrap gap-x-3">
          <button type="button" class="font-semibold hover:underline" @click="$emit('view', o)">{{ label(o) }}</button>
          <span class="text-muted">{{ o.technology }} · {{ hours(o) }} h</span>
        </li>
      </ul>
    </section>
  </section>
</template>
