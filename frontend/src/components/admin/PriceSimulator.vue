<template>
 <Teleport to="body">
 <div v-if="visible" class="fixed inset-0 z-[9997] flex items-center justify-center p-4" @click.self="$emit('close')">
 <div class="absolute inset-0 bg-black/70 backdrop-blur-sm"></div>
 <div class="relative bg-[#151a22] border border-gray-700 rounded-[24px] w-full max-w-5xl max-h-[90vh] overflow-y-auto">
 <!-- Header -->
 <div class="sticky top-0 bg-[#151a22] z-10 p-6 pb-4 border-b border-gray-800 flex justify-between items-start">
 <div>
 <h2 class="text-2xl font-black text-white tracking-tight">SIMULADOR DE COSTOS</h2>
 <p class="text-sm text-[#a4aea6] mt-1 font-semibold">Motor de costos N3XT • Manufactura Digital</p>
 </div>
 <button class="p-2 hover:bg-[#283041] rounded-[6px] transition-colors" @click="$emit('close')">
 <svg class="w-5 h-5 text-[#c3c4c5]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
 </button>
 </div>

 <div class="p-4 md:p-6 grid grid-cols-1 lg:grid-cols-5 gap-4 md:gap-6">
 <!-- Left: Inputs -->
 <div class="lg:col-span-3 space-y-4 md:space-y-5">
 <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 md:gap-4">
 <div>
 <label class="block text-xs font-bold text-[#c3c4c5] uppercase tracking-wider mb-2">Nombre del Proyecto</label>
 <input v-model="form.job_name" placeholder="Ej: Engranaje Hélice" class="w-full bg-[#283041] border border-gray-700 rounded-[6px] px-4 py-3 text-white font-semibold text-sm focus:ring-2 focus:ring-emerald-500 focus:border-transparent outline-none transition-all">
 </div>
 <div>
 <label class="block text-xs font-bold text-[#c3c4c5] uppercase tracking-wider mb-2">Cliente</label>
 <select class="w-full bg-[#283041] border border-gray-700 rounded-[6px] px-4 py-3 text-white font-semibold text-sm focus:ring-2 focus:ring-emerald-500 outline-none transition-all" @change="selectCustomer">
 <option value="">Seleccionar cliente</option>
 <option v-for="c in contacts" :key="c.id" :value="c.id">{{ c.name }}</option>
 </select>
 </div>
 </div>

 <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 md:gap-4">
 <div>
 <label class="block text-xs font-bold text-[#c3c4c5] uppercase tracking-wider mb-2">Nombre</label>
 <input v-model="form.customer_name" placeholder="Cliente" class="w-full bg-[#283041] border border-gray-700 rounded-[6px] px-3 md:px-4 py-2.5 md:py-3 text-xs md:text-sm font-semibold focus:ring-2 focus:ring-emerald-500 outline-none transition-all">
 </div>
 <div>
 <label class="block text-xs font-bold text-[#c3c4c5] uppercase tracking-wider mb-2">WhatsApp</label>
 <input v-model="form.customer_phone" placeholder="+57" class="w-full bg-[#283041] border border-gray-700 rounded-[6px] px-3 md:px-4 py-2.5 md:py-3 text-xs md:text-sm font-semibold focus:ring-2 focus:ring-emerald-500 outline-none transition-all">
 </div>
 <div>
 <label class="block text-xs font-bold text-[#c3c4c5] uppercase tracking-wider mb-2">Correo</label>
 <input v-model="form.customer_email" placeholder="email@ejemplo.com" class="w-full bg-[#283041] border border-gray-700 rounded-[6px] px-3 md:px-4 py-2.5 md:py-3 text-xs md:text-sm font-semibold focus:ring-2 focus:ring-emerald-500 outline-none transition-all">
 </div>
 </div>

 <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 md:gap-4">
 <div>
 <label class="block text-xs font-bold text-[#c3c4c5] uppercase tracking-wider mb-2">Material Base</label>
 <select v-model="form.material_id" class="w-full bg-[#283041] border border-gray-700 rounded-[6px] px-4 py-3 text-white font-semibold text-sm focus:ring-2 focus:ring-emerald-500 outline-none transition-all">
 <option value="">Seleccionar...</option>
 <option v-for="m in materials" :key="m.id" :value="m.id">{{ m.name }} (${{ m.cost_per_kg }}/kg)</option>
 </select>
 </div>
 <div>
 <label class="block text-xs font-bold text-[#c3c4c5] uppercase tracking-wider mb-2">Peso (gramos)</label>
 <input v-model.number="form.weight_g" type="number" min="0" step="0.1" class="w-full bg-[#283041] border border-gray-700 rounded-[6px] px-4 py-3 text-white text-sm font-semibold focus:ring-2 focus:ring-emerald-500 outline-none transition-all">
 </div>
 <div>
 <label class="block text-xs font-bold text-[#c3c4c5] uppercase tracking-wider mb-2">Tiempo (HH:MM)</label>
 <input v-model="form.time_str" placeholder="2:30" class="w-full bg-[#283041] border border-gray-700 rounded-[6px] px-4 py-3 text-white text-sm font-semibold focus:ring-2 focus:ring-emerald-500 outline-none transition-all">
 </div>
 </div>

 <div class="grid grid-cols-2 gap-3 md:gap-4">
 <div>
 <label class="block text-xs font-bold text-[#c3c4c5] uppercase tracking-wider mb-2">Piezas por Lote</label>
 <input v-model.number="form.pieces_per_batch" type="number" min="1" class="w-full bg-[#283041] border border-gray-700 rounded-[6px] px-3 md:px-4 py-2.5 md:py-3 text-xs md:text-sm font-semibold focus:ring-2 focus:ring-emerald-500 outline-none transition-all">
 </div>
 <div>
 <label class="block text-xs font-bold text-[#c3c4c5] uppercase tracking-wider mb-2">% Descuento</label>
 <input v-model.number="form.discount_pct" type="number" min="0" max="100" class="w-full bg-[#283041] border border-gray-700 rounded-[6px] px-3 md:px-4 py-2.5 md:py-3 text-xs md:text-sm font-semibold focus:ring-2 focus:ring-emerald-500 outline-none transition-all">
 </div>
 </div>

 <!-- Extras -->
 <div>
 <label class="block text-xs font-bold text-[#c3c4c5] uppercase tracking-wider mb-2">Insumos / Extras</label>
 <select class="w-full bg-[#283041] border border-gray-700 rounded-[6px] px-4 py-3 text-white text-sm font-semibold focus:ring-2 focus:ring-emerald-500 outline-none transition-all" @change="addExtra">
 <option value="">Agregar insumo adicional...</option>
 <option v-for="m in extraMaterials" :key="m.id" :value="m.id">{{ m.name }} (${{ m.cost_per_kg }}/{{ m.unit || 'unidad' }})</option>
 </select>
 <div v-for="(item, idx) in form.extra_items" :key="idx" class="flex items-center gap-3 mt-3 bg-[#283041]/50 rounded-[6px] px-4 py-3">
 <span class="flex-1 text-white text-sm font-semibold">{{ item.name }}</span>
 <div class="flex items-center gap-2">
 <button class="w-8 h-8 rounded-[6px] bg-gray-700 hover:bg-gray-600 text-white font-bold transition-colors" @click="item.qty = Math.max(1, item.qty - 1)" aria-label="Botón interactivo">-</button>
 <span class="text-white font-bold w-8 text-center">{{ item.qty }}</span>
 <button class="w-8 h-8 rounded-[6px] bg-gray-700 hover:bg-gray-600 text-white font-bold transition-colors" @click="item.qty++" aria-label="Botón interactivo">+</button>
 </div>
 <span class="text-emerald-400 font-bold w-24 text-right">${{ (item.cost * item.qty).toLocaleString() }}</span>
 <button class="p-1 hover:bg-red-900/50 rounded-[6px] transition-colors" @click="removeExtra(idx)">
 <svg class="w-4 h-4 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
 </button>
 </div>
 </div>

 <div>
 <label class="block text-xs font-bold text-[#c3c4c5] uppercase tracking-wider mb-2">Notas Internas</label>
 <textarea v-model="form.comments" rows="3" placeholder="Notas para el taller..." class="w-full bg-[#283041] border border-gray-700 rounded-[6px] px-4 py-3 text-white text-sm font-semibold focus:ring-2 focus:ring-emerald-500 outline-none transition-all resize-none"></textarea>
 </div>
 </div>

 <!-- Right: Results -->
 <div class="lg:col-span-2 space-y-3 md:space-y-4">
 <div class="bg-[#283041]/50 rounded-[24px] p-5 border border-gray-700/50">
 <div class="flex justify-between items-start mb-4">
 <div>
 <p class="text-xs font-bold text-[#a4aea6] uppercase tracking-wider">Total Proyectado</p>
 <p class="text-3xl font-black text-emerald-400 mt-1">${{ result.total.toLocaleString() }}</p>
 </div>
 <div class="flex gap-2">
 <button class="px-3 py-2 bg-gray-700 hover:bg-gray-600 rounded-[6px] text-xs font-bold text-white transition-colors" @click="emitFormData('download-pdf')">PDF</button>
 </div>
 </div>

 <!-- Metrics Cards -->
 <div class="grid grid-cols-2 gap-2 mb-4">
 <div class="bg-[#151a22]/80 rounded-[6px] p-3 border border-gray-700/30">
 <p class="text-[9px] font-bold text-[#a4aea6] uppercase tracking-wider">Precio Unitario</p>
 <p class="text-lg font-black text-white mt-0.5">${{ result.unit_price.toLocaleString() }}</p>
 </div>
 <div class="bg-[#151a22]/80 rounded-[6px] p-3 border border-gray-700/30">
 <p class="text-[9px] font-bold text-[#a4aea6] uppercase tracking-wider">Margen de Ganancia</p>
 <p class="text-lg font-black" :class="result.profit_margin_pct >= 20 ? 'text-emerald-400' : result.profit_margin_pct >= 10 ? 'text-amber-400' : 'text-red-400'">{{ result.profit_margin_pct.toFixed(1) }}%</p>
 </div>
 <div class="bg-[#151a22]/80 rounded-[6px] p-3 border border-gray-700/30">
 <p class="text-[9px] font-bold text-[#a4aea6] uppercase tracking-wider">Costo / Hora</p>
 <p class="text-lg font-black text-white mt-0.5">${{ (result.production / (result.total_hours || 1)).toLocaleString(undefined, {maximumFractionDigits: 0}) }}</p>
 </div>
 <div class="bg-[#151a22]/80 rounded-[6px] p-3 border border-gray-700/30">
 <p class="text-[9px] font-bold text-[#a4aea6] uppercase tracking-wider">Piezas por Lote</p>
 <p class="text-lg font-black text-white mt-0.5">{{ form.pieces_per_batch || 1 }}</p>
 </div>
 </div>

 <!-- Cost distribution bar -->
 <div class="h-3 rounded-[60px] bg-gray-700 flex overflow-hidden mb-4">
 <div :style="{ width: (result.material / (result.total || 1) * 100) + '%' }" class="h-full bg-blue-500 transition-all min-w-[2px]" :title="'Material: ' + ((result.material / (result.total || 1) * 100).toFixed(1)) + '%'"></div>
 <div :style="{ width: ((result.luz + result.labor + result.depr + result.mant + result.etiquetas) / (result.total || 1) * 100) + '%' }" class="h-full bg-amber-500 transition-all min-w-[2px]" :title="'Infraestructura: ' + (((result.luz + result.labor + result.depr + result.mant + result.etiquetas) / (result.total || 1) * 100).toFixed(1)) + '%'"></div>
 <div :style="{ width: (result.extras / (result.total || 1) * 100) + '%' }" class="h-full bg-violet-500 transition-all min-w-[2px]" :title="'Extras: ' + ((result.extras / (result.total || 1) * 100).toFixed(1)) + '%'"></div>
 <div :style="{ width: (result.logistics / (result.total || 1) * 100) + '%' }" class="h-full bg-cyan-500 transition-all min-w-[2px]" :title="'Logística: ' + ((result.logistics / (result.total || 1) * 100).toFixed(1)) + '%'"></div>
 <div :style="{ width: (result.marketing / (result.total || 1) * 100) + '%' }" class="h-full bg-pink-500 transition-all min-w-[2px]" :title="'Marketing: ' + ((result.marketing / (result.total || 1) * 100).toFixed(1)) + '%'"></div>
 <div :style="{ width: (result.failures / (result.total || 1) * 100) + '%' }" class="h-full bg-red-500 transition-all min-w-[2px]" :title="'Fallos: ' + ((result.failures / (result.total || 1) * 100).toFixed(1)) + '%'"></div>
 <div :style="{ width: (result.profit / (result.total || 1) * 100) + '%' }" class="h-full bg-emerald-500 transition-all min-w-[2px]" :title="'Ganancia: ' + ((result.profit / (result.total || 1) * 100).toFixed(1)) + '%'"></div>
 </div>

 <!-- Breakdown -->
 <div class="space-y-2 text-sm">
 <div class="flex justify-between text-[#c3c4c5]"><span>Material</span><span class="text-white font-bold">${{ result.material.toLocaleString() }}</span></div>
 <div class="flex justify-between text-[#c3c4c5]"><span>Energía <span class="text-[9px] text-[#a4aea6]">(carga: {{ ((settings.infra?.load_factor ?? 0.4) * 100).toFixed(0) }}%)</span></span><span class="text-white font-bold">${{ result.luz.toLocaleString() }}</span></div>
 <div class="flex justify-between text-[#c3c4c5]"><span>Mano de Obra <span class="text-[9px] text-[#a4aea6]">(prep: {{ settings.prep?.prep_time_pct ?? 10 }}%)</span></span><span class="text-white font-bold">${{ result.labor.toLocaleString() }}</span></div>
 <div class="flex justify-between text-[#c3c4c5]"><span>Depreciación</span><span class="text-white font-bold">${{ result.depr.toLocaleString() }}</span></div>
 <div class="flex justify-between text-[#c3c4c5]"><span>Mantenimiento</span><span class="text-white font-bold">${{ result.mant.toLocaleString() }}</span></div>
 <div class="flex justify-between text-[#c3c4c5]"><span>Etiquetas</span><span class="text-white font-bold">${{ result.etiquetas.toLocaleString() }}</span></div>
 <div class="flex justify-between text-[#c3c4c5]"><span>Extras</span><span class="text-white font-bold">${{ result.extras.toLocaleString() }}</span></div>
 <div class="border-t border-gray-700/50 pt-2 mt-2">
 <div class="flex justify-between text-[#c3c4c5]"><span class="font-bold text-[#a4aea6] uppercase text-[10px] tracking-wider">Costos Operativos</span></div>
 <div class="flex justify-between text-[#c3c4c5] mt-1"><span class="pl-3">Logística ({{ form.transporte_pct }}%)</span><span class="text-white font-bold">${{ result.logistics.toLocaleString() }}</span></div>
 <div class="flex justify-between text-[#c3c4c5]"><span class="pl-3">Marketing ({{ form.marketing_pct }}%)</span><span class="text-white font-bold">${{ result.marketing.toLocaleString() }}</span></div>
 <div class="flex justify-between text-[#c3c4c5]"><span class="pl-3">Fallos ({{ form.fallos_pct }}%)</span><span class="text-white font-bold">${{ result.failures.toLocaleString() }}</span></div>
 <div class="flex justify-between text-emerald-400/80"><span class="pl-3">Ganancia ({{ form.profit_pct }}%)</span><span class="text-emerald-400 font-bold">${{ result.profit.toLocaleString() }}</span></div>
 </div>
 <div v-if="result.discount > 0" class="flex justify-between text-red-400"><span>Descuento (-{{ form.discount_pct }}%)</span><span class="text-red-400 font-bold">-${{ result.discount.toLocaleString() }}</span></div>
 <div class="border-t border-gray-700 pt-2 mt-2">
 <div class="flex justify-between text-gray-300"><span class="font-semibold">Subtotal</span><span class="text-white font-bold">${{ result.subtotal.toLocaleString() }}</span></div>
 <div class="flex justify-between text-[#c3c4c5]"><span>IVA ({{ form.iva_pct }}%)</span><span class="text-white font-bold">${{ result.iva.toLocaleString() }}</span></div>
 <div class="flex justify-between text-emerald-400 text-base font-bold mt-2 pt-2 border-t border-gray-700/50"><span>TOTAL PROYECTADO</span><span>${{ result.total.toLocaleString() }}</span></div>
 </div>
 </div>
 </div>

 <!-- Controls -->
 <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 md:gap-3">
 <div>
 <label class="block text-[10px] md:text-xs font-bold text-[#c3c4c5] uppercase tracking-wider mb-1 md:mb-2">% Logística</label>
 <input v-model.number="form.transporte_pct" type="number" min="0" step="0.5" class="w-full bg-[#283041] border border-gray-700 rounded-[6px] px-2 md:px-3 py-1.5 md:py-2 text-xs md:text-sm font-semibold focus:ring-2 focus:ring-emerald-500 outline-none transition-all">
 </div>
 <div>
 <label class="block text-[10px] md:text-xs font-bold text-[#c3c4c5] uppercase tracking-wider mb-1 md:mb-2">% Marketing</label>
 <input v-model.number="form.marketing_pct" type="number" min="0" step="0.5" class="w-full bg-[#283041] border border-gray-700 rounded-[6px] px-2 md:px-3 py-1.5 md:py-2 text-xs md:text-sm font-semibold focus:ring-2 focus:ring-emerald-500 outline-none transition-all">
 </div>
 <div>
 <label class="block text-[10px] md:text-xs font-bold text-[#c3c4c5] uppercase tracking-wider mb-1 md:mb-2">% Fallos</label>
 <input v-model.number="form.fallos_pct" type="number" min="0" step="0.5" class="w-full bg-[#283041] border border-gray-700 rounded-[6px] px-2 md:px-3 py-1.5 md:py-2 text-xs md:text-sm font-semibold focus:ring-2 focus:ring-emerald-500 outline-none transition-all">
 </div>
 <div>
 <label class="block text-[10px] md:text-xs font-bold text-[#c3c4c5] uppercase tracking-wider mb-1 md:mb-2">% Ganancia (Obj)</label>
 <input v-model.number="form.profit_pct" type="number" min="0" step="1" class="w-full bg-[#283041] border border-gray-700 rounded-[6px] px-2 md:px-3 py-1.5 md:py-2 text-xs md:text-sm font-semibold focus:ring-2 focus:ring-emerald-500 outline-none transition-all">
 </div>
 <div>
 <label class="block text-[10px] md:text-xs font-bold text-[#c3c4c5] uppercase tracking-wider mb-1 md:mb-2">% IVA</label>
 <input v-model.number="form.iva_pct" type="number" min="0" max="100" step="0.5" class="w-full bg-[#283041] border border-gray-700 rounded-[6px] px-2 md:px-3 py-1.5 md:py-2 text-xs md:text-sm font-semibold focus:ring-2 focus:ring-emerald-500 outline-none transition-all">
 </div>
 </div>

 <button
 class="w-full py-4 bg-emerald-600 hover:bg-emerald-500 rounded-[24px] font-black text-white text-lg tracking-tight transition-all hover: hover:-emerald-600/30"
 @click="emitFormData('create-order')"
 >
 CONVERTIR A ORDEN REAL
 </button>
 </div>
 </div>
 </div>
 </div>
 </Teleport>
