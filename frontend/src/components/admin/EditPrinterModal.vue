<script setup lang="ts">
import { reactive, watch } from 'vue'
import AdminModal from './AdminModal.vue'
import { toBuildMm, partialBuildVolume } from '../../utils/buildVolume'

const props = defineProps<{
  modelValue: boolean
  editingPrinter: any
  submitting: boolean
}>()

// Se envía el formulario completo al guardar: antes los cambios quedaban en una
// copia local y se guardaban los valores originales
const emit = defineEmits<{
  (e: 'update:modelValue', val: boolean): void
  (e: 'update-printer', payload: any): void
  (e: 'maintenance-complete', id: number | string): void
  (e: 'reset-printer', id: number | string): void
  (e: 'delete-printer', id: number | string): void
}>()

const form = reactive<any>({})
watch(() => props.modelValue, (open) => {
  if (!open) return
  Object.keys(form).forEach(k => delete form[k])
  Object.assign(form, props.editingPrinter || {})
})

const submit = () => emit('update-printer', {
  id: form.id,
  name: form.name,
  model: form.model,
  total_hours_run: Number(form.total_hours_run) || 0,
  maintenance_interval_h: Math.max(1, Math.round(Number(form.maintenance_interval_h) || 200)),
  next_maintenance: form.next_maintenance || null,
  maintenance_notes: form.maintenance_notes || null,
  build_x_mm: toBuildMm(form.build_x_mm),
  build_y_mm: toBuildMm(form.build_y_mm),
  build_z_mm: toBuildMm(form.build_z_mm),
})
</script>

<template>
  <AdminModal :show="modelValue" :title="`Editar ${editingPrinter?.name || 'impresora'}`" :description="`${editingPrinter?.technology || ''} · estado: ${editingPrinter?.status || ''}`" size="lg" :busy="submitting" @close="emit('update:modelValue', false)">
    <form id="edit-printer-form" class="grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
      <div><label for="ep-name" class="admin-label mb-2">Nombre</label><input id="ep-name" v-model="form.name" type="text" required class="field" data-autofocus></div>
      <div><label for="ep-model" class="admin-label mb-2">Modelo</label><input id="ep-model" v-model="form.model" type="text" class="field"></div>
      <div><label for="ep-hours" class="admin-label mb-2">Horas desde el último mantenimiento</label><input id="ep-hours" v-model.number="form.total_hours_run" type="number" min="0" step="0.1" class="field tabular-nums"></div>
      <div><label for="ep-interval" class="admin-label mb-2">Mantenimiento cada (horas)</label><input id="ep-interval" v-model.number="form.maintenance_interval_h" type="number" min="1" class="field tabular-nums"></div>
      <div><label for="ep-next" class="admin-label mb-2">Próxima revisión</label><input id="ep-next" v-model="form.next_maintenance" type="date" class="field"></div>
      <fieldset class="sm:col-span-2">
        <legend class="admin-label mb-2">Volumen de impresión (mm) <span class="font-normal text-muted">· opcional</span></legend>
        <div class="flex items-center gap-2">
          <input v-model.number="form.build_x_mm" type="number" min="1" max="2000" step="1" placeholder="X" aria-label="Ancho X (mm)" class="field tabular-nums">
          <span class="text-muted" aria-hidden="true">×</span>
          <input v-model.number="form.build_y_mm" type="number" min="1" max="2000" step="1" placeholder="Y" aria-label="Fondo Y (mm)" class="field tabular-nums">
          <span class="text-muted" aria-hidden="true">×</span>
          <input v-model.number="form.build_z_mm" type="number" min="1" max="2000" step="1" placeholder="Z" aria-label="Alto Z (mm)" class="field tabular-nums">
        </div>
        <p class="mt-1.5 text-xs text-muted">Sirve para avisar al cliente si su pieza no cabe.</p>
        <p v-if="partialBuildVolume(form.build_x_mm, form.build_y_mm, form.build_z_mm)" class="mt-1 text-xs text-warning" role="status">Completa las tres medidas: con una o dos, el cotizador no usa esta máquina.</p>
      </fieldset>
      <div class="sm:col-span-2"><label for="ep-notes" class="admin-label mb-2">Notas de mantenimiento</label><textarea id="ep-notes" v-model="form.maintenance_notes" rows="3" placeholder="Cambio de boquilla, limpieza, calibración…" class="field !h-auto py-2.5"></textarea></div>
    </form>
    <div class="mt-6 pt-5 border-t border-line flex flex-wrap gap-2">
      <button type="button" class="btn-outlined btn-sm" @click="emit('reset-printer', form.id)">Marcar como libre</button>
      <button type="button" class="btn-sm ml-auto px-3 rounded-full font-semibold text-danger hover:bg-danger/10" @click="emit('delete-printer', form.id)">Eliminar impresora</button>
    </div>
    <template #footer>
      <button type="button" class="btn-outlined" :disabled="submitting" @click="emit('update:modelValue', false)">Cancelar</button>
      <button type="submit" form="edit-printer-form" class="btn-primary" :disabled="submitting">{{ submitting ? 'Guardando…' : 'Guardar cambios' }}</button>
    </template>
  </AdminModal>
</template>
