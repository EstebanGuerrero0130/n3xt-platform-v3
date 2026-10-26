<script setup lang="ts">
import { localDateISO } from '../../utils/format'
import { ref, computed, type PropType } from 'vue'

const props = defineProps({
  orders: { type: Array as PropType<any[]>, required: true },
  loadingAnalytics: { type: Boolean, default: false }
})

defineEmits(['export-report', 'view-details', 'download-pdf', 'print-label', 'delete'])

const searchQuery = ref('')
const statusFilter = ref('all')
const dateRange = ref({
  start: '',
  end: ''
})

const setQuickMonth = (offset: number) => {
  const now = new Date()
  const start = new Date(now.getFullYear(), now.getMonth() + offset, 1)
  const end = new Date(now.getFullYear(), now.getMonth() + offset + 1, 0)
  dateRange.value.start = localDateISO(start)
  dateRange.value.end = localDateISO(end)
}

const filteredOrders = computed(() => {
  return props.orders.filter(order => {
    // 1. Filtrar por estado
    if (statusFilter.value !== 'all' && order.status !== statusFilter.value) {
      return false
    }
    // 2. Filtrar por búsqueda (nombre o ID)
    if (searchQuery.value) {
      const q = searchQuery.value.toLowerCase()
      const matchesSearch = (
        (order.id?.toString() || '').includes(q) ||
        (order.customer_name && order.customer_name.toLowerCase().includes(q))
      )
      if (!matchesSearch) return false
    }
    // 3. Filtrar por fecha
    if (dateRange.value.start) {
      if (new Date(order.created_at || 0) < new Date(dateRange.value.start + 'T00:00:00')) return false
    }
    if (dateRange.value.end) {
      if (new Date(order.created_at || 0) > new Date(dateRange.value.end + 'T23:59:59')) return false
    }
    return true
  }).sort((a, b) => new Date(b.created_at || 0).getTime() - new Date(a.created_at || 0).getTime()) // Más recientes primero
})

// Los cancelados no suman ventas ni material (igual que Contabilidad)
const billableOrders = computed(() => filteredOrders.value.filter(o => o.status !== 'cancelled'))

const metricsSummary = computed(() => {
  const total = billableOrders.value.reduce((acc, o) => acc + (parseFloat(o.total_price) || 0), 0)
  const weight = billableOrders.value.reduce((acc, o) => acc + (parseFloat(o.estimated_weight_g) || 0), 0)
  const count = billableOrders.value.length
  return {
    total,
    weight: (weight / 1000).toFixed(2), // Convert to KG
    count,
    avg: count > 0 ? total / count : 0
  }
})

const topCustomers = computed(() => {
  const map: Record<string, { name: string; total: number; count: number }> = {}
  billableOrders.value.forEach(o => {
    if (!map[o.customer_name]) map[o.customer_name] = { name: o.customer_name, total: 0, count: 0 }
    map[o.customer_name].total += parseFloat(o.total_price) || 0
    map[o.customer_name].count++
  })
  return Object.values(map).sort((a, b) => b.total - a.total).slice(0, 3)
})

const getStatusColor = (status: any) => {
  const map: Record<string, string> = {
    pending: 'bg-warning/10 text-warning',
    printing: 'bg-primary text-white',
    'post-processing': 'bg-[#3b82f6]/15 text-[#2563eb] dark:text-[#93c5fd]',
    completed: 'bg-success/10 text-success',
    shipped: 'bg-surface-2 text-ink',
    cancelled: 'bg-danger/10 text-danger'
  }
  return map[status] || 'bg-surface-2 text-muted'
}

const STATUS_FILTERS = [
  { id: 'all', label: 'Todos' }, { id: 'pending', label: 'Por revisar' }, { id: 'printing', label: 'En impresión' },
  { id: 'post-processing', label: 'En acabado' }, { id: 'completed', label: 'Listos' }, { id: 'shipped', label: 'Enviados' },
  { id: 'cancelled', label: 'Cancelados' },
]
const money = (n: any) => '$' + Math.round(Number(n) || 0).toLocaleString('es-CO')
const techLabel = (t: string) => t === 'CATALOGO' ? 'Catálogo' : t
const techCounts = computed(() => {
  const map: Record<string, number> = {}
  billableOrders.value.forEach(o => { const t = techLabel(o.technology || 'Otro'); map[t] = (map[t] || 0) + 1 })
  return Object.entries(map).sort((a, b) => b[1] - a[1])
})

