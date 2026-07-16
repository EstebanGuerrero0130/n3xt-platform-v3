<template>
 <Teleport to="body">
 <div v-if="visible" class="fixed inset-0 z-[9996] flex items-center justify-center p-4" @click.self="$emit('close')">
 <div class="absolute inset-0 bg-black/70 backdrop-blur-sm"></div>
 <div class="relative bg-[#151a22] border border-gray-700 rounded-[24px] w-full max-w-4xl max-h-[90vh] overflow-y-auto">
 <!-- Header -->
 <div class="sticky top-0 bg-[#151a22] z-10 p-4 md:p-6 pb-3 md:pb-4 border-b border-gray-800 flex justify-between items-start gap-3">
 <div class="min-w-0">
 <h2 class="text-xl md:text-2xl font-black text-white tracking-tight">ORDEN MANUAL</h2>
 <p class="text-xs md:text-sm text-[#a4aea6] mt-1 font-semibold">Registro directo de fabricación</p>
 </div>
 <button class="p-2 hover:bg-[#283041] rounded-[6px] transition-colors shrink-0" @click="$emit('close')">
 <svg class="w-5 h-5 text-[#c3c4c5]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
 </button>
 </div>

 <div class="p-4 md:p-6 grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
 <!-- Left: Client Data -->
 <div class="space-y-4">
 <h3 class="text-sm font-black text-gray-300 uppercase tracking-wider">Datos del Cliente</h3>
 <div>
 <label for="manual-client" class="block text-xs font-bold text-[#c3c4c5] uppercase tracking-wider mb-2">Cliente Existente</label>
 <select id="manual-client" class="w-full bg-[#283041] border border-gray-700 rounded-[6px] px-4 py-3 text-white font-semibold text-sm focus:ring-2 focus:ring-emerald-500 outline-none transition-all" @change="selectCustomer">
 <option value="">Seleccionar...</option>
 <option v-for="c in contacts" :key="c.id" :value="c.id">{{ c.name }}</option>
 </select>
 </div>
 <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
 <div>
 <label for="manual-name" class="block text-xs font-bold text-[#c3c4c5] uppercase tracking-wider mb-2">Nombre</label>
 <input id="manual-name" v-model="form.customer_name" class="w-full bg-[#283041] border border-gray-700 rounded-[6px] px-4 py-3 text-white text-sm font-semibold outline-none">
 </div>
 <div>
 <label for="manual-phone" class="block text-xs font-bold text-[#c3c4c5] uppercase tracking-wider mb-2">WhatsApp</label>
 <input id="manual-phone" v-model="form.customer_phone" class="w-full bg-[#283041] border border-gray-700 rounded-[6px] px-4 py-3 text-white text-sm font-semibold outline-none">
 </div>
 </div>
 <div>
 <label for="manual-document" class="block text-xs font-bold text-[#c3c4c5] uppercase tracking-wider mb-2">Documento ID</label>
 <input id="manual-document" v-model="form.customer_id_document" class="w-full bg-[#283041] border border-gray-700 rounded-[6px] px-4 py-3 text-white text-sm font-semibold outline-none">
 </div>
 </div>

 <!-- Right: Job Data -->
 <div class="space-y-4">
 <h3 class="text-sm font-black text-gray-300 uppercase tracking-wider">Datos del Trabajo</h3>
 <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
 <div>
 <label for="manual-tech" class="block text-xs font-bold text-[#c3c4c5] uppercase tracking-wider mb-2">Tecnología</label>
 <select id="manual-tech" v-model="form.technology" class="w-full bg-[#283041] border border-gray-700 rounded-[6px] px-4 py-3 text-white font-semibold text-sm outline-none">
 <option value="FDM">FDM</option>
 <option value="SLA">SLA</option>
 <option value="SERVICIO">Servicio (Pintura/Post-pro)</option>
 </select>
 </div>
 <div>
 <label for="manual-material" class="block text-xs font-bold text-[#c3c4c5] uppercase tracking-wider mb-2">Material</label>
 <select id="manual-material" v-model="form.material_id" class="w-full bg-[#283041] border border-gray-700 rounded-[6px] px-4 py-3 text-white font-semibold text-sm outline-none">
 <option value="">Seleccionar...</option>
 <option v-for="m in filteredMaterials" :key="m.id" :value="m.id">{{ m.name }}</option>
 </select>
 </div>
 </div>
 <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
 <div>
 <label for="manual-weight" class="block text-xs font-bold text-[#c3c4c5] uppercase tracking-wider mb-2">Peso (g)</label>
 <input id="manual-weight" v-model.number="form.weight_g" type="number" min="0" step="0.1" class="w-full bg-[#283041] border border-gray-700 rounded-[6px] px-3 md:px-4 py-2.5 md:py-3 text-white text-sm font-semibold outline-none">
 </div>
 <div>
 <label for="manual-qty" class="block text-xs font-bold text-[#c3c4c5] uppercase tracking-wider mb-2">Cantidad</label>
 <input id="manual-qty" v-model.number="form.qty" type="number" min="1" class="w-full bg-[#283041] border border-gray-700 rounded-[6px] px-3 md:px-4 py-2.5 md:py-3 text-white text-sm font-semibold outline-none">
 </div>
 <div>
 <label for="manual-hours" class="block text-xs font-bold text-[#c3c4c5] uppercase tracking-wider mb-2">Horas</label>
 <input id="manual-hours" v-model.number="form.duration_h" type="number" min="0" step="0.5" class="w-full bg-[#283041] border border-gray-700 rounded-[6px] px-3 md:px-4 py-2.5 md:py-3 text-white text-sm font-semibold outline-none">
 </div>
 </div>
 <div>
 <label for="manual-price" class="block text-xs font-bold text-[#c3c4c5] uppercase tracking-wider mb-2">Precio Final</label>
 <div class="relative">
 <span class="absolute left-4 top-1/2 -translate-y-1/2 text-[#c3c4c5] font-bold">$</span>
 <input id="manual-price" v-model.number="form.total_price" type="number" min="0" class="w-full bg-[#283041] border border-gray-700 rounded-[6px] pl-8 pr-4 py-3 text-white font-bold text-lg outline-none">
 </div>
 </div>
 <div>
 <label for="manual-comments" class="block text-xs font-bold text-[#c3c4c5] uppercase tracking-wider mb-2">Comentarios</label>
 <textarea id="manual-comments" v-model="form.comments" rows="2" class="w-full bg-[#283041] border border-gray-700 rounded-[6px] px-4 py-3 text-white text-sm outline-none resize-none"></textarea>
 </div>
 </div>
 </div>

 <div class="p-4 md:p-6 border-t border-gray-800 flex flex-col sm:flex-row justify-end gap-3 md:gap-4">
 <button class="w-full sm:w-auto px-6 py-3 rounded-[6px] bg-[#283041] hover:bg-gray-700 text-gray-300 font-bold transition-all text-sm" @click="$emit('close')">Descartar</button>
 <button :disabled="submitting" class="w-full sm:w-auto px-8 py-3 rounded-[6px] bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white font-black transition-all text-sm" @click="submit">
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

interface Material { id: string | number; name?: string; category?: string; cost_per_kg?: number; [key: string]: unknown }
interface Contact { id: string | number; name?: string; company?: string; customer_id_document?: string; email?: string; phone?: string; address_full?: string; location?: string; city_dept_country?: string; zip_code?: string; location_reference?: string; notes?: string; [key: string]: unknown }

const props = defineProps({
 visible: Boolean,
 materials: { type: Array as () => Material[], default: () => [] },
 contacts: { type: Array as () => Contact[], default: () => [] },
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
 form.customer_id = String(c.id); form.customer_name = c.name || ''
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
 })
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
