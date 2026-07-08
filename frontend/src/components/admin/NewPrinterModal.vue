<script setup lang="ts">
import { reactive, watch } from "vue"

const props = defineProps<{
 modelValue: boolean
 newPrinter: any
 submitting: boolean
}>()

const emit = defineEmits<{
 (e: "update:modelValue", val: boolean): void
 (e: "add-printer"): void
 (e: "update:newPrinter", val: any): void
}>()

const form = reactive({
 name: '',
 model: '',
 technology: 'FDM',
 watts: 350,
 nozzle_mm: 0.4,
 wall_count: 2,
 max_flow_mm3_hr: 15500,
 ...(props.newPrinter || {})
})

watch(() => props.newPrinter, (val) => {
 if (val) Object.assign(form, val)
}, { deep: true })

watch(form, (val) => {
 emit("update:newPrinter", { ...val })
}, { deep: true })

// Cuando cambia tecnología, ajustar defaults automáticos
watch(() => form.technology, (tech) => {
 if (tech === 'SLA') {
 form.nozzle_mm = 0.05    // resolución UV en mm (simbólico)
 form.wall_count = 0       // SLA no aplica
 form.max_flow_mm3_hr = 0  // SLA no aplica
 } else {
 form.nozzle_mm = 0.4
 form.wall_count = 2
 form.max_flow_mm3_hr = 15500
 }
})

import { flowPresets } from '../../utils/printerConstants'
</script>

