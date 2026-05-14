<script setup>
import { computed } from 'vue'

const props = defineProps({
  order: { type: Object, required: true }
})

const emit = defineEmits(['assign', 'download', 'view', 'delete', 'download-pdf', 'update-status', 'toggle-paid'])

const statusOrder = ['pending', 'printing', 'post-processing', 'completed', 'shipped']

const canMoveLeft = computed(() => {
  const idx = statusOrder.indexOf(props.order.status)
  return idx > 0
})

const canMoveRight = computed(() => {
  const idx = statusOrder.indexOf(props.order.status)
  return idx >= 0 && idx < statusOrder.length - 1
})

const moveLeft = () => {
  const idx = statusOrder.indexOf(props.order.status)
  if (idx > 0) {
    emit('update-status', { orderId: props.order.id, status: statusOrder[idx - 1] })
  }
}

const moveRight = () => {
  const idx = statusOrder.indexOf(props.order.status)
  if (idx < statusOrder.length - 1) {
    const nextStatus = statusOrder[idx + 1]
    if (nextStatus === 'printing') {
      emit('assign', props.order)
    } else {
      emit('update-status', { orderId: props.order.id, status: nextStatus })
    }
  }
}

const isStalled = computed(() => {
  if (['shipped', 'cancelled', 'completed'].includes(props.order.status)) return false;
  
  const dateStr = props.order.updated_at || props.order.created_at;
  if (!dateStr) return false;

  // Lógica de parsing ultra-robusta
  let lastUpdate;
  if (dateStr.includes('T')) {
    lastUpdate = new Date(dateStr).getTime();
  } else {
    lastUpdate = new Date(dateStr.replace(' ', 'T')).getTime();
  }
  
  if (isNaN(lastUpdate)) return false;

  const now = Date.now();
  const diffInMs = now - lastUpdate;
  
  // Solo si la diferencia es positiva y mayor a 24 horas (86,400,000 ms)
  return diffInMs > (24 * 60 * 60 * 1000);
});
</script>

