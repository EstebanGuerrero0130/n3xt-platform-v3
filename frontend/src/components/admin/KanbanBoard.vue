<script setup>
import { computed, ref } from 'vue'
import draggable from 'vuedraggable'
import OrderCard from './OrderCard.vue'

const props = defineProps({
  orders: { type: Array, required: true }
})

const emit = defineEmits(['update-status', 'assign', 'download', 'view', 'new-order', 'delete', 'download-pdf', 'toggle-paid'])

const scrollContainer = ref(null)

const scroll = (direction) => {
  if (scrollContainer.value) {
    const amount = 344 // Ancho de columna (320) + gap (24)
    scrollContainer.value.scrollBy({ left: direction * amount, behavior: 'smooth' })
  }
}

const columns = [
  { id: 'pending', title: 'Pendientes', color: 'bg-red-500' },
  { id: 'printing', title: 'Impresora', color: 'bg-amber-500' },
  { id: 'post-processing', title: 'Post-Pro', color: 'bg-blue-500' },
  { id: 'completed', title: 'Terminado', color: 'bg-indigo-500' },
  { id: 'shipped', title: 'Enviados', color: 'bg-emerald-500' },
  { id: 'cancelled', title: 'Fallida / Cancelada', color: 'bg-rose-900' }
]

// Agrupamos las órdenes por estado de forma reactiva y eficiente
const ordersByStatus = computed(() => {
  const grouped = {}
  columns.forEach(col => grouped[col.id] = [])
  
  if (!props.orders) return grouped

  props.orders.forEach(o => {
    // Normalización de estado
    const rawStatus = o.status?.toLowerCase() || 'pending'
    let status = rawStatus
    
    // Mapeo de seguridad para estados huérfanos
    if (!grouped[status]) {
        if (status === 'shipped') status = 'shipped'
        else if (status === 'cancelled' || status === 'failed') status = 'cancelled'
        else status = 'pending'
    }

    if (grouped[status]) {
      // Auto-hide cancelled orders after 24 hours
      if (status === 'cancelled' && o.updated_at) {
        const updatedTime = new Date(o.updated_at.replace(' ', 'T')).getTime()
        const hoursDiff = (Date.now() - updatedTime) / (1000 * 60 * 60)
        if (hoursDiff <= 24) grouped[status].push(o)
      } else {
        grouped[status].push(o)
      }
    }
  })
  return grouped
})

const onDragChange = (evt, newStatus) => {
  if (evt.added) {
    const order = evt.added.element
    // Only allow manual status changes if not moving to 'printing' (which requires printer assignment)
    if (newStatus === 'printing') {
      emit('assign', order)
    } else {
      emit('update-status', { orderId: order.id, status: newStatus })
    }
  }
}
</script>

<template>
  <div class="relative group/kanban">
    <!-- Botones de Navegación Lateral -->
    <button 
      @click="scroll(-1)"
      class="absolute left-0 top-1/2 -translate-y-1/2 -translate-x-4 md:-translate-x-6 z-10 w-12 h-12 md:w-14 md:h-14 bg-white dark:bg-gray-800 shadow-2xl rounded-full flex items-center justify-center text-gray-400 dark:text-gray-500 hover:text-primary transition-all duration-300 opacity-100 md:opacity-0 md:group-hover/kanban:opacity-100 md:group-hover/kanban:translate-x-0 border border-gray-100 dark:border-white/5"
    >
      <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 19l-7-7 7-7"/></svg>
    </button>

    <button 
      @click="scroll(1)"
      class="absolute right-0 top-1/2 -translate-y-1/2 translate-x-4 md:translate-x-6 z-10 w-12 h-12 md:w-14 md:h-14 bg-white dark:bg-gray-800 shadow-2xl rounded-full flex items-center justify-center text-gray-400 dark:text-gray-500 hover:text-primary transition-all duration-300 opacity-100 md:opacity-0 md:group-hover/kanban:opacity-100 md:group-hover/kanban:translate-x-0 border border-gray-100 dark:border-white/5"
    >
      <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 5l7 7-7 7"/></svg>
    </button>

    <!-- Container -->
    <div ref="scrollContainer" class="flex gap-6 md:gap-8 overflow-x-auto pb-8 snap-x no-scrollbar scroll-smooth">
      <div v-for="(col, idx) in columns" :key="col.id" 
        class="flex flex-col min-h-[75vh] min-w-[320px] md:min-w-[340px] w-[320px] md:w-[340px] snap-center animate-slide-up" 
        :style="{ animationDelay: (idx * 150) + 'ms' }"
      >
        <!-- Column Header: Premium Glass -->
        <div class="flex items-center justify-between mb-6 px-6 py-4 bg-white/80 dark:bg-gray-950/80 backdrop-blur-2xl rounded-[2rem] border border-white dark:border-white/10 shadow-xl shadow-gray-200/20 dark:shadow-none group/header relative overflow-hidden">
          <div class="absolute inset-0 bg-gradient-to-r from-primary/5 to-transparent opacity-0 group-hover/header:opacity-100 transition-opacity"></div>
          <div class="flex items-center gap-4 relative z-10">
            <div :class="['w-4 h-4 rounded-full shadow-lg shadow-black/5 ring-4 ring-white dark:ring-gray-900', col.color]"></div>
            <h3 class="font-black text-gray-900 dark:text-white uppercase tracking-[0.2em] text-[10px]">{{ col.title }}</h3>
          </div>
          <span class="bg-gray-900 dark:bg-white text-white dark:text-gray-900 text-[9px] font-black px-3 py-1 rounded-full tabular-nums relative z-10">
            {{ ordersByStatus[col.id].length }}
          </span>
        </div>

        <!-- Column Content (Draggable Area) -->
        <draggable 
          :list="ordersByStatus[col.id]" 
          group="orders"
          item-key="id"
          class="flex-1 bg-gray-100/30 dark:bg-white/5 backdrop-blur-sm rounded-[3rem] p-5 space-y-6 border-2 border-dashed border-gray-200/50 dark:border-white/10 min-h-[600px] hover:bg-gray-100/50 dark:hover:bg-white/10 hover:border-primary/20"
          ghost-class="opacity-50"
          drag-class="rotate-3 scale-105"
          @change="(evt) => onDragChange(evt, col.id)"
        >
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
        </draggable>
      </div>
    </div>
  </div>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
  display: none;
}
.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>
