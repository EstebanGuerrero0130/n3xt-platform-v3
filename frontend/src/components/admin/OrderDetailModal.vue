<template>
 <Teleport to="body">
 <div v-if="order" class="fixed inset-0 z-[9997] flex items-center justify-center p-4" @click.self="$emit('close')">
 <div class="absolute inset-0 bg-black/70 backdrop-blur-sm"></div>
 <div class="relative bg-[#151a22] border border-gray-700 rounded-[24px] w-full max-w-5xl max-h-[90vh] overflow-y-auto">
 <!-- Header -->
 <div class="sticky top-0 bg-[#151a22] z-10 p-4 md:p-6 pb-3 md:pb-4 border-b border-gray-800 flex justify-between items-start gap-3">
 <div class="flex items-center gap-3 md:gap-4 min-w-0">
 <div class="w-10 md:w-12 h-10 md:h-12 rounded-[24px] bg-emerald-600/20 flex items-center justify-center shrink-0">
 <svg class="w-5 md:w-6 h-5 md:h-6 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
 </div>
 <div class="min-w-0">
 <h2 class="text-lg md:text-2xl font-black text-white tracking-tight truncate">ORDEN #{{ order.id }}</h2>
 <div class="flex items-center gap-2 mt-1">
 <span :class="statusClass" class="text-[10px] md:text-xs font-bold px-2 md:px-3 py-0.5 md:py-1 rounded-[60px] uppercase">{{ statusLabel }}</span>
 </div>
 </div>
 </div>
 <button class="p-2 hover:bg-[#283041] rounded-[6px] transition-colors shrink-0" @click="$emit('close')">
 <svg class="w-5 h-5 text-[#c3c4c5]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
 </button>
 </div>

 <div class="p-4 md:p-6 grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-6">
 <!-- Left: General Info + Logistics + Timeline -->
 <div class="space-y-6">
 <!-- Customer Info -->
 <div class="bg-[#283041]/50 rounded-[6px] md:rounded-[24px] p-3 md:p-5 border border-gray-700/50">
 <h3 class="text-xs font-black text-[#c3c4c5] uppercase tracking-wider mb-3">Cliente</h3>
 <p class="text-xl font-black text-white">{{ order.customer_name }}</p>
 <p v-if="order.customer_id_document" class="text-sm text-[#a4aea6] mt-1">ID: {{ order.customer_id_document }}</p>
 <p v-if="order.customer_phone" class="text-sm text-[#c3c4c5] mt-2">{{ order.customer_phone }}</p>
 <p v-if="order.customer_email" class="text-sm text-[#c3c4c5]">{{ order.customer_email }}</p>
 </div>

 <!-- Technical Specs -->
 <div class="bg-[#283041]/50 rounded-[6px] md:rounded-[24px] p-3 md:p-5 border border-gray-700/50">
 <h3 class="text-xs font-black text-[#c3c4c5] uppercase tracking-wider mb-3">Especificaciones Técnicas</h3>
 <div class="grid grid-cols-2 gap-2 md:gap-3 text-xs md:text-sm">
 <div><span class="text-[#a4aea6]">Tecnología:</span><span class="text-white font-bold ml-2">{{ order.technology }}</span></div>
 <div><span class="text-[#a4aea6]">Material:</span><span class="text-white font-bold ml-2">{{ materialName }}</span></div>
 <div><span class="text-[#a4aea6]">Peso:</span><span class="text-white font-bold ml-2">{{ order.estimated_weight_g || 0 }}g</span></div>
 <div><span class="text-[#a4aea6]">Tiempo:</span><span class="text-white font-bold ml-2">{{ order.estimated_duration_h || 0 }}h</span></div>
 </div>
 </div>

 <!-- Shipping / Logistics -->
 <div v-if="order.status !== 'pending'" class="bg-[#283041]/50 rounded-[6px] md:rounded-[24px] p-3 md:p-5 border border-gray-700/50">
 <h3 class="text-xs font-black text-[#c3c4c5] uppercase tracking-wider mb-3">Logística de Envío</h3>
 <div v-if="order.customer_phone || order.shipping_address" class="space-y-2 text-sm mb-4">
 <p v-if="order.shipping_address" class="text-[#c3c4c5]">{{ order.shipping_address }}</p>
 <p v-if="order.shipping_city" class="text-[#c3c4c5]">{{ order.shipping_city }} {{ order.shipping_zip }}</p>
 </div>
 <div v-if="order.status === 'shipped'" class="space-y-3">
 <div>
 <label for="detail-carrier" class="block text-xs font-bold text-[#c3c4c5] uppercase tracking-wider mb-2">Transportadora</label>
 <input id="detail-carrier" v-model="localCarrier" type="text" placeholder="EJ: SERVIENTREGA" class="w-full bg-[#283041] border border-gray-700 rounded-[6px] px-4 py-3 text-white text-sm outline-none">
 </div>
 <div>
 <label for="detail-guide" class="block text-xs font-bold text-[#c3c4c5] uppercase tracking-wider mb-2">Número de Guía</label>
 <div class="flex flex-col sm:flex-row gap-2">
 <input id="detail-guide" v-model="localGuide" type="text" placeholder="EJ: 982347123" class="flex-1 bg-[#283041] border border-gray-700 rounded-[6px] px-3 md:px-4 py-2.5 md:py-3 text-white text-sm outline-none">
 <button class="w-full sm:w-auto px-4 py-2.5 md:py-3 bg-emerald-600 hover:bg-emerald-500 rounded-[6px] text-white font-bold text-sm transition-colors" @click="saveTracking">Guardar</button>
 </div>
 </div>
 </div>
 </div>

 <!-- Timeline -->
 <div class="bg-[#283041]/50 rounded-[6px] md:rounded-[24px] p-3 md:p-5 border border-gray-700/50">
 <h3 class="text-xs font-black text-[#c3c4c5] uppercase tracking-wider mb-3 md:mb-4">Línea de Producción</h3>
 <div class="flex items-center gap-1">
 <div v-for="(step, idx) in statusSteps" :key="step.id" class="flex-1 flex flex-col items-center">
 <div :class="['w-8 h-8 rounded-[60px] flex items-center justify-center text-xs font-black transition-all', getStatusIndex(order.status) >= idx ? 'bg-emerald-600 text-white' : 'bg-gray-700 text-[#a4aea6]']">
 {{ getStatusIndex(order.status) >= idx ? '✓' : idx + 1 }}
 </div>
 <p :class="['text-[10px] font-bold mt-1 text-center', getStatusIndex(order.status) >= idx ? 'text-emerald-400' : 'text-[#a4aea6]']">{{ step.label }}</p>
 </div>
 <div v-if="idx < statusSteps.length - 1" :key="'line-' + idx" :class="['h-0.5 flex-1', getStatusIndex(order.status) > idx ? 'bg-emerald-600' : 'bg-gray-700']"></div>
 </div>
 </div>
 </div>

 <!-- Right: Economic Analysis + Extras -->
 <div class="space-y-6">
 <!-- Price & Profitability -->
 <div class="bg-[#283041]/50 rounded-[6px] md:rounded-[24px] p-3 md:p-5 border border-gray-700/50">
 <div class="flex justify-between items-center mb-3 md:mb-4">
 <h3 class="text-xs font-black text-[#c3c4c5] uppercase tracking-wider">Análisis Económico</h3>
 <span :class="profitClass" class="text-xs md:text-sm font-black">{{ breakdown?.profit_margin_pct || 0 }}% margen</span>
 </div>
 <div class="text-2xl md:text-3xl font-black text-emerald-400 mb-3 md:mb-4">${{ (order.total_price || 0).toLocaleString(undefined, {maximumFractionDigits: 0}) }}</div>

 <div v-if="breakdown" class="space-y-2 text-sm">
 <div class="flex justify-between text-[#c3c4c5]"><span>Material</span><span class="text-white font-bold">${{ breakdown.material.toLocaleString(undefined, {maximumFractionDigits: 0}) }}</span></div>
 <div class="flex justify-between text-[#c3c4c5]"><span>Energía</span><span class="text-white font-bold">${{ breakdown.luz.toLocaleString(undefined, {maximumFractionDigits: 0}) }}</span></div>
 <div class="flex justify-between text-[#c3c4c5]"><span>Mano de Obra</span><span class="text-white font-bold">${{ breakdown.labor.toLocaleString(undefined, {maximumFractionDigits: 0}) }}</span></div>
 <div class="flex justify-between text-[#c3c4c5]"><span>Depreciación</span><span class="text-white font-bold">${{ breakdown.depr.toLocaleString(undefined, {maximumFractionDigits: 0}) }}</span></div>
 <div class="flex justify-between text-[#c3c4c5]"><span>Mantenimiento</span><span class="text-white font-bold">${{ breakdown.mant.toLocaleString(undefined, {maximumFractionDigits: 0}) }}</span></div>
 <div class="flex justify-between text-[#c3c4c5]"><span>Prep/Empaque</span><span class="text-white font-bold">${{ breakdown.prep_pack.toLocaleString(undefined, {maximumFractionDigits: 0}) }}</span></div>
 <div class="border-t border-gray-700 pt-2 mt-2">
 <div class="flex justify-between text-emerald-400 font-bold"><span>Costo Producción</span><span>${{ breakdown.production_cost.toLocaleString(undefined, {maximumFractionDigits: 0}) }}</span></div>
 <div class="flex justify-between text-white font-bold text-base mt-1"><span>Margen Operativo</span><span>${{ breakdown.operating_margin.toLocaleString(undefined, {maximumFractionDigits: 0}) }}</span></div>
 </div>
 </div>
 </div>

 <!-- Registered Extras -->
 <div class="bg-[#283041]/50 rounded-[6px] md:rounded-[24px] p-3 md:p-5 border border-gray-700/50">
 <h3 class="text-xs font-black text-[#c3c4c5] uppercase tracking-wider mb-3">Extras Registrados</h3>
 <div v-if="order.extra_items && order.extra_items.length" class="space-y-2 mb-4">
 <div v-for="(item, idx) in order.extra_items" :key="idx" class="flex justify-between items-center text-sm bg-[#283041]/50 rounded-[6px] px-4 py-2">
 <span class="text-white">{{ item.name }}</span>
 <span class="text-emerald-400 font-bold">${{ (item.cost * item.qty).toLocaleString(undefined, {maximumFractionDigits: 0}) }}</span>
 </div>
 </div>
 <p v-else class="text-sm text-[#a4aea6]">Sin extras registrados</p>

 <!-- Add Extra -->
 <div v-if="showAddForm" class="mt-4 space-y-3">
 <select v-model="newExtra.material_id" class="w-full bg-[#283041] border border-gray-700 rounded-[6px] px-4 py-3 text-white text-sm outline-none">
 <option value="">Seleccionar insumo...</option>
 <option v-for="m in materials" :key="m.id" :value="m.id">{{ m.name }}</option>
 </select>
 <div class="flex flex-col sm:flex-row gap-2">
 <input v-model.number="newExtra.qty" type="number" min="1" class="flex-1 bg-[#283041] border border-gray-700 rounded-[6px] px-3 md:px-4 py-2.5 md:py-3 text-white text-sm outline-none">
 <button class="w-full sm:w-auto px-4 md:px-6 py-2.5 md:py-3 bg-emerald-600 hover:bg-emerald-500 rounded-[6px] text-white font-bold text-sm transition-colors" @click="addExtra">Agregar</button>
 </div>
 </div>
 <button v-else class="mt-3 text-sm text-emerald-400 hover:text-emerald-300 font-bold transition-colors" @click="showAddForm = true">+ Agregar Consumible</button>
 </div>

 <!-- Notes -->
 <div v-if="order.comments" class="bg-[#283041]/50 rounded-[6px] md:rounded-[24px] p-3 md:p-5 border border-gray-700/50">
 <h3 class="text-xs font-black text-[#c3c4c5] uppercase tracking-wider mb-2">Notas</h3>
 <p class="text-sm text-[#c3c4c5] whitespace-pre-wrap">{{ order.comments }}</p>
 </div>
 </div>
 </div>
 </div>
 </div>
 </Teleport>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { api } from '../../services/api'