<template>
  <div :class="[
    'bg-white dark:bg-gray-900 rounded-[2.5rem] md:rounded-[3rem] p-6 md:p-10 shadow-xl border transition-all duration-500 ease-[cubic-bezier(0.19,1,0.22,1)] group relative overflow-hidden active:scale-[0.98] hover:-translate-y-1 hover:shadow-2xl',
    isStalled ? 'border-rose-400 shadow-2xl shadow-rose-500/10' : 'border-gray-100 dark:border-white/5 hover:border-primary/30',
    order.status === 'printing' ? 'ring-4 ring-primary/20' : ''
  ]">

    <!-- Dynamic Glow on Hover -->
    <div class="absolute inset-0 bg-gradient-to-br from-primary/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none"></div>

    <div :class="[
        'absolute top-0 right-0 w-24 h-24 -mr-8 -mt-8 rotate-45 transition-transform group-hover:scale-110 duration-500',
        order.technology === 'FDM' ? 'bg-indigo-600/10' : 'bg-primary/10',
        'pointer-events-none'
    ]"></div>
    <div :class="[
        'absolute top-4 right-4 flex items-center gap-2 px-3 py-1 rounded-full text-[8px] font-black uppercase tracking-[0.2em] shadow-lg',
        order.technology === 'FDM' ? 'bg-indigo-600 text-white shadow-indigo-500/20' : 'bg-primary text-white shadow-primary/20'
    ]">
        <svg v-if="order.technology === 'FDM'" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
        <svg v-else class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.618.309a6 6 0 01-3.86.517l-2.387-.477a2 2 0 00-1.022.547l-1.16 1.16a2 2 0 00.442 3.102l1.644.822a6 6 0 005.366 0l1.644-.822a2 2 0 00.442-3.102l-1.16-1.16zM15.75 9a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" /></svg>
        {{ order.technology }}
    </div>
    <!-- Quick Move Controls -->
    <div class="absolute inset-x-0 top-0 flex justify-between px-2 pt-2 opacity-100 md:opacity-0 md:group-hover:opacity-100 transition-opacity pointer-events-none">
      <button 
        v-if="canMoveLeft" 
        @click.stop="moveLeft"
        class="w-8 h-8 bg-gray-900/10 dark:bg-white/10 backdrop-blur-md text-gray-900 dark:text-white rounded-full flex items-center justify-center hover:bg-primary hover:text-white transition-all pointer-events-auto active:scale-75"
        title="Retroceder Estado"
      >
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 19l-7-7 7-7"/></svg>
      </button>
      <div v-else class="w-8"></div>
      
      <button 
        v-if="canMoveRight" 
        @click.stop="moveRight"
        class="w-8 h-8 bg-gray-900/10 dark:bg-white/10 backdrop-blur-md text-gray-900 dark:text-white rounded-full flex items-center justify-center hover:bg-primary hover:text-white transition-all pointer-events-auto active:scale-75"
        title="Avanzar Estado"
      >
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 5l7 7-7 7"/></svg>
      </button>
    </div>

    <!-- Header: Status & ID -->
    <div class="flex justify-between items-center mb-6">
      <div class="flex items-center gap-3">
        <div :class="[
          order.status === 'printing' ? 'animate-pulse' : '', 
          'w-2.5 h-2.5 rounded-full shadow-lg shadow-current/50',
          order.status === 'pending' ? 'bg-red-500' : 
          order.status === 'printing' ? 'bg-amber-500' : 
          order.status === 'post-processing' ? 'bg-blue-500' : 
          order.status === 'completed' ? 'bg-indigo-500' : 
          order.status === 'shipped' ? 'bg-emerald-500' : 'bg-gray-400'
        ]"></div>
        <span class="text-[10px] font-black text-gray-400 dark:text-gray-300 uppercase tracking-widest">{{ order.status === 'pending' ? (order.is_paid ? 'Verificada' : 'Auditoría') : order.status }}</span>
      </div>
      <div class="flex items-center gap-2">
        <span v-if="isStalled" class="text-[8px] font-black bg-red-100 text-red-600 px-2 py-0.5 rounded-full uppercase tracking-widest flex items-center gap-1 animate-pulse">
            Retraso
        </span>
        <span class="text-[10px] font-black text-gray-300 dark:text-gray-600 bg-gray-50 dark:bg-white/5 px-3 py-1 rounded-full uppercase tracking-widest">#{{ order.id }}</span>
      </div>
    </div>

    <!-- Job Title -->
    <h4 class="text-xl font-black text-gray-900 dark:text-white uppercase tracking-tighter mb-1 line-clamp-1">
      {{ order.customer_name }}
    </h4>
    <div class="flex items-baseline gap-1 mb-6 md:mb-8">
      <span class="text-xs font-black text-gray-400 dark:text-gray-300 uppercase tracking-widest">$</span>
      <span class="text-2xl md:text-3xl font-black text-gray-900 dark:text-white tracking-tighter">{{ Number(order.total_price).toLocaleString() }}</span>
      <span v-if="order.extra_items && order.extra_items.length > 0" class="ml-2 px-2 py-0.5 bg-emerald-100 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-[8px] font-black uppercase rounded-full tracking-widest">
        + Extras
      </span>
    </div>

    <!-- Specs: Material & Weight -->
    <div class="grid grid-cols-2 gap-4 mb-8">
      <div class="bg-gray-50/50 dark:bg-slate-800/50 backdrop-blur-sm p-4 rounded-2xl border border-gray-100/50 dark:border-white/5 group-hover:bg-white dark:group-hover:bg-slate-800 transition-all">
        <p class="text-[8px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-1">Insumo</p>
        <p class="text-[10px] font-black text-gray-800 dark:text-gray-200 uppercase truncate">{{ order.material_name || order.material_id }}</p>
      </div>
      <div v-if="order.status === 'printing' && order.printer" class="bg-primary/5 dark:bg-primary/10 p-4 rounded-2xl border border-primary/20 dark:border-primary/40 animate-pulse">
        <p class="text-[8px] font-black text-primary uppercase tracking-widest mb-1">Operando en</p>
        <p class="text-[10px] font-black text-primary uppercase truncate">{{ order.printer.name }}</p>
      </div>
      <div v-else class="bg-gray-50/50 dark:bg-slate-800/50 backdrop-blur-sm p-4 rounded-2xl border border-gray-100/50 dark:border-white/5 group-hover:bg-white dark:group-hover:bg-slate-800 transition-all">
        <p class="text-[8px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-1">Masa Neta</p>
        <p class="text-[10px] font-black text-gray-800 dark:text-gray-200 uppercase">{{ order.estimated_weight_g }}g</p>
      </div>
    </div>


    <!-- Payment Badge: Sleek Horizontal Ribbon -->
    <div v-if="order.is_paid" class="absolute top-0 left-1/2 -translate-x-1/2 px-4 py-1.5 bg-emerald-500 text-white text-[8px] font-black uppercase rounded-b-xl tracking-widest shadow-lg shadow-emerald-500/20 z-10 pointer-events-none">
        PAGADO
    </div>

    <!-- Tracking Info (Mini Badge) -->
    <div v-if="order.status === 'shipped' && order.tracking_guide" class="mb-4 p-3 bg-primary/5 border border-primary/10 rounded-2xl flex items-center justify-between">
        <div class="flex flex-col">
            <span class="text-[7px] font-black text-primary uppercase tracking-widest">{{ order.tracking_carrier || 'Transportadora' }}</span>
            <span class="text-[10px] font-black text-gray-700 dark:text-gray-300">{{ order.tracking_guide }}</span>
        </div>
        <div class="w-2 h-2 rounded-full bg-primary animate-pulse"></div>
    </div>

    <!-- Actions: Premium Bar -->
    <div class="border-t border-gray-50 pt-6 space-y-4">
      <div class="flex items-center justify-between gap-2">
        <!-- Group 1: Viewing/Files -->
        <div class="flex items-center gap-1.5">
          <button @click="$emit('view', order)" class="w-10 h-10 flex items-center justify-center bg-gray-950 dark:bg-primary text-white rounded-xl hover:bg-primary dark:hover:bg-white dark:hover:text-primary hover:scale-110 active:scale-90 transition-all duration-300 shadow-lg" title="Ver Monitor">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
          </button>

          <button @click="$emit('download-pdf', order)" class="w-11 h-11 flex items-center justify-center bg-white dark:bg-gray-800 border border-gray-100 dark:border-white/5 text-gray-900 dark:text-white rounded-2xl hover:bg-gray-900 dark:hover:bg-primary hover:text-white hover:scale-110 active:scale-90 transition-all duration-300 shadow-sm" title="Cotización PDF">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
          </button>

          <button @click="$emit('download', order)" class="w-11 h-11 flex items-center justify-center bg-white dark:bg-gray-800 border border-gray-100 dark:border-white/5 text-gray-900 dark:text-white rounded-2xl hover:bg-gray-900 dark:hover:bg-primary hover:text-white hover:scale-110 active:scale-90 transition-all duration-300 shadow-sm" title="Descargar STL">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
          </button>
        </div>

        <!-- Group 2: Management -->
        <div class="flex items-center gap-1.5">
          <button 
            @click="$emit('toggle-paid', order.id)" 
            :class="[
              'w-10 h-10 flex items-center justify-center rounded-xl transition-all duration-300 shadow-sm border',
              order.is_paid ? 'bg-emerald-500 text-white border-emerald-500 cursor-pointer shadow-emerald-500/20 hover:scale-110 active:scale-90' : 'bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-200 dark:border-amber-500/20 hover:scale-110 active:scale-90'
            ]" 
            :title="order.is_paid ? 'Pago Verificado (Clic para revertir)' : 'Confirmar Pago'"
          >
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
          </button>

          <button @click="$emit('delete', order.id)" class="w-10 h-10 flex items-center justify-center bg-gray-50 dark:bg-red-500/10 text-gray-400 dark:text-red-400 rounded-xl hover:bg-red-500 dark:hover:bg-red-500 hover:text-white hover:scale-110 active:scale-90 transition-all duration-300 shadow-sm" title="Eliminar Orden">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
          </button>
        </div>
      </div>

      <!-- Final Decision Action -->
      <button 
        v-if="order.status === 'pending'"
        @click="$emit('assign', order)" 
        class="w-full py-4 bg-gray-950 dark:bg-primary text-white text-[10px] font-black uppercase tracking-[0.2em] rounded-xl hover:bg-primary dark:hover:bg-white dark:hover:text-primary transition-all duration-300 shadow-lg shadow-black/10 active:scale-95"
      >
        Asignar a Impresora
      </button>

      <div v-else-if="order.status === 'printing'" class="grid grid-cols-2 gap-2">
        <button 
          @click="$emit('assign', order)" 
          class="py-4 bg-gray-50 dark:bg-white/5 text-gray-500 dark:text-gray-300 text-[8px] font-black uppercase tracking-widest rounded-xl hover:bg-gray-200 dark:hover:bg-white/10 transition-all border border-gray-100 dark:border-white/5"
        >
          Re-Asignar
        </button>
        <button 
          @click="$emit('update-status', { orderId: order.id, status: 'post-processing' })" 
          class="py-4 bg-primary text-white text-[8px] font-black uppercase tracking-widest rounded-xl hover:bg-gray-900 transition-all shadow-lg shadow-primary/20"
        >
          Terminar Pieza
        </button>
      </div>

      <button 
        v-else-if="order.status === 'post-processing'"
        @click="$emit('update-status', { orderId: order.id, status: 'completed' })" 
        class="w-full py-4 bg-emerald-500 text-white text-[10px] font-black uppercase tracking-[0.2em] rounded-xl hover:bg-emerald-600 transition-all shadow-lg shadow-emerald-500/20 active:scale-95"
      >
        Lista para Entrega
      </button>

      <button 
        v-else-if="order.status === 'completed'"
        @click="$emit('update-status', { orderId: order.id, status: 'shipped' })" 
        class="w-full py-4 bg-blue-600 text-white text-[10px] font-black uppercase tracking-[0.2em] rounded-xl hover:bg-blue-700 transition-all shadow-lg shadow-blue-500/20 active:scale-95"
      >
        Marcar como Enviado
      </button>
    </div>
  </div>
</template>