</template>

<script setup lang="ts">
import { reactive, computed } from 'vue'
import { calcProductionCost, calcFinalPrice } from '../../services/costCalculator'

const props = withDefaults(defineProps<{
 visible: boolean
 settings?: Record<string, any>
 materials?: Array<any>
 contacts?: Array<any>
}>(), {
 visible: false,
 settings: () => ({ infra: {}, prep: {}, oper: {}, margin: {} }),
 materials: () => [],
 contacts: () => []
})

const emit = defineEmits(['close', 'create-order', 'download-pdf'])

const form = reactive({
 job_name: '', customer_id: '', customer_name: '', customer_company: '',
 customer_id_document: '', customer_email: '', customer_phone: '',
 shipping_address: '', shipping_city: '', shipping_zip: '', shipping_reference: '',
 material_id: '', weight_g: 0, time_str: '0:00', pieces_per_batch: 1,
 profit_pct: props.settings?.oper?.ganancia ?? 20,
 discount_pct: 0,
 transporte_pct: props.settings?.oper?.transporte ?? 5,
 marketing_pct: props.settings?.oper?.marketing ?? 10,
 fallos_pct: props.settings?.oper?.fallos ?? 5,
 iva_pct: props.settings?.margin?.iva ?? 19,
 extra_items: [] as Array<{ id: string; name: string; cost: number; unit: string; qty: number }>, comments: '',
})

