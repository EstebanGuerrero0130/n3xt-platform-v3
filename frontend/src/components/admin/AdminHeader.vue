<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  activeTab: string
  loading: boolean
  isSidebarOpen: boolean
  settings: any
  uploadingLogo?: boolean
  logoInput?: any
}>()

const emit = defineEmits<{
  (e: 'toggle-sidebar'): void
  (e: 'sync'): void
  (e: 'logout'): void
  (e: 'update:isSidebarOpen', val: boolean): void
}>()

const TAB_TITLES: Record<string, string> = {
  kanban: 'Producción', inventory: 'Inventario', accounting: 'Contabilidad', machines: 'Maquinaria',
  history: 'Historial', purchases: 'Compras', contacts: 'Contactos', discounts: 'Descuentos',
  settings: 'Parámetros', web: 'Sitio web', team: 'Equipo',
}
const title = computed(() => TAB_TITLES[props.activeTab] ?? 'Panel')
</script>

<template>
  <header class="h-16 shrink-0 bg-canvas/90 backdrop-blur border-b border-line flex items-center justify-between gap-4 px-4 md:px-8 relative z-40">
    <div class="flex items-center gap-3 min-w-0">
      <button
        type="button"
        class="md:hidden w-11 h-11 -ml-2 inline-flex items-center justify-center rounded-full text-ink hover:bg-surface-2"
        :aria-expanded="isSidebarOpen"
        aria-label="Abrir el menú"
        @click="emit('toggle-sidebar')"
      >
        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
      </button>
      <p class="text-lg font-bold text-ink truncate">{{ title }}</p>
    </div>

    <div class="flex items-center gap-2">
      <button
        type="button"
        :disabled="loading"
        class="btn-outlined btn-sm"
        title="Volver a cargar pedidos, inventario y máquinas"
        @click="emit('sync')"
      >
        <svg :class="['w-4 h-4', loading ? 'animate-spin' : '']" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
        <span class="hidden sm:inline">{{ loading ? 'Actualizando…' : 'Actualizar' }}</span>
        <span class="sm:hidden sr-only">Actualizar</span>
      </button>
    </div>
  </header>
</template>
