<script setup lang="ts">
import { reactive, watch, computed } from 'vue'
import AdminModal from './AdminModal.vue'

const props = defineProps<{
  modelValue: boolean
  newMaterial: any
  submitting: boolean
}>()

// El formulario se envía completo al guardar. Antes se editaba una copia que el
// padre nunca recibía: se enviaba sin código (el servidor lo exige) y no se creaba nada
const emit = defineEmits<{
  (e: 'update:modelValue', val: boolean): void
  (e: 'add-material', payload: any): void
}>()

const TYPES = [
  { type: 'material', label: 'Material', hint: 'Filamento o resina' },
  { type: 'utility', label: 'Insumo', hint: 'Cajas, imanes, pintura' },
  { type: 'service', label: 'Servicio', hint: 'Mano de obra, lavado' },
  { type: 'product', label: 'Producto', hint: 'Pieza terminada para vender' },
]
const CATEGORY_FOR: Record<string, string> = { material: 'FDM', utility: 'UTIL', service: 'UTIL', product: 'PROD' }
const UNIT_FOR: Record<string, string> = { FDM: 'g', SLA: 'ml', UTIL: 'unid', PROD: 'unid' }

const empty = () => ({
  id: '', name: '', category: 'FDM', type: 'material', unit: 'g', cost_per_kg: 0, color: '#000000',
  initial_stock: 0, low_stock_threshold: 200, package_units: 1, package_qty: null as number | null, package_price: null as number | null,
})
const form = reactive(empty())
const idTouched = { value: false }
watch(() => props.modelValue, (open) => {
  if (!open) return
  Object.assign(form, empty(), { ...(props.newMaterial || {}), id: '' })
  idTouched.value = false
})

const setType = (t: string) => {
  form.type = t
  form.category = CATEGORY_FOR[t]
  form.unit = UNIT_FOR[form.category]
}
const setCategory = (c: string) => { form.category = c; form.unit = UNIT_FOR[c] }

// Código sugerido a partir de la categoría y el nombre (editable)
const suggestedId = computed(() => {
  const name = form.name.normalize('NFD').replace(/[̀-ͯ]/g, '').toUpperCase().replace(/[^A-Z0-9]+/g, '_').replace(/^_|_$/g, '').slice(0, 24)
  return name ? `${form.category}_${name}` : ''
})
watch(suggestedId, (v) => { if (!idTouched.value) form.id = v })

// Asistente: precio de un paquete → costo por kg / litro / unidad
const totalQty = computed(() => (Number(form.package_qty) || 0) * (Number(form.package_units) || 1))
const perUnit = computed(() => totalQty.value > 0 ? (Number(form.package_price) || 0) / totalQty.value : 0)
const bulkFactor = computed(() => (form.unit === 'g' || form.unit === 'ml') ? 1000 : 1)
watch([perUnit, bulkFactor], () => {
  if (perUnit.value > 0) {
    form.cost_per_kg = Math.round(perUnit.value * bulkFactor.value * 100) / 100
    if (!form.initial_stock) form.initial_stock = totalQty.value
  }
})
const costLabel = computed(() => form.unit === 'g' ? 'kg' : form.unit === 'ml' ? 'litro' : 'unidad')

const valid = computed(() => !!form.name.trim() && /^[A-Z0-9_-]{2,50}$/.test(form.id) && Number(form.cost_per_kg) >= 0)
const submit = () => {
  if (!valid.value) return
  emit('add-material', {
    id: form.id, name: form.name.trim(), category: form.category, type: form.type, unit: form.unit,
    cost_per_kg: Number(form.cost_per_kg) || 0, color: form.color,
    initial_stock: Number(form.initial_stock) || 0, low_stock_threshold: Number(form.low_stock_threshold) || 0,
  })
}
</script>