const extraMaterials = computed(() =>
 props.materials.filter(m => m.type !== 'material')
)

const result = computed(() => {
 const mat = props.materials.find(m => m.id === form.material_id)
 if (!mat || !props.settings.infra) return {
 material: 0, luz: 0, labor: 0, depr: 0, mant: 0, etiquetas: 0, extras: 0,
 production: 0, logistics: 0, marketing: 0, failures: 0, profit: 0,
 subtotal: 0, iva: 0, total: 0, unit_price: 0, discount: 0,
 profit_margin_pct: 0, total_hours: 0,
 isSafetyAlert: false, pcts: { material: 0, luz: 0, labor: 0, depr: 0, mant: 0, etiquetas: 0, extras: 0 }
 }

 const qty = Math.max(1, form.pieces_per_batch || 1)
 const [hours, minutes] = form.time_str.split(':').map(Number)
 const totalHours = (hours || 0) + (minutes || 0) / 60
 const extrasCost = form.extra_items.reduce((acc, item) => acc + item.cost * item.qty, 0)

 const prod = calcProductionCost({
 weightG: form.weight_g, totalHours, costPerKg: mat.cost_per_kg,
 infra: props.settings.infra, prep: props.settings.prep, extrasCost,
 })

 const price = calcFinalPrice({
 productionCost: prod.total,
 oper: { transporte: form.transporte_pct, marketing: form.marketing_pct, fallos: form.fallos_pct, ganancia: form.profit_pct },
 margin: { iva: props.settings.margin?.iva || 0 },
 overrides: { discountPct: form.discount_pct, ivaPct: form.iva_pct },
 })

 return {
 material: prod.material, luz: prod.luz, labor: prod.labor,
 depr: prod.depr, mant: prod.mant, etiquetas: prod.etiquetas,
 extras: prod.extras, production: prod.total,
 logistics: price.logistics, marketing: price.marketing,
 failures: price.failures, profit: price.profit,
 discount: price.discount, subtotal: price.subtotal,
 iva: price.iva, total: price.total,
 unit_price: Math.round(price.total / qty),
 total_hours: totalHours, profit_margin_pct: price.profitMarginPct,
 isSafetyAlert: form.discount_pct === 100, pcts: prod.pcts,
 }
})

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

