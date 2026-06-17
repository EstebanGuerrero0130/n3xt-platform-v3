<script setup lang="ts">
import { ref, computed, reactive, type PropType } from 'vue'

const stockInputs = reactive<Record<string, number>>({})

const props = defineProps({
 inventory: { type: Array as PropType<any[]>, required: true },
 loading: { type: Boolean, default: false }
})

defineEmits(['add-item', 'edit-item', 'delete-item', 'update-stock'])

const activeMainTab = ref('Materiales')
const searchQuery = ref('')
const activeFilter = ref('Todos')

const totalItems = computed(() => props.inventory.length)
const lowStockCount = computed(() => props.inventory.filter((m: any) => {
 const threshold = m.inventory?.low_stock_threshold || 500
 return (m.inventory?.stock_available || 0) < threshold
}).length)

const totalFilaments = computed(() => props.inventory.filter((m: any) => m.category === 'FDM' && m.type === 'material').length)
const totalResins = computed(() => props.inventory.filter((m: any) => m.category === 'SLA' && m.type === 'material').length)

const filteredInventory = computed(() => {
 let filtered: any[] = props.inventory as any[];

 if (searchQuery.value) {
 const q = searchQuery.value.toLowerCase()
 filtered = filtered.filter((m: any) => m.name.toLowerCase().includes(q) || (m.category && m.category.toLowerCase().includes(q)))
 }

 if (activeMainTab.value === 'Materiales') {
 filtered = filtered.filter((m: any) => m.type === 'material' && m.category !== 'UTIL')
 if (activeFilter.value === 'Filamentos') filtered = filtered.filter((m: any) => m.category === 'FDM')
 if (activeFilter.value === 'Resinas') filtered = filtered.filter((m: any) => m.category === 'SLA')
 } else if (activeMainTab.value === 'Productos') {
 filtered = filtered.filter((m: any) => m.type === 'product' || m.category === 'PROD')
 } else {
 filtered = []
 }

 return filtered;
})

const filaments = computed(() => filteredInventory.value.filter((m: any) => m.category === 'FDM'))
const resins = computed(() => filteredInventory.value.filter((m: any) => m.category === 'SLA'))
const utilities = computed(() => filteredInventory.value.filter((m: any) => m.type === 'utility' || m.type === 'service' || m.category === 'UTIL'))
const products = computed(() => filteredInventory.value.filter((m: any) => m.type === 'product' || m.category === 'PROD'))

const getProgressColor = (type: any) => {
 if (type === 'FDM') return 'from-primary to-[#2c554c]'
 if (type === 'SLA') return 'from-indigo-500 to-purple-600'
 if (type === 'PROD') return 'from-emerald-400 to-emerald-600'
 return 'from-orange-400 to-orange-600'
}
</script>

<template>
 <div class="animate-fade-in"> <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-8 md:mb-12 lg:mb-16 px-4 md:px-6 lg:px-10 gap-6 md:gap-10">
 <!-- TABS & ACTIONS -->
 <div class="flex flex-col md:flex-row items-start md:items-center gap-4 md:gap-6 w-full lg:w-auto">
 <div class="inline-flex bg-[#151a22]/50 dark:bg-[#151a22]/50 backdrop-blur-md p-1.5 rounded-[2rem] border border-[#21262d] dark:border-[#21262d] -gray-200/20 gap-1 overflow-x-auto no-scrollbar">
 <button :class="activeMainTab === 'Materiales' ? 'bg-[#151a22] dark:bg-[#08872b] text-white -black/20' : 'text-[#a4aea6] dark:text-[#c3c4c5] hover:bg-[#151a22] dark:hover:bg-[#151a22]/5'" class="px-4 md:px-8 py-2.5 md:py-3 rounded-[1.5rem] font-black text-[10px] uppercase tracking-widest" @click="activeMainTab = 'Materiales'; activeFilter = 'Todos'">Materiales</button>
 <button :class="activeMainTab === 'Productos' ? 'bg-[#151a22] dark:bg-[#08872b] text-white -black/20' : 'text-[#a4aea6] dark:text-[#c3c4c5] hover:bg-[#151a22] dark:hover:bg-[#151a22]/5'" class="px-4 md:px-8 py-2.5 md:py-3 rounded-[1.5rem] font-black text-[10px] uppercase tracking-widest" @click="activeMainTab = 'Productos'">Productos</button>
 <button :class="activeMainTab === 'Postprocesado' ? 'bg-[#151a22] dark:bg-[#08872b] text-white -black/20' : 'text-[#a4aea6] dark:text-[#c3c4c5] hover:bg-[#151a22] dark:hover:bg-[#151a22]/5'" class="px-4 md:px-8 py-2.5 md:py-3 rounded-[1.5rem] font-black text-[10px] uppercase tracking-widest" @click="activeMainTab = 'Postprocesado'">Postprocesado</button>
