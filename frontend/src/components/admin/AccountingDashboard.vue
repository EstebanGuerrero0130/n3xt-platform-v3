<script setup lang="ts">
import { ref, computed, onUnmounted, defineAsyncComponent, type PropType } from 'vue'

// ─── ECharts Lazy Load (antes registrado globalmente en main.ts) ───
const VChart = defineAsyncComponent(async () => {
 const [{ use }, { CanvasRenderer }, { LineChart, PieChart, BarChart }, components] = await Promise.all([
 import('echarts/core'),
 import('echarts/renderers'),
 import('echarts/charts'),
 import('echarts/components')
 ])

 use([
 CanvasRenderer,
 LineChart,
 PieChart,
 BarChart,
 components.GridComponent,
 components.TooltipComponent,
 components.LegendComponent,
 components.TitleComponent,
 components.ToolboxComponent,
 components.DataZoomComponent
 ])

 const { default: VChart } = await import('vue-echarts')
 return VChart
})

defineEmits(['export', 'toggle-paid'])

const props = defineProps({
 analytics: { type: Object as PropType<any>, default: null },
 loading: { type: Boolean, default: false }
})

// --- Dark Mode Detection ---
const isDark = ref(document.documentElement.classList.contains('dark'))
const chartTheme = computed(() => isDark.value ? 'dark' : '')

// Watch for dark mode changes
const darkObserver = new MutationObserver(() => {
 isDark.value = document.documentElement.classList.contains('dark')
})
darkObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] })

onUnmounted(() => {
 darkObserver.disconnect()
})

// --- ECharts Line Chart Option (Flujo de Ingresos) ---
const lineChartOption = computed(() => {
 const monthly = props.analytics?.monthly ? [...props.analytics.monthly] : []
 const months = monthly.map(m => m.month)
 const isD = isDark.value

 return {
 color: ['#8dd6ff', '#ff7b72', '#08872b'],
 tooltip: {
 trigger: 'axis',
 backgroundColor: isD ? 'rgba(13,17,23,0.95)' : 'rgba(255,255,255,0.95)',
 borderColor: isD ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.1)',
 borderWidth: 1,
 padding: [14, 18],
 textStyle: {
 fontSize: 12,
 fontWeight: 600,
 color: isD ? '#f0f6fc' : '#1f2937',
 fontFamily: 'Inter, sans-serif'
 },
 formatter: (params: any) => {
 const title = `<span style="font-size:13px;font-weight:800;color:${isD ? '#fff' : '#111'}">${params[0].axisValue}</span>`
 const lines = params.map((p: any) => 
 `<div style="display:flex;align-items:center;gap:8px;margin-top:6px">
 <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:${p.color}"></span>
 <span style="font-weight:600;color:${isD ? '#f0f6fc' : '#374151'}">${p.seriesName}</span>
 <span style="margin-left:auto;font-weight:800;color:${isD ? '#fff' : '#111'}">${formatPrice(p.value)}</span>
 </div>`
 ).join('')
 return `${title}${lines}`
 }
 },
 legend: {
 data: ['Ventas (Ingresos)', 'Gastos Operativos', 'Utilidad Neta'],
 top: 0,
 right: 0,
 icon: 'circle',
 itemWidth: 10,
 itemHeight: 10,
 textStyle: {
 fontSize: 11,
 fontWeight: 700,
 color: isD ? '#f0f6fc' : '#64748b',
 fontFamily: 'Inter, sans-serif'
 }
 },
 grid: {
 left: '3%',
 right: '4%',
 bottom: '3%',
 top: '60px',
 containLabel: true
 },
 xAxis: {
 type: 'category',
 data: months,
 boundaryGap: false,
 axisLine: { show: false },
 axisTick: { show: false },
 axisLabel: {
 fontSize: 10,
 fontWeight: 600,
 color: isD ? '#f0f6fc' : '#94a3b8',
 fontFamily: 'Inter, sans-serif'
 },
 splitLine: { show: false }
 },
 yAxis: {
 type: 'value',
 splitLine: {
 lineStyle: {
 color: isD ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.06)',
 type: 'dashed'
 }
 },
 axisLabel: {
 fontSize: 10,
 fontWeight: 600,
 color: isD ? '#f0f6fc' : '#94a3b8',
 fontFamily: 'Inter, sans-serif',
 formatter: (v: any) => '$' + v.toLocaleString(undefined, {maximumFractionDigits: 0})
 },
 axisLine: { show: false },
 axisTick: { show: false }
 },
 series: [
 {
 name: 'Ventas (Ingresos)',
 type: 'line',
 smooth: true,
 symbol: 'circle',
 symbolSize: 6,
 lineStyle: { width: 3 },
 areaStyle: {
 color: {
 type: 'linear',
 x: 0, y: 0, x2: 0, y2: 1,
 colorStops: [
 { offset: 0, color: 'rgba(141, 214, 255, 0.25)' },
 { offset: 1, color: 'rgba(141, 214, 255, 0.02)' }
 ]
 }
 },
 data: monthly.map(m => m.revenue),
 animationDuration: 1500,
 animationEasing: 'cubicOut'
 },
 {
 name: 'Gastos Operativos',
 type: 'line',
 smooth: true,
 symbol: 'diamond',
 symbolSize: 6,
 lineStyle: { width: 2, type: 'dashed' },
 areaStyle: {
 color: {
 type: 'linear',
 x: 0, y: 0, x2: 0, y2: 1,
 colorStops: [
 { offset: 0, color: 'rgba(255, 123, 114, 0.08)' },
 { offset: 1, color: 'rgba(255, 123, 114, 0.01)' }
 ]
 }
 },
 data: monthly.map(m => m.expenses),
 animationDuration: 1800,
 animationEasing: 'cubicOut'
 },
 {
 name: 'Utilidad Neta',
 type: 'line',
 smooth: true,
 symbol: 'circle',
 symbolSize: 6,
 lineStyle: { width: 3 },
 areaStyle: {
 color: {
 type: 'linear',
 x: 0, y: 0, x2: 0, y2: 1,
 colorStops: [
 { offset: 0, color: 'rgba(8, 135, 43, 0.25)' },
 { offset: 1, color: 'rgba(8, 135, 43, 0.02)' }
 ]
 }
 },
 data: monthly.map(m => m.profit),
 animationDuration: 2000,
 animationEasing: 'cubicOut'
 }
 ],
 // Enable data zoom for interactivity
 dataZoom: [
 {
 type: 'inside',
 start: 0,
 end: 100
 }
 ]
 }
})