<template>
 <div v-if="modelValue" class="fixed inset-0 bg-[#090d0a]/60 backdrop-blur-md z-[500] flex items-center justify-center p-4">
 <div class="bg-[#151a22] rounded-[3rem] w-full max-w-xl overflow-hidden animate-in zoom-in duration-300">
 <div class="p-6 md:p-10 max-h-[90vh] overflow-y-auto no-scrollbar">

 <!-- Header -->
 <div class="flex justify-between items-center mb-8">
 <h3 class="text-xl md:text-2xl font-black text-white uppercase tracking-tighter">Nueva Impresora</h3>
 <button class="text-[#c3c4c5] hover:text-white transition-colors text-2xl" aria-label="Cerrar modal" @click="emit('update:modelValue', false)">✕</button>
 </div>

 <!-- Datos Básicos -->
 <div class="space-y-4 mb-6">
 <input v-model="form.name" type="text" placeholder="Nombre Identificador (ej: Alpha-1)" class="w-full bg-[#0a0f14] border border-[#21262d] rounded-[24px] px-6 py-4 text-sm font-bold text-white focus:ring-2 focus:ring-primary/30 outline-none transition-all">
 <input v-model="form.model" type="text" placeholder="Modelo (ej: Bambu A1, Ender 3, Mars 4)" class="w-full bg-[#0a0f14] border border-[#21262d] rounded-[24px] px-6 py-4 text-sm font-bold text-white focus:ring-2 focus:ring-primary/30 outline-none transition-all">

 <div class="grid grid-cols-2 gap-4">
 <!-- Tecnología -->
 <div class="space-y-2">
 <label class="text-[10px] font-black text-[#c3c4c5] uppercase tracking-widest">Tecnología</label>
 <select v-model="form.technology" class="w-full bg-[#0a0f14] border border-[#21262d] rounded-[24px] px-5 py-4 text-sm font-bold text-white outline-none focus:ring-2 focus:ring-primary/30 transition-all">
 <option value="FDM">FDM — Filamento</option>
 <option value="SLA">SLA — Resina</option>
 </select>
 </div>
 <!-- Watts -->
 <div class="space-y-2">
 <label class="text-[10px] font-black text-[#c3c4c5] uppercase tracking-widest">Potencia (W)</label>
 <input v-model.number="form.watts" type="number" min="0" placeholder="350" class="w-full bg-[#0a0f14] border border-[#21262d] rounded-[24px] px-5 py-4 text-sm font-bold text-white outline-none focus:ring-2 focus:ring-primary/30 transition-all">
 </div>
 </div>
 </div>

 <!-- ── PERFIL ORCAENGINE (solo FDM) ──────────────────────────────── -->
 <div v-if="form.technology === 'FDM'" class="mb-6 p-5 bg-[#08872b]/5 border border-[#08872b]/20 rounded-[2rem]">
 <div class="flex items-center gap-3 mb-5">
 <div class="w-8 h-8 bg-[#08872b]/20 rounded-[12px] flex items-center justify-center shrink-0">
 <svg class="w-4 h-4 text-[#08872b]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18"/>
 </svg>
 </div>
 <div>
 <p class="text-[10px] font-black text-[#08872b] uppercase tracking-[0.4em]">Perfil OrcaEngine</p>
 <p class="text-[9px] text-[#a4aea6] font-medium mt-0.5">Define cómo el motor calcula peso y tiempo de impresión</p>
 </div>
 </div>

 <!-- Nozzle + Walls + Preview -->
 <div class="grid grid-cols-3 gap-3 mb-5">
 <div class="space-y-2">
 <label class="text-[10px] font-black text-[#c3c4c5] uppercase tracking-widest">Nozzle</label>
 <select v-model.number="form.nozzle_mm" class="w-full bg-[#0a0f14] border border-[#21262d] rounded-[18px] p-3 font-bold text-sm text-white outline-none focus:ring-2 focus:ring-primary/30 transition-all">
 <option :value="0.2">0.2 mm</option>
 <option :value="0.4">0.4 mm ★</option>
 <option :value="0.6">0.6 mm</option>
 <option :value="0.8">0.8 mm</option>
 </select>
 </div>
 <div class="space-y-2">
 <label class="text-[10px] font-black text-[#c3c4c5] uppercase tracking-widest">Paredes</label>
 <select v-model.number="form.wall_count" class="w-full bg-[#0a0f14] border border-[#21262d] rounded-[18px] p-3 font-bold text-sm text-white outline-none focus:ring-2 focus:ring-primary/30 transition-all">
 <option :value="1">1</option>
 <option :value="2">2 ★</option>
 <option :value="3">3</option>
 <option :value="4">4</option>
 </select>
 </div>
 <div class="space-y-2">
 <label class="text-[10px] font-black text-[#c3c4c5] uppercase tracking-widest">Shell</label>
 <div class="w-full h-[46px] bg-[#08872b]/10 border border-[#08872b]/25 rounded-[18px] flex items-center justify-center">
 <span class="text-[#08872b] font-black text-sm">{{ ((form.nozzle_mm || 0.4) * (form.wall_count || 2)).toFixed(1) }} mm</span>
 </div>
 </div>
 </div>

 <!-- Flujo Volumétrico -->
 <div class="space-y-3">
 <div class="flex items-center justify-between">
 <label class="text-[10px] font-black text-[#c3c4c5] uppercase tracking-widest">Flujo Máx. (mm³/h)</label>
 <span class="text-[10px] text-[#8dd6ff] font-black">{{ (form.max_flow_mm3_hr || 15500).toLocaleString() }}</span>
 </div>
 <input
 v-model.number="form.max_flow_mm3_hr"
 type="range" min="2000" max="25000" step="500"
 class="w-full h-2 bg-[#21262d] rounded-full appearance-none cursor-pointer accent-[#08872b]"
 />
 <div class="flex gap-2 flex-wrap">
 <button
 v-for="p in flowPresets" :key="p.label"
 :class="[
 'px-3 py-1.5 rounded-[12px] text-[9px] font-black uppercase tracking-wider transition-all',
 (form.max_flow_mm3_hr || 15500) === p.value
 ? 'bg-[#08872b] text-white'
 : 'bg-[#21262d] text-[#a4aea6] hover:bg-[#08872b]/20 hover:text-[#08872b]'
 ]"
 @click="form.max_flow_mm3_hr = p.value"
 >{{ p.label }} <span class="opacity-60">· {{ p.value.toLocaleString() }}</span></button>
 </div>
 </div>
 </div>

 <!-- SLA notice -->
 <div v-else class="mb-6 p-4 bg-[#8dd6ff]/5 border border-[#8dd6ff]/15 rounded-[1.5rem] flex items-center gap-3">
 <svg class="w-5 h-5 text-[#8dd6ff] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
 <p class="text-[9px] text-[#8dd6ff] font-bold uppercase tracking-wider">Las impresoras SLA usan lógica de capas UV en OrcaEngine — no requieren perfil de flujo.</p>
 </div>

 <!-- Botones -->
 <div class="flex flex-col gap-3">
 <button
 :disabled="submitting"
 class="w-full py-4 bg-[#08872b] text-white rounded-[24px] font-black text-xs uppercase tracking-widest hover:-translate-y-1 transition-all disabled:opacity-50 flex items-center justify-center gap-2"
 @click="emit('add-printer')"
 >
 <svg v-if="submitting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
 {{ submitting ? 'Registrando...' : 'Registrar Máquina' }}
 </button>
 <button class="w-full py-4 bg-[#0a0f14] border border-[#21262d] text-[#a4aea6] rounded-[24px] font-black text-xs uppercase tracking-widest hover:border-[#a4aea6]/40 transition-all" @click="emit('update:modelValue', false)">Cerrar</button>
 </div>

 </div>
 </div>
 </div>
</template>
