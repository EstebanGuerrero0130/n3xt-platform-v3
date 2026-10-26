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
    backgroundColor: 'transparent',
    color: ['#3fcf72', '#f97066', '#8dd6ff'],
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
      data: ['Ventas', 'Gastos', 'Ganancia'],
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
        formatter: (v: any) => '$' + v.toLocaleString()
      },
      axisLine: { show: false },
      axisTick: { show: false }
    },
    series: [
      {
        name: 'Ventas',
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
        name: 'Gastos',
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
        name: 'Ganancia',
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
    backgroundColor: 'transparent',
    color: ['#3fcf72', '#8dd6ff', '#58a6ff', '#bc8cff', '#f97066', '#fbbf24'],
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
        data: counts.map((c: any) => ({ name: c.technology === 'CATALOGO' ? 'Catálogo' : c.technology, value: c.count })),
        animationDuration: 1200,
        animationEasing: 'cubicOut'
      }
    ]
  }
})
const formatPrice = (val: any) => {
  const num = Math.round(Number(val || 0))
  if (num < 0) return `-$${Math.abs(num).toLocaleString('es-CO')}`
  return `$${num.toLocaleString('es-CO')}`
}
const marginWidth = computed(() => Math.max(0, Math.min(100, Number(displaySummary.value?.profit_margin_pct) || 0)))