const getStatusLabel = (status: any) => {
  const map: Record<string, string> = {
    pending: 'Por revisar',
    printing: 'En impresión',
    'post-processing': 'En acabado',
    completed: 'Listo',
    shipped: 'Enviado',
    cancelled: 'Cancelado'
  }
  return map[status] || status
}
</script>

<template>
  <div class="space-y-8">
    <header class="flex flex-col md:flex-row md:items-end md:justify-between gap-6">
      <div>
        <h1 class="admin-title">Historial <span class="text-brand">de pedidos</span></h1>
        <p class="admin-lead">Todos los pedidos con filtros por estado y fecha. Los cancelados no suman en las cifras.</p>
      </div>
      <div class="flex flex-wrap gap-3">
        <button type="button" class="btn-outlined btn-lg" :disabled="loadingAnalytics" @click="$emit('export-report', { type: 'ledger', start: dateRange.start, end: dateRange.end, filteredOrders })">Libro de pedidos (PDF)</button>
        <button type="button" class="btn-outlined btn-lg" :disabled="loadingAnalytics" @click="$emit('export-report', { type: 'metrics', start: dateRange.start, end: dateRange.end, filteredOrders })">Balance (PDF)</button>
      </div>
    </header>

    <!-- Filtros -->
    <div class="admin-card space-y-4">
      <div class="grid grid-cols-1 gap-4 lg:grid-cols-12 lg:items-end">
        <div class="lg:col-span-4">
          <label for="history-search" class="admin-label mb-2">Buscar</label>
          <input id="history-search" v-model="searchQuery" type="search" placeholder="Número de pedido o cliente" class="field">
        </div>
        <div class="lg:col-span-5 grid grid-cols-2 gap-3">
          <div>
            <label for="history-from" class="admin-label mb-2">Desde</label>
            <input id="history-from" v-model="dateRange.start" type="date" class="field">
          </div>
          <div>
            <label for="history-to" class="admin-label mb-2">Hasta</label>
            <input id="history-to" v-model="dateRange.end" type="date" class="field">
          </div>
        </div>
        <div class="lg:col-span-3 flex flex-wrap gap-2">
          <button type="button" class="btn-outlined btn-sm" @click="setQuickMonth(0)">Este mes</button>
          <button type="button" class="btn-outlined btn-sm" @click="setQuickMonth(-1)">Mes anterior</button>
          <button v-if="dateRange.start || dateRange.end" type="button" class="btn-sm px-3 rounded-full text-brand font-semibold hover:underline" @click="dateRange.start = ''; dateRange.end = ''">Quitar fechas</button>
        </div>
      </div>
      <div class="flex flex-wrap gap-2" role="group" aria-label="Estado">
        <button v-for="f in STATUS_FILTERS" :key="f.id" type="button" class="chip !min-h-9 !px-3 text-sm" :aria-pressed="statusFilter === f.id" @click="statusFilter = f.id">{{ f.label }}</button>
      </div>
    </div>

    <!-- Cifras del filtro -->
    <dl class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4">
      <div class="admin-card"><dt class="admin-label">Pedidos</dt><dd class="mt-2 admin-kpi">{{ metricsSummary.count }}</dd></div>
      <div class="admin-card"><dt class="admin-label">Ventas</dt><dd class="mt-2 admin-kpi">{{ money(metricsSummary.total) }}</dd></div>
      <div class="admin-card"><dt class="admin-label">Valor promedio</dt><dd class="mt-2 admin-kpi">{{ money(metricsSummary.avg) }}</dd></div>
      <div class="admin-card"><dt class="admin-label">Material</dt><dd class="mt-2 admin-kpi">{{ metricsSummary.weight }} kg</dd></div>
    </dl>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-12 items-start">
      <!-- Tabla -->
      <section class="admin-card xl:col-span-8" aria-label="Pedidos">
        <div class="overflow-x-auto -mx-1 px-1">
          <table class="admin-table min-w-[52rem]">
            <thead><tr><th>Pedido</th><th>Fecha</th><th>Cliente</th><th>Detalle</th><th>Valor</th><th>Estado</th><th class="sr-only">Acciones</th></tr></thead>
            <tbody>
              <tr v-for="order in filteredOrders" :key="order.id">
                <td class="font-semibold tabular-nums">#{{ order.id }}</td>
                <td class="tabular-nums whitespace-nowrap">{{ new Date(order.created_at).toLocaleDateString('es-CO', { day: 'numeric', month: 'short', year: 'numeric' }) }}</td>
                <td>
                  <span class="block font-medium truncate max-w-[14rem]">{{ order.customer_name || 'Sin nombre' }}</span>
                  <span class="block text-sm text-muted truncate max-w-[14rem]">{{ order.customer_email || 'Sin correo' }}</span>
                </td>
                <td class="text-sm whitespace-nowrap">{{ techLabel(order.technology) }}<template v-if="Number(order.estimated_weight_g) > 0"> · {{ Math.round(order.estimated_weight_g) }} g</template></td>
                <td class="tabular-nums font-semibold whitespace-nowrap">{{ money(order.total_price) }}</td>
                <td><span :class="['badge', getStatusColor(order.status)]">{{ getStatusLabel(order.status) }}</span></td>
                <td class="text-right whitespace-nowrap">
                  <button type="button" class="icon-btn inline-flex text-muted hover:text-ink hover:bg-surface-2" title="Ver detalle" :aria-label="`Ver el pedido #${order.id}`" @click="$emit('view-details', order)">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                  </button>
                  <button type="button" class="icon-btn inline-flex text-muted hover:text-ink hover:bg-surface-2" title="Cotización en PDF" :aria-label="`Cotización del pedido #${order.id}`" @click="$emit('download-pdf', order)">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                  </button>
                  <button v-if="order.status === 'shipped' || order.status === 'completed'" type="button" class="icon-btn inline-flex text-muted hover:text-ink hover:bg-surface-2" title="Rótulo de envío" :aria-label="`Rótulo del pedido #${order.id}`" @click="$emit('print-label', order)">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                  </button>
                  <button type="button" class="icon-btn inline-flex text-muted hover:text-danger hover:bg-danger/10" title="Eliminar" :aria-label="`Eliminar el pedido #${order.id}`" @click="$emit('delete', order.id)">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                  </button>
                </td>
              </tr>
              <tr v-if="!filteredOrders.length"><td colspan="7" class="py-12 text-center text-muted">Ningún pedido coincide con los filtros.</td></tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Resumen lateral -->
      <aside class="xl:col-span-4 space-y-6">
        <section class="admin-card" aria-labelledby="history-tech">
          <h2 id="history-tech" class="admin-h2">Por tecnología</h2>
          <ul class="mt-4 space-y-3">
            <li v-for="[tech, count] in techCounts" :key="tech">
              <div class="flex justify-between text-sm"><span>{{ tech }}</span><span class="tabular-nums text-muted">{{ count }}</span></div>
              <div class="mt-1.5 h-2 rounded-full bg-surface-2 overflow-hidden" aria-hidden="true"><div class="h-full rounded-full bg-primary" :style="{ width: (count / (metricsSummary.count || 1)) * 100 + '%' }"></div></div>
            </li>
            <li v-if="!techCounts.length" class="text-sm text-muted">Sin pedidos en el filtro.</li>
          </ul>
        </section>
        <section class="admin-card" aria-labelledby="history-top">
          <h2 id="history-top" class="admin-h2">Mejores clientes</h2>
          <ol class="mt-4 space-y-3">
            <li v-for="(customer, idx) in topCustomers" :key="customer.name" class="flex items-center gap-3">
              <span class="w-7 h-7 shrink-0 rounded-full bg-surface-2 inline-flex items-center justify-center text-sm font-semibold tabular-nums">{{ idx + 1 }}</span>
              <span class="min-w-0 flex-1">
                <span class="block font-medium truncate">{{ customer.name || 'Sin nombre' }}</span>
                <span class="block text-sm text-muted">{{ customer.count }} {{ customer.count === 1 ? 'pedido' : 'pedidos' }}</span>
              </span>
              <span class="tabular-nums font-semibold">{{ money(customer.total) }}</span>
            </li>
            <li v-if="!topCustomers.length" class="text-sm text-muted">Sin datos en el filtro.</li>
          </ol>
        </section>
      </aside>
    </div>
  </div>
</template>
