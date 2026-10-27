<script setup lang="ts">
import { reactive, watch } from 'vue'
import AdminModal from './AdminModal.vue'
import { toBuildMm } from '../../utils/buildVolume'

const props = defineProps<{
  modelValue: boolean
  newPrinter: any
  submitting: boolean
}>()

// El formulario se envía completo al guardar (antes se editaba una copia que el
// padre nunca recibía y la impresora se creaba con el formulario vacío)
const emit = defineEmits<{
  (e: 'update:modelValue', val: boolean): void
  (e: 'add-printer', payload: { name: string; model: string; technology: string; build_x_mm: number | null; build_y_mm: number | null; build_z_mm: number | null }): void
}>()

const empty = () => ({ name: '', model: '', technology: 'FDM', build_x_mm: '' as number | string | null, build_y_mm: '' as number | string | null, build_z_mm: '' as number | string | null })
const form = reactive(empty())
watch(() => props.modelValue, (open) => { if (open) Object.assign(form, empty(), props.newPrinter || {}) })

const submit = () => {
  if (!form.name.trim() || !form.model.trim()) return
  emit('add-printer', {
    name: form.name.trim(), model: form.model.trim(), technology: form.technology,
    build_x_mm: toBuildMm(form.build_x_mm), build_y_mm: toBuildMm(form.build_y_mm), build_z_mm: toBuildMm(form.build_z_mm),
  })
}
</script>

<template>
  <AdminModal :show="modelValue" title="Nueva impresora" :busy="submitting" @close="emit('update:modelValue', false)">
    <form id="new-printer-form" class="space-y-4" @submit.prevent="submit">
      <div>
        <label for="np-name" class="admin-label mb-2">Nombre</label>
        <input id="np-name" v-model="form.name" type="text" required placeholder="Ej. Alpha-3" class="field" data-autofocus>
      </div>
      <div>
        <label for="np-model" class="admin-label mb-2">Modelo</label>
        <input id="np-model" v-model="form.model" type="text" required placeholder="Ej. Ender 3 V2, Mars 4" class="field">
      </div>
      <fieldset>
        <legend class="admin-label mb-2">Tecnología</legend>
        <div class="flex gap-2">
          <button v-for="t in ['FDM', 'SLA']" :key="t" type="button" class="chip" :aria-pressed="form.technology === t" @click="form.technology = t">{{ t === 'FDM' ? 'Filamento (FDM)' : 'Resina (SLA)' }}</button>
        </div>
      </fieldset>
      <fieldset>
        <legend class="admin-label mb-2">Volumen de impresión (mm) <span class="font-normal text-muted">· opcional</span></legend>
        <div class="flex items-center gap-2">
          <input v-model.number="form.build_x_mm" type="number" min="1" max="2000" step="1" placeholder="X" aria-label="Ancho X (mm)" class="field tabular-nums">
          <span class="text-muted" aria-hidden="true">×</span>
          <input v-model.number="form.build_y_mm" type="number" min="1" max="2000" step="1" placeholder="Y" aria-label="Fondo Y (mm)" class="field tabular-nums">
          <span class="text-muted" aria-hidden="true">×</span>
          <input v-model.number="form.build_z_mm" type="number" min="1" max="2000" step="1" placeholder="Z" aria-label="Alto Z (mm)" class="field tabular-nums">
        </div>
        <p class="mt-1.5 text-xs text-muted">Sirve para avisar al cliente si su pieza no cabe.</p>
      </fieldset>
    </form>
    <template #footer>
      <button type="button" class="btn-outlined" :disabled="submitting" @click="emit('update:modelValue', false)">Cancelar</button>
      <button type="submit" form="new-printer-form" class="btn-primary" :disabled="submitting || !form.name.trim() || !form.model.trim()">{{ submitting ? 'Guardando…' : 'Agregar impresora' }}</button>
    </template>
  </AdminModal>
</template>