// --- ECharts Pie Chart Option (Carga por Tecnología) ---
const techChartOption = computed(() => {
 const counts = props.analytics?.by_technology || []
 const isD = isDark.value
 const total = counts.reduce((s: any, c: any) => s + c.count, 0)

 return {
 color: ['#08872b', '#8dd6ff', '#58a6ff', '#bc8cff', '#ff7b72', '#fbbf24'],
 tooltip: {
 trigger: 'item',
 backgroundColor: isD ? 'rgba(13,17,23,0.95)' : 'rgba(255,255,255,0.95)',
 borderColor: isD ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.1)',
 borderWidth: 1,
 padding: [14, 18],
 textStyle: {
 fontSize: 12,
 fontWeight: 600,
 color: isD ? '#f0f6fc' : '#1f2937',
 fontFamily: 'Inter, sans-serif'
 },
 formatter: (params: any) => {
 const pct = ((Number(params.value) / Number(total)) * 100).toFixed(1)
 return `
 <div style="font-size:13px;font-weight:800;color:${isD ? '#fff' : '#111'}">${params.name}</div>
 <div style="display:flex;align-items:center;gap:8px;margin-top:6px">
 <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:${params.color}"></span>
 <span style="font-weight:600;color:${isD ? '#f0f6fc' : '#374151'}">Pedidos: ${params.value}</span>
 <span style="margin-left:auto;font-weight:800;color:${isD ? '#8dd6ff' : '#059669'}">${pct}%</span>
 </div>`
 }
 },
 legend: {
 show: counts.length <= 6,
 orient: 'vertical',
 right: 0,
 top: 'center',
 icon: 'circle',
 itemWidth: 10,
 itemHeight: 10,
 textStyle: {
 fontSize: 11,
 fontWeight: 700,
 color: isD ? '#f0f6fc' : '#64748b',
 fontFamily: 'Inter, sans-serif'
 }
 },
 series: [
 {
 type: 'pie',
 radius: ['55%', '78%'],
 center: ['35%', '50%'],
 avoidLabelOverlap: true,
 padAngle: 2,
 itemStyle: {
 borderRadius: 4,
 borderColor: isD ? '#151a22' : '#fff',
 borderWidth: 3
 },
 label: {
 show: false
 },
 emphasis: {
 label: {
 show: true,
 fontSize: 13,
 fontWeight: 800,
 color: isD ? '#fff' : '#111'
 },
 itemStyle: {
 shadowBlur: 20,
 shadowOffsetX: 0,
 shadowColor: 'rgba(0, 0, 0, 0.3)'
 }
 },
 labelLine: {
 show: false
 },
 data: counts.map((c: any) => ({ name: c.technology, value: c.count })),
 animationDuration: 1200,
 animationEasing: 'cubicOut'
 }
 ]
 }
})
const formatPrice = (val: any) => {
 const num = Number(val || 0)
 if (num < 0) return `-$${Math.abs(num).toLocaleString(undefined, {maximumFractionDigits: 0})}`
 return `$${num.toLocaleString(undefined, {maximumFractionDigits: 0})}`
}

const selectedOrderId = ref<any>(null)

const displayBreakdown = computed(() => {
 if (!props.analytics) return {}
 if (selectedOrderId.value) {
 const order = props.analytics.detailed_orders?.find((o: any) => o.id === selectedOrderId.value)
 return order ? order.breakdown : props.analytics.summary?.breakdown
 }
 return props.analytics.summary?.breakdown
})

const displaySummary = computed(() => {
 if (!props.analytics) return {}
 if (selectedOrderId.value) {
 const order = props.analytics.detailed_orders?.find((o: any) => o.id === selectedOrderId.value)
 if (order) {
 return {
 total_revenue: order.total_price,
 total_expenses: order.expenses,
 net_profit: order.profit,
 collected_revenue: order.is_paid ? order.total_price : 0,
 pending_revenue: order.is_paid ? 0 : order.total_price,
 profit_margin_pct: order.total_price > 0 ? Math.round((order.profit / order.total_price) * 100) : 0
 }
 }
 }
 return props.analytics.summary
})

const selectOrder = (id: any) => {
 selectedOrderId.value = selectedOrderId.value === id ? null : id
}
</script>

