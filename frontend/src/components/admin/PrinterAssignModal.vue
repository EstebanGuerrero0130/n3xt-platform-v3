<script setup lang="ts">
/**
 * Elegir la máquina al pasar un pedido a impresión. Se había perdido del
 * template: «Asignar a impresora» abría un modal que no existía y el pedido
 * nunca llegaba a «En impresión».
 */
import { ref, computed, watch, type PropType } from 'vue'
import AdminModal from './AdminModal.vue'

const props = defineProps({
  show: { type: Boolean, default: false },
  order: { type: Object as PropType<any>, default: null },
  printers: { type: Array as PropType<any[]>, default: () => [] },
  busy: { type: Boolean, default: false },
})
const emit = defineEmits<{ (e: 'close'): void; (e: 'confirm', printerId: number | null): void }>()

const STATUS: Record<string, { label: string; cls: string }> = {
  idle: { label: 'Libre', cls: 'bg-success/10 text-success' },
  printing: { label: 'Ocupada', cls: 'bg-warning/10 text-warning' },
  maintenance: { label: 'Mantenimiento', cls: 'bg-surface-2 text-muted' },
  offline: { label: 'Apagada', cls: 'bg-surface-2 text-muted' },
}

// Primero las de la misma tecnología y libres
const options = computed(() => {
  const tech = props.order?.technology
  return [...props.printers]
    .filter(p => !tech || tech === 'CATALOGO' || !p.technology || p.technology === tech)
    .sort((a, b) => Number(b.status === 'idle') - Number(a.status === 'idle'))
})
const hidden = computed(() => props.printers.length - options.value.length)

const selected = ref<number | null>(null)
watch(() => props.show, (open) => {
  if (!open) return
  const current = props.order?.printer_id
  selected.value = current ?? options.value.find(p => p.status === 'idle')?.id ?? null
})
</script>

<template>
  <AdminModal
    :show="show"
    :title="`Imprimir el pedido #${order?.id ?? ''}`"
    :description="order ? `${order.customer_name || 'Cliente'} · ${order.material_name || order.technology}` : ''"
    :busy="busy"
    @close="emit('close')"
  >
    <fieldset>
      <legend class="admin-label mb-3">Máquina</legend>
      <p v-if="!options.length" class="text-muted">No hay impresoras {{ order?.technology }} registradas. Puedes pasar el pedido a impresión sin máquina.</p>
      <div class="space-y-2">
        <label
          v-for="p in options" :key="p.id"
          :class="['flex items-center gap-3 p-3 rounded-[var(--radius-control)] border cursor-pointer transition-colors', selected === p.id ? 'border-primary bg-primary/5' : 'border-line hover:bg-surface']"
        >
          <input v-model="selected" type="radio" name="assign-printer" :value="p.id" class="w-5 h-5 accent-[var(--brand-text)]">
          <span class="min-w-0 flex-1">
            <span class="block font-semibold">{{ p.name }}</span>
            <span class="block text-sm text-muted">{{ p.model || 'Sin modelo' }} · {{ p.technology }} · {{ Math.round(p.total_hours_run || 0) }} h de uso</span>
          </span>
          <span :class="['badge', (STATUS[p.status] || STATUS.offline).cls]">{{ (STATUS[p.status] || { label: p.status }).label }}</span>
        </label>
        <label :class="['flex items-center gap-3 p-3 rounded-[var(--radius-control)] border cursor-pointer transition-colors', selected === null ? 'border-primary bg-primary/5' : 'border-line hover:bg-surface']">
          <input v-model="selected" type="radio" name="assign-printer" :value="null" class="w-5 h-5 accent-[var(--brand-text)]">
          <span class="flex-1">Sin máquina asignada</span>
        </label>
      </div>
      <p v-if="hidden > 0" class="mt-3 text-sm text-muted">Se ocultan {{ hidden }} máquina(s) de otra tecnología.</p>
    </fieldset>
    <template #footer>
      <button type="button" class="btn-outlined" :disabled="busy" @click="emit('close')">Cancelar</button>
      <button type="button" class="btn-primary" :disabled="busy" @click="emit('confirm', selected)">{{ busy ? 'Guardando…' : 'Pasar a impresión' }}</button>
    </template>
  </AdminModal>
</template>
