<script setup lang="ts">
import { reactive, watch, computed } from 'vue'
import AdminModal from './AdminModal.vue'

const props = defineProps<{
  modelValue: boolean
  editingMaterial: any
  submitting: boolean
}>()

// Se envía el formulario al guardar: antes los cambios quedaban en una copia
// local y se guardaban los valores originales
const emit = defineEmits<{
  (e: 'update:modelValue', val: boolean): void
  (e: 'update-material', payload: any): void
}>()

const form = reactive<any>({})
watch(() => props.modelValue, (open) => {
  if (!open) return
  Object.keys(form).forEach(k => delete form[k])
  Object.assign(form, props.editingMaterial || {})
})

const costUnit = computed(() => form.unit === 'g' ? 'kg' : form.unit === 'ml' ? 'litro' : 'unidad')
const submit = () => emit('update-material', {
  id: form.id,
  name: form.name,
  cost_per_kg: Number(form.cost_per_kg) || 0,
  color: form.color || null,
  low_stock_threshold: Number(form.low_stock_threshold) || 0,
})
</script>

<template>
  <AdminModal :show="modelValue" :title="`Editar ${editingMaterial?.name || 'ítem'}`" :description="`${editingMaterial?.category || ''} · ${editingMaterial?.id || ''}`" :busy="submitting" @close="emit('update:modelValue', false)">
    <form id="edit-material-form" class="space-y-4" @submit.prevent="submit">
      <div><label for="em-name" class="admin-label mb-2">Nombre</label><input id="em-name" v-model="form.name" type="text" required class="field" data-autofocus></div>
      <div class="grid grid-cols-2 gap-4">
        <div><label for="em-cost" class="admin-label mb-2">Costo por {{ costUnit }} ($)</label><input id="em-cost" v-model.number="form.cost_per_kg" type="number" min="0" step="any" class="field tabular-nums"></div>
        <div><label for="em-min" class="admin-label mb-2">Avisar con menos de ({{ form.unit }})</label><input id="em-min" v-model.number="form.low_stock_threshold" type="number" min="0" class="field tabular-nums"></div>
      </div>
      <div class="flex items-center gap-3">
        <label for="em-color" class="admin-label">Color</label>
        <input id="em-color" v-model="form.color" type="color" class="h-10 w-14 rounded-[var(--radius-control)] border border-line bg-canvas p-1">
        <span class="font-mono text-sm text-muted">{{ form.color }}</span>
      </div>
      <p class="text-sm text-muted">Las existencias se suman desde la tabla de Inventario o registrando una compra.</p>
    </form>
    <template #footer>
      <button type="button" class="btn-outlined" :disabled="submitting" @click="emit('update:modelValue', false)">Cancelar</button>
      <button type="submit" form="edit-material-form" class="btn-primary" :disabled="submitting">{{ submitting ? 'Guardando…' : 'Guardar cambios' }}</button>
    </template>
  </AdminModal>
</template>