<template>
 <div v-if="analytics || loading" class="animate-fade-in">
 <!-- Header Strategy -->
 <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-6 mb-8 md:mb-16 px-8 lg:px-10">
 <div>
 <h2 class="text-3xl md:text-5xl font-black text-[#ffffff] dark:text-white tracking-tighter uppercase mb-2">Métricas N3XT</h2>
 <div class="flex items-center gap-3">
 <div class="w-2 h-2 rounded-[60px] bg-[#1e3a34] dark:bg-[#08872b] animate-pulse"></div>
 <p class="text-[#c3c4c5] dark:text-gray-300 font-bold uppercase tracking-widest text-[10px] md:text-[10px]">Business Intelligence y Análisis</p>
 </div>
 </div>
 <button class="btn-pdf -gray-200/50 active:scale-95" @click="$emit('export')">
 <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
 <span>Exportar Data</span>
 </button>
 </div>

 <!-- Skeleton Loading: muestra la estructura real mientras carga -->
 <div v-if="loading" class="space-y-16 lg:space-y-20 animate-pulse">
 <!-- Summary Cards Skeleton -->
 <div class="grid grid-cols-2 lg:grid-cols-5 gap-5 lg:gap-8 px-4 lg:px-8">
 <div v-for="i in 5" :key="'sum-'+i" class="rounded-[24px] p-6 lg:p-10 bg-[#151a22] dark:bg-[#1a1f2e] border border-[#21262d] dark:border-[#252b3a]">
 <div class="h-3 bg-gray-200 dark:bg-[#2a3040] rounded-[60px] w-16 mb-4"></div>
 <div class="h-8 bg-gray-200 dark:bg-[#2a3040] rounded-[60px] w-28 mb-6"></div>
 <div class="h-2 bg-gray-200 dark:bg-[#2a3040] rounded-[60px] w-12"></div>
 </div>
 </div>

 <!-- Secondary Stats Skeleton -->
 <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-7 gap-5 lg:gap-8 px-6 lg:px-8 opacity-90">
 <div v-for="i in 7" :key="'sec-'+i" class="rounded-[24px] p-5 lg:p-8 bg-[#151a22] dark:bg-[#1a1f2e] border border-[#21262d] dark:border-[#252b3a] flex items-center gap-3 md:gap-5">
 <div class="w-10 h-10 md:w-12 md:h-12 bg-gray-200 dark:bg-[#2a3040] rounded-[24px] flex-shrink-0"></div>
 <div class="flex-1">
 <div class="h-2 bg-gray-200 dark:bg-[#2a3040] rounded-[60px] w-16 mb-2"></div>
 <div class="h-5 bg-gray-200 dark:bg-[#2a3040] rounded-[60px] w-20"></div>
 </div>
 </div>
 </div>

 <!-- Audit Table Skeleton -->
 <div class="px-6 lg:px-8">
 <div class="rounded-[24px] p-8 lg:p-10 bg-[#151a22] dark:bg-[#1a1f2e] border border-[#21262d] dark:border-[#252b3a]">
 <div class="flex items-center justify-between mb-8">
 <div class="h-4 bg-gray-200 dark:bg-[#2a3040] rounded-[60px] w-48"></div>
 <div class="h-3 bg-gray-200 dark:bg-[#2a3040] rounded-[60px] w-12"></div>
 </div>
 <div class="space-y-4">
 <div v-for="i in 5" :key="'row-'+i" class="h-10 bg-gray-200 dark:bg-[#2a3040] rounded-[6px]"></div>
 </div>
 </div>
 </div>

 <!-- Cost Breakdown Skeleton -->
 <div class="px-8 lg:px-10">
 <div class="h-4 bg-gray-200 dark:bg-[#2a3040] rounded-[60px] w-64 mb-6"></div>
 <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-5">
 <div v-for="i in 8" :key="'cost-'+i" class="rounded-[24px] p-6 bg-[#151a22] dark:bg-[#1a1f2e] border border-[#21262d] dark:border-[#252b3a]">
 <div class="h-5 bg-gray-200 dark:bg-[#2a3040] rounded-[60px] w-8 mx-auto mb-3"></div>
 <div class="h-2 bg-gray-200 dark:bg-[#2a3040] rounded-[60px] w-16 mx-auto mb-2"></div>
 <div class="h-4 bg-gray-200 dark:bg-[#2a3040] rounded-[60px] w-12 mx-auto"></div>
 </div>
 </div>
 </div>

 <!-- Charts Skeleton -->
 <div class="grid grid-cols-1 md:grid-cols-2 gap-12 px-8 lg:px-10">
 <div v-for="i in 2" :key="'chart-'+i" class="rounded-[24px] p-8 lg:p-10 bg-[#151a22] dark:bg-[#1a1f2e] border border-[#21262d] dark:border-[#252b3a]">
 <div class="h-4 bg-gray-200 dark:bg-[#2a3040] rounded-[60px] w-40 mb-2"></div>
 <div class="h-2 bg-gray-200 dark:bg-[#2a3040] rounded-[60px] w-24 mb-8"></div>
 <div class="h-[250px] bg-gray-200 dark:bg-[#2a3040] rounded-[24px]"></div>
 </div>
 </div>

 <!-- Top Customers Skeleton -->
 <div class="px-8 lg:px-10 pb-24">
 <div class="rounded-[24px] p-8 lg:p-10 bg-[#151a22] dark:bg-[#1a1f2e] border border-[#21262d] dark:border-[#252b3a]">
 <div class="flex items-center gap-4 mb-8">
 <div class="h-5 bg-gray-200 dark:bg-[#2a3040] rounded-[60px] w-48"></div>
 <div class="h-3 bg-gray-200 dark:bg-[#2a3040] rounded-[60px] w-16"></div>
 </div>
 <div class="space-y-4">
 <div v-for="i in 4" :key="'client-'+i" class="h-12 bg-gray-200 dark:bg-[#2a3040] rounded-[6px]"></div>
 </div>
 </div>
 </div>
 </div>
 <div v-else-if="analytics" class="space-y-16 lg:space-y-20">
 <!-- Summary Cards: Premium Grid -->
 <div class="grid grid-cols-2 lg:grid-cols-5 gap-5 lg:gap-8 px-4 lg:px-8">
 <!-- Ingresos Cobrados (Verde) -->
 <div class="bg-gradient-to-br from-[#08872b]/15 to-[#08872b]/5 dark:bg-[#151a22] border border-[#08872b]/30 dark:border-[#08872b]/20 -[#08872b]/5 rounded-[24px] p-6 lg:p-10 relative overflow-hidden group hover:border-[#08872b]/50 transition-all duration-300 animate-slide-up animate-stagger-1">
 <div class="absolute -right-6 -top-6 w-24 h-24 bg-[#151a22]/10 rounded-[60px] blur-2xl group-hover:scale-150 transition-transform"></div>
 <p class="text-[10px] font-black text-emerald-600 dark:text-[#8dd6ff] uppercase tracking-widest mb-4">Cobrados</p>
 <h3 class="text-2xl font-black text-[#ffffff] dark:text-white tracking-tighter leading-none">{{ formatPrice(displaySummary?.collected_revenue) }}</h3>
 <div class="mt-6 flex items-center gap-2">
 <span class="w-2 h-2 rounded-[60px] bg-[#08872b] dark:bg-[#151a22] animate-pulse"></span>
 <span class="text-[8px] font-bold text-[#a4aea6] dark:text-white/60 uppercase">Liquidez Activa</span>
 </div>
 </div>

 <!-- Pendiente de Cobro (Amarillo) -->
 <div class="bg-gradient-to-br from-amber-400/15 to-amber-600/5 dark:bg-[#151a22] border border-amber-500/30 dark:border-amber-500/20 -amber-500/5 rounded-[24px] p-6 lg:p-10 relative overflow-hidden group hover:border-amber-500/40 transition-all duration-300 animate-slide-up animate-stagger-2">
 <div class="absolute -right-6 -top-6 w-24 h-24 bg-[#151a22]/10 rounded-[60px] blur-2xl group-hover:scale-150 transition-transform"></div>
 <p class="text-[10px] font-black text-amber-600 dark:text-amber-300 uppercase tracking-widest mb-4">Cartera</p>
 <h3 class="text-2xl font-black text-[#ffffff] dark:text-white tracking-tighter leading-none">{{ formatPrice(displaySummary?.pending_revenue) }}</h3>
 <div class="mt-6 flex items-center gap-2">
 <span class="w-2 h-2 rounded-[60px] bg-amber-500 dark:bg-[#151a22]/40"></span>
 <span class="text-[8px] font-bold text-[#a4aea6] dark:text-white/60 uppercase">Por Recaudar</span>
 </div>
 </div>

 <!-- Utilidad Neta -->
 <div class="bg-gradient-to-br from-[#08872b]/20 to-[#08872b]/5 dark:bg-[#151a22] border border-[#08872b]/40 dark:border-[#08872b]/30 -[#08872b]/10 rounded-[24px] p-6 lg:p-10 relative overflow-hidden group hover:border-[#08872b]/60 transition-all duration-300 animate-slide-up animate-stagger-3">
 <div class="absolute -right-10 -bottom-10 w-32 h-32 bg-[#08872b]/10 rounded-[60px] blur-3xl"></div>
 <p class="text-[10px] font-black text-[#08872b] dark:text-[#8dd6ff] uppercase tracking-widest mb-4">Beneficio Neto</p>
 <h3 class="text-2xl font-black text-[#ffffff] dark:text-white tracking-tighter leading-none">{{ formatPrice(displaySummary?.net_profit) }}</h3>
 <div class="mt-6 flex items-center gap-2 text-[#8dd6ff]">
 <span class="text-lg font-black text-[#8dd6ff]">$</span>
 <span class="text-[8px] font-black uppercase text-[#a4aea6] dark:text-white/60">Rentabilidad Real</span>
 </div>
 </div>

 <!-- Margen de Beneficio -->
 <div class="bg-[#151a22] dark:bg-[#151a22] p-6 lg:p-10 rounded-[24px] border border-[#21262d] dark:border-[#21262d] dark:-none relative overflow-hidden group hover:border-primary/30 transition-all duration-300 animate-slide-up animate-stagger-4">
 <p class="text-[10px] font-black text-[#c3c4c5] dark:text-gray-300 uppercase tracking-widest mb-4">Margen Operativo</p>
 <h3 class="text-3xl font-black text-[#ffffff] dark:text-white tracking-tighter leading-none">{{ displaySummary?.profit_margin_pct || 0 }}<span class="text-[#8dd6ff] text-xl">%</span></h3>
 <div class="mt-6 w-full h-1.5 bg-[#151a22] dark:bg-[#151a22]/5 rounded-[60px] overflow-hidden">
 <div class="h-full bg-[#08872b]" :style="{width: (displaySummary?.profit_margin_pct || 0) + '%'}"></div>
 </div>
 </div>

 <!-- Costos Totales -->
 <div class="bg-gradient-to-br from-rose-500/15 to-rose-700/5 dark:bg-[#151a22] border border-[#ff7b72]/30 dark:border-[#ff7b72]/20 -[#ff7b72]/5 rounded-[24px] p-6 lg:p-10 relative overflow-hidden group hover:border-[#ff7b72]/40 transition-all duration-300">
 <p class="text-[10px] font-black text-rose-600 dark:text-[#ff7b72] uppercase tracking-widest mb-4">Egresos / Costos</p>
 <h3 class="text-2xl font-black text-[#ffffff] dark:text-white tracking-tighter leading-none">{{ formatPrice(displaySummary?.total_expenses) }}</h3>
 <div class="mt-6 flex items-center gap-2">
 <span class="w-2 h-2 rounded-[60px] bg-rose-500 dark:bg-[#151a22]/30"></span>
 <span class="text-[8px] font-bold text-[#a4aea6] dark:text-white/60 uppercase">Operación de Planta</span>
 </div>
 </div>
 </div>

 <!-- Secondary Stats Row -->
 <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-7 gap-5 lg:gap-8 px-6 lg:px-8 opacity-90">
 <div class="bg-[#151a22] dark:bg-[#151a22] p-5 lg:p-8 rounded-[24px] border border-[#21262d] dark:border-[#21262d] flex items-center gap-3 md:gap-5 hover:border-primary/30 transition-all">
 <div class="w-10 h-10 md:w-12 md:h-12 bg-indigo-50 dark:bg-indigo-500/10 rounded-[24px] flex items-center justify-center text-xs font-black text-indigo-500">M</div>
 <div>
 <p class="text-[8px] md:text-[10px] font-black text-indigo-400 uppercase">Utilidades</p>
 <p class="text-sm md:text-xl font-black text-indigo-900 dark:text-indigo-100 tracking-tighter">{{ formatPrice(analytics.summary?.total_extras_cost) }}</p>
 </div>
 </div>
 <div class="bg-[#151a22] dark:bg-[#151a22] p-5 lg:p-8 rounded-[24px] border border-[#21262d] dark:border-[#21262d] flex items-center gap-3 md:gap-5 hover:border-primary/30 transition-all">
 <div class="w-10 h-10 md:w-12 md:h-12 bg-[#151a22] dark:bg-[#151a22]/5 rounded-[24px] flex items-center justify-center text-xs font-black text-[#a4aea6]">S</div>
 <div>
 <p class="text-[8px] md:text-[10px] font-black text-[#c3c4c5] dark:text-[#a4aea6] uppercase">Ordenes</p>
 <p class="text-sm md:text-xl font-black text-[#ffffff] dark:text-white tracking-tighter">{{ analytics.summary?.orders_count || 0 }}</p>
 </div>
 </div>
 <div class="bg-[#151a22] dark:bg-[#151a22] p-5 lg:p-8 rounded-[24px] border border-[#21262d] dark:border-[#21262d] flex items-center gap-3 md:gap-5 hover:border-primary/30 transition-all">
 <div class="w-10 h-10 md:w-12 md:h-12 bg-emerald-50 dark:bg-[#08872b]/10 rounded-[24px] flex items-center justify-center text-xs font-black text-[#08872b]">P</div>
 <div>
 <p class="text-[8px] md:text-[10px] font-black text-[#c3c4c5] dark:text-[#8dd6ff] uppercase">Despachos</p>
 <p class="text-sm md:text-xl font-black text-[#ffffff] dark:text-white tracking-tighter">{{ analytics.summary?.completed_count || 0 }}</p>
 </div>
 </div>
 <div class="bg-[#151a22] dark:bg-[#151a22] p-5 lg:p-8 rounded-[24px] border border-[#21262d] dark:border-[#21262d] flex items-center gap-3 md:gap-5 hover:border-primary/30 transition-all">
 <div class="w-10 h-10 md:w-12 md:h-12 bg-blue-50 dark:bg-blue-500/10 rounded-[24px] flex items-center justify-center text-lg md:text-xl">⏱️</div>
 <div>
 <p class="text-[8px] md:text-[10px] font-black text-[#c3c4c5] dark:text-[#a4aea6] uppercase">Horas</p>
 <p class="text-sm md:text-xl font-black text-[#ffffff] dark:text-white tracking-tighter">{{ analytics.summary?.total_hours || 0 }}h</p>
 </div>
 </div>
 <div class="bg-[#151a22] dark:bg-[#151a22] p-5 lg:p-8 rounded-[24px] border border-[#21262d] dark:border-[#21262d] flex items-center gap-3 md:gap-5 hover:border-primary/30 transition-all">
 <div class="w-10 h-10 md:w-12 md:h-12 bg-amber-50 dark:bg-amber-500/10 rounded-[24px] flex items-center justify-center text-xs font-black text-amber-500">F</div>
 <div>
 <p class="text-[8px] md:text-[10px] font-black text-[#c3c4c5] dark:text-[#a4aea6] uppercase">Filamento</p>
 <p class="text-sm md:text-xl font-black text-[#ffffff] dark:text-white tracking-tighter">{{ analytics.summary?.total_weight_kg || 0 }} kg</p>
 </div>
 </div>
 <div class="bg-[#151a22] dark:bg-[#151a22] p-5 lg:p-8 rounded-[24px] border border-[#21262d] dark:border-[#21262d] flex items-center gap-3 md:gap-5 hover:border-primary/30 transition-all">
 <div class="w-10 h-10 md:w-12 md:h-12 bg-blue-50 dark:bg-blue-500/10 rounded-[24px] flex items-center justify-center text-xs font-black text-blue-500">R</div>
 <div>
 <p class="text-[8px] md:text-[10px] font-black text-[#c3c4c5] dark:text-[#a4aea6] uppercase">Resina</p>
 <p class="text-sm md:text-xl font-black text-[#ffffff] dark:text-white tracking-tighter">{{ analytics.summary?.total_resin_ml || 0 }} ml</p>
 </div>
 </div>
 <div class="bg-[#151a22] dark:bg-[#151a22] p-5 lg:p-8 rounded-[24px] border border-[#21262d] dark:border-[#21262d] flex items-center gap-3 md:gap-5 hover:border-primary/30 transition-all">
 <div class="w-10 h-10 md:w-12 md:h-12 bg-rose-50 dark:bg-rose-500/10 rounded-[24px] flex items-center justify-center text-xs font-black text-rose-500">D</div>
 <div>
 <p class="text-[8px] md:text-[10px] font-black text-rose-400 uppercase">Desperdicio</p>
 <p class="text-sm md:text-xl font-black text-rose-600 dark:text-rose-400 tracking-tighter">{{ analytics.summary?.waste_weight_g || 0 }}g</p>
 </div>
 </div>
 </div>

 <!-- Master Audit Table: Deep Dive -->
 <div class="px-6 lg:px-8">
 <div class="bg-[#151a22] dark:bg-[#151a22] p-8 lg:p-10 rounded-[24px] border border-[#21262d] dark:border-[#21262d] dark:-none">
 <div class="flex items-center justify-between mb-10">
 <h4 class="text-[10px] md:text-xs font-black text-[#ffffff] dark:text-white uppercase tracking-[0.2em] pl-4 border-l-4 border-primary">Auditoría de Pedidos</h4>
 <div class="flex items-center gap-2">
 <span v-if="selectedOrderId" class="text-[8px] md:text-[10px] font-black text-[#8dd6ff] bg-[#08872b]/10 px-3 py-1 rounded-[60px] uppercase">#{{ selectedOrderId }}</span>
 <button v-if="selectedOrderId" class="text-[8px] md:text-[10px] font-black text-rose-500 hover:underline uppercase" @click="selectedOrderId = null">X</button>
 </div>
 </div>
 <div class="overflow-x-auto no-scrollbar">
 <table class="w-full text-left border-collapse min-w-[600px]">
 <thead>
 <tr class="border-b border-gray-50 dark:border-[#21262d]">
 <th class="py-3 px-4 text-[10px] font-black text-[#c3c4c5] dark:text-[#a4aea6] uppercase tracking-widest">ID</th>
 <th class="py-3 px-4 text-[10px] font-black text-[#c3c4c5] dark:text-[#a4aea6] uppercase tracking-widest">Cliente</th>
 <th class="py-3 px-4 text-[10px] font-black text-[#c3c4c5] dark:text-[#a4aea6] uppercase tracking-widest">Total</th>
 <th class="py-3 px-4 text-[10px] font-black text-[#c3c4c5] dark:text-[#a4aea6] uppercase tracking-widest">Utilidad</th>
 <th class="py-3 px-4 text-[10px] font-black text-[#c3c4c5] dark:text-[#a4aea6] uppercase tracking-widest text-center">Pago</th>
 </tr>
 </thead>
 <tbody class="divide-y divide-gray-50 dark:divide-white/5">
 <tr
