<script setup lang="ts">
import { ref, computed, type PropType } from 'vue'

const props = defineProps({
 orders: { type: Array as PropType<any[]>, required: true },
 loadingAnalytics: { type: Boolean, default: false }
})

defineEmits(['export-report', 'view-details', 'download-pdf', 'print-label', 'delete'])

const searchQuery = ref('')
const statusFilter = ref('all')
const activeSubTab = ref('list') // list, metrics
const dateRange = ref({
 start: '',
 end: ''
})

const setQuickMonth = (offset) => {
 const now = new Date()
 const start = new Date(now.getFullYear(), now.getMonth() + offset, 1)
 const end = new Date(now.getFullYear(), now.getMonth() + offset + 1, 0)
 dateRange.value.start = start.toISOString().split('T')[0]
 dateRange.value.end = end.toISOString().split('T')[0]
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

const metricsSummary = computed(() => {
 const total = filteredOrders.value.reduce((acc, o) => acc + (parseFloat(o.total_price) || 0), 0)
 const weight = filteredOrders.value.reduce((acc, o) => acc + (parseFloat(o.estimated_weight_g) || 0), 0)
 const count = filteredOrders.value.length
 return {
 total,
 weight: (weight / 1000).toFixed(2), // Convert to KG
 count,
 avg: count > 0 ? total / count : 0
 }
})

const topCustomers = computed(() => {
 const map: Record<string, { name: string; total: number; count: number }> = {}
 filteredOrders.value.forEach(o => {
 if (!map[o.customer_name]) map[o.customer_name] = { name: o.customer_name, total: 0, count: 0 }
 map[o.customer_name].total += parseFloat(o.total_price) || 0
 map[o.customer_name].count++
 })
 return Object.values(map).sort((a, b) => b.total - a.total).slice(0, 3)
})

const getStatusColor = (status: any) => {
 const map = {
 pending: 'bg-red-50 text-red-600 border-red-200',
 printing: 'bg-amber-50 text-amber-600 border-amber-200',
 'post-processing': 'bg-blue-50 text-blue-600 border-blue-200',
 completed: 'bg-emerald-50 text-emerald-600 border-emerald-200',
 shipped: 'bg-purple-50 text-purple-600 border-purple-200'
 }
 return map[status] || 'bg-[#151a22] text-[#a4aea6] border-[#21262d]'
}

const getStatusLabel = (status: any) => {
 const map = {
 pending: 'Pendiente',
 printing: 'En Máquina',
 'post-processing': 'Post-Proceso',
 completed: 'Terminado',
 shipped: 'Entregado'
 }
 return map[status] || status
}
</script>

<template>
 <div class="animate-in fade-in duration-700">
 <!-- Header -->
 <div class="flex flex-col lg:flex-row justify-between items-start lg:items-end gap-4 md:gap-6 mb-8 md:mb-12 px-4">
 <div>
 <h2 class="text-xl md:text-4xl font-black text-[#ffffff] dark:text-white tracking-tighter uppercase mb-1 md:mb-2">Historial<span class="text-[#8dd6ff]">.</span>OS</h2>
 <div class="flex items-center gap-2 md:gap-3">
 <div class="w-2 h-2.5 rounded-[60px] bg-emerald-500 -[0_0_10px_rgba(16,185,129,0.5)] animate-pulse"></div>
 <p class="text-[#c3c4c5] dark:text-[#a4aea6] font-black uppercase tracking-[0.3em] text-[9px] md:text-[10px]">Auditoría Logística de Precisión</p>
 </div>
 </div>
 
 <div class="flex flex-col sm:flex-row gap-3 md:gap-4 w-full lg:w-auto">
 <button 
 v-if="activeSubTab === 'list'"
 :disabled="loadingAnalytics" 
 class="flex-1 lg:flex-none px-6 md:px-8 py-4 md:py-5 bg-[#090d0a] dark:bg-[#08872b] text-white rounded-[1.5rem] md:rounded-[2rem] -black/20 hover:bg-[#08872b] dark:hover:bg-[#151a22] dark:hover:text-[#8dd6ff] transition-all font-black text-[9px] md:text-[10px] uppercase tracking-widest flex items-center justify-center gap-2 md:gap-3 active:scale-95 disabled:opacity-50 border border-white/10"
 @click="$emit('export-report', { type: 'ledger', start: dateRange.start, end: dateRange.end, filteredOrders })"
 >
 <svg class="w-4 md:w-5 h-4 md:h-5 inline mr-1 md:mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg> {{ loadingAnalytics ? 'Sincronizando...' : 'Libro de Auditoria' }}
 </button>
 </div>
 </div>

 <!-- Sub-Navegación y Sincronización -->
 <div class="flex flex-wrap items-center justify-between gap-4 md:gap-6 mb-8 md:mb-12 px-4 bg-[#151a22]/40 dark:bg-[#151a22]/40 backdrop-blur-2xl p-4 md:p-6 rounded-[1.5rem] md:rounded-[3rem] border border-[#21262d]/50 dark:border-[#21262d] overflow-x-auto no-scrollbar">
 <div class="flex gap-2 md:gap-3">
 <button :class="activeSubTab === 'list' ? 'bg-[#090d0a] dark:bg-[#08872b] text-white ' : 'bg-transparent text-[#a4aea6] dark:text-[#c3c4c5] hover:bg-[#151a22] dark:hover:bg-[#151a22]/5'" class="px-4 md:px-8 py-3 md:py-4 rounded-[24px] text-[10px] font-black uppercase tracking-widest transition-all whitespace-nowrap" @click="activeSubTab = 'list'">Listado</button>
 <button :class="activeSubTab === 'metrics' ? 'bg-[#090d0a] dark:bg-[#08872b] text-white ' : 'bg-transparent text-[#a4aea6] dark:text-[#c3c4c5] hover:bg-[#151a22] dark:hover:bg-[#151a22]/5'" class="px-4 md:px-8 py-3 md:py-4 rounded-[24px] text-[10px] font-black uppercase tracking-widest transition-all whitespace-nowrap" @click="activeSubTab = 'metrics'">Analíticas</button>
 </div>
 </div>

 <!-- Controles Maestros -->
 <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-12 px-4">
 <!-- Buscador y Estado -->
 <div class="lg:col-span-5 flex flex-col sm:flex-row gap-3 md:gap-4">
 <div class="relative flex-1 group">
 <svg class="absolute left-4 md:left-6 top-1/2 -translate-y-1/2 text-gray-300 dark:text-[#a4aea6] group-focus-within:text-[#8dd6ff] transition-colors w-3.5 md:w-4 h-3.5 md:h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
 <input 
 v-model="searchQuery" 
 type="text" 
 placeholder="Rastrear Orden o Cliente..." 
 class="pl-12 md:pl-16 pr-4 md:pr-6 py-4 md:py-5 bg-[#151a22] dark:bg-[#151a22] border border-[#21262d] dark:border-[#21262d] rounded-[1.5rem] md:rounded-[2rem] text-sm font-bold text-[#ffffff] dark:text-white w-full outline-none focus:ring-4 focus:ring-primary/5 -gray-200/20 dark:-none transition-all"
 >
 </div>
 <select 
 v-model="statusFilter" 
 class="px-4 md:px-8 py-4 md:py-5 bg-[#151a22] dark:bg-[#151a22] border border-[#21262d] dark:border-[#21262d] rounded-[1.5rem] md:rounded-[2rem] text-[10px] font-black outline-none focus:ring-4 focus:ring-primary/5 -gray-200/20 dark:-none uppercase tracking-widest text-[#a4aea6] dark:text-[#c3c4c5] min-w-[140px] md:min-w-[180px] appearance-none"
 >
 <option value="all">Estados: TODOS</option>
 <option value="completed">Terminados</option>
 <option value="shipped">Entregados</option>
 <option value="printing">Produccion</option>
 <option value="pending">⏳ Pendientes</option>
 </select>
 </div>

 <!-- Filtro de Fechas -->
 <div class="lg:col-span-7 bg-[#151a22] dark:bg-[#151a22] p-2 md:p-3 rounded-[1.5rem] md:rounded-[2rem] border border-[#21262d] dark:border-[#21262d] -gray-200/20 dark:-none flex flex-col sm:flex-row items-center gap-2 md:gap-3 flex-wrap">
 <div class="flex items-center gap-2 md:gap-4 px-3 md:px-6 py-2 md:py-3 bg-[#151a22]/80 dark:bg-[#151a22]/5 rounded-[1.5rem] border border-gray-50 dark:border-[#21262d] flex-1 w-full sm:w-auto min-w-0">
 <span class="text-[8px] md:text-[10px] font-black text-[#c3c4c5] dark:text-[#a4aea6] uppercase tracking-widest">Desde</span>
 <input v-model="dateRange.start" type="date" class="bg-transparent border-none text-[10px] md:text-xs font-black text-[#ffffff] dark:text-white outline-none p-0 flex-1 sm:w-28 md:w-32 uppercase tracking-tighter">
 <div class="h-3 md:h-4 w-px bg-gray-200 dark:bg-[#151a22]/10 mx-1 md:mx-2"></div>
 <span class="text-[8px] md:text-[10px] font-black text-[#c3c4c5] dark:text-[#a4aea6] uppercase tracking-widest">Hasta</span>
 <input v-model="dateRange.end" type="date" class="bg-transparent border-none text-[10px] md:text-xs font-black text-[#ffffff] dark:text-white outline-none p-0 flex-1 sm:w-28 md:w-32 uppercase tracking-tighter">
 </div>
 
 <div class="flex items-center gap-1.5 md:gap-2 w-full sm:w-auto">
 <button class="flex-1 sm:flex-none px-3 md:px-6 py-2 md:py-3 bg-[#090d0a] dark:bg-[#08872b] text-white text-[9px] md:text-[10px] font-black uppercase tracking-widest rounded-[6px] hover:bg-[#08872b] dark:hover:bg-[#151a22] dark:hover:text-[#8dd6ff] transition-all " @click="setQuickMonth(0)">Actual</button>
 <button class="flex-1 sm:flex-none px-3 md:px-6 py-2 md:py-3 bg-[#151a22] dark:bg-[#151a22]/5 text-[#a4aea6] dark:text-[#c3c4c5] text-[9px] md:text-[10px] font-black uppercase tracking-widest rounded-[6px] hover:bg-gray-200 dark:hover:bg-[#151a22]/10 transition-all" @click="setQuickMonth(-1)">Anterior</button>
 <button 
 v-if="dateRange.start || dateRange.end"
 class="p-2 md:p-3 text-rose-500 hover:bg-rose-50 rounded-[6px] transition-all shrink-0 border border-rose-100" 
 @click="dateRange.start = ''; dateRange.end = ''"
 >
 <svg class="w-4 md:w-5 h-4 md:h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"/></svg>
 </button>
 </div>
 </div>
 </div>

 <!-- Vista de Métricas -->
 <div v-if="activeSubTab === 'metrics'" class="px-2 animate-in slide-in-from-right-4 duration-500">
 <div class="bg-[#090d0a] rounded-[3rem] md:rounded-[4rem] p-6 md:p-16 text-white relative overflow-hidden mb-10 -black/40 border border-white/5 group">
 <div class="absolute -right-20 -top-20 w-80 h-80 bg-[#08872b]/10 rounded-[60px] blur-[100px] group-hover:bg-[#08872b]/20 transition-all duration-1000"></div>
 <div class="relative z-10">
 <h3 class="text-2xl md:text-4xl font-black uppercase tracking-tighter mb-4">Balanced Scorecard</h3>
 <p class="text-[#a4aea6] text-[10px] md:text-xs font-black uppercase tracking-[0.3em] mb-12 max-w-md">Análisis de rendimiento financiero y volumétrico del periodo</p>
 
 <div class="grid grid-cols-2 md:grid-cols-3 gap-6 md:gap-10">
 <div class="p-8 rounded-[2.5rem] bg-[#151a22]/5 border border-white/10 hover:bg-[#151a22]/10 transition-all duration-500">
 <span class="text-[10px] md:text-[10px] font-black text-[#8dd6ff] uppercase tracking-widest block mb-3">Flujo Órdenes</span>
 <div class="text-4xl md:text-6xl font-black tracking-tighter text-white">{{ filteredOrders.length }}</div>
 </div>
 <div class="p-8 rounded-[2.5rem] bg-[#151a22]/5 border border-white/10 hover:bg-[#151a22]/10 transition-all duration-500">
 <span class="text-[10px] md:text-[10px] font-black text-emerald-400 uppercase tracking-widest block mb-3">Capitalizado</span>
 <div class="text-3xl md:text-6xl font-black tracking-tighter">$ {{ Math.round(metricsSummary.total).toLocaleString() }}</div>
 </div>
 <div class="col-span-2 md:col-span-1 p-8 rounded-[2.5rem] bg-[#151a22]/5 border border-white/10 hover:bg-[#151a22]/10 transition-all duration-500">
 <span class="text-[10px] md:text-[10px] font-black text-indigo-400 uppercase tracking-widest block mb-3">Producción</span>
 <div class="text-4xl md:text-6xl font-black tracking-tighter">{{ metricsSummary.weight }} <span class="text-xl md:text-2xl font-bold opacity-30 italic">KG</span></div>
 </div>
 </div>
 </div>
 </div>
 
 <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 md:gap-8">
 <div class="bg-[#151a22] dark:bg-[#151a22] p-6 md:p-10 rounded-[24px] md:rounded-[3rem] border border-[#21262d] dark:border-[#21262d] dark:-none">
 <h4 class="text-[10px] md:text-xs font-black uppercase tracking-widest text-[#c3c4c5] dark:text-[#a4aea6] mb-6 md:mb-8 border-l-4 border-primary pl-4">Distribución Técnica</h4>
 <div class="space-y-6">
 <div v-for="tech in ['FDM', 'SLA']" :key="tech" class="space-y-2">
 <div class="flex justify-between items-end">
 <span class="text-sm font-black text-[#ffffff] dark:text-white">{{ tech === 'FDM' ? 'Filamento' : 'Resina' }}</span>
 <span class="text-sm font-black text-[#c3c4c5] dark:text-[#a4aea6]">{{ filteredOrders.filter(o => o.technology === tech).length }} órdenes</span>
 </div>
 <div class="h-3 bg-[#151a22] dark:bg-[#151a22]/5 rounded-[60px] overflow-hidden p-0.5">
 <div class="h-full rounded-[60px] transition-all duration-1000" :class="tech === 'FDM' ? 'bg-[#08872b]' : 'bg-indigo-500'" :style="{ width: ((filteredOrders.filter(o => o.technology === tech).length / (filteredOrders.length || 1)) * 100) + '%' }"></div>
 </div>
 </div>
 </div>
 </div>
 
 <div class="bg-[#151a22] dark:bg-[#151a22] p-6 md:p-10 rounded-[24px] md:rounded-[3rem] border border-[#21262d] dark:border-[#21262d] dark:-none flex flex-col relative overflow-hidden">
 <h4 class="text-[10px] md:text-xs font-black uppercase tracking-widest text-[#c3c4c5] dark:text-[#a4aea6] mb-6 md:mb-8 border-l-4 border-gray-900 dark:border-primary pl-4">Clientes Top</h4>
 
 <div class="flex-1 space-y-6">
 <div v-for="(customer, idx) in topCustomers" :key="customer.name" class="flex items-center gap-4 group">
 <div class="w-10 h-10 rounded-[24px] bg-[#151a22] dark:bg-[#151a22]/5 flex items-center justify-center font-black text-xs text-[#c3c4c5] dark:text-[#a4aea6] group-hover:bg-[#08872b] group-hover:text-white transition-all">
 {{ idx + 1 }}
 </div>
 <div class="flex-1">
 <div class="text-xs font-black text-[#ffffff] dark:text-white uppercase truncate max-w-[150px]">{{ customer.name }}</div>
 <div class="text-[10px] font-bold text-[#c3c4c5] dark:text-[#a4aea6] uppercase tracking-widest">{{ customer.count }} órdenes registradas</div>
 </div>
 <div class="text-sm font-black text-[#ffffff] dark:text-white">${{ customer.total.toLocaleString() }}</div>
 </div>

 <div v-if="topCustomers.length === 0" class="py-10 text-center">
 <p class="text-[10px] font-black text-gray-300 uppercase tracking-[0.2em]">Sin datos en este periodo</p>
 </div>
 </div>

 <div class="mt-8 pt-6 border-t border-[#21262d] dark:border-[#21262d]">
 <button 
 class="w-full py-4 bg-[#151a22] dark:bg-[#08872b] text-white rounded-[24px] font-black text-[10px] uppercase tracking-widest hover:bg-[#08872b] dark:hover:bg-[#151a22] dark:hover:text-[#8dd6ff] transition-all active:scale-95 flex items-center justify-center gap-3"
 @click="$emit('export-report', { type: 'metrics', start: dateRange.start, end: dateRange.end, filteredOrders })"
 >
 <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
 Balance Ejecutivo PDF
 </button>
 </div>
 </div>
 </div>
 </div>

 <!-- Skeleton Loading: tabla mientras carga -->
 <div v-if="activeSubTab === 'list' && loadingAnalytics" class="mx-4 animate-pulse">
 <div class="bg-[#151a22] dark:bg-[#151a22] rounded-[3rem] md:rounded-[4rem] border border-[#21262d] dark:border-[#21262d] overflow-hidden">
 <div class="overflow-x-auto">
 <table class="w-full text-left border-collapse min-w-[900px]">
 <thead>
 <tr class="border-b border-[#21262d] dark:border-[#21262d] bg-[#151a22]/50 dark:bg-[#151a22]/5">
 <th v-for="i in 7" :key="'th-'+i" class="p-8"><div class="h-4 bg-gray-200 dark:bg-[#2a3040] rounded-[60px] w-16"></div></th>
 </tr>
 </thead>
 <tbody>
 <tr v-for="row in 5" :key="'row-'+row" class="border-b border-gray-50 dark:border-[#21262d]">
 <td v-for="col in 7" :key="'col-'+col" class="p-8">
 <div v-if="col === 1" class="h-6 bg-gray-200 dark:bg-[#2a3040] rounded-[6px] w-20"></div>
 <div v-else-if="col === 2" class="h-4 bg-gray-200 dark:bg-[#2a3040] rounded-[60px] w-24"></div>
 <div v-else-if="col === 3" class="space-y-2">
 <div class="h-4 bg-gray-200 dark:bg-[#2a3040] rounded-[60px] w-28"></div>
 <div class="h-3 bg-gray-200 dark:bg-[#2a3040] rounded-[60px] w-20"></div>
 </div>
 <div v-else-if="col === 4" class="flex gap-2">
 <div class="h-6 bg-gray-200 dark:bg-[#2a3040] rounded-[6px] w-12"></div>
 <div class="h-6 bg-gray-200 dark:bg-[#2a3040] rounded-[6px] w-16"></div>
 </div>
 <div v-else-if="col === 5" class="h-5 bg-gray-200 dark:bg-[#2a3040] rounded-[60px] w-20"></div>
 <div v-else-if="col === 6" class="h-6 bg-gray-200 dark:bg-[#2a3040] rounded-[6px] w-24 mx-auto"></div>
 <div v-else class="flex justify-end gap-2">
 <div v-for="btn in 4" :key="'btn-'+btn" class="w-10 h-10 bg-gray-200 dark:bg-[#2a3040] rounded-[6px]"></div>
 </div>
 </td>
 </tr>
 </tbody>
 </table>
 </div>
 </div>
 </div>

 <!-- Tabla (real) -->
 <div v-if="activeSubTab === 'list' && !loadingAnalytics" class="bg-[#151a22] dark:bg-[#151a22] rounded-[3rem] md:rounded-[4rem] border border-[#21262d] dark:border-[#21262d] -gray-200/20 dark:-none overflow-hidden mx-4">
 <div class="overflow-x-auto no-scrollbar">
 <table class="w-full text-left border-collapse min-w-[900px]">
 <thead>
 <tr class="border-b border-[#21262d] dark:border-[#21262d] bg-[#151a22]/50 dark:bg-[#151a22]/5">
 <th class="p-4 md:p-8 text-[10px] font-black text-[#c3c4c5] dark:text-[#a4aea6] uppercase tracking-[0.2em] whitespace-nowrap">Registro</th>
 <th class="p-4 md:p-8 text-[10px] font-black text-[#c3c4c5] dark:text-[#a4aea6] uppercase tracking-[0.2em] whitespace-nowrap">Fecha</th>
 <th class="p-4 md:p-8 text-[10px] font-black text-[#c3c4c5] dark:text-[#a4aea6] uppercase tracking-[0.2em] whitespace-nowrap">Cliente</th>
 <th class="p-4 md:p-8 text-[10px] font-black text-[#c3c4c5] dark:text-[#a4aea6] uppercase tracking-[0.2em] whitespace-nowrap">Config.</th>
 <th class="p-4 md:p-8 text-[10px] font-black text-[#c3c4c5] dark:text-[#a4aea6] uppercase tracking-[0.2em] whitespace-nowrap">Valor</th>
 <th class="p-4 md:p-8 text-[10px] font-black text-[#c3c4c5] dark:text-[#a4aea6] uppercase tracking-[0.2em] whitespace-nowrap text-center">Estado</th>
 <th class="p-4 md:p-8 text-[10px] font-black text-[#c3c4c5] dark:text-[#a4aea6] uppercase tracking-[0.2em] whitespace-nowrap text-right">Acciones</th>
 </tr>
 </thead>
 <tbody>
 <tr v-for="order in filteredOrders" :key="order.id" class="border-b border-gray-50 dark:border-[#21262d] hover:bg-[#151a22]/80 dark:hover:bg-[#151a22]/5 transition-all group cursor-pointer">
 <td class="p-4 md:p-8">
 <span class="px-3 md:px-4 py-1.5 md:py-2 bg-[#090d0a] dark:bg-[#08872b] text-white rounded-[6px] text-[9px] md:text-[10px] font-black uppercase tracking-widest -black/20">ID-{{ order.id }}</span>
 </td>
 <td class="p-4 md:p-8 text-[11px] md:text-xs font-black text-[#ffffff] dark:text-white uppercase italic">
 {{ new Date(order.created_at).toLocaleDateString() }}
 </td>
 <td class="p-4 md:p-8">
 <p class="text-xs md:text-sm font-black text-[#ffffff] dark:text-white tracking-tighter uppercase truncate max-w-[100px] md:max-w-none">{{ order.customer_name }}</p>
 <p class="text-[9px] md:text-[10px] font-bold text-[#c3c4c5] dark:text-[#a4aea6] uppercase tracking-widest truncate max-w-[100px] md:max-w-none">{{ order.customer_email || 'Sin Contacto' }}</p>
 </td>
 <td class="p-4 md:p-8">
 <div class="flex items-center gap-2 md:gap-3">
 <span :class="order.technology === 'FDM' ? 'bg-indigo-600' : 'bg-[#08872b]'" class="px-2 md:px-3 py-1 md:py-1.5 text-white rounded-[6px] text-[8px] md:text-[10px] font-black uppercase tracking-widest ">{{ order.technology }}</span>
 <span class="text-[9px] md:text-[10px] font-black text-[#ffffff] dark:text-gray-300">{{ order.estimated_weight_g }}<span class="text-gray-300 dark:text-gray-700">g</span></span>
 </div>
 </td>
 <td class="p-4 md:p-8">
 <p class="text-sm md:text-lg font-black text-[#ffffff] dark:text-white tracking-tighter">${{ Number(order.total_price).toLocaleString() }}</p>
 </td>
 <td class="p-4 md:p-8 text-center">
 <span :class="['px-2 md:px-4 py-1 md:py-2 rounded-[6px] text-[8px] md:text-[10px] font-black uppercase tracking-[0.1em] border ', getStatusColor(order.status)]">
 {{ getStatusLabel(order.status) }}
 </span>
 </td>
 <td class="p-4 md:p-8">
 <div class="flex items-center justify-end gap-1 md:gap-2">
 <button class="w-8 md:w-10 h-8 md:h-10 rounded-[6px] bg-blue-50 text-blue-600 flex items-center justify-center hover:bg-blue-600 hover:text-white transition-all " title="Ver Detalles" @click="$emit('view-details', order)">
 <svg class="w-3.5 md:w-4 h-3.5 md:h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
 </button>
 <button class="w-8 md:w-10 h-8 md:h-10 rounded-[6px] bg-[#090d0a] text-white flex items-center justify-center hover:bg-[#08872b] transition-all " title="Cotización PDF" @click="$emit('download-pdf', order)">
 <svg class="w-3.5 md:w-4 h-3.5 md:h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
 </button>
 <button v-if="order.status === 'shipped'" class="w-8 md:w-10 h-8 md:h-10 rounded-[6px] bg-emerald-50 text-emerald-600 flex items-center justify-center hover:bg-emerald-600 hover:text-white transition-all " title="Rótulo de Envío" @click="$emit('print-label', order)">
 <svg class="w-3.5 md:w-4 h-3.5 md:h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
 </button>
 <button class="w-8 md:w-10 h-8 md:h-10 rounded-[6px] bg-rose-50 text-rose-500 flex items-center justify-center hover:bg-rose-600 hover:text-white transition-all " title="Eliminar Registro" @click="$emit('delete', order.id)">
 <svg class="w-3.5 md:w-4 h-3.5 md:h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
 </button>
 </div>
 </td>
 </tr>
 <tr v-if="filteredOrders.length === 0">
 <td colspan="7" class="p-20 text-center text-[#c3c4c5] font-bold uppercase tracking-widest text-xs">
 No hay registros que coincidan con la búsqueda
 </td>
 </tr>
 </tbody>
 </table>
 </div>
 </div>
 </div>
</template>

<style scoped>
.tooltip {
 position: relative;
}
.tooltip:hover::after {
 content: attr(title);
 position: absolute;
 bottom: 100%;
 left: 50%;
 transform: translateX(-50%) translateY(-4px);
 background: #111827;
 color: white;
 padding: 4px 8px;
 border-radius: 6px;
 font-size: 10px;
 font-weight: 800;
 text-transform: uppercase;
 letter-spacing: 0.1em;
 white-space: nowrap;
 pointer-events: none;
 z-index: 10;
}
</style>
