<script setup lang="ts">
import { ref, reactive, watch, type PropType } from 'vue'
import draggable from 'vuedraggable'
import OrderCard from './OrderCard.vue'

const props = defineProps({
  orders: { type: Array as PropType<any[]>, required: true },
  loading: { type: Boolean, default: false }
})

const emit = defineEmits(['update-status', 'assign', 'download', 'view', 'new-order', 'delete', 'download-pdf', 'toggle-paid'])

const scrollContainer = ref<any>(null)

const scroll = (direction: any) => {
  if (scrollContainer.value) {
    const amount = 344 // Ancho de columna (320) + gap (24)
    scrollContainer.value.scrollBy({ left: direction * amount, behavior: 'smooth' })
  }
}

// Mismos nombres que ve el cliente en Rastreo
const columns = [
  { id: 'pending', title: 'Por revisar', color: 'bg-warning' },
  { id: 'printing', title: 'En impresión', color: 'bg-primary' },
  { id: 'post-processing', title: 'En acabado', color: 'bg-[#3b82f6]' },
  { id: 'completed', title: 'Listo', color: 'bg-success' },
  { id: 'shipped', title: 'Enviado', color: 'bg-muted' },
  { id: 'cancelled', title: 'Cancelado (24 h)', color: 'bg-danger' }
]

// Mapa reactivo escribible para que vuedraggable pueda modificar las listas
const groupedOrders = reactive<Record<string, any[]>>({})

const rebuildGroups = (ordersList: any) => {
  // Inicializar groups vacíos
  columns.forEach(col => {
    if (!groupedOrders[col.id]) {
      groupedOrders[col.id] = []
    } else {
      groupedOrders[col.id].splice(0, groupedOrders[col.id].length)
    }
  })
  
  if (!ordersList) return

  ordersList.forEach((o: any) => {
    const rawStatus = o.status?.toLowerCase() || 'pending'
    let status = rawStatus
    
    if (!groupedOrders[status]) {
        if (status === 'shipped') status = 'shipped'
        else if (status === 'cancelled' || status === 'failed') status = 'cancelled'
        else status = 'pending'
    }

    if (groupedOrders[status]) {
      if (status === 'cancelled' && o.updated_at) {
        const updatedTime = new Date(o.updated_at.replace(' ', 'T')).getTime()
        const hoursDiff = (Date.now() - updatedTime) / (1000 * 60 * 60)
        if (hoursDiff <= 24) groupedOrders[status].push(o)
      } else {
        groupedOrders[status].push(o)
      }
    }
  })
}

// Sincronizar groupedOrders cuando cambien las orders prop
watch(() => props.orders, (newOrders) => {
  rebuildGroups(newOrders)
}, { immediate: true })

const onDragChange = (evt: any, newStatus: any) => {
  if (evt.added) {
    const order = evt.added.element
    if (newStatus === 'printing') {
      emit('assign', order)
    } else {
      emit('update-status', { orderId: order.id, status: newStatus })
    }
    // No reconstruir grupos aquí: vuedraggable ya actualizó las listas reactivas,
    // y el padre sincronizará los datos reales vía API
  }
}
</script>

<template>
  <div class="relative">
    <!-- Esqueleto de carga -->
    <div v-if="loading" class="flex gap-4 overflow-x-auto pb-4 no-scrollbar" aria-hidden="true">
      <div v-for="colIdx in 6" :key="'skel-col-'+colIdx" class="w-[300px] shrink-0">
        <div class="h-11 mb-3 rounded-[var(--radius-control)] bg-surface animate-pulse"></div>
        <div class="space-y-3">
          <div v-for="cardIdx in (colIdx < 3 ? 3 : 1)" :key="cardIdx" class="h-40 rounded-[var(--radius-card)] bg-surface animate-pulse"></div>
        </div>
      </div>
    </div>
    <p v-if="loading" class="sr-only" role="status">Cargando pedidos…</p>

    <template v-else>
      <div class="flex items-center justify-end gap-2 mb-3">
        <button type="button" class="icon-btn bg-surface hover:bg-surface-2 text-ink" aria-label="Ver columnas anteriores" @click="scroll(-1)">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </button>
        <button type="button" class="icon-btn bg-surface hover:bg-surface-2 text-ink" aria-label="Ver columnas siguientes" @click="scroll(1)">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </button>
      </div>

      <div ref="scrollContainer" class="flex gap-4 overflow-x-auto pb-4 snap-x scroll-smooth">
        <section v-for="col in columns" :key="col.id" class="w-[300px] md:w-[320px] shrink-0 snap-start flex flex-col" :aria-label="col.title">
          <header class="flex items-center justify-between h-11 px-3 mb-3 rounded-[var(--radius-control)] bg-surface">
            <h2 class="flex items-center gap-2 text-[0.9375rem] font-semibold">
              <span :class="['w-2.5 h-2.5 rounded-full', col.color]" aria-hidden="true"></span>
              {{ col.title }}
            </h2>
            <span class="min-w-7 h-7 px-2 inline-flex items-center justify-center rounded-full bg-surface-2 text-sm font-semibold tabular-nums">{{ (groupedOrders[col.id] || []).length }}</span>
          </header>

          <draggable
            :list="groupedOrders[col.id]"
            group="orders"
            item-key="id"
            class="flex-1 min-h-[420px] space-y-3 rounded-[var(--radius-card)] p-2 -m-2 transition-colors"
            ghost-class="kanban-ghost"
            @change="(evt: any) => onDragChange(evt, col.id)"
          >
            <!-- vuedraggable no tipa sus slots -->
            <!-- @vue-ignore -->
            <template #item="{ element }">
              <OrderCard
                :order="element"
                @assign="$emit('assign', $event)"
                @download="$emit('download', $event)"
                @view="$emit('view', $event)"
                @delete="$emit('delete', $event)"
                @download-pdf="$emit('download-pdf', $event)"
                @update-status="$emit('update-status', $event)"
                @toggle-paid="$emit('toggle-paid', $event)"
              />
            </template>
            <!-- @vue-ignore -->
            <template #footer>
              <p v-if="!(groupedOrders[col.id] || []).length" class="py-10 text-center text-sm text-muted rounded-[var(--radius-card)] border border-dashed border-line">Sin pedidos</p>
            </template>
          </draggable>
        </section>
      </div>
    </template>
  </div>
</template>

<style scoped>
.kanban-ghost { opacity: 0.4; }
</style>