v-for="order in analytics.detailed_orders" :key="order.id" 
 :class="selectedOrderId === order.id ? 'bg-[#08872b]/5 dark:bg-[#08872b]/10' : 'hover:bg-[#151a22] dark:hover:bg-[#151a22]/5'"
 class="transition-colors group cursor-pointer border-b border-gray-50 dark:border-[#21262d]"
 @click="selectOrder(order.id)">
 <td class="py-4 px-4 text-[10px] font-black text-[#ffffff] dark:text-white">#{{ order.id }}</td>
 <td class="py-4 px-4">
 <div class="flex flex-col">
 <span class="text-[10px] font-black text-[#ffffff] dark:text-white">{{ order.customer }}</span>
 <span class="text-[8px] font-bold text-[#c3c4c5] dark:text-gray-300 uppercase truncate max-w-[120px]">{{ order.file }}</span>
 </div>
 </td>
 <td class="py-4 px-4 text-[10px] font-black text-[#ffffff] dark:text-white">{{ formatPrice(order.total_price) }}</td>
 <td class="py-4 px-4 text-[10px] font-black text-emerald-600 dark:text-[#08872b]">{{ formatPrice(order.profit) }}</td>
 <td class="py-4 px-4 text-center">
 <span
:class="order.is_paid ? 'bg-emerald-100 dark:bg-[#08872b]/20 text-emerald-600 dark:text-[#08872b]' : 'bg-amber-100 dark:bg-amber-500/20 text-amber-600 dark:text-amber-400'"
 class="text-[8px] font-black uppercase px-2 py-1 rounded-[60px]">
 {{ order.is_paid ? 'SÍ' : 'NO' }}
 </span>
 </td>
 </tr>
 </tbody>
 </table>
 </div>
 </div>
 </div>

 <!-- Desglose de Costos Industriales -->
 <div class="px-8 lg:px-10">
 <h4 class="text-xs font-black text-[#ffffff] dark:text-white uppercase tracking-[0.2em] mb-8 pl-2 border-l-4 border-primary">
 {{ selectedOrderId ? `Desglose de Costos: Pedido #${selectedOrderId}` : 'Desglose de Costos de Producción (Totales)' }}
 </h4>
 <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-5">
 <!-- Electricidad -->
 <div class="bg-[#151a22] dark:bg-[#151a22] p-6 rounded-[24px] border border-[#21262d] dark:border-[#21262d] flex flex-col justify-center items-center text-center">
 <span class="text-lg font-black text-amber-500">E</span>
 <span class="text-[10px] font-black text-[#c3c4c5] dark:text-[#a4aea6] uppercase tracking-wider mb-1">Electricidad</span>
 <span class="text-sm font-black text-[#ffffff] dark:text-white">{{ formatPrice(displayBreakdown?.luz) }}</span>
 </div>
 <!-- Mano de Obra -->
 <div class="bg-[#151a22] dark:bg-[#151a22] p-6 rounded-[24px] border border-[#21262d] dark:border-[#21262d] flex flex-col justify-center items-center text-center">
 <span class="text-lg font-black text-blue-500">T</span>
 <span class="text-[10px] font-black text-[#c3c4c5] dark:text-[#a4aea6] uppercase tracking-wider mb-1">Mano de Obra</span>
 <span class="text-sm font-black text-[#ffffff] dark:text-white">{{ formatPrice(displayBreakdown?.labor) }}</span>
 </div>
 <!-- Mantenimiento -->
 <div class="bg-[#151a22] dark:bg-[#151a22] p-6 rounded-[24px] border border-[#21262d] dark:border-[#21262d] flex flex-col justify-center items-center text-center">
 <span class="text-lg font-black text-indigo-500">I</span>
 <span class="text-[10px] font-black text-[#c3c4c5] dark:text-[#a4aea6] uppercase tracking-wider mb-1">Mantenimiento</span>
 <span class="text-sm font-black text-[#ffffff] dark:text-white">{{ formatPrice(displayBreakdown?.mant) }}</span>
 </div>
 <!-- Depreciación -->
 <div class="bg-[#151a22] dark:bg-[#151a22] p-6 rounded-[24px] border border-[#21262d] dark:border-[#21262d] flex flex-col justify-center items-center text-center">
 <span class="text-lg font-black text-[#ff7b72]">D</span>
 <span class="text-[10px] font-black text-[#c3c4c5] dark:text-[#a4aea6] uppercase tracking-wider mb-1">Depreciación</span>
 <span class="text-sm font-black text-[#ffffff] dark:text-white">{{ formatPrice(displayBreakdown?.depr) }}</span>
 </div>
 <!-- Material -->
 <div class="bg-[#151a22] dark:bg-[#151a22] p-6 rounded-[24px] border border-[#21262d] dark:border-[#21262d] flex flex-col justify-center items-center text-center border-b-2 border-b-[#08872b]/50">
 <span class="text-lg font-black text-[#8dd6ff]">F</span>
 <span class="text-[10px] font-black text-[#c3c4c5] dark:text-[#a4aea6] uppercase tracking-wider mb-1">Material Base</span>
 <span class="text-sm font-black text-[#ffffff] dark:text-white">{{ formatPrice(displayBreakdown?.mat || ((displayBreakdown?.mat_fdm || 0) + (displayBreakdown?.mat_sla || 0))) }}</span>
 </div>
 <!-- Arranque -->
 <div v-if="displayBreakdown?.setup > 0" class="bg-[#151a22] dark:bg-[#151a22] p-6 rounded-[24px] border border-[#21262d] dark:border-[#21262d] flex flex-col justify-center items-center text-center">
 <span class="text-lg font-black text-[#08872b]">G</span>
 <span class="text-[10px] font-black text-[#c3c4c5] dark:text-[#a4aea6] uppercase tracking-wider mb-1">Arranque</span>
 <span class="text-sm font-black text-[#ffffff] dark:text-white">{{ formatPrice(displayBreakdown?.setup) }}</span>
 </div>
 <!-- Etiquetas -->
 <div class="bg-[#151a22] dark:bg-[#151a22] p-6 rounded-[24px] border border-[#21262d] dark:border-[#21262d] flex flex-col justify-center items-center text-center">
 <span class="text-lg font-black text-[#a4aea6]">X</span>
 <span class="text-[10px] font-black text-[#c3c4c5] dark:text-[#a4aea6] uppercase tracking-wider mb-1">Prep/Etiquetas</span>
 <span class="text-sm font-black text-[#ffffff] dark:text-white">{{ formatPrice(displayBreakdown?.etiquetas) }}</span>
 </div>
 <!-- Extras -->
 <div class="bg-[#151a22] dark:bg-[#151a22] p-6 rounded-[24px] border border-[#21262d] dark:border-[#21262d] flex flex-col justify-center items-center text-center border-b-2 border-b-[#8dd6ff]/50">
 <span class="text-lg font-black text-[#8dd6ff]">+</span>
 <span class="text-[10px] font-black text-[#c3c4c5] dark:text-[#a4aea6] uppercase tracking-wider mb-1">Extras/Util</span>
 <span class="text-sm font-black text-[#ffffff] dark:text-white">{{ formatPrice(displayBreakdown?.extras) }}</span>
 </div>
 </div>
 </div>

 <!-- Charts Section: High Contrast -->
 <div class="grid grid-cols-1 md:grid-cols-2 gap-12 px-8 lg:px-10 pb-12">
 <div class="bg-[#151a22] dark:bg-[#151a22] p-8 lg:p-10 rounded-[24px] border border-[#21262d] dark:border-[#21262d] dark:-none">
 <div class="flex items-center justify-between mb-10">
 <div>
 <h4 class="text-xs font-black text-[#ffffff] dark:text-white uppercase tracking-[0.2em] mb-1">Flujo de Ingresos</h4>
 <p class="text-[10px] font-black text-[#c3c4c5] dark:text-[#a4aea6] uppercase">Histórico Mensual</p>
 </div>
 </div>
 <div class="h-[300px]">
 <VChart :option="lineChartOption" :theme="chartTheme" autoresize style="width:100%;height:100%" />
 </div>
 </div>

 <div class="bg-[#151a22] dark:bg-[#151a22] p-8 lg:p-10 rounded-[24px] border border-[#21262d] dark:border-[#21262d] dark:-none">
 <div class="flex items-center justify-between mb-10">
 <div>
 <h4 class="text-xs font-black text-[#ffffff] dark:text-white uppercase tracking-[0.2em] mb-1">Carga por Tecnología</h4>
 <p class="text-[10px] font-black text-[#c3c4c5] dark:text-[#a4aea6] uppercase">Distribución de Pedidos</p>
 </div>
 </div>
 <div class="h-[300px]">
 <VChart :option="techChartOption" :theme="chartTheme" autoresize style="width:100%;height:100%" />
 </div>
 </div>
 </div>
 <!-- LTV Top Customers -->
 <div class="px-8 lg:px-10 pb-24">
 <div class="bg-[#151a22] dark:bg-[#151a22] p-8 lg:p-10 rounded-[24px] border border-[#21262d] dark:border-[#21262d] dark:-none">
 <div class="flex items-center gap-4 mb-12 border-l-4 border-primary pl-6">
 <h4 class="text-xl font-black text-[#ffffff] dark:text-white uppercase tracking-tighter">Top Clientes (LTV)</h4>
 <span class="text-[10px] font-black text-[#c3c4c5] dark:text-[#a4aea6] uppercase tracking-widest bg-[#151a22] dark:bg-[#151a22]/5 px-3 py-1 rounded-[60px]">Lifetime Value</span>
 </div>
 
 <div class="no-scrollbar">
 <table class="w-full text-left border-collapse table-fixed">
 <colgroup>
 <col class="w-[50px]">
 <col class="w-[18%]">
 <col class="w-[35%]">
 <col class="w-[12%]">
 <col class="w-[20%]">
 </colgroup>
 <thead>
 <tr class="border-b-2 border-[#21262d] dark:border-[#21262d]">
 <th class="text-left py-4 px-6 text-[10px] font-black text-[#c3c4c5] dark:text-[#a4aea6] uppercase tracking-widest">#</th>
 <th class="text-left py-4 px-6 text-[10px] font-black text-[#c3c4c5] dark:text-[#a4aea6] uppercase tracking-widest">Cliente</th>
 <th class="text-left py-4 px-6 text-[10px] font-black text-[#c3c4c5] dark:text-[#a4aea6] uppercase tracking-widest">Último Trabajo</th>
 <th class="text-left py-4 px-6 text-[10px] font-black text-[#c3c4c5] dark:text-[#a4aea6] uppercase tracking-widest text-center">Pedidos</th>
 <th class="text-right py-4 px-6 text-[10px] font-black text-[#c3c4c5] dark:text-[#a4aea6] uppercase tracking-widest">Total Invertido</th>
 </tr>
 </thead>
 <tbody>
 <tr v-for="(client, index) in analytics.top_customers || []" :key="index" :class="index === 0 ? 'bg-[#08872b]/5 dark:bg-[#08872b]/10' : 'hover:bg-[#151a22]/50 dark:hover:bg-[#151a22]/5'" class="border-b border-gray-50 dark:border-[#21262d] transition-colors group">
 <td class="py-5 pl-6">
 <div
