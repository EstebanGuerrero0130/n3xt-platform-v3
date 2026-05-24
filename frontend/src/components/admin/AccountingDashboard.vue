<script setup>
import { ref, computed } from 'vue'
import { Line, Doughnut } from 'vue-chartjs'
import { 
  Chart as ChartJS, 
  registerables 
} from 'chart.js'

ChartJS.register(...registerables)


const emit = defineEmits(['export', 'back']);

const props = defineProps({
  analytics: { type: Object, required: true },
  loading: { type: Boolean, default: false }
})

const chartData = computed(() => {
  const monthly = props.analytics?.monthly ? [...props.analytics.monthly] : []
  
  // Custom gradients can be added via canvas context, but for now we use nice modern colors with opacity
  return {
    labels: monthly.map(m => m.month),
    datasets: [
      {
        label: 'Ventas (Ingresos)',
        data: monthly.map(m => m.revenue),
        borderColor: '#10b981', // emerald-500
        backgroundColor: 'rgba(16, 185, 129, 0.15)',
        fill: true,
        tension: 0.4,
        pointRadius: 4,
        pointBackgroundColor: '#10b981',
        pointBorderColor: '#ffffff',
        pointBorderWidth: 2,
        borderWidth: 3
      },
      {
        label: 'Gastos Operativos',
        data: monthly.map(m => m.expenses),
        borderColor: '#f43f5e', // rose-500
        backgroundColor: 'rgba(244, 63, 94, 0.05)',
        borderDash: [5, 5],
        fill: true,
        tension: 0.4,
        pointRadius: 4,
        pointBackgroundColor: '#f43f5e',
        pointBorderColor: '#ffffff',
        pointBorderWidth: 2,
        borderWidth: 2
      },
      {
        label: 'Utilidad Neta',
        data: monthly.map(m => m.profit),
        borderColor: '#6366f1', // indigo-500
        backgroundColor: 'rgba(99, 102, 241, 0.15)',
        fill: true,
        tension: 0.4,
        pointRadius: 4,
        pointBackgroundColor: '#6366f1',
        pointBorderColor: '#ffffff',
        pointBorderWidth: 2,
        borderWidth: 3
      }
    ]
  }
})

const techData = computed(() => {
  const counts = props.analytics?.by_technology || []
  return {
    labels: counts.map(c => c.technology),
    datasets: [{
      data: counts.map(c => c.count),
      backgroundColor: ['#10b981', '#6366f1', '#f59e0b', '#ec4899', '#8b5cf6', '#06b6d4'],
      borderWidth: 0,
      hoverOffset: 15
    }]
  }
})

const lineChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  interaction: {
    mode: 'index',
    intersect: false,
  },
  plugins: {
    legend: { 
      display: true, 
      position: 'top',
      align: 'end',
      labels: {
        usePointStyle: true,
        pointStyle: 'circle',
        padding: 20,
        color: '#94a3b8',
        font: { size: 11, weight: 'bold', family: 'Outfit, sans-serif' }
      }
    },
    tooltip: {
      backgroundColor: 'rgba(15, 23, 42, 0.95)',
      titleFont: { size: 13, weight: 'bold', family: 'Outfit, sans-serif' },
      bodyFont: { size: 12, weight: '600', family: 'Outfit, sans-serif' },
      padding: 14,
      cornerRadius: 16,
      displayColors: true,
      borderColor: 'rgba(255,255,255,0.1)',
      borderWidth: 1,
      callbacks: {
        label: (context) => `${context.dataset.label}: ${formatPrice(context.raw)}`
      }
    }
  },
  scales: {
    y: {
      grid: { color: 'rgba(148, 163, 184, 0.1)', drawBorder: false },
      border: { display: false },
      ticks: { 
        color: '#94a3b8',
        font: { weight: '600', size: 10, family: 'Outfit, sans-serif' }, 
        callback: (v) => '$' + v.toLocaleString(),
        padding: 10
      }
    },
    x: {
      grid: { display: false },
      border: { display: false },
      ticks: { 
        color: '#94a3b8',
        font: { weight: '600', size: 10, family: 'Outfit, sans-serif' },
        padding: 10
      }
    }
  }
}

const doughnutChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  cutout: '75%',
  plugins: {
    legend: { 
      display: true, 
      position: 'right',
      labels: {
        usePointStyle: true,
        pointStyle: 'circle',
        padding: 20,
        color: '#94a3b8',
        font: { size: 12, weight: 'bold', family: 'Outfit, sans-serif' }
      }
    },
    tooltip: {
      backgroundColor: 'rgba(15, 23, 42, 0.95)',
      titleFont: { size: 13, weight: 'bold', family: 'Outfit, sans-serif' },
      bodyFont: { size: 13, weight: '800', family: 'Outfit, sans-serif' },
      padding: 14,
      cornerRadius: 16,
      displayColors: true,
      borderColor: 'rgba(255,255,255,0.1)',
      borderWidth: 1,
      callbacks: {
        label: (context) => ` ${context.label}: ${context.raw} pedidos`
      }
    }
  }
}
const formatPrice = (val) => {
  const num = Number(val || 0)
  if (num < 0) return `-$${Math.abs(num).toLocaleString()}`
  return `$${num.toLocaleString()}`
}

const selectedOrderId = ref(null)

const displayBreakdown = computed(() => {
  if (!props.analytics) return {}
  if (selectedOrderId.value) {
    const order = props.analytics.detailed_orders?.find(o => o.id === selectedOrderId.value)
    return order ? order.breakdown : props.analytics.summary?.breakdown
  }
  return props.analytics.summary?.breakdown
})