const emitFormData = (action: 'download-pdf' | 'create-order') => {
 const [hours, minutes] = form.time_str.split(':').map(Number)
 const totalHours = (hours || 0) + ((minutes || 0) / 60)
 const extrasCost = form.extra_items.reduce((acc, item) => acc + item.cost * item.qty, 0)
 const mat = props.materials.find(m => m.id === form.material_id)
 
 const payload = {
 job_name: form.job_name,
 material_id: form.material_id,
 weight_g: form.weight_g,
 time_str: form.time_str,
 total_hours: totalHours,
 total_price: result.value.total,
 pieces_per_batch: form.pieces_per_batch,
 profit_pct: form.profit_pct,
 discount_pct: form.discount_pct,
 extra_items: form.extra_items,
 extras_cost: extrasCost,
 comments: form.comments,
 customer_id: form.customer_id,
 customer_name: form.customer_name,
 customer_company: form.customer_company,
 customer_id_document: form.customer_id_document,
 customer_email: form.customer_email,
 customer_phone: form.customer_phone,
 shipping_address: form.shipping_address,
 shipping_city: form.shipping_city,
 shipping_zip: form.shipping_zip,
 shipping_reference: form.shipping_reference,
 transportation_pct: form.transporte_pct,
 marketing_pct: form.marketing_pct,
 failures_pct: form.fallos_pct,
 }
 emit(action, payload)
}

const addExtra = (e: any) => {
 const matId = e.target.value
 if (!matId) return
 const item = props.materials.find(m => m.id === matId)
 if (item) {
 const existing = form.extra_items.find(i => i.id === item.id)
 if (existing) existing.qty++
 else form.extra_items.push({ id: item.id, name: item.name, cost: item.cost_per_kg, unit: item.unit, qty: 1 })
 }
 e.target.value = ''
}

const removeExtra = (idx: number) => { form.extra_items.splice(idx, 1) }
</script>