import { calcOrderDetailBreakdown } from '../../services/costCalculator'
import logger from '../../utils/logger'

const props = defineProps({
 order: { type: Object, default: null },
 materials: { type: Array, default: () => [] },
 settings: { type: Object, default: () => ({ infra: {}, prep: {}, margin: {} }) },
})

const emit = defineEmits(['close', 'updated', 'update-order'])

const showAddForm = ref(false)
const newExtra = ref({ material_id: '', qty: 1 })

// Local state for tracking fields to avoid prop mutation
const localCarrier = ref(props.order?.tracking_carrier || '')
const localGuide = ref(props.order?.tracking_guide || '')

// Sync local state when prop changes
watch(() => props.order?.tracking_carrier, (val) => { localCarrier.value = val || '' })
watch(() => props.order?.tracking_guide, (val) => { localGuide.value = val || '' })

const statusSteps = [
 { id: 'pending', label: 'Recibido', desc: 'En cola de revisión.' },
 { id: 'printing', label: 'Impresión', desc: 'En máquina.' },
 { id: 'post-processing', label: 'Acabado', desc: 'Limpieza y Curado.' },
 { id: 'completed', label: 'Terminado', desc: 'Listo para entrega.' },
 { id: 'shipped', label: 'Enviado', desc: 'En camino al cliente.' },
]