:class="[
 'w-8 h-8 rounded-[60px] flex items-center justify-center text-[10px] font-black transition-all',
 index === 0 ? 'bg-[#08872b] text-white scale-125' : 'bg-[#151a22] dark:bg-[#151a22]/5 text-[#a4aea6]'
 ]">
 {{ Number(index) + 1 }}
 </div>
 </td>
 <td class="py-5 px-6">
 <div class="flex flex-col">
 <span class="font-black text-sm text-[#f0f6fc] dark:text-white flex items-center gap-2 whitespace-nowrap">
 {{ client.customer_name }}
 <span v-if="index === 0" class="text-[8px] bg-[#08872b] text-white px-2 py-0.5 rounded-[60px] uppercase tracking-tighter flex-shrink-0">Top VIP</span>
 <span v-else-if="Number(index) < 3" class="text-[8px] bg-[#151a22] dark:bg-[#151a22] text-white dark:text-gray-950 px-2 py-0.5 rounded-[60px] uppercase tracking-tighter flex-shrink-0">Platinum</span>
 </span>
 </div>
 </td>
 <td class="py-6 px-6">
 <div class="flex flex-col">
 <span class="text-[10px] font-black text-[#c3c4c5] dark:text-[#a4aea6] uppercase tracking-widest mb-1">{{ new Date(client.last_order_date).toLocaleDateString(undefined, {month: 'short', day: 'numeric'}) }}</span>
 <span class="text-[10px] font-bold text-gray-700 dark:text-gray-300">#{{ client.last_order_id }} - {{ client.last_order_file }}</span>
 </div>
 </td>
 <td class="py-5 text-center">
 <div class="inline-flex items-center gap-1.5 px-3 py-1 bg-[#151a22] dark:bg-[#151a22]/5 rounded-[60px]">
 <span class="text-[10px] font-black text-[#ffffff] dark:text-white">{{ client.orders_count }}</span>
 <span class="text-[8px] font-bold text-[#c3c4c5] dark:text-[#a4aea6] uppercase">Proyectos</span>
 </div>
 </td>
 <td class="py-5 text-right font-black text-xl text-[#08872b] dark:text-[#8dd6ff] tracking-tighter pr-8 whitespace-nowrap">{{ formatPrice(client.total_spent) }}</td>
 </tr>
 <tr v-if="!analytics.top_customers || analytics.top_customers.length === 0">
 <td colspan="5" class="text-center py-10 text-[#c3c4c5] font-black text-xs uppercase tracking-widest">Sin datos de clientes</td>
 </tr>
 </tbody>
 </table>
 </div>
 </div>
 </div>
 </div>
 </div>
</template>