<template>
  <AdminModal :show="modelValue" title="Nuevo ítem de inventario" size="lg" :busy="submitting" @close="emit('update:modelValue', false)">
    <form id="new-material-form" class="space-y-6" novalidate @submit.prevent="submit">
      <fieldset>
        <legend class="admin-label mb-2">Qué es</legend>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
          <button
            v-for="t in TYPES" :key="t.type" type="button"
            :class="['text-left p-3 rounded-[var(--radius-control)] border transition-colors', form.type === t.type ? 'border-primary bg-primary/5' : 'border-line hover:bg-surface']"
            :aria-pressed="form.type === t.type"
            @click="setType(t.type)"
          >
            <span class="block font-semibold">{{ t.label }}</span>
            <span class="block text-sm text-muted">{{ t.hint }}</span>
          </button>
        </div>
      </fieldset>

      <div class="grid gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2">
          <label for="nm-name" class="admin-label mb-2">Nombre</label>
          <input id="nm-name" v-model="form.name" type="text" required placeholder="Ej. PLA Pro Negro Mate" class="field" data-autofocus>
        </div>
        <div v-if="form.type === 'material'">
          <label for="nm-tech" class="admin-label mb-2">Tecnología</label>
          <select id="nm-tech" :value="form.category" class="field" @change="setCategory(($event.target as HTMLSelectElement).value)">
            <option value="FDM">Filamento (FDM)</option>
            <option value="SLA">Resina (SLA)</option>
          </select>
        </div>
        <div>
          <label for="nm-unit" class="admin-label mb-2">Unidad</label>
          <select id="nm-unit" v-model="form.unit" class="field">
            <option value="g">Gramos (g)</option>
            <option value="ml">Mililitros (ml)</option>
            <option value="unid">Unidades</option>
          </select>
        </div>
        <div>
          <label for="nm-id" class="admin-label mb-2">Código</label>
          <input id="nm-id" v-model="form.id" type="text" class="field font-mono uppercase" maxlength="50" @input="idTouched.value = true; form.id = form.id.toUpperCase()">
          <p class="mt-1.5 text-sm text-muted">Letras, números, guion o guion bajo. Se sugiere del nombre.</p>
        </div>
        <div class="flex items-end gap-3">
          <div>
            <label for="nm-color" class="admin-label mb-2">Color</label>
            <input id="nm-color" v-model="form.color" type="color" class="h-11 w-16 rounded-[var(--radius-control)] border border-line bg-canvas p-1">
          </div>
        </div>
      </div>

      <!-- Asistente de costo -->
      <section class="admin-card bg-surface" aria-labelledby="nm-assist">
        <h3 id="nm-assist" class="font-semibold">Calcular desde el precio del paquete <span class="font-normal text-muted">(opcional)</span></h3>
        <div class="mt-3 grid grid-cols-3 gap-3">
          <div><label for="nm-pu" class="admin-label mb-1">Paquetes</label><input id="nm-pu" v-model.number="form.package_units" type="number" min="1" class="field h-10 tabular-nums"></div>
          <div><label for="nm-pq" class="admin-label mb-1">{{ form.unit === 'unid' ? 'Unidades' : form.unit }} por paquete</label><input id="nm-pq" v-model.number="form.package_qty" type="number" min="0" placeholder="1000" class="field h-10 tabular-nums"></div>
          <div><label for="nm-pp" class="admin-label mb-1">Precio total ($)</label><input id="nm-pp" v-model.number="form.package_price" type="number" min="0" placeholder="85000" class="field h-10 tabular-nums"></div>
        </div>
        <p v-if="perUnit > 0" class="mt-3 text-sm">Sale a <strong class="tabular-nums">${{ (perUnit * bulkFactor).toLocaleString('es-CO', { maximumFractionDigits: 2 }) }}</strong> por {{ costLabel }} · entran {{ totalQty.toLocaleString('es-CO') }} {{ form.unit }}.</p>
      </section>

      <div class="grid gap-4 sm:grid-cols-3">
        <div><label for="nm-cost" class="admin-label mb-2">Costo por {{ costLabel }} ($)</label><input id="nm-cost" v-model.number="form.cost_per_kg" type="number" min="0" step="any" class="field tabular-nums"></div>
        <div><label for="nm-stock" class="admin-label mb-2">Existencias iniciales ({{ form.unit }})</label><input id="nm-stock" v-model.number="form.initial_stock" type="number" min="0" class="field tabular-nums"></div>
        <div><label for="nm-min" class="admin-label mb-2">Avisar con menos de ({{ form.unit }})</label><input id="nm-min" v-model.number="form.low_stock_threshold" type="number" min="0" class="field tabular-nums"></div>
      </div>
    </form>
    <template #footer>
      <button type="button" class="btn-outlined" :disabled="submitting" @click="emit('update:modelValue', false)">Cancelar</button>
      <button type="submit" form="new-material-form" class="btn-primary" :disabled="submitting || !valid">{{ submitting ? 'Guardando…' : 'Crear ítem' }}</button>
    </template>
  </AdminModal>
</template>