const getStatusIndex = (status: any) => {
 const mapping = { pending: 0, printing: 1, 'post-processing': 2, completed: 3, shipped: 4 }
 return mapping[status] ?? -1
}

const statusLabel = computed(() => {
 const idx = getStatusIndex(props.order?.status)
 return idx >= 0 ? statusSteps[idx].label : props.order?.status
})

const statusClass = computed(() => {
 switch (props.order?.status) {
 case 'pending': return 'bg-amber-600/20 text-amber-400'
 case 'printing': return 'bg-blue-600/20 text-blue-400'
 case 'post-processing': return 'bg-violet-600/20 text-violet-400'
 case 'completed': return 'bg-emerald-600/20 text-emerald-400'
 case 'shipped': return 'bg-cyan-600/20 text-cyan-400'
 default: return 'bg-gray-600/20 text-[#c3c4c5]'
 }
})

const profitClass = computed(() => {
 const pct = breakdown.value?.profit_margin_pct || 0
 if (pct >= 40) return 'text-emerald-400'
 if (pct >= 25) return 'text-amber-400'
 return 'text-red-400'
})

const materialName = computed(() => {
 if (!props.order?.material_id) return '-'
 const mat = props.materials.find(m => m.id === props.order.material_id)
 return mat ? mat.name : props.order.material_id
})

const breakdown = computed(() => {
 if (!props.order) return null
 const s = props.order.cost_snapshot?.settings || props.settings
 const matCostPerKg = props.order.cost_snapshot?.material_cost_per_kg
 || props.materials.find(m => m.id === props.order.material_id)?.cost_per_kg
 || 0
 return calcOrderDetailBreakdown(props.order, s, matCostPerKg)
})

const addExtra = async () => {
 if (!newExtra.value.material_id || newExtra.value.qty <= 0) return
 try {
 await api.post(`/admin/orders/${props.order.id}/extras`, newExtra.value, true)
 emit('updated')
 showAddForm.value = false
 newExtra.value = { material_id: '', qty: 1 }
 } catch (err) {
 logger.error('Error adding extra:', err)
 }
}

const saveTracking = async () => {
 try {
 await api.patch(`/admin/orders/${props.order.id}/status`, {
 tracking_guide: localGuide.value,
 tracking_carrier: localCarrier.value
 }, true)
 emit('updated')
 } catch (err) {
 logger.error('Error saving tracking:', err)
 }
}
</script>