const displaySummary = computed(() => {
  if (!props.analytics) return {}
  if (selectedOrderId.value) {
    const order = props.analytics.detailed_orders?.find(o => o.id === selectedOrderId.value)
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

const selectOrder = (id) => {
  selectedOrderId.value = selectedOrderId.value === id ? null : id
}
</script>

<template>
  <div v-if="analytics || loading" class="animate-fade-in">
    <!-- Header Strategy -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-6 mb-8 md:mb-16 px-6">
      <div>
        <h2 class="text-3xl md:text-5xl font-black text-gray-900 dark:text-white tracking-tighter uppercase mb-2">Métricas N3XT</h2>
        <div class="flex items-center gap-3">
          <div class="w-2 h-2 rounded-full bg-[#1e3a34] dark:bg-primary animate-pulse"></div>
          <p class="text-gray-400 dark:text-gray-300 font-bold uppercase tracking-widest text-[9px] md:text-[10px]">Business Intelligence y Análisis</p>
        </div>
      </div>
      <button @click="$emit('export')" class="btn-pdf shadow-2xl shadow-gray-200/50 active:scale-95">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
        <span>Exportar Data</span>
      </button>
    </div>

    <div v-if="loading" class="flex flex-col items-center justify-center py-40 text-gray-400">
      <div class="animate-spin w-16 h-16 border-8 border-gray-100 border-t-primary rounded-full mb-8"></div>
      <p class="font-black uppercase tracking-[0.3em] text-xs">Compilando Métricas Maestras...</p>
    </div>

    <div v-else-if="analytics" class="space-y-12">
      <!-- Summary Cards: Premium Grid -->
      <div class="grid grid-cols-2 lg:grid-cols-5 gap-6 px-4 md:px-6">
        <!-- Ingresos Cobrados (Verde) -->
        <div class="bg-gradient-to-br from-emerald-500 to-emerald-700 p-8 rounded-[3rem] shadow-2xl shadow-emerald-500/20 relative overflow-hidden group hover:scale-[1.02] transition-transform duration-300 animate-slide-up animate-stagger-1">
          <div class="absolute -right-6 -top-6 w-24 h-24 bg-white/10 rounded-full blur-2xl group-hover:scale-150 transition-transform"></div>
          <p class="text-[9px] font-black text-emerald-100 uppercase tracking-widest mb-4">Cobrados</p>
          <h3 class="text-2xl font-black text-white tracking-tighter leading-none">{{ formatPrice(displaySummary?.collected_revenue) }}</h3>
          <div class="mt-6 flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-white animate-pulse"></span>
            <span class="text-[8px] font-bold text-white/60 uppercase">Liquidez Activa</span>
          </div>
        </div>

        <!-- Pendiente de Cobro (Amarillo) -->
        <div class="bg-gradient-to-br from-amber-400 to-amber-600 p-8 rounded-[3rem] shadow-2xl shadow-amber-500/20 relative overflow-hidden group hover:scale-[1.02] transition-transform duration-300 animate-slide-up animate-stagger-2">
          <div class="absolute -right-6 -top-6 w-24 h-24 bg-white/10 rounded-full blur-2xl group-hover:scale-150 transition-transform"></div>
          <p class="text-[9px] font-black text-amber-100 uppercase tracking-widest mb-4">Cartera</p>
          <h3 class="text-2xl font-black text-white tracking-tighter leading-none">{{ formatPrice(displaySummary?.pending_revenue) }}</h3>
          <div class="mt-6 flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-white/40"></span>
            <span class="text-[8px] font-bold text-white/60 uppercase">Por Recaudar</span>
          </div>
        </div>

        <!-- Utilidad Neta -->
        <div class="bg-gray-950 p-8 rounded-[3rem] shadow-2xl shadow-black/30 relative overflow-hidden group border border-white/5 hover:scale-[1.02] transition-transform duration-300 animate-slide-up animate-stagger-3">
          <div class="absolute -right-10 -bottom-10 w-32 h-32 bg-primary/10 rounded-full blur-3xl"></div>
          <p class="text-[9px] font-black text-primary uppercase tracking-widest mb-4">Beneficio Neto</p>
          <h3 class="text-2xl font-black text-white tracking-tighter leading-none">{{ formatPrice(displaySummary?.net_profit) }}</h3>
          <div class="mt-6 flex items-center gap-2 text-primary">
             <span class="text-lg font-black text-primary">$</span>
             <span class="text-[8px] font-black uppercase">Rentabilidad Real</span>
          </div>
        </div>

        <!-- Margen de Beneficio -->
        <div class="bg-white dark:bg-gray-900 p-8 rounded-[3rem] border border-gray-100 dark:border-white/5 shadow-xl dark:shadow-none relative overflow-hidden group hover:scale-[1.02] transition-transform duration-300 animate-slide-up animate-stagger-4">
          <p class="text-[9px] font-black text-gray-400 dark:text-gray-300 uppercase tracking-widest mb-4">Margen Operativo</p>
          <h3 class="text-3xl font-black text-gray-900 dark:text-white tracking-tighter leading-none">{{ displaySummary?.profit_margin_pct || 0 }}<span class="text-primary text-xl">%</span></h3>
          <div class="mt-6 w-full h-1.5 bg-gray-100 dark:bg-white/5 rounded-full overflow-hidden">
             <div class="h-full bg-primary" :style="{width: (displaySummary?.profit_margin_pct || 0) + '%'}"></div>
          </div>
        </div>

        <!-- Costos Totales -->
        <div class="bg-gradient-to-br from-rose-500 to-rose-700 p-8 rounded-[3rem] shadow-2xl shadow-rose-500/20 relative overflow-hidden group">
          <p class="text-[9px] font-black text-rose-100 uppercase tracking-widest mb-4">Egresos / Costos</p>
          <h3 class="text-2xl font-black text-white tracking-tighter leading-none">{{ formatPrice(displaySummary?.total_expenses) }}</h3>
          <div class="mt-6 flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-white/30"></span>
            <span class="text-[8px] font-bold text-white/60 uppercase">Operación de Planta</span>
          </div>
        </div>
      </div>

      <!-- Secondary Stats Row -->
      <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 md:gap-6 px-4 md:px-6 opacity-90">
        <div class="bg-white dark:bg-gray-900 p-4 md:p-6 rounded-[1.5rem] md:rounded-[2rem] border border-gray-100 dark:border-white/5 flex items-center gap-3 md:gap-5 hover:scale-105 transition-all">
            <div class="w-10 h-10 md:w-12 md:h-12 bg-indigo-50 dark:bg-indigo-500/10 rounded-2xl flex items-center justify-center text-xs font-black text-indigo-500">M</div>
            <div>
                <p class="text-[8px] md:text-[9px] font-black text-indigo-400 uppercase">Utilidades</p>
                <p class="text-sm md:text-xl font-black text-indigo-900 dark:text-indigo-100 tracking-tighter">{{ formatPrice(analytics.summary?.total_extras_cost) }}</p>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-900 p-4 md:p-6 rounded-[1.5rem] md:rounded-[2rem] border border-gray-100 dark:border-white/5 flex items-center gap-3 md:gap-5 hover:scale-105 transition-all">
            <div class="w-10 h-10 md:w-12 md:h-12 bg-gray-50 dark:bg-white/5 rounded-2xl flex items-center justify-center text-xs font-black text-gray-500">S</div>
            <div>
                <p class="text-[8px] md:text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase">Ordenes</p>
                <p class="text-sm md:text-xl font-black text-gray-900 dark:text-white tracking-tighter">{{ analytics.summary?.orders_count || 0 }}</p>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-900 p-4 md:p-6 rounded-[1.5rem] md:rounded-[2rem] border border-gray-100 dark:border-white/5 flex items-center gap-3 md:gap-5 hover:scale-105 transition-all">
            <div class="w-10 h-10 md:w-12 md:h-12 bg-emerald-50 dark:bg-emerald-500/10 rounded-2xl flex items-center justify-center text-xs font-black text-emerald-500">P</div>
            <div>
                <p class="text-[8px] md:text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase">Despachos</p>
                <p class="text-sm md:text-xl font-black text-gray-900 dark:text-white tracking-tighter">{{ analytics.summary?.completed_count || 0 }}</p>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-900 p-4 md:p-6 rounded-[1.5rem] md:rounded-[2rem] border border-gray-100 dark:border-white/5 flex items-center gap-3 md:gap-5 hover:scale-105 transition-all">
            <div class="w-10 h-10 md:w-12 md:h-12 bg-blue-50 dark:bg-blue-500/10 rounded-2xl flex items-center justify-center text-lg md:text-xl">⏱️</div>
            <div>
                <p class="text-[8px] md:text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase">Horas</p>
                <p class="text-sm md:text-xl font-black text-gray-900 dark:text-white tracking-tighter">{{ analytics.summary?.total_hours || 0 }}h</p>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-900 p-4 md:p-6 rounded-[1.5rem] md:rounded-[2rem] border border-gray-100 dark:border-white/5 flex items-center gap-3 md:gap-5 hover:scale-105 transition-all">
            <div class="w-10 h-10 md:w-12 md:h-12 bg-amber-50 dark:bg-amber-500/10 rounded-2xl flex items-center justify-center text-xs font-black text-amber-500">F</div>
            <div>
                <p class="text-[8px] md:text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase">Filamento</p>
                <p class="text-sm md:text-xl font-black text-gray-900 dark:text-white tracking-tighter">{{ analytics.summary?.total_weight_kg || 0 }} kg</p>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-900 p-4 md:p-6 rounded-[1.5rem] md:rounded-[2rem] border border-gray-100 dark:border-white/5 flex items-center gap-3 md:gap-5 hover:scale-105 transition-all">
            <div class="w-10 h-10 md:w-12 md:h-12 bg-blue-50 dark:bg-blue-500/10 rounded-2xl flex items-center justify-center text-xs font-black text-blue-500">R</div>
            <div>
                <p class="text-[8px] md:text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase">Resina</p>
                <p class="text-sm md:text-xl font-black text-gray-900 dark:text-white tracking-tighter">{{ analytics.summary?.total_resin_ml || 0 }} ml</p>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-900 p-4 md:p-6 rounded-[1.5rem] md:rounded-[2rem] border border-gray-100 dark:border-white/5 flex items-center gap-3 md:gap-5 hover:scale-105 transition-all">
            <div class="w-10 h-10 md:w-12 md:h-12 bg-rose-50 dark:bg-rose-500/10 rounded-2xl flex items-center justify-center text-xs font-black text-rose-500">D</div>
            <div>
                <p class="text-[8px] md:text-[9px] font-black text-rose-400 uppercase">Waste</p>
                <p class="text-sm md:text-xl font-black text-rose-600 dark:text-rose-400 tracking-tighter">{{ analytics.summary?.waste_weight_g || 0 }}g</p>
            </div>
        </div>
      </div>

      <!-- Master Audit Table: Deep Dive -->
      <div class="px-4 md:px-6">
        <div class="bg-white dark:bg-gray-900 p-6 md:p-10 rounded-3xl md:rounded-[3rem] border border-gray-100 dark:border-white/5 shadow-sm dark:shadow-none">
          <div class="flex items-center justify-between mb-8">
            <h4 class="text-[10px] md:text-xs font-black text-gray-900 dark:text-white uppercase tracking-[0.2em] pl-4 border-l-4 border-primary">Auditoría de Pedidos</h4>
            <div class="flex items-center gap-2">
              <span v-if="selectedOrderId" class="text-[8px] md:text-[9px] font-black text-primary bg-primary/10 px-3 py-1 rounded-full uppercase">#{{ selectedOrderId }}</span>
              <button @click="selectedOrderId = null" v-if="selectedOrderId" class="text-[8px] md:text-[9px] font-black text-rose-500 hover:underline uppercase">X</button>
            </div>
          </div>
          <div class="overflow-x-auto no-scrollbar">
            <table class="w-full text-left border-collapse min-w-[600px]">
              <thead>
                <tr class="border-b border-gray-50">
                  <th class="py-3 px-4 text-[9px] font-black text-gray-400 uppercase tracking-widest">ID</th>
                  <th class="py-3 px-4 text-[9px] font-black text-gray-400 uppercase tracking-widest">Cliente</th>
                  <th class="py-3 px-4 text-[9px] font-black text-gray-400 uppercase tracking-widest">Total</th>
                  <th class="py-3 px-4 text-[9px] font-black text-gray-400 uppercase tracking-widest">Utilidad</th>
                  <th class="py-3 px-4 text-[9px] font-black text-gray-400 uppercase tracking-widest text-center">Pago</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-50">
                <tr v-for="order in analytics.detailed_orders" :key="order.id" 
                    :class="selectedOrderId === order.id ? 'bg-primary/5 dark:bg-primary/10' : 'hover:bg-gray-50 dark:hover:bg-white/5'"
                    class="transition-colors group cursor-pointer border-b border-gray-50 dark:border-white/5"
                    @click="selectOrder(order.id)">
                  <td class="py-4 px-4 text-[10px] font-black text-gray-900 dark:text-white">#{{ order.id }}</td>
                  <td class="py-4 px-4">
                    <div class="flex flex-col">
                      <span class="text-[10px] font-black text-gray-900 dark:text-white">{{ order.customer }}</span>
                      <span class="text-[8px] font-bold text-gray-400 dark:text-gray-300 uppercase truncate max-w-[120px]">{{ order.file }}</span>
                    </div>
                  </td>
                  <td class="py-4 px-4 text-[10px] font-black text-gray-900 dark:text-white">{{ formatPrice(order.total_price) }}</td>
                  <td class="py-4 px-4 text-[10px] font-black text-emerald-600 dark:text-emerald-400">{{ formatPrice(order.profit) }}</td>
                  <td class="py-4 px-4 text-center">
                    <span :class="order.is_paid ? 'bg-emerald-100 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400' : 'bg-amber-100 dark:bg-amber-500/20 text-amber-600 dark:text-amber-400'"
                          class="text-[8px] font-black uppercase px-2 py-1 rounded-full">
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
      <div class="px-6">
        <h4 class="text-xs font-black text-gray-900 dark:text-white uppercase tracking-[0.2em] mb-6 pl-2 border-l-4 border-primary">
          {{ selectedOrderId ? `Desglose de Costos: Pedido #${selectedOrderId}` : 'Desglose de Costos de Producción (Totales)' }}
        </h4>
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-4">
          <!-- Electricidad -->
          <div class="bg-white dark:bg-gray-900 p-5 rounded-3xl border border-gray-100 dark:border-white/5 flex flex-col justify-center items-center text-center">
            <span class="text-lg font-black text-amber-500">E</span>
            <span class="text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1">Electricidad</span>
            <span class="text-sm font-black text-gray-900 dark:text-white">{{ formatPrice(displayBreakdown?.luz) }}</span>
          </div>
          <!-- Mano de Obra -->
          <div class="bg-white dark:bg-gray-900 p-5 rounded-3xl border border-gray-100 dark:border-white/5 flex flex-col justify-center items-center text-center">
            <span class="text-lg font-black text-blue-500">T</span>
            <span class="text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1">Mano de Obra</span>
            <span class="text-sm font-black text-gray-900 dark:text-white">{{ formatPrice(displayBreakdown?.labor) }}</span>
          </div>
          <!-- Mantenimiento -->
          <div class="bg-white dark:bg-gray-900 p-5 rounded-3xl border border-gray-100 dark:border-white/5 flex flex-col justify-center items-center text-center">
            <span class="text-lg font-black text-indigo-500">I</span>
            <span class="text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1">Mantenimiento</span>
            <span class="text-sm font-black text-gray-900 dark:text-white">{{ formatPrice(displayBreakdown?.mant) }}</span>
          </div>
          <!-- Depreciación -->
          <div class="bg-white dark:bg-gray-900 p-5 rounded-3xl border border-gray-100 dark:border-white/5 flex flex-col justify-center items-center text-center">
            <span class="text-lg font-black text-rose-500">D</span>
            <span class="text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1">Depreciación</span>
            <span class="text-sm font-black text-gray-900 dark:text-white">{{ formatPrice(displayBreakdown?.depr) }}</span>
          </div>
          <!-- Material -->
          <div class="bg-white dark:bg-gray-900 p-5 rounded-3xl border border-gray-100 dark:border-white/5 flex flex-col justify-center items-center text-center border-b-2 border-b-[#1e3a34]">
            <span class="text-lg font-black text-amber-500">F</span>
            <span class="text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1">Material Base</span>
            <span class="text-sm font-black text-gray-900 dark:text-white">{{ formatPrice(displayBreakdown?.mat || ((displayBreakdown?.mat_fdm || 0) + (displayBreakdown?.mat_sla || 0))) }}</span>
          </div>
          <!-- Arranque -->
          <div v-if="displayBreakdown?.setup > 0" class="bg-white dark:bg-gray-900 p-5 rounded-3xl border border-gray-100 dark:border-white/5 flex flex-col justify-center items-center text-center">
            <span class="text-lg font-black text-emerald-500">G</span>
            <span class="text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1">Arranque</span>
            <span class="text-sm font-black text-gray-900 dark:text-white">{{ formatPrice(displayBreakdown?.setup) }}</span>
          </div>
          <!-- Etiquetas -->
          <div class="bg-white dark:bg-gray-900 p-5 rounded-3xl border border-gray-100 dark:border-white/5 flex flex-col justify-center items-center text-center">
            <span class="text-lg font-black text-gray-500">X</span>
            <span class="text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1">Prep/Etiquetas</span>
            <span class="text-sm font-black text-gray-900 dark:text-white">{{ formatPrice(displayBreakdown?.etiquetas) }}</span>
          </div>
          <!-- Extras -->
          <div class="bg-white dark:bg-gray-900 p-5 rounded-3xl border border-gray-100 dark:border-white/5 flex flex-col justify-center items-center text-center border-b-2 border-b-indigo-400">
            <span class="text-lg font-black text-primary">+</span>
            <span class="text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1">Extras/Util</span>
            <span class="text-sm font-black text-gray-900 dark:text-white">{{ formatPrice(displayBreakdown?.extras) }}</span>
          </div>
        </div>
      </div>

      <!-- Charts Section: High Contrast -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-10 px-6 pb-8">
        <div class="bg-white dark:bg-gray-900 p-10 rounded-[3rem] border border-gray-100 dark:border-white/5 shadow-sm dark:shadow-none">
          <div class="flex items-center justify-between mb-10">
            <div>
              <h4 class="text-xs font-black text-gray-900 dark:text-white uppercase tracking-[0.2em] mb-1">Flujo de Ingresos</h4>
              <p class="text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase">Histórico Mensual</p>
            </div>
          </div>
          <div class="h-[300px]">
            <Line :data="chartData" :options="lineChartOptions" />
          </div>
        </div>

        <div class="bg-white dark:bg-gray-900 p-10 rounded-[3rem] border border-gray-100 dark:border-white/5 shadow-sm dark:shadow-none">
           <div class="flex items-center justify-between mb-10">
            <div>
              <h4 class="text-xs font-black text-gray-900 dark:text-white uppercase tracking-[0.2em] mb-1">Carga por Tecnología</h4>
              <p class="text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase">Distribución de Pedidos</p>
            </div>
          </div>
          <div class="h-[300px] flex items-center justify-center relative">
            <Doughnut :data="techData" :options="doughnutChartOptions" />
            <div class="absolute inset-0 flex items-center justify-center pointer-events-none -ml-28">
              <!-- El text centrado dependerá del offset de la leyenda, ajustamos un poco hacia la izq con -ml-28 -->
            </div>
          </div>
        </div>
      </div>

      <!-- LTV Top Customers -->
      <div class="px-6 pb-20">
        <div class="bg-white dark:bg-gray-900 p-12 rounded-[4rem] border border-gray-100 dark:border-white/5 shadow-sm dark:shadow-none">
          <div class="flex items-center gap-4 mb-10 border-l-4 border-primary pl-4">
            <h4 class="text-xl font-black text-gray-900 dark:text-white uppercase tracking-tighter">Top Clientes (LTV)</h4>
            <span class="text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest bg-gray-100 dark:bg-white/5 px-3 py-1 rounded-full">Lifetime Value</span>
          </div>
          
          <div class="overflow-x-auto no-scrollbar">
            <table class="w-full text-left border-collapse">
              <thead>
                <tr class="border-b-2 border-gray-100 dark:border-white/5">
                  <th class="text-left py-4 px-6 text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest">#</th>
                  <th class="text-left py-4 px-6 text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest">Cliente</th>
                  <th class="text-left py-4 px-6 text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest">Último Trabajo (ID - Archivo)</th>
                  <th class="text-left py-4 px-6 text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest">Pedidos</th>
                  <th class="text-right py-4 px-6 text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest">Total Invertido</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(client, index) in analytics.top_customers || []" :key="index" :class="index === 0 ? 'bg-primary/5 dark:bg-primary/10' : 'hover:bg-gray-50/50 dark:hover:bg-white/5'" class="border-b border-gray-50 dark:border-white/5 transition-colors group">
                  <td class="py-5 pl-6">
                    <div :class="[
                        'w-8 h-8 rounded-full flex items-center justify-center text-[10px] font-black transition-all',
                        index === 0 ? 'bg-primary text-white scale-125' : 'bg-gray-100 dark:bg-white/5 text-gray-500'
                    ]">
                      {{ index + 1 }}
                    </div>
                  </td>
                  <td class="py-5">
                    <div class="flex flex-col">
                        <span class="font-black text-sm text-gray-800 dark:text-white flex items-center gap-2">
                            {{ client.customer_name }}
                            <span v-if="index === 0" class="text-[8px] bg-primary text-white px-2 py-0.5 rounded-full uppercase tracking-tighter">Top VIP</span>
                            <span v-else-if="index < 3" class="text-[8px] bg-gray-900 dark:bg-white text-white dark:text-gray-950 px-2 py-0.5 rounded-full uppercase tracking-tighter">Platinum</span>
                        </span>
                    </div>
                  </td>
                  <td class="py-6 px-6">
                    <div class="flex flex-col">
                      <span class="text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-1">{{ new Date(client.last_order_date).toLocaleDateString(undefined, {month: 'short', day: 'numeric'}) }}</span>
                      <span class="text-[10px] font-bold text-gray-700 dark:text-gray-300 truncate max-w-[200px]">#{{ client.last_order_id }} - {{ client.last_order_file }}</span>
                    </div>
                  </td>
                  <td class="py-5 text-center">
                    <div class="inline-flex items-center gap-1.5 px-3 py-1 bg-gray-100 dark:bg-white/5 rounded-full">
                        <span class="text-[10px] font-black text-gray-900 dark:text-white">{{ client.orders_count }}</span>
                        <span class="text-[8px] font-bold text-gray-400 dark:text-gray-500 uppercase">Proyectos</span>
                    </div>
                  </td>
                  <td class="py-5 text-right font-black text-xl text-[#1e3a34] dark:text-emerald-400 tracking-tighter pr-8">{{ formatPrice(client.total_spent) }}</td>
                </tr>
                <tr v-if="!analytics.top_customers || analytics.top_customers.length === 0">
                    <td colspan="4" class="text-center py-10 text-gray-400 font-black text-xs uppercase tracking-widest">Sin datos de clientes</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
