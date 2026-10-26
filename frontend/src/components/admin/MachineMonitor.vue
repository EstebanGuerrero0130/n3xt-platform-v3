<script setup lang="ts">
import { localDateISO } from '../../utils/format'
import { computed, type PropType } from 'vue'

const props = defineProps({
  printers: { type: Array as PropType<any[]>, required: true },
  loading: { type: Boolean, default: false }
})

const emit = defineEmits(['sync', 'add-printer', 'update-printer-status', 'maintenance-complete', 'edit-printer', 'delete-printer'])

const STATUSES = [
  { id: 'idle', label: 'Libre' },
  { id: 'printing', label: 'Imprimiendo' },
  { id: 'maintenance', label: 'Mantenimiento' },
  { id: 'offline', label: 'Apagada' },
]

// Reinicia el contador de horas: se confirma porque no se puede deshacer
const completeMaintenance = (printer: any) => {
  if (!confirm(`¿Registrar el mantenimiento de ${printer.name}? El contador de ${Math.round(printer.total_hours_run || 0)} h vuelve a cero.`)) return
  emit('maintenance-complete', printer.id)
}

const getStatusLabel = (status: any) => {
  const labels: Record<string, string> = {
    'idle': 'Libre',
    'printing': 'Imprimiendo',
    'maintenance': 'Mantenimiento',
    'offline': 'Apagada'
  }
  return labels[status] || status
}

const getStatusColor = (status: any) => {
  const colors: Record<string, string> = {
    'idle': 'bg-success/10 text-success',
    'printing': 'bg-primary text-white',
    'maintenance': 'bg-warning/10 text-warning',
    'offline': 'bg-surface-2 text-muted'
  }
  return colors[status] || 'bg-surface-2 text-muted'
}

const getMaintenanceProgress = (printer: any) => {
  const run = printer.total_hours_run || 0
  const interval = printer.maintenance_interval_h || 200
  return Math.min((run / interval) * 100, 100)
}

const isMaintenanceNear = (printer: any) => {
  const run = printer.total_hours_run || 0
  const interval = printer.maintenance_interval_h || 200
  return (run / interval) >= 0.8
}

const isMaintenanceOverdue = (printer: any) => {
  if (!printer.next_maintenance) return false
  const today = localDateISO()
  return printer.next_maintenance <= today
}

const overduePrintersCount = computed(() => {
  return props.printers.filter((p: any) => isMaintenanceOverdue(p)).length
})
</script>

<template>
  <div class="space-y-8">
    <header class="flex flex-col md:flex-row md:items-end md:justify-between gap-6">
      <div>
        <h1 class="admin-title">Maquinaria <span class="text-brand">del taller</span></h1>
        <p class="admin-lead">Estado de cada impresora y horas hasta el próximo mantenimiento. Las horas se suman al sacar un pedido de impresión.</p>
      </div>
      <button type="button" class="btn-primary btn-lg" @click="$emit('add-printer')">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14m-7-7h14"/></svg>
        Nueva impresora
      </button>
    </header>

    <div v-if="overduePrintersCount > 0" role="alert" class="admin-card border border-warning/40 flex flex-col sm:flex-row sm:items-center gap-3">
      <span class="badge bg-warning/10 text-warning self-start">Mantenimiento vencido</span>
      <p class="text-ink">{{ overduePrintersCount === 1 ? 'Una impresora tiene' : `${overduePrintersCount} impresoras tienen` }} la fecha de mantenimiento vencida: {{ printers.filter(isMaintenanceOverdue).map(p => p.name).join(', ') }}.</p>
    </div>

    <div v-if="loading" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3" aria-hidden="true">
      <div v-for="i in 3" :key="i" class="h-64 admin-card animate-pulse"></div>
    </div>

    <div v-else-if="printers.length" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
      <article v-for="printer in printers" :key="printer.id" class="admin-card flex flex-col" :aria-label="printer.name">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <h2 class="admin-h2 truncate">{{ printer.name }}</h2>
            <p class="text-sm text-muted">{{ printer.model || 'Sin modelo' }} · {{ printer.technology }}</p>
          </div>
          <span :class="['badge shrink-0', getStatusColor(printer.status)]">{{ getStatusLabel(printer.status) }}</span>
        </div>

        <!-- Horas hasta mantenimiento -->
        <div class="mt-5">
          <div class="flex items-baseline justify-between text-sm">
            <span class="text-muted">Horas desde el último mantenimiento</span>
            <span class="font-semibold tabular-nums" :class="getMaintenanceProgress(printer) >= 100 ? 'text-danger' : isMaintenanceNear(printer) ? 'text-warning' : ''">{{ Math.round(printer.total_hours_run || 0) }} / {{ printer.maintenance_interval_h || 200 }} h</span>
          </div>
          <div class="mt-2 h-2 rounded-full bg-surface-2 overflow-hidden" role="progressbar" :aria-valuenow="Math.round(getMaintenanceProgress(printer))" aria-valuemin="0" aria-valuemax="100" :aria-label="`Uso hasta mantenimiento de ${printer.name}`">
            <div class="h-full rounded-full" :class="getMaintenanceProgress(printer) >= 100 ? 'bg-danger' : isMaintenanceNear(printer) ? 'bg-warning' : 'bg-primary'" :style="{ width: getMaintenanceProgress(printer) + '%' }"></div>
          </div>
          <p class="mt-2 text-sm text-muted">Próxima revisión: <span :class="isMaintenanceOverdue(printer) ? 'text-danger font-semibold' : 'text-ink'">{{ printer.next_maintenance || 'sin fecha' }}</span></p>
        </div>

        <!-- Estado manual -->
        <fieldset class="mt-5">
          <legend class="admin-label mb-2">Cambiar estado</legend>
          <div class="flex flex-wrap gap-2">
            <button
              v-for="st in STATUSES" :key="st.id"
              type="button"
              class="chip !min-h-9 !px-3 text-sm"
              :aria-pressed="printer.status === st.id"
              @click="printer.status !== st.id && $emit('update-printer-status', { id: printer.id, status: st.id })"
            >{{ st.label }}</button>
          </div>
        </fieldset>

        <div class="mt-auto pt-5 flex flex-wrap items-center gap-2">
          <button type="button" class="btn-outlined btn-sm" @click="completeMaintenance(printer)">Registrar mantenimiento</button>
          <button type="button" class="btn-outlined btn-sm" @click="$emit('edit-printer', printer)">Editar</button>
          <button type="button" class="icon-btn ml-auto text-muted hover:text-danger hover:bg-danger/10" :aria-label="`Eliminar ${printer.name}`" title="Eliminar" @click="$emit('delete-printer', printer.id)">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
          </button>
        </div>
      </article>
    </div>

    <div v-else class="admin-card py-14 text-center">
      <p class="font-semibold">Aún no hay impresoras registradas.</p>
      <p class="mt-1 text-muted">Agrega tu primera máquina FDM o de resina para asignarle pedidos.</p>
      <button type="button" class="mt-6 btn-primary" @click="$emit('add-printer')">Agregar impresora</button>
    </div>
  </div>
</template>