const COST_ROWS = [
  { key: 'mat', label: 'Material' },
  { key: 'luz', label: 'Electricidad' },
  { key: 'labor', label: 'Mano de obra' },
  { key: 'mant', label: 'Mantenimiento' },
  { key: 'depr', label: 'Depreciación' },
  { key: 'etiquetas', label: 'Empaque y etiquetas' },
  { key: 'extras', label: 'Insumos extra' },
  { key: 'setup', label: 'Arranque' },
]
const costValue = (key: string) => {
  const bd: any = displayBreakdown.value || {}
  if (key === 'mat') return bd.mat ?? ((bd.mat_fdm || 0) + (bd.mat_sla || 0))
  return bd[key] || 0
}
const costRows = computed(() => COST_ROWS.filter(r => r.key !== 'setup' || costValue('setup') > 0))
const costTotal = computed(() => costRows.value.reduce((s, r) => s + Number(costValue(r.key) || 0), 0))

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
  <div class="space-y-8">
    <header class="flex flex-col md:flex-row md:items-end md:justify-between gap-6">
      <div>
        <h1 class="admin-title">Contabilidad <span class="text-brand">del taller</span></h1>
        <p class="admin-lead">Ventas sin pedidos cancelados. Los costos se cuentan solo en pedidos pagados.</p>
      </div>
      <button type="button" class="btn-outlined btn-lg" @click="$emit('export')">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
        Exportar CSV
      </button>
    </header>

    <!-- Carga -->
    <div v-if="loading" class="grid grid-cols-2 lg:grid-cols-5 gap-4" aria-hidden="true">
      <div v-for="i in 5" :key="i" class="h-28 admin-card animate-pulse"></div>
    </div>
    <p v-if="loading" class="sr-only" role="status">Cargando cifras…</p>

    <!-- Sin datos -->
    <div v-else-if="!analytics" class="admin-card py-14 text-center">
      <p class="font-semibold">No se pudieron cargar las cifras.</p>
      <p class="mt-1 text-muted">Pulsa «Actualizar» arriba para intentarlo de nuevo.</p>
    </div>

    <template v-else>
      <!-- Resumen (de un pedido si está seleccionado) -->
      <div>
        <div v-if="selectedOrderId" class="mb-3 flex items-center gap-3">
          <span class="badge bg-primary/10 text-brand">Viendo el pedido #{{ selectedOrderId }}</span>
          <button type="button" class="text-sm font-semibold text-brand hover:underline" @click="selectedOrderId = null">Ver todo</button>
        </div>
        <dl class="grid grid-cols-2 lg:grid-cols-5 gap-3 md:gap-4">
          <div class="admin-card"><dt class="admin-label">Cobrado</dt><dd class="mt-2 admin-kpi text-success">{{ formatPrice(displaySummary?.collected_revenue) }}</dd></div>
          <div class="admin-card"><dt class="admin-label">Por cobrar</dt><dd class="mt-2 admin-kpi text-warning">{{ formatPrice(displaySummary?.pending_revenue) }}</dd></div>
          <div class="admin-card"><dt class="admin-label">Costos</dt><dd class="mt-2 admin-kpi">{{ formatPrice(displaySummary?.total_expenses) }}</dd></div>
          <div class="admin-card"><dt class="admin-label">Ganancia neta</dt><dd class="mt-2 admin-kpi" :class="Number(displaySummary?.net_profit) < 0 ? 'text-danger' : ''">{{ formatPrice(displaySummary?.net_profit) }}</dd></div>
          <div class="admin-card col-span-2 lg:col-span-1">
            <dt class="admin-label">Margen sobre lo cobrado</dt>
            <dd class="mt-2 admin-kpi">{{ displaySummary?.profit_margin_pct || 0 }} %</dd>
            <dd class="mt-3 h-2 rounded-full bg-surface-2 overflow-hidden" aria-hidden="true"><div class="h-full rounded-full bg-primary" :style="{ width: marginWidth + '%' }"></div></dd>
          </div>
        </dl>
      </div>

      <!-- Producción -->
      <dl class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-7 gap-3">
        <div class="admin-card !p-4"><dt class="admin-label">Pedidos</dt><dd class="mt-1 text-xl font-bold tabular-nums">{{ analytics.summary?.orders_count || 0 }}</dd></div>
        <div class="admin-card !p-4"><dt class="admin-label">Enviados</dt><dd class="mt-1 text-xl font-bold tabular-nums">{{ analytics.summary?.completed_count || 0 }}</dd></div>
        <div class="admin-card !p-4"><dt class="admin-label">Horas de máquina</dt><dd class="mt-1 text-xl font-bold tabular-nums">{{ analytics.summary?.total_hours || 0 }} h</dd></div>
        <div class="admin-card !p-4"><dt class="admin-label">Filamento</dt><dd class="mt-1 text-xl font-bold tabular-nums">{{ analytics.summary?.total_weight_kg || 0 }} kg</dd></div>
        <div class="admin-card !p-4"><dt class="admin-label">Resina</dt><dd class="mt-1 text-xl font-bold tabular-nums">{{ analytics.summary?.total_resin_ml || 0 }} ml</dd></div>
        <div class="admin-card !p-4"><dt class="admin-label">Insumos extra</dt><dd class="mt-1 text-xl font-bold tabular-nums">{{ formatPrice(analytics.summary?.total_extras_cost) }}</dd></div>
        <div class="admin-card !p-4"><dt class="admin-label">Material perdido</dt><dd class="mt-1 text-xl font-bold tabular-nums" :class="analytics.summary?.waste_weight_g > 0 ? 'text-danger' : ''">{{ analytics.summary?.waste_weight_g || 0 }} g</dd></div>
      </dl>

      <div class="grid gap-6 xl:grid-cols-12 items-start">
        <!-- Pedidos -->
        <section class="admin-card xl:col-span-8" aria-labelledby="audit-title">
          <h2 id="audit-title" class="admin-h2">Pedidos</h2>
          <p class="mt-1 text-sm text-muted">Toca un pedido para ver su resumen y sus costos.</p>
          <div class="mt-4 overflow-auto max-h-[28rem] -mx-1 px-1">
            <table class="admin-table min-w-[36rem]">
              <thead class="sticky top-0 bg-surface"><tr><th>Pedido</th><th>Cliente</th><th>Total</th><th>Ganancia</th><th>Pago</th></tr></thead>
              <tbody>
                <tr
                  v-for="order in analytics.detailed_orders"
                  :key="order.id"
                  :class="['cursor-pointer hover:bg-surface-2', selectedOrderId === order.id ? 'bg-primary/5' : '']"
                  :aria-selected="selectedOrderId === order.id"
                  tabindex="0"
                  @click="selectOrder(order.id)"
                  @keydown.enter="selectOrder(order.id)"
                >
                  <td class="tabular-nums font-semibold">#{{ order.id }}</td>
                  <td>
                    <span class="block font-medium truncate max-w-[14rem]">{{ order.customer || 'Sin nombre' }}</span>
                    <span v-if="order.file" class="block text-sm text-muted truncate max-w-[14rem]">{{ order.file }}</span>
                  </td>
                  <td class="tabular-nums">{{ formatPrice(order.total_price) }}</td>
                  <td class="tabular-nums" :class="order.profit < 0 ? 'text-danger' : ''">{{ formatPrice(order.profit) }}</td>
                  <td><span :class="['badge', order.is_paid ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning']">{{ order.is_paid ? 'Pagado' : 'Pendiente' }}</span></td>
                </tr>
                <tr v-if="!analytics.detailed_orders?.length"><td colspan="5" class="py-10 text-center text-muted">Aún no hay pedidos en este periodo.</td></tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- Costos -->
        <section class="admin-card xl:col-span-4" aria-labelledby="costs-title">
          <h2 id="costs-title" class="admin-h2">{{ selectedOrderId ? `Costos del pedido #${selectedOrderId}` : 'Costos de producción' }}</h2>
          <dl class="mt-4">
            <div v-for="row in costRows" :key="row.key" class="flex items-center justify-between gap-4 py-2.5 border-t border-line first:border-t-0">
              <dt class="text-muted">{{ row.label }}</dt>
              <dd class="font-semibold tabular-nums">{{ formatPrice(costValue(row.key)) }}</dd>
            </div>
            <div class="flex items-center justify-between gap-4 pt-3 mt-1 border-t-2 border-line">
              <dt class="font-semibold">Total</dt>
              <dd class="font-extrabold tabular-nums">{{ formatPrice(costTotal) }}</dd>
            </div>
          </dl>
        </section>
      </div>

      <!-- Gráficas -->
      <div class="grid gap-6 xl:grid-cols-12">
        <section class="admin-card xl:col-span-8" aria-labelledby="flow-title">
          <h2 id="flow-title" class="admin-h2">Ventas, gastos y ganancia por mes</h2>
          <div class="mt-4 h-72 md:h-80"><VChart :option="lineChartOption" :theme="chartTheme" autoresize style="width:100%;height:100%" /></div>
        </section>
        <section class="admin-card xl:col-span-4" aria-labelledby="tech-title">
          <h2 id="tech-title" class="admin-h2">Pedidos por tecnología</h2>
          <div class="mt-4 h-72 md:h-80"><VChart :option="techChartOption" :theme="chartTheme" autoresize style="width:100%;height:100%" /></div>
        </section>
      </div>

      <!-- Mejores clientes -->
      <section class="admin-card" aria-labelledby="clients-title">
        <h2 id="clients-title" class="admin-h2">Clientes que más compran</h2>
        <div class="mt-4 overflow-x-auto -mx-1 px-1">
          <table class="admin-table min-w-[40rem]">
            <thead><tr><th class="w-10">#</th><th>Cliente</th><th>Último pedido</th><th>Pedidos</th><th>Total comprado</th></tr></thead>
            <tbody>
              <tr v-for="(client, index) in analytics.top_customers || []" :key="index">
                <td class="tabular-nums text-muted">{{ Number(index) + 1 }}</td>
                <td class="font-semibold">{{ client.customer_name || 'Sin nombre' }}</td>
                <td>
                  <span class="block tabular-nums">{{ client.last_order_date ? new Date(client.last_order_date).toLocaleDateString('es-CO', { day: 'numeric', month: 'short', year: 'numeric' }) : '—' }}</span>
                  <span class="block text-sm text-muted truncate max-w-[16rem]">#{{ client.last_order_id }} · {{ client.last_order_file }}</span>
                </td>
                <td class="tabular-nums">{{ client.orders_count }}</td>
                <td class="tabular-nums font-semibold">{{ formatPrice(client.total_spent) }}</td>
              </tr>
              <tr v-if="!analytics.top_customers?.length"><td colspan="5" class="py-10 text-center text-muted">Aún no hay clientes con pedidos.</td></tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>
  </div>
</template>
