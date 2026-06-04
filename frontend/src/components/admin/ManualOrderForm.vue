<template>
  <Teleport to="body">
    <div v-if="visible" class="fixed inset-0 z-[9996] flex items-center justify-center p-4" @click.self="$emit('close')">
      <div class="absolute inset-0 bg-black/70 backdrop-blur-sm"></div>
      <div class="relative bg-gray-900 border border-gray-700 rounded-3xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto">
        <!-- Header -->
        <div class="sticky top-0 bg-gray-900 z-10 p-6 pb-4 border-b border-gray-800 flex justify-between items-start">
          <div>
            <h2 class="text-2xl font-black text-white tracking-tight">ORDEN MANUAL</h2>
            <p class="text-sm text-gray-500 mt-1 font-semibold">Registro directo de fabricación</p>
          </div>
          <button class="p-2 hover:bg-gray-800 rounded-xl transition-colors" @click="$emit('close')">
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>

        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Left: Client Data -->
          <div class="space-y-4">
            <h3 class="text-sm font-black text-gray-300 uppercase tracking-wider">Datos del Cliente</h3>
            <div>
              <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Cliente Existente</label>
              <select class="w-full bg-gray-800 border border-gray-700 rounded-xl px-4 py-3 text-white font-semibold text-sm focus:ring-2 focus:ring-emerald-500 outline-none transition-all" @change="selectCustomer">
                <option value="">Seleccionar...</option>
                <option v-for="c in contacts" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Nombre</label>
                <input v-model="form.customer_name" class="w-full bg-gray-800 border border-gray-700 rounded-xl px-4 py-3 text-white text-sm font-semibold outline-none">
              </div>
              <div>
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">WhatsApp</label>
                <input v-model="form.customer_phone" class="w-full bg-gray-800 border border-gray-700 rounded-xl px-4 py-3 text-white text-sm font-semibold outline-none">
              </div>
            </div>
            <div>
              <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Documento ID</label>
              <input v-model="form.customer_id_document" class="w-full bg-gray-800 border border-gray-700 rounded-xl px-4 py-3 text-white text-sm font-semibold outline-none">
            </div>
          </div>

          <!-- Right: Job Data -->
          <div class="space-y-4">
            <h3 class="text-sm font-black text-gray-300 uppercase tracking-wider">Datos del Trabajo</h3>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Tecnología</label>
                <select v-model="form.technology" class="w-full bg-gray-800 border border-gray-700 rounded-xl px-4 py-3 text-white font-semibold text-sm outline-none">
                  <option value="FDM">FDM</option>
                  <option value="SLA">SLA</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Material</label>
                <select v-model="form.material_id" class="w-full bg-gray-800 border border-gray-700 rounded-xl px-4 py-3 text-white font-semibold text-sm outline-none">
                  <option value="">Seleccionar...</option>
                  <option v-for="m in filteredMaterials" :key="m.id" :value="m.id">{{ m.name }}</option>
                </select>
              </div>
            </div>
            <div class="grid grid-cols-3 gap-3">
              <div>
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Peso (g)</label>
                <input v-model.number="form.weight_g" type="number" min="0" step="0.1" class="w-full bg-gray-800 border border-gray-700 rounded-xl px-4 py-3 text-white text-sm font-semibold outline-none">
              </div>
              <div>
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Cantidad</label>
                <input v-model.number="form.qty" type="number" min="1" class="w-full bg-gray-800 border border-gray-700 rounded-xl px-4 py-3 text-white text-sm font-semibold outline-none">
              </div>
              <div>
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Horas</label>
                <input v-model.number="form.duration_h" type="number" min="0" step="0.5" class="w-full bg-gray-800 border border-gray-700 rounded-xl px-4 py-3 text-white text-sm font-semibold outline-none">
              </div>
            </div>
            <div>
              <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Precio Final</label>
              <div class="relative">
                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 font-bold">$</span>
                <input v-model.number="form.total_price" type="number" min="0" class="w-full bg-gray-800 border border-gray-700 rounded-xl pl-8 pr-4 py-3 text-white font-bold text-lg outline-none">
              </div>
            </div>
            <div>
              <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Comentarios</label>
              <textarea v-model="form.comments" rows="2" class="w-full bg-gray-800 border border-gray-700 rounded-xl px-4 py-3 text-white text-sm outline-none resize-none"></textarea>
            </div>
          </div>
        </div>

        <div class="p-6 border-t border-gray-800 flex justify-end gap-4">
          <button class="px-6 py-3 rounded-xl bg-gray-800 hover:bg-gray-700 text-gray-300 font-bold transition-all text-sm" @click="$emit('close')">Descartar</button>
          <button :disabled="submitting" class="px-8 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white font-black transition-all text-sm" @click="submit">
            {{ submitting ? 'Creando...' : 'CREAR ORDEN' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, reactive, computed } from 'vue'
import { api } from '../../services/api'
import logger from '../../utils/logger'

const props = defineProps({
  visible: Boolean,
  materials: { type: Array, default: () => [] },
  contacts: { type: Array, default: () => [] },
  settings: { type: Object, default: () => ({ infra: {}, prep: {}, margin: {} }) },
})

const emit = defineEmits(['close', 'created'])

const submitting = ref(false)

const form = reactive({
  customer_id: '', customer_name: '', customer_company: '', customer_id_document: '',
  customer_email: '', customer_phone: '', shipping_address: '', shipping_city: '',
  shipping_zip: '', shipping_reference: '',
  material_id: '', weight_g: 0, duration_h: 0, total_price: 0,
  qty: 1, technology: 'FDM', comments: '',
})

const filteredMaterials = computed(() =>
  props.materials.filter(m => m.category === form.technology || m.category === 'General')
)

const selectCustomer = (e: any) => {
  const c = props.contacts.find(c => String(c.id) === String(e.target.value))
  if (c) {
    form.customer_id = c.id; form.customer_name = c.name
    form.customer_company = c.company || ''; form.customer_id_document = c.customer_id_document || ''
    form.customer_email = c.email || ''; form.customer_phone = c.phone || ''
    form.shipping_address = c.address_full || c.location || ''
    form.shipping_city = c.city_dept_country || ''; form.shipping_zip = c.zip_code || ''
    form.shipping_reference = c.location_reference || ''
    if (c.notes) form.comments = c.notes
  }
}

const submit = async () => {
  if (submitting.value) return
  submitting.value = true
  try {
    await api.post('/orders', {
      ...form, estimated_weight_g: form.weight_g, estimated_duration_h: form.duration_h,
      volume_mm3: (form.weight_g / 1.25) * 1000,
      cost_snapshot: {
        settings: JSON.parse(JSON.stringify(props.settings)),
        material_cost_per_kg: props.materials.find(m => m.id === form.material_id)?.cost_per_kg || 0,
      }
    }, true)
    emit('created')
    reset()
  } catch (err) {
    logger.error('Error:', err)
  } finally {
    submitting.value = false
  }
}

const reset = () => {
  Object.assign(form, {
    customer_id: '', customer_name: '', customer_id_document: '', customer_email: '',
    customer_phone: '', material_id: '', weight_g: 0, duration_h: 0, total_price: 0,
    technology: 'FDM', comments: '',
  })
}
</script>
