<script setup lang="ts">
import { ref, computed, reactive, type PropType } from 'vue'

const stockInputs = reactive<Record<string, number>>({})

const props = defineProps({
  inventory: { type: Array as PropType<any[]>, required: true },
  loading: { type: Boolean, default: false }
})

const emit = defineEmits(['add-item', 'edit-item', 'delete-item', 'update-stock'])

const TABS = [
  { id: 'Materiales', label: 'Materiales' },
  { id: 'Productos', label: 'Productos' },
  { id: 'Postprocesado', label: 'Insumos y acabado' },
]

const stockOf = (m: any) => Number(m.inventory?.stock_available || 0)
const thresholdOf = (m: any) => Number(m.inventory?.low_stock_threshold || 500)
const isLow = (m: any) => stockOf(m) < thresholdOf(m)
// cost_per_kg guarda el costo por kg, por litro o por unidad según la unidad
const costUnit = (m: any) => m.unit === 'g' ? 'kg' : m.unit === 'ml' ? 'L' : (m.unit || 'unidad')
const money = (n: any) => '$' + Math.round(Number(n) || 0).toLocaleString('es-CO')

const addStock = (m: any) => {
  const qty = Number(stockInputs[m.id])
  if (!qty) return
  emit('update-stock', m.id, qty)
  stockInputs[m.id] = undefined as unknown as number
}

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
        // Postprocesado: insumos y servicios (cajas, pintura, lijas…)
        filtered = filtered.filter((m: any) => m.type === 'utility' || m.type === 'service' || m.category === 'UTIL')
    }

    return filtered;
})

</script>

<template>
  <div class="space-y-8">
    <header class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-6">
      <div>
        <h1 class="admin-title">Inventario <span class="text-brand">del taller</span></h1>
        <p class="admin-lead">Materiales, productos e insumos con su costo y existencias. El stock baja solo al confirmar el pago de un pedido.</p>
      </div>
      <button type="button" class="btn-primary btn-lg" @click="$emit('add-item')">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14m-7-7h14"/></svg>
        Nuevo ítem
      </button>
    </header>

    <dl class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4">
      <div class="admin-card"><dt class="admin-label">Ítems registrados</dt><dd class="mt-2 admin-kpi">{{ totalItems }}</dd></div>
      <div class="admin-card"><dt class="admin-label">Por reponer</dt><dd class="mt-2 admin-kpi" :class="lowStockCount ? 'text-danger' : ''">{{ lowStockCount }}</dd></div>
      <div class="admin-card"><dt class="admin-label">Filamentos (FDM)</dt><dd class="mt-2 admin-kpi">{{ totalFilaments }}</dd></div>
      <div class="admin-card"><dt class="admin-label">Resinas (SLA)</dt><dd class="mt-2 admin-kpi">{{ totalResins }}</dd></div>
    </dl>

    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div class="flex flex-wrap gap-2" role="group" aria-label="Tipo de ítem">
        <button v-for="t in TABS" :key="t.id" type="button" class="chip" :aria-pressed="activeMainTab === t.id" @click="activeMainTab = t.id; activeFilter = 'Todos'">{{ t.label }}</button>
        <template v-if="activeMainTab === 'Materiales'">
          <span class="w-px self-stretch bg-line mx-1" aria-hidden="true"></span>
          <button v-for="f in ['Todos', 'Filamentos', 'Resinas']" :key="f" type="button" class="chip" :aria-pressed="activeFilter === f" @click="activeFilter = f">{{ f }}</button>
        </template>
      </div>
      <div class="relative md:w-72">
        <label for="inventory-search" class="sr-only">Buscar en el inventario</label>
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-muted pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <input id="inventory-search" v-model="searchQuery" type="search" placeholder="Buscar por nombre o tipo" class="field pl-10">
      </div>
    </div>

    <!-- Carga -->
    <div v-if="loading" class="admin-card space-y-3" aria-hidden="true">
      <div v-for="i in 5" :key="i" class="h-12 rounded-[var(--radius-control)] bg-surface-2 animate-pulse"></div>
    </div>

    <!-- Vacío -->
    <div v-else-if="!filteredInventory.length" class="admin-card py-14 text-center">
      <p class="font-semibold">{{ searchQuery ? 'Nada coincide con la búsqueda.' : 'Todavía no hay ítems en esta sección.' }}</p>
      <p class="mt-1 text-muted">Registra un material, producto o insumo con «Nuevo ítem».</p>
    </div>

    <!-- Lista -->
    <section v-else class="admin-card" :aria-label="TABS.find(t => t.id === activeMainTab)?.label">
      <div class="overflow-x-auto -mx-1 px-1">
        <table class="admin-table min-w-[48rem]">
          <thead>
            <tr><th>Ítem</th><th>Costo</th><th>Existencias</th><th>Agregar</th><th class="sr-only">Acciones</th></tr>
          </thead>
          <tbody>
            <tr v-for="mat in filteredInventory" :key="mat.id">
              <td>
                <div class="flex items-center gap-3">
                  <span class="w-8 h-8 shrink-0 rounded-full ring-1 ring-inset ring-[color-mix(in_srgb,var(--text-main)_25%,transparent)]" :style="{ backgroundColor: mat.color || 'var(--bg-surface-2)' }" aria-hidden="true"></span>
                  <div class="min-w-0">
                    <p class="font-semibold truncate max-w-[16rem]" :title="mat.name">{{ mat.name }}</p>
                    <p class="text-sm text-muted">{{ mat.category }} · <span class="font-mono">{{ mat.id }}</span></p>
                  </div>
                </div>
              </td>
              <td class="tabular-nums whitespace-nowrap">{{ money(mat.cost_per_kg) }} <span class="text-muted">/ {{ costUnit(mat) }}</span></td>
              <td class="whitespace-nowrap">
                <span class="font-semibold tabular-nums" :class="isLow(mat) ? 'text-danger' : ''">{{ Math.round(stockOf(mat)).toLocaleString('es-CO') }} {{ mat.unit }}</span>
                <span v-if="isLow(mat)" class="ml-2 badge bg-danger/10 text-danger">Reponer</span>
                <span class="block text-sm text-muted">Mínimo {{ thresholdOf(mat).toLocaleString('es-CO') }} {{ mat.unit }}</span>
              </td>
              <td>
                <form class="flex gap-2" @submit.prevent="addStock(mat)">
                  <label :for="`stock-${mat.id}`" class="sr-only">Cantidad a agregar a {{ mat.name }}</label>
                  <input :id="`stock-${mat.id}`" v-model.number="stockInputs[mat.id]" type="number" min="1" :placeholder="`+ ${mat.unit || ''}`" class="field h-10 w-28 tabular-nums">
                  <button type="submit" class="btn-outlined btn-sm" :disabled="!stockInputs[mat.id]">Sumar</button>
                </form>
              </td>
              <td class="text-right whitespace-nowrap">
                <button type="button" class="icon-btn inline-flex text-muted hover:text-ink hover:bg-surface-2" :aria-label="`Editar ${mat.name}`" title="Editar" @click="$emit('edit-item', mat)">
                  <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                </button>
                <button type="button" class="icon-btn inline-flex text-muted hover:text-danger hover:bg-danger/10" :aria-label="`Eliminar ${mat.name}`" title="Eliminar" @click="$emit('delete-item', mat.id)">
                  <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>
