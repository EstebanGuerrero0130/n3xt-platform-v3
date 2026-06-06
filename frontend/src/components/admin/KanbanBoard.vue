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

const columns = [
  { id: 'pending', title: 'Pendientes', color: 'bg-red-500' },
  { id: 'printing', title: 'Impresora', color: 'bg-amber-500' },
  { id: 'post-processing', title: 'Post-Pro', color: 'bg-blue-500' },
  { id: 'completed', title: 'Terminado', color: 'bg-indigo-500' },
  { id: 'shipped', title: 'Enviados', color: 'bg-emerald-500' },
  { id: 'cancelled', title: 'Fallida / Cancelada', color: 'bg-rose-900' }
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

  ordersList.forEach(o => {
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
  <div class="relative group/kanban">
    <!-- Skeleton Loading -->
    <div v-if="loading" class="flex gap-6 md:gap-8 overflow-x-auto pb-8 snap-x no-scrollbar scroll-smooth animate-pulse">
      <div
v-for="colIdx in 6" :key="'skel-col-'+colIdx" 
        class="flex flex-col min-h-[75vh] min-w-[280px] md:min-w-[340px] w-[280px] md:w-[340px] snap-center"
      >
        <!-- Column Header Skeleton -->
        <div class="flex items-center justify-between mb-6 px-6 py-4 bg-gray-100 dark:bg-[#151a22] rounded-[24px]">
          <div class="flex items-center gap-4">
            <div class="w-4 h-4 rounded-full bg-gray-200 dark:bg-[#2a3040]"></div>
            <div class="h-3 w-24 bg-gray-200 dark:bg-[#2a3040] rounded-full"></div>
          </div>
          <div class="h-6 w-8 bg-gray-200 dark:bg-[#2a3040] rounded-full"></div>
        </div>
        
        <!-- Cards Skeleton -->
        <div class="flex-1 bg-gray-100/30 dark:bg-white/5 rounded-[24px] p-5 space-y-6 border-2 border-dashed border-gray-200/50 dark:border-[#21262d]">
          <div
v-for="cardIdx in (colIdx < 2 ? 3 : 2)" :key="'skel-card-'+colIdx+'-'+cardIdx" 
            class="bg-white dark:bg-[#151a22] rounded-[28px] p-6 border border-gray-100 dark:border-[#21262d] space-y-4"
          >
            <div class="flex justify-between items-start">
              <div class="space-y-2 flex-1">
                <div class="h-4 w-3/4 bg-gray-200 dark:bg-[#2a3040] rounded-full"></div>
                <div class="h-3 w-1/2 bg-gray-200 dark:bg-[#2a3040] rounded-full"></div>
              </div>
              <div class="h-8 w-8 bg-gray-200 dark:bg-[#2a3040] rounded-xl"></div>
            </div>
            <div class="flex gap-2">
              <div class="h-5 w-16 bg-gray-200 dark:bg-[#2a3040] rounded-full"></div>
              <div class="h-5 w-20 bg-gray-200 dark:bg-[#2a3040] rounded-full"></div>
            </div>
            <div class="flex justify-between items-center pt-2 border-t border-gray-100 dark:border-[#21262d]">
              <div class="h-4 w-16 bg-gray-200 dark:bg-[#2a3040] rounded-full"></div>
              <div class="h-4 w-12 bg-gray-200 dark:bg-[#2a3040] rounded-full"></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Real Content -->
    <template v-else>
    <!-- Botones de Navegación Lateral -->
    <button 
      class="absolute left-1 top-1/2 -translate-y-1/2 z-10 w-12 h-12 md:w-14 md:h-14 bg-white dark:bg-[#151a22] shadow-2xl rounded-full flex items-center justify-center text-gray-400 dark:text-gray-500 hover:text-primary transition-all duration-300 opacity-100 md:opacity-0 md:group-hover/kanban:opacity-100 border border-gray-100 dark:border-[#21262d]"
      @click="scroll(-1)"
    >
      <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 19l-7-7 7-7"/></svg>
    </button>

    <button 
      class="absolute right-1 top-1/2 -translate-y-1/2 z-10 w-12 h-12 md:w-14 md:h-14 bg-white dark:bg-[#151a22] shadow-2xl rounded-full flex items-center justify-center text-gray-400 dark:text-gray-500 hover:text-primary transition-all duration-300 opacity-100 md:opacity-0 md:group-hover/kanban:opacity-100 border border-gray-100 dark:border-[#21262d]"
      @click="scroll(1)"
    >
      <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 5l7 7-7 7"/></svg>
    </button>

    <!-- Container -->
    <div ref="scrollContainer" class="flex gap-6 md:gap-8 overflow-x-auto pb-8 snap-x no-scrollbar scroll-smooth">
      <div
v-for="(col, idx) in columns" :key="col.id" 
        class="flex flex-col min-h-[75vh] min-w-[320px] md:min-w-[340px] w-[320px] md:w-[340px] snap-center animate-slide-up" 
        :style="{ animationDelay: (idx * 150) + 'ms' }"
      >
        <!-- Column Header: Premium Glass -->
        <div class="flex items-center justify-between mb-4 md:mb-6 px-4 md:px-6 py-3 md:py-4 bg-white/80 dark:bg-[#151a22]/90 backdrop-blur-2xl rounded-[20px] md:rounded-[24px] border border-white dark:border-[#21262d] shadow-xl shadow-gray-200/20 dark:shadow-none group/header relative overflow-hidden">
          <div class="absolute inset-0 bg-gradient-to-r from-primary/5 to-transparent opacity-0 group-hover/header:opacity-100 transition-opacity"></div>
          <div class="flex items-center gap-4 relative z-10">
            <div :class="['w-4 h-4 rounded-full shadow-lg shadow-black/5 ring-4 ring-white dark:ring-gray-900', col.color]"></div>
            <h3 class="font-black text-gray-900 dark:text-white uppercase tracking-[0.2em] text-[10px]">{{ col.title }}</h3>
          </div>
          <span class="bg-gray-900 dark:bg-white text-white dark:text-gray-900 text-[10px] font-black px-3 py-1 rounded-full tabular-nums relative z-10">
            {{ (groupedOrders[col.id] || []).length }}
          </span>
        </div>

        <!-- Column Content (Draggable Area) -->
        <draggable 
          :list="groupedOrders[col.id]" 
          group="orders"
          item-key="id"
          class="flex-1 bg-gray-100/30 dark:bg-white/5 backdrop-blur-sm rounded-[20px] md:rounded-[24px] p-3 md:p-5 space-y-4 md:space-y-6 border-2 border-dashed border-gray-200/50 dark:border-[#21262d] min-h-[400px] md:min-h-[600px] hover:bg-gray-100/50 dark:hover:bg-white/10 hover:border-primary/20"
          ghost-class="opacity-50"
          drag-class="rotate-3 scale-105"
          @change="(evt: any) => onDragChange(evt, col.id)"
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
    </template>
  </div>
</template>

<style scoped>
</style>
