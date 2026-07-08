<script setup lang="ts">
import { reactive, watch } from "vue"

const props = defineProps<{
 modelValue: boolean
 editingPrinter: any
 submitting: boolean
}>()

const emit = defineEmits<{
 (e: "update:modelValue", val: boolean): void
 (e: "update-printer"): void
 (e: "update:editingPrinter", val: any): void
 (e: "maintenance-complete", id: number | string): void
 (e: "reset-printer", id: number | string): void
 (e: "delete-printer", id: number | string): void
}>()

const form = reactive({ ...(props.editingPrinter || {}) })

watch(() => props.editingPrinter, (val) => {
 if (val) Object.assign(form, val)
}, { deep: true })

watch(form, (val) => {
 emit("update:editingPrinter", { ...val })
}, { deep: true })

import { flowPresets } from '../../utils/printerConstants'
</script>

<template>
 <div v-if="modelValue" class="fixed inset-0 bg-[#090d0a]/60 backdrop-blur-md z-[500] flex items-center justify-center p-4">
 <div class="bg-[#151a22] rounded-[3rem] w-full max-w-xl overflow-hidden animate-in zoom-in duration-300">
 <div class="p-6 md:p-10 max-h-[90vh] overflow-y-auto no-scrollbar">

 <!-- Header -->
 <div class="flex justify-between items-center mb-8">
 <h3 class="text-xl md:text-2xl font-black text-white uppercase tracking-tighter">Detalles de Máquina</h3>
 <button class="text-[#c3c4c5] hover:text-white transition-colors text-2xl shrink-0 ml-4" aria-label="Cerrar modal" @click="emit('update:modelValue', false)">✕</button>
 </div>

 <!-- Datos Básicos -->
 <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
 <div class="space-y-2">
 <label class="text-[10px] font-black text-[#c3c4c5] uppercase tracking-widest">Nombre</label>
 <input v-model="form.name" type="text" class="w-full bg-[#0a0f14] border border-[#21262d] rounded-[24px] p-4 font-bold text-sm text-white outline-none focus:ring-2 focus:ring-primary/30 transition-all">
 </div>
 <div class="space-y-2">
 <label class="text-[10px] font-black text-[#c3c4c5] uppercase tracking-widest">Modelo</label>
 <input v-model="form.model" type="text" class="w-full bg-[#0a0f14] border border-[#21262d] rounded-[24px] p-4 font-bold text-sm text-white outline-none focus:ring-2 focus:ring-primary/30 transition-all">
 </div>
 <div class="space-y-2">
 <label class="text-[10px] font-black text-[#c3c4c5] uppercase tracking-widest">Horas de Uso</label>
 <input v-model.number="form.total_hours_run" type="number" min="0" class="w-full bg-[#0a0f14] border border-[#21262d] rounded-[24px] p-4 font-bold text-sm text-white outline-none focus:ring-2 focus:ring-primary/30 transition-all">
 </div>
 <div class="space-y-2">
 <label class="text-[10px] font-black text-[#c3c4c5] uppercase tracking-widest">Potencia (Watts)</label>
 <input v-model.number="form.watts" type="number" min="0" placeholder="350" class="w-full bg-[#0a0f14] border border-[#21262d] rounded-[24px] p-4 font-bold text-sm text-white outline-none focus:ring-2 focus:ring-primary/30 transition-all">
 </div>
 <div class="space-y-2 sm:col-span-2">
 <label class="text-[10px] font-black text-[#c3c4c5] uppercase tracking-widest">Próximo Mantenimiento</label>
 <input v-model="form.next_maintenance" type="date" class="w-full bg-[#0a0f14] border border-[#21262d] rounded-[24px] p-4 font-bold text-sm text-white outline-none focus:ring-2 focus:ring-primary/30 transition-all">
 </div>
 </div>

 <!-- ── SECCIÓN ORCAENGINE v2 ──────────────────────────────────────── -->
 <div class="mb-6 p-5 bg-[#08872b]/5 border border-[#08872b]/20 rounded-[2rem]">
 <!-- Header sección -->
 <div class="flex items-center gap-3 mb-5">
 <div class="w-8 h-8 bg-[#08872b]/20 rounded-[12px] flex items-center justify-center shrink-0">
 <svg class="w-4 h-4 text-[#08872b]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18"/>
 </svg>
 </div>
 <div>
 <p class="text-[10px] font-black text-[#08872b] uppercase tracking-[0.4em]">Perfil OrcaEngine</p>
 <p class="text-[9px] text-[#a4aea6] font-medium mt-0.5">Parámetros para cálculo preciso de peso y tiempo</p>
 </div>
 </div>

 <!-- Nozzle + Walls + Preview -->
 <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
 <div class="space-y-2">
 <label class="text-[10px] font-black text-[#c3c4c5] uppercase tracking-widest">Nozzle (mm)</label>
 <select v-model.number="form.nozzle_mm" class="w-full bg-[#0a0f14] border border-[#21262d] rounded-[20px] p-3 font-bold text-sm text-white outline-none focus:ring-2 focus:ring-primary/30 transition-all">
 <option :value="0.2">0.2 mm — Detalle</option>
 <option :value="0.4">0.4 mm — Estándar</option>
 <option :value="0.6">0.6 mm — Rápido</option>
 <option :value="0.8">0.8 mm — Industrial</option>
 </select>
 </div>
 <div class="space-y-2">
 <label class="text-[10px] font-black text-[#c3c4c5] uppercase tracking-widest">Paredes (Shell)</label>
 <select v-model.number="form.wall_count" class="w-full bg-[#0a0f14] border border-[#21262d] rounded-[20px] p-3 font-bold text-sm text-white outline-none focus:ring-2 focus:ring-primary/30 transition-all">
 <option :value="1">1 pared</option>
 <option :value="2">2 paredes — Std</option>
 <option :value="3">3 paredes</option>
 <option :value="4">4 paredes — Rígido</option>
 </select>
 </div>
 <!-- Shell calculado en tiempo real -->
 <div class="space-y-2">
 <label class="text-[10px] font-black text-[#c3c4c5] uppercase tracking-widest">Shell Calculado</label>
 <div class="w-full h-[46px] bg-[#08872b]/10 border border-[#08872b]/25 rounded-[20px] flex items-center justify-center">
 <span class="text-[#08872b] font-black text-base">
 {{ ((form.nozzle_mm || 0.4) * (form.wall_count || 2)).toFixed(1) }} mm
 </span>
 </div>
 </div>
 </div>

 <!-- Flujo Volumétrico Slider -->
 <div class="space-y-3">
 <div class="flex items-center justify-between">
 <label class="text-[10px] font-black text-[#c3c4c5] uppercase tracking-widest">Flujo Volumétrico Máx.</label>
 <span class="text-[10px] text-[#8dd6ff] font-black tabular-nums">
 {{ (form.max_flow_mm3_hr || 15500).toLocaleString() }} mm³/h
 </span>
 </div>
 <input
 v-model.number="form.max_flow_mm3_hr"
 type="range"
 min="2000"
 max="25000"
 step="500"
 class="w-full h-2 bg-[#21262d] rounded-full appearance-none cursor-pointer accent-[#08872b]"
 />
 <!-- Presets rápidos -->
 <div class="flex flex-wrap gap-2">
 <button
 v-for="preset in flowPresets"
 :key="preset.label"
 :disabled="preset.value === 0"
 :class="[
 'px-3 py-1.5 rounded-[12px] text-[9px] font-black uppercase tracking-wider transition-all',
 preset.value === 0
 ? 'opacity-35 cursor-not-allowed bg-[#21262d] text-[#a4aea6]'
 : (form.max_flow_mm3_hr || 15500) === preset.value
 ? 'bg-[#08872b] text-white shadow-[0_0_12px_rgba(8,135,43,0.4)]'
 : 'bg-[#21262d] text-[#a4aea6] hover:bg-[#08872b]/20 hover:text-[#08872b]'
 ]"
 @click="preset.value > 0 ? (form.max_flow_mm3_hr = preset.value) : null"
 >
 {{ preset.label }}
 <span class="opacity-60 ml-1">{{ preset.value > 0 ? '· ' + preset.value.toLocaleString() : '· SLA' }}</span>
 </button>
 </div>
 </div>
 </div>

 <!-- Notas de Mantenimiento -->
 <div class="space-y-2 mb-8">
 <label class="text-[10px] font-black text-[#c3c4c5] uppercase tracking-widest">Notas de Mantenimiento</label>
 <textarea
 v-model="form.maintenance_notes"
 rows="3"
 class="w-full bg-[#0a0f14] border border-[#21262d] rounded-[24px] p-5 font-medium text-sm text-white outline-none focus:ring-2 focus:ring-primary/30 resize-none transition-all"
 placeholder="Historial de cambios de boquilla, limpieza, calibraciones, etc."
 ></textarea>
 </div>

 <!-- Acciones de Mantenimiento -->
 <div class="grid grid-cols-3 gap-2 mb-6">
 <button class="py-3 bg-amber-500/10 text-amber-400 border border-amber-500/20 rounded-[24px] font-black text-[8px] uppercase tracking-widest hover:scale-105 transition-all flex items-center justify-center gap-1.5" @click="emit('maintenance-complete', form.id || form._id)">
 <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
 Mantenim.
 </button>
 <button class="py-3 bg-blue-500/10 text-blue-400 border border-blue-500/20 rounded-[24px] font-black text-[8px] uppercase tracking-widest hover:scale-105 transition-all flex items-center justify-center gap-1.5" @click="emit('reset-printer', form.id || form._id)">
 <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
 Reset
 </button>
 <button class="py-3 bg-rose-500/10 text-rose-400 border border-rose-500/20 rounded-[24px] font-black text-[8px] uppercase tracking-widest hover:scale-105 hover:bg-rose-500 hover:text-white transition-all flex items-center justify-center gap-1.5" @click="emit('delete-printer', form.id || form._id)">
 <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
 Eliminar
 </button>
 </div>

 <!-- Botones principales -->
 <div class="flex flex-col sm:flex-row gap-3">
 <button class="flex-1 py-4 bg-[#0a0f14] border border-[#21262d] text-[#a4aea6] rounded-[24px] font-black text-xs uppercase tracking-widest hover:border-[#a4aea6]/40 transition-all" @click="emit('update:modelValue', false)">Cerrar</button>
 <button
 :disabled="submitting"
 class="sm:flex-[2] py-4 bg-[#08872b] text-white rounded-[24px] font-black text-xs uppercase tracking-widest hover:-translate-y-0.5 transition-all disabled:opacity-50 flex items-center justify-center gap-2"
 @click="emit('update-printer')"
 >
 <svg v-if="submitting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
 {{ submitting ? 'Guardando...' : 'Guardar Cambios' }}
 </button>
 </div>

 </div>
 </div>
 </div>
</template>