</div>

 <button class="btn-primary w-full md:w-auto px-6 md:px-8 py-3 md:py-4" @click="$emit('add-item')">
 <span class="text-lg">+</span>
 Registrar Nuevo Ítem
 </button>
 </div>
 
 <!-- Search with Debounce style (Visual) -->
 <div class="w-full lg:max-w-md bg-[#151a22] dark:bg-[#151a22] border border-[#21262d] dark:border-[#21262d] rounded-[1.5rem] md:rounded-[2rem] -gray-200/20 dark:-none flex items-center px-4 md:px-6 py-3 md:py-4 group focus-within:border-primary/30 focus-within:ring-4 focus-within:ring-primary/5">
 <svg class="w-4 md:w-5 h-4 md:h-5 text-gray-300 dark:text-[#a4aea6] group-focus-within:text-[#8dd6ff] transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
 <input v-model="searchQuery" type="text" placeholder="Buscar en base de datos..." class="w-full bg-transparent border-none px-3 md:px-4 text-xs font-bold text-[#f0f6fc] dark:text-white outline-none placeholder:text-gray-300 dark:placeholder:text-[#a4aea6]">
 </div>
 </div>

 <!-- Skeleton Loading: mientras carga el inventario -->
 <div v-if="loading || (inventory.length === 0 && !loading && !$slots.default)" class="space-y-16 lg:space-y-20 animate-pulse px-6 lg:px-10">
 <!-- Metrics Skeleton -->
 <div class="grid grid-cols-2 lg:grid-cols-4 gap-8 lg:gap-10 mb-16 lg:mb-20">
 <div v-for="i in 4" :key="'m-'+i" class="rounded-[3rem] p-8 bg-[#151a22] dark:bg-[#1a1f2e] border border-[#21262d] dark:border-[#252b3a]">
 <div class="flex items-center gap-4 mb-4">
 <div class="w-10 h-10 bg-gray-200 dark:bg-[#2a3040] rounded-[6px]"></div>
 <div class="h-3 bg-gray-200 dark:bg-[#2a3040] rounded-[60px] w-20"></div>
 </div>
 <div class="h-8 bg-gray-200 dark:bg-[#2a3040] rounded-[60px] w-24 mb-4"></div>
 <div class="h-2 bg-gray-200 dark:bg-[#2a3040] rounded-[60px] w-16"></div>
 </div>
 </div>
 <!-- Cards Skeleton -->
 <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-10 lg:gap-12">
 <div v-for="i in 4" :key="'c-'+i" class="rounded-[3rem] p-8 bg-[#151a22] dark:bg-[#1a1f2e] border border-[#21262d] dark:border-[#252b3a]">
 <div class="flex justify-between items-start mb-8">
 <div class="w-16 h-16 bg-gray-200 dark:bg-[#2a3040] rounded-[1.5rem]"></div>
 <div class="flex gap-2">
 <div class="w-10 h-10 bg-gray-200 dark:bg-[#2a3040] rounded-[6px]"></div>
 <div class="w-10 h-10 bg-gray-200 dark:bg-[#2a3040] rounded-[6px]"></div>
 </div>
 </div>
 <div class="h-5 bg-gray-200 dark:bg-[#2a3040] rounded-[60px] w-28 mb-2"></div>
 <div class="h-3 bg-gray-200 dark:bg-[#2a3040] rounded-[60px] w-16 mb-8"></div>
 <div class="bg-gray-200/50 dark:bg-[#2a3040]/30 p-6 rounded-[2rem] mb-8 space-y-4">
 <div class="h-4 bg-gray-200 dark:bg-[#2a3040] rounded-[60px] w-20"></div>
 <div class="h-8 bg-gray-200 dark:bg-[#2a3040] rounded-[60px] w-32"></div>
 <div class="h-3 bg-gray-200 dark:bg-[#2a3040] rounded-[60px]"></div>
 </div>
 <div class="h-10 bg-gray-200 dark:bg-[#2a3040] rounded-[6px]"></div>
 </div>
 </div>
 </div>

 <!-- METRICS EXECUTIVE (real) -->
 <div v-if="!loading" class="grid grid-cols-2 lg:grid-cols-4 gap-4 md:gap-8 lg:gap-10 px-4 md:px-6 lg:px-10 mb-8 md:mb-16 lg:mb-20">
 <div class="bg-[#151a22] dark:bg-[#151a22] p-4 md:p-8 rounded-[1.5rem] md:rounded-[3rem] border border-[#21262d] dark:border-[#21262d] -gray-200/20 dark:-none group relative overflow-hidden animate-slide-up animate-stagger-1">
 <div class="absolute -right-6 -top-6 w-20 h-20 bg-[#08872b]/5 rounded-[60px] blur-2xl group-hover:bg-[#08872b]/10 transition-all"></div>
 <div class="flex items-center gap-2 md:gap-4 mb-2 md:mb-4">
 <div class="w-8 md:w-10 h-8 md:h-10 bg-[#151a22] dark:bg-[#151a22]/5 rounded-[6px] flex items-center justify-center group-hover:scale-110 transition-transform text-[10px] font-black text-[#c3c4c5]">T</div>
 <span class="text-[8px] md:text-[10px] font-black text-[#c3c4c5] dark:text-gray-300 uppercase tracking-widest">Base de Datos</span>
 </div>
 <div class="flex items-end justify-between">
 <span class="text-2xl md:text-4xl font-black text-[#ffffff] dark:text-white tracking-tighter">{{ totalItems }}</span>
 <span class="text-[8px] md:text-[10px] font-bold text-emerald-500 uppercase flex items-center gap-1"><span class="w-1 h-1 rounded-[60px] bg-emerald-500 animate-ping"></span> Activos</span>
 </div>
 </div>
 <div class="bg-[#151a22] dark:bg-[#151a22] p-4 md:p-8 rounded-[1.5rem] md:rounded-[3rem] border border-[#21262d] dark:border-[#21262d] -rose-500/10 dark:-none group relative overflow-hidden animate-slide-up animate-stagger-2">
 <div class="absolute -right-6 -top-6 w-20 h-20 bg-rose-500/5 rounded-[60px] blur-2xl group-hover:bg-rose-500/10 transition-all"></div>
 <div class="flex items-center gap-2 md:gap-4 mb-2 md:mb-4">
 <div class="w-8 md:w-10 h-8 md:h-10 bg-rose-50 dark:bg-rose-500/10 rounded-[6px] flex items-center justify-center text-rose-500 group-hover:scale-110 transition-transform text-[10px] font-black">!</div>
 <span class="text-[8px] md:text-[10px] font-black text-[#c3c4c5] dark:text-gray-300 uppercase tracking-widest">Puntos Críticos</span>
 </div>
 <div class="flex items-end justify-between">
 <span class="text-2xl md:text-4xl font-black text-rose-600 tracking-tighter">{{ lowStockCount }}</span>
 <span class="text-[8px] md:text-[10px] font-bold text-rose-400 uppercase">Reponer</span>
 </div>
 </div>
 <div class="bg-[#090d0a] p-4 md:p-8 rounded-[1.5rem] md:rounded-[3rem] -black/20 group relative overflow-hidden border border-white/5 animate-slide-up animate-stagger-3">
 <div class="absolute -right-6 -top-6 w-20 h-20 bg-[#08872b]/10 rounded-[60px] blur-2xl group-hover:bg-[#08872b]/20 transition-all"></div>
 <div class="flex items-center gap-2 md:gap-4 mb-2 md:mb-4">
 <div class="w-8 md:w-10 h-8 md:h-10 bg-[#151a22]/5 rounded-[6px] flex items-center justify-center text-[#8dd6ff] group-hover:scale-110 transition-transform text-[10px] font-black">F</div>
 <span class="text-[8px] md:text-[10px] font-black text-[#a4aea6] uppercase tracking-widest">Stock FDM</span>
 </div>
 <div class="flex items-end justify-between">
 <span class="text-2xl md:text-4xl font-black text-white tracking-tighter">{{ totalFilaments }}</span>
 <span class="text-[8px] md:text-[10px] font-bold text-[#8dd6ff] uppercase">Variedades</span>
 </div>
 </div>
 <div class="bg-indigo-950 p-4 md:p-8 rounded-[1.5rem] md:rounded-[3rem] -indigo-900/20 group relative overflow-hidden border border-white/5 animate-slide-up animate-stagger-4">
 <div class="absolute -right-6 -top-6 w-20 h-20 bg-[#151a22]/10 rounded-[60px] blur-2xl group-hover:bg-[#151a22]/20 transition-all"></div>
 <div class="flex items-center gap-2 md:gap-4 mb-2 md:mb-4">
 <div class="w-8 md:w-10 h-8 md:h-10 bg-[#151a22]/10 rounded-[6px] flex items-center justify-center text-indigo-300 group-hover:scale-110 transition-transform text-[10px] font-black">C</div>
 <span class="text-[8px] md:text-[10px] font-black text-indigo-400 uppercase tracking-widest">Stock SLA</span>
 </div>
 <div class="flex items-end justify-between">
 <span class="text-2xl md:text-4xl font-black text-white tracking-tighter">{{ totalResins }}</span>
 <span class="text-[8px] md:text-[10px] font-bold text-indigo-300 uppercase">Resinas</span>
 </div>
 </div>
 </div>

 <!-- SUB-FILTERS -->
 <div v-if="activeMainTab === 'Materiales'" class="px-4 md:px-6 lg:px-10 mb-6 md:mb-12 flex gap-2 md:gap-3 overflow-x-auto no-scrollbar">
 <button v-for="f in ['Todos', 'Filamentos', 'Resinas']" :key="f" :class="activeFilter === f ? 'bg-[#151a22] dark:bg-[#08872b] text-[#ffffff] dark:text-white border-[#21262d] dark:border-primary/20 ' : 'bg-[#151a22] dark:bg-[#151a22] text-[#c3c4c5] dark:text-gray-300 border-gray-50 dark:border-[#21262d] hover:bg-[#151a22] dark:hover:bg-[#151a22]/5'" class="px-4 md:px-8 py-2.5 md:py-3 rounded-[24px] border font-black text-[10px] uppercase tracking-[0.2em] whitespace-nowrap" @click="activeFilter = f">
 {{ f }}
 </button>
 </div>


 <!-- Section: Filaments (FDM) -->
 <div v-if="activeMainTab === 'Materiales' && filaments.length > 0" class="mb-20 lg:mb-24">
 <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 md:gap-8 lg:gap-12 px-4 md:px-6 lg:px-10">
 <div v-for="mat in filaments" :key="mat.id" class="group bg-[#151a22] dark:bg-[#151a22] p-4 md:p-8 rounded-[1.5rem] md:rounded-[3rem] border border-[#21262d] dark:border-[#21262d] -gray-200/20 dark:-none relative overflow-hidden animate-slide-up transition-all hover:border-primary/30 hover:">
 
 <div class="flex justify-between items-start mb-8 relative z-10">
 <div class="relative">
 <div class="w-16 h-16 rounded-[1.5rem] border-8 border-gray-50 dark:border-gray-800 flex items-center justify-center transform group-hover:rotate-12 transition-transform duration-700" :style="{ backgroundColor: mat.color || '#cccccc' }">
 <div class="w-2 h-2 rounded-[60px] bg-[#151a22]/40 animate-pulse"></div>
 </div>
 <div class="absolute -bottom-2 -right-2 bg-[#151a22] dark:bg-[#283041] px-2 py-1 rounded-[6px] border border-[#21262d] dark:border-[#21262d] text-[7px] font-black text-[#c3c4c5] dark:text-gray-300 uppercase">HEX: {{ mat.color }}</div>
 </div>
 
 <div class="flex gap-2 opacity-100 md:opacity-0 group-hover:opacity-100 transition-all duration-500 transform translate-y-0 md:translate-y-4 group-hover:translate-y-0">
 <button class="icon-btn icon-btn-edit" @click="$emit('edit-item', mat)">
 <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
 </button>
 <button class="icon-btn icon-btn-danger" @click="$emit('delete-item', mat.id)">
 <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
 </button>
 </div>
 </div>

 <div class="space-y-2 mb-8 relative z-10">
 <h3 class="text-xl font-black text-[#ffffff] dark:text-white tracking-tighter truncate leading-tight" :title="mat.name">{{ mat.name }}</h3>
 <div class="flex flex-wrap gap-2">
 <span class="text-[8px] font-black bg-[#151a22] dark:bg-[#08872b] text-white px-3 py-1 rounded-[60px] uppercase tracking-widest -black/10">{{ mat.category }}</span>
 </div>
 </div>

 <div class="bg-[#151a22]/50 dark:bg-[#151a22]/5 p-4 md:p-6 rounded-[1.5rem] md:rounded-[2rem] border border-[#21262d] dark:border-[#21262d] mb-6 md:mb-8 space-y-3 md:space-y-4">
 <div class="flex justify-between items-end">
 <div>
 <p class="text-[8px] font-black text-[#c3c4c5] dark:text-gray-300 uppercase tracking-widest mb-1">Costo Unitario</p>
 <p class="text-xl font-black text-[#ffffff] dark:text-white tracking-tighter">${{ Number(mat.cost_per_kg).toLocaleString() }} <span class="text-xs text-[#c3c4c5] dark:text-[#a4aea6] font-bold">/Kg</span></p>
 </div>
 <div class="text-right">
 <p class="text-[8px] font-black text-[#c3c4c5] dark:text-[#a4aea6] uppercase tracking-widest mb-1">Stock Actual</p>
 <p :class="['text-xl font-black tracking-tighter', (mat.inventory?.stock_available < (mat.inventory?.low_stock_threshold || 500)) ? 'text-rose-500 animate-pulse' : 'text-emerald-600 dark:text-emerald-400']">
 {{ Math.round(mat.inventory?.stock_available || 0) }}<span class="text-[10px] font-bold uppercase ml-0.5">{{ mat.unit }}</span>
 </p>
 </div>
 </div>
 
 <div class="space-y-1.5">
 <div class="h-3 bg-[#151a22] dark:bg-[#283041] border border-[#21262d] dark:border-[#21262d] rounded-[60px] overflow-hidden p-0.5 ">
 <div class="h-full rounded-[60px] bg-gradient-to-r transition-all duration-1000 -[0_0_15px_rgba(var(--primary),0.3)]" :class="getProgressColor('FDM')" :style="{ width: Math.min(((mat.inventory?.stock_available || 0) / 1000) * 100, 100) + '%' }"></div>
 </div>
 <div class="flex justify-between px-1">
 <span class="text-[7px] font-black text-gray-300 uppercase tracking-widest">0</span>
 <span class="text-[7px] font-black text-gray-300 uppercase tracking-widest">1000g Capacidad</span>
 </div>
 </div>
 </div>

 <!-- Acción: Ajuste Rápido + Gestión -->
 <div class="pt-6 border-t border-gray-50 mt-auto space-y-4">
 <div class="flex gap-2">
 <input 
 v-model.number="stockInputs[mat.id]" 
 type="number" 
 placeholder="+ Añadir" 
 class="flex-1 bg-[#151a22] dark:bg-[#151a22]/5 border-none rounded-[6px] px-4 py-2 text-[10px] font-black text-[#ffffff] dark:text-white outline-none focus:ring-2 focus:ring-primary/20 transition-all"
 @keyup.enter="$emit('update-stock', mat.id, stockInputs[mat.id])"
 >
 <button 
 class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-[6px] hover:bg-emerald-600 hover:text-white transition-all flex items-center justify-center -emerald-500/10" @click="$emit('update-stock', mat.id, stockInputs[mat.id])" 
 >
 <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4" /></svg>
 </button>
 </div>
 <button class="w-full py-3 bg-[#151a22] dark:bg-[#08872b] text-white rounded-[24px] text-[10px] font-black uppercase tracking-[0.2em] hover:bg-[#08872b] dark:hover:bg-[#151a22] dark:hover:text-[#8dd6ff] transition-all -black/10" @click="$emit('edit-item', mat)">
 Gestionar Avanzado
 </button>
 </div>

 <!-- Decorative background element -->
 <div class="absolute -right-10 -bottom-10 w-32 h-32 opacity-[0.03] text-[#ffffff] dark:text-white pointer-events-none transform rotate-12 group-hover:scale-125 transition-transform duration-1000">
 <svg fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.47 2 2 6.47 2 12s4.47 10 10 10 10-4.47 10-10S17.53 2 12 2zm1 14h-2v-2h2v2zm0-4h-2V7h2v5z"/></svg>
 </div>
 </div>
 </div>
 </div>

 <!-- Section: Resins (SLA) -->
 <div v-if="activeMainTab === 'Materiales' && resins.length > 0" class="mb-20 lg:mb-24">
 <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 md:gap-8 lg:gap-12 px-4 md:px-6 lg:px-10">
 <div v-for="mat in resins" :key="mat.id" class="group bg-[#151a22] dark:bg-[#151a22] p-4 md:p-8 rounded-[1.5rem] md:rounded-[3rem] border border-[#21262d] dark:border-[#21262d] -gray-200/20 dark:-none relative overflow-hidden transition-all duration-700 hover: hover:-indigo-500/10 hover:-translate-y-2">
 
 <div class="flex justify-between items-start mb-8 relative z-10">
 <div class="relative">
 <div class="w-16 h-16 rounded-[1.5rem] border-8 border-gray-50 dark:border-gray-800 flex items-center justify-center transform group-hover:-rotate-12 transition-transform duration-700" :style="{ backgroundColor: mat.color || '#cccccc' }">
 <div class="w-2 h-2 rounded-[60px] bg-[#151a22]/40 animate-pulse"></div>
 </div>
 <div class="absolute -bottom-2 -right-2 bg-[#151a22] dark:bg-[#283041] px-2 py-1 rounded-[6px] border border-[#21262d] dark:border-[#21262d] text-[7px] font-black text-indigo-400 uppercase">HEX: {{ mat.color }}</div>
 </div>
 
 <div class="flex gap-2 opacity-100 md:opacity-0 group-hover:opacity-100 transition-all duration-500 transform translate-y-0 md:translate-y-4 group-hover:translate-y-0">
 <button class="w-10 h-10 bg-blue-50 text-blue-500 rounded-[6px] hover:bg-blue-500 hover:text-white transition-all -blue-500/10 flex items-center justify-center" @click="$emit('edit-item', mat)">
 <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
 </button>
 <button class="w-10 h-10 bg-rose-50 text-rose-500 rounded-[6px] hover:bg-rose-500 hover:text-white transition-all -rose-500/10 flex items-center justify-center" @click="$emit('delete-item', mat.id)">
 <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
 </button>
 </div>
 </div>

 <div class="space-y-2 mb-8 relative z-10">
 <h3 class="text-xl font-black text-[#ffffff] dark:text-white tracking-tighter truncate leading-tight" :title="mat.name">{{ mat.name }}</h3>
 <div class="flex flex-wrap gap-2">
 <span class="text-[8px] font-black bg-indigo-950 text-white px-3 py-1 rounded-[60px] uppercase tracking-widest -indigo-900/20">{{ mat.category }}</span>
 </div>
 </div>

 <div class="bg-indigo-50/30 dark:bg-[#151a22]/5 p-6 rounded-[2rem] border border-indigo-100/50 dark:border-[#21262d] mb-8 space-y-4">
 <div class="flex justify-between items-end">
 <div>
 <p class="text-[8px] font-black text-indigo-400 uppercase tracking-widest mb-1">Costo Unitario</p>
 <p class="text-xl font-black text-indigo-900 dark:text-white tracking-tighter">${{ Number(mat.cost_per_kg).toLocaleString() }} <span class="text-xs text-indigo-400 font-bold">/L</span></p>
 </div>
 <div class="text-right">
 <p class="text-[8px] font-black text-indigo-400 uppercase tracking-widest mb-1">Volumen Actual</p>
 <p :class="['text-xl font-black tracking-tighter', (mat.inventory?.stock_available < (mat.inventory?.low_stock_threshold || 500)) ? 'text-rose-500 animate-pulse' : 'text-indigo-600']">
 {{ Math.round(mat.inventory?.stock_available || 0) }}<span class="text-[10px] font-bold uppercase ml-0.5">{{ mat.unit }}</span>
 </p>
 </div>
 </div>
 
 <div class="space-y-1.5">
 <div class="h-3 bg-[#151a22] dark:bg-[#283041] border border-indigo-100 dark:border-[#21262d] rounded-[60px] overflow-hidden p-0.5 ">
 <div class="h-full rounded-[60px] bg-gradient-to-r transition-all duration-1000 -[0_0_15px_rgba(79,70,229,0.3)]" :class="getProgressColor('SLA')" :style="{ width: Math.min(((mat.inventory?.stock_available || 0) / 1000) * 100, 100) + '%' }"></div>
 </div>
 <div class="flex justify-between px-1">
 <span class="text-[7px] font-black text-indigo-300 uppercase tracking-widest">0</span>
 <span class="text-[7px] font-black text-indigo-300 uppercase tracking-widest">1000ml Capacidad</span>
 </div>
 </div>
 </div>

 <!-- Acción: Ajuste Rápido + Gestión -->
 <div class="pt-6 border-t border-indigo-50 dark:border-[#21262d] mt-auto space-y-4">
 <div class="flex gap-2">
 <input 
 v-model.number="stockInputs[mat.id]" 
 type="number" 
 placeholder="+ Añadir ml" 
 class="flex-1 bg-indigo-50/50 dark:bg-[#151a22]/5 border-none rounded-[6px] px-4 py-2 text-[10px] font-black text-[#ffffff] dark:text-white outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all"
 @keyup.enter="$emit('update-stock', mat.id, stockInputs[mat.id])"
 >
 <button 
 class="w-10 h-10 bg-indigo-600 text-white rounded-[6px] hover:bg-indigo-700 transition-all flex items-center justify-center -indigo-500/20" @click="$emit('update-stock', mat.id, stockInputs[mat.id])" 
 >
 <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4" /></svg>
 </button>
 </div>
 <button class="w-full py-3 bg-indigo-950 text-white rounded-[24px] text-[10px] font-black uppercase tracking-[0.2em] hover:bg-indigo-600 transition-all -indigo-900/20" @click="$emit('edit-item', mat)">
 Gestionar Resina
 </button>
 </div>
 </div>
 </div>
 </div>

 <!-- Section: Products (Stock for direct sale) -->
 <div v-if="activeMainTab === 'Productos' && products.length > 0" class="mb-20 lg:mb-24">
 <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 md:gap-8 lg:gap-12 px-4 md:px-6 lg:px-10">
 <div v-for="mat in products" :key="mat.id" class="group bg-[#151a22] dark:bg-[#151a22] p-4 md:p-8 rounded-[1.5rem] md:rounded-[3rem] border border-[#21262d] dark:border-[#21262d] -gray-200/20 dark:-none relative overflow-hidden transition-all duration-700 hover: hover:-emerald-500/10 hover:-translate-y-2">
 
 <div class="flex justify-between items-start mb-8 relative z-10">
 <div class="w-16 h-16 rounded-[1.5rem] bg-emerald-50 dark:bg-emerald-500/10 border-8 border-gray-50 dark:border-gray-800 flex items-center justify-center transform group-hover:scale-110 transition-transform duration-700 text-xl font-black text-emerald-500">M</div>
 
 <div class="flex gap-2 opacity-100 md:opacity-0 group-hover:opacity-100 transition-all duration-500 transform translate-y-0 md:translate-y-4 group-hover:translate-y-0">
 <button class="w-10 h-10 bg-blue-50 text-blue-500 rounded-[6px] hover:bg-blue-500 hover:text-white transition-all -blue-500/10 flex items-center justify-center" @click="$emit('edit-item', mat)">
 <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
 </button>
 <button class="w-10 h-10 bg-rose-50 text-rose-500 rounded-[6px] hover:bg-rose-500 hover:text-white transition-all -rose-500/10 flex items-center justify-center" @click="$emit('delete-item', mat.id)">
 <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
 </button>
 </div>
 </div>

 <div class="space-y-2 mb-8 relative z-10">
 <h3 class="text-xl font-black text-[#ffffff] dark:text-white tracking-tighter truncate leading-tight" :title="mat.name">{{ mat.name }}</h3>
 <div class="flex flex-wrap gap-2">
 <span class="text-[8px] font-black bg-emerald-950 dark:bg-[#08872b] text-white px-3 py-1 rounded-[60px] uppercase tracking-widest -emerald-900/20">Producto Final</span>
 <span class="text-[8px] font-black bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 px-3 py-1 rounded-[60px] uppercase tracking-widest border border-emerald-100 dark:border-[#21262d]">Unidad: {{ mat.unit }}</span>
 </div>
 </div>

 <div class="bg-emerald-50/30 dark:bg-[#151a22]/5 p-6 rounded-[2rem] border border-emerald-100/50 dark:border-[#21262d] mb-8 space-y-4">
 <div class="flex justify-between items-end">
 <div>
 <p class="text-[8px] font-black text-emerald-600 uppercase tracking-widest mb-1">Precio Unitario</p>
 <p class="text-xl font-black text-emerald-950 dark:text-white tracking-tighter">${{ Number(mat.cost_per_kg).toLocaleString() }}</p>
 </div>
 <div class="text-right">
 <p class="text-[8px] font-black text-emerald-600 uppercase tracking-widest mb-1">Stock Disponible</p>
 <p :class="['text-xl font-black tracking-tighter', (mat.inventory?.stock_available < (mat.inventory?.low_stock_threshold || 5)) ? 'text-rose-500 animate-pulse' : 'text-emerald-700 dark:text-emerald-400']">
 {{ Math.round(mat.inventory?.stock_available || 0) }} <span class="text-[10px] font-bold uppercase ml-0.5">uds</span>
 </p>
 </div>
 </div>
 
 <div class="h-3 bg-[#151a22] dark:bg-[#283041] border border-emerald-100 dark:border-[#21262d] rounded-[60px] overflow-hidden p-0.5 ">
 <div class="h-full rounded-[60px] bg-gradient-to-r transition-all duration-1000 -[0_0_15px_rgba(16,185,129,0.3)]" :class="getProgressColor('PROD')" :style="{ width: Math.min(((mat.inventory?.stock_available || 0) / 50) * 100, 100) + '%' }"></div>
 </div>
 </div>

 <!-- Acción: Ajuste Rápido + Gestión -->
 <div class="pt-6 border-t border-emerald-50 dark:border-[#21262d] mt-auto space-y-4">
 <div class="flex gap-2">
 <input 
 v-model.number="stockInputs[mat.id]" 
 type="number" 
 placeholder="+ Cantidad" 
 class="flex-1 bg-emerald-50/50 dark:bg-[#151a22]/5 border-none rounded-[6px] px-4 py-2 text-[10px] font-black text-[#ffffff] dark:text-white outline-none focus:ring-2 focus:ring-emerald-500/20 transition-all"
 @keyup.enter="$emit('update-stock', mat.id, stockInputs[mat.id])"
 >
 <button 
 class="w-10 h-10 bg-emerald-600 text-white rounded-[6px] hover:bg-emerald-700 transition-all flex items-center justify-center -emerald-500/20" @click="$emit('update-stock', mat.id, stockInputs[mat.id])" 
 >
 <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4" /></svg>
 </button>
 </div>
 <button class="w-full py-3 bg-emerald-900 text-white rounded-[24px] text-[10px] font-black uppercase tracking-[0.2em] hover:bg-emerald-600 transition-all -emerald-900/20" @click="$emit('edit-item', mat)">
 Gestionar Stock
 </button>
 </div>
 </div>
 </div>
 </div>

 <!-- Section: Utilities / Postprocesado -->
 <div v-if="activeMainTab === 'Postprocesado' && utilities.length > 0" class="mb-20 lg:mb-24">
 <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 md:gap-8 lg:gap-12 px-4 md:px-6 lg:px-10">
 <div v-for="mat in utilities" :key="mat.id" class="group bg-[#151a22] dark:bg-[#151a22] p-4 md:p-8 rounded-[1.5rem] md:rounded-[3rem] border border-[#21262d] dark:border-[#21262d] -gray-200/20 dark:-none relative overflow-hidden transition-all duration-700 hover: hover:-orange-500/10 hover:-translate-y-2">
 
 <div class="flex justify-between items-start mb-8 relative z-10">
 <div class="w-16 h-16 rounded-[1.5rem] bg-orange-50 dark:bg-orange-500/10 border-8 border-gray-50 dark:border-gray-800 flex items-center justify-center text-orange-500 font-black text-xs transform group-hover:rotate-12 transition-transform duration-700 italic">UTL</div>
 
 <div class="flex gap-2 opacity-100 md:opacity-0 group-hover:opacity-100 transition-all duration-500 transform translate-y-0 md:translate-y-4 group-hover:translate-y-0">
 <button class="w-10 h-10 bg-blue-50 text-blue-500 rounded-[6px] hover:bg-blue-500 hover:text-white transition-all -blue-500/10 flex items-center justify-center" @click="$emit('edit-item', mat)">
 <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
 </button>
 <button class="w-10 h-10 bg-rose-50 text-rose-500 rounded-[6px] hover:bg-rose-500 hover:text-white transition-all -rose-500/10 flex items-center justify-center" @click="$emit('delete-item', mat.id)">
 <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
 </button>
 </div>
 </div>

 <div class="space-y-2 mb-8 relative z-10">
 <h3 class="text-xl font-black text-[#ffffff] dark:text-white tracking-tighter truncate leading-tight" :title="mat.name">{{ mat.name }}</h3>
 <div class="flex flex-wrap gap-2">
 <span class="text-[8px] font-black bg-orange-900 dark:bg-[#08872b] text-white px-3 py-1 rounded-[60px] uppercase tracking-widest -orange-900/20">Insumo</span>
 <span class="text-[8px] font-black bg-orange-50 dark:bg-orange-500/10 text-orange-600 dark:text-orange-400 px-3 py-1 rounded-[60px] uppercase tracking-widest border border-orange-100 dark:border-[#21262d]">{{ mat.unit }}</span>
 </div>
 </div>

 <div class="bg-orange-50/30 dark:bg-[#151a22]/5 p-6 rounded-[2rem] border border-orange-100/50 dark:border-[#21262d] mb-8 space-y-4">
 <div class="flex justify-between items-end">
 <div>
 <p class="text-[8px] font-black text-orange-400 uppercase tracking-widest mb-1">Costo Unitario</p>
 <p class="text-xl font-black text-orange-900 dark:text-white tracking-tighter">${{ Number(mat.cost_per_kg).toLocaleString() }}</p>
 </div>
 <div class="text-right">
 <p class="text-[8px] font-black text-orange-400 uppercase tracking-widest mb-1">Disponibilidad</p>
 <p :class="['text-xl font-black tracking-tighter', (mat.inventory?.stock_available < (mat.inventory?.low_stock_threshold || 10)) ? 'text-rose-500 animate-pulse' : 'text-orange-700 dark:text-orange-400']">
 {{ Math.round(mat.inventory?.stock_available || 0) }}<span class="text-[10px] font-bold ml-0.5">{{ mat.unit }}</span>
 </p>
 </div>
 </div>
 
 <div class="h-3 bg-[#151a22] dark:bg-[#283041] border border-orange-100 dark:border-[#21262d] rounded-[60px] overflow-hidden p-0.5 ">
 <div class="h-full rounded-[60px] bg-gradient-to-r transition-all duration-1000 -[0_0_15px_rgba(249,115,22,0.3)]" :class="getProgressColor('UTIL')" :style="{ width: Math.min(((mat.inventory?.stock_available || 0) / 100) * 100, 100) + '%' }"></div>
 </div>
 </div>

 <div class="pt-4 border-t border-orange-50 dark:border-[#21262d] mt-auto">
 <button class="btn-primary w-full py-3 !bg-orange-900 hover:!bg-orange-600" @click="$emit('edit-item', mat)">
 <svg class="w-4 h-4 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg> Gestionar Insumo
 </button>
 </div>
 </div>
 </div>
 </div>
 
 <!-- Empty States -->
 <div v-if="(activeMainTab === 'Materiales' && filaments.length === 0 && resins.length === 0) || (activeMainTab === 'Postprocesado' && utilities.length === 0) || (activeMainTab === 'Productos' && products.length === 0)" class="text-center py-20 px-4">
 <div class="w-20 h-20 bg-[#151a22] dark:bg-[#151a22]/5 rounded-[60px] flex items-center justify-center mx-auto mb-6">
 <svg class="w-8 h-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
 </div>
 <h3 class="text-xl font-black text-[#f0f6fc] dark:text-white mb-2">No se encontraron resultados</h3>
 <p class="text-xs text-[#c3c4c5] font-bold uppercase tracking-widest max-w-sm mx-auto">No hay inventario que coincida con tus criterios de búsqueda en esta sección.</p>
 </div>

 </div>
</template>
