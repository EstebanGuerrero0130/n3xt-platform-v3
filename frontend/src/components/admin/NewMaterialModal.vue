<script setup lang="ts">
import { reactive, watch } from "vue"

const props = defineProps<{
 modelValue: boolean
 newMaterial: any
 submitting: boolean
}>()

const emit = defineEmits<{
 (e: "update:modelValue", val: boolean): void
 (e: "add-material"): void
 (e: "update:newMaterial", val: any): void
}>()

// Local copy for editing (props are readonly in Vue 3)
const form = reactive({ ...(props.newMaterial || {}) })

watch(() => props.newMaterial, (val) => {
 if (val) Object.assign(form, val)
}, { deep: true })

watch(form, (val) => {
 emit("update:newMaterial", { ...val })
}, { deep: true })
</script>

<template>
 <div v-if="modelValue" class="fixed inset-0 bg-[#090d0a]/60 backdrop-blur-md z-[500] flex items-center justify-center p-4 overflow-y-auto">
 <div class="bg-[#151a22] dark:bg-[#151a22] rounded-[3rem] w-full max-w-2xl overflow-hidden animate-in zoom-in duration-300 my-auto">
 <div class="p-6 md:p-10 max-h-[90vh] overflow-y-auto no-scrollbar">
 <div class="flex justify-between items-start gap-4 mb-8 md:mb-10">
 <div class="min-w-0">
 <h3 class="text-2xl md:text-3xl font-black text-[#ffffff] dark:text-white uppercase tracking-tighter truncate">Registrar Nuevo Ítem</h3>
 <p class="text-[9px] md:text-xs text-[#c3c4c5] font-bold uppercase tracking-widest mt-1">Configuración técnica de inventario</p>
 </div>
 <button class="w-10 h-10 md:w-12 md:h-12 shrink-0 bg-[#151a22] dark:bg-[#151a22]/5 text-[#c3c4c5] rounded-[24px] flex items-center justify-center hover:bg-[#151a22] dark:hover:bg-[#151a22]/10 transition-all text-lg md:text-xl" @click="emit('update:modelValue', false)" aria-label="Botón interactivo">
 ✕
 </button>
 </div>
 
 <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 md:gap-8 mb-8 md:mb-10">
 <!-- ID Automático (ReadOnly) -->
 <div class="space-y-2">
 <label class="text-[10px] font-black text-[#8dd6ff] uppercase tracking-widest flex items-center gap-2">
 <span class="w-1.5 h-1.5 rounded-[60px] bg-[#08872b] animate-pulse"></span>
 ID Técnico (Auto)
 </label>
 <input v-model="form.id" type="text" readonly class="w-full bg-[#151a22] dark:bg-[#151a22]/5 border-none rounded-[24px] p-5 font-black text-sm text-[#a4aea6] outline-none opacity-80 cursor-not-allowed">
 </div>

 <!-- Nombre Comercial -->
 <div class="space-y-2">
 <label class="text-[10px] font-black text-[#c3c4c5] uppercase tracking-widest">Nombre Comercial / Marca</label>
 <input v-model="form.name" type="text" placeholder="Ej: PLA Pro Negro Mate" class="w-full bg-[#151a22] dark:bg-[#151a22]/5 border-none rounded-[24px] p-5 font-bold text-sm text-[#ffffff] dark:text-white outline-none focus:ring-2 focus:ring-primary/20 transition-all">
 </div>

 <!-- Categoría -->
 <div class="space-y-2">
 <label class="text-[10px] font-black text-[#c3c4c5] uppercase tracking-widest">Categoría Tecnológica</label>
 <select v-model="form.category" class="w-full bg-[#151a22] dark:bg-[#151a22]/5 border-none rounded-[24px] p-5 font-bold text-sm text-[#ffffff] dark:text-white outline-none focus:ring-2 focus:ring-primary/20 transition-all">
 <option value="FDM">Filamento (FDM)</option>
 <option value="SLA">Resina (SLA)</option>
 <option value="UTIL">Otros / Adicionales</option>
 </select>
 </div>

 <!-- Tipo de Ítem -->
 <div class="space-y-2">
 <label class="text-[10px] font-black text-[#c3c4c5] uppercase tracking-widest">Tipo de Registro</label>
 <select v-model="form.type" class="w-full bg-[#151a22] dark:bg-[#151a22]/5 border-none rounded-[24px] p-5 font-bold text-sm text-[#ffffff] dark:text-white outline-none focus:ring-2 focus:ring-primary/20 transition-all">
 <option value="material">Materiales (Filamento/Resina)</option>
 <option value="utility">Insumos (Argollas/Cajas/Bolsas)</option>
 <option value="service">Servicios (Mano de Obra/Lavado)</option>
 </select>
 </div>

 <!-- Asistente de Paquete (Opcional) -->
 <div class="col-span-1 sm:col-span-2 p-5 md:p-6 bg-[#08872b]/5 rounded-[2rem] border border-primary/10 space-y-4">
 <div class="flex justify-between items-start">
 <div>
 <p class="text-[9px] font-black text-[#8dd6ff] uppercase tracking-[0.2em]">Asistente de Costo Pro</p>
 <p class="text-[10px] text-[#a4aea6] font-bold mt-1">Calcula el precio unitario exacto por lotes.</p>
 </div>
 <div v-if="form.package_price > 0 && form.package_qty > 0" class="px-4 py-2 bg-emerald-50 rounded-[6px] border border-emerald-100 animate-in fade-in zoom-in duration-300">
 <p class="text-[7px] font-black text-emerald-600 uppercase mb-1">Auditoría de Cálculo:</p>
 <p class="text-xs font-black text-emerald-700">
 $ {{ (form.package_price / (form.package_qty * (form.package_units || 1))).toLocaleString(undefined, {minimumFractionDigits: 2}) }} / {{ form.unit }}
 <span v-if="form.unit === 'g' || form.unit === 'ml'" class="text-[8px] opacity-70 ml-1">
 ($ {{ ((form.package_price / (form.package_qty * (form.package_units || 1))) * 1000).toLocaleString() }} x Kg/L)
 </span>
 </p>
 </div>
 </div>
 
 <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 md:gap-4">
 <div class="space-y-1">
 <label class="text-[8px] font-black text-[#c3c4c5] uppercase">Nº Paquetes</label>
 <input v-model.number="form.package_units" type="number" placeholder="Ej: 2" class="w-full bg-[#151a22] dark:bg-[#151a22]/5 border-none rounded-[6px] p-3 font-bold text-xs outline-none focus:ring-2 focus:ring-primary/20 transition-all">
 </div>
 <div class="space-y-1">
 <label class="text-[8px] font-black text-[#c3c4c5] uppercase">{{ form.unit === 'g' ? 'Gramos x Paq' : form.unit === 'ml' ? 'ML x Paq' : 'Piezas x Paq' }}</label>
 <input v-model.number="form.package_qty" type="number" placeholder="Ej: 500" class="w-full bg-[#151a22] dark:bg-[#151a22]/5 border-none rounded-[6px] p-3 font-bold text-xs outline-none focus:ring-2 focus:ring-primary/20 transition-all">
 </div>
 <div class="space-y-1">
 <label class="text-[8px] font-black text-[#c3c4c5] uppercase">Precio Total ($)</label>
 <input v-model.number="form.package_price" type="number" placeholder="Ej: 120000" class="w-full bg-[#151a22] dark:bg-[#151a22]/5 border-none rounded-[6px] p-3 font-bold text-xs outline-none focus:ring-2 focus:ring-primary/20 transition-all">
 </div>
 </div>
 
 <div v-if="form.package_qty > 0" class="pt-2 border-t border-primary/5 flex justify-between items-center text-[8px] font-black text-[#c3c4c5] uppercase tracking-widest">
 <span>Cantidad total a ingresar:</span>
 <span class="text-[#8dd6ff]">{{ (form.package_qty * (form.package_units || 1)).toLocaleString() }} {{ form.unit }}</span>
 </div>
 </div>

 <!-- Costo por Unidad -->
 <div class="space-y-2">
 <label class="text-[10px] font-black text-[#c3c4c5] uppercase tracking-widest">Costo por Kilo/Litro/Unid.</label>
 <div class="relative">
 <span class="absolute left-5 top-1/2 -translate-y-1/2 text-[#c3c4c5] font-bold">$</span>
 <input v-model.number="form.cost_per_kg" type="number" class="w-full bg-[#151a22] dark:bg-[#151a22]/5 border-none rounded-[24px] p-5 pl-10 font-black text-sm text-[#ffffff] dark:text-white outline-none focus:ring-2 focus:ring-primary/20 transition-all">
 </div>
 </div>

 <!-- Unidad de Medida -->
 <div class="space-y-2">
 <label class="text-[10px] font-black text-[#c3c4c5] uppercase tracking-widest">Unidad de Medida</label>
 <select v-model="form.unit" class="w-full bg-[#151a22] dark:bg-[#151a22]/5 border-none rounded-[24px] p-5 font-bold text-sm text-[#ffffff] dark:text-white outline-none focus:ring-2 focus:ring-primary/20 transition-all">
 <option value="g">Gramos (g)</option>
 <option value="ml">Mililitros (ml)</option>
 <option value="unid">Unidad (ud)</option>
 </select>
 </div>

 <!-- Stock Inicial -->
 <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
 <div class="space-y-2">
 <label class="text-[10px] font-black text-[#c3c4c5] uppercase tracking-widest">Stock de Apertura</label>
 <input v-model.number="form.initial_stock" type="number" class="w-full bg-[#151a22] dark:bg-[#151a22]/5 border-none rounded-[24px] p-5 font-black text-sm text-[#ffffff] dark:text-white outline-none focus:ring-2 focus:ring-primary/20 transition-all">
 </div>
 <div class="space-y-2">
 <label class="text-[10px] font-black text-rose-400 uppercase tracking-widest">Stock de Alerta (Rojo)</label>
 <input v-model.number="form.low_stock_threshold" type="number" class="w-full bg-rose-50 dark:bg-rose-500/10 border-none rounded-[24px] p-5 font-black text-sm text-rose-900 dark:text-rose-100 outline-none focus:ring-2 focus:ring-rose-500/20 transition-all">
 </div>
 </div>

 <!-- Selector de Color -->
 <div class="space-y-2">
 <label class="text-[10px] font-black text-[#c3c4c5] uppercase tracking-widest">Identificador Visual</label>
 <div class="flex items-center gap-4">
 <input v-model="form.color" type="color" class="w-16 h-14 bg-[#151a22] border-none rounded-[24px] p-1 cursor-pointer overflow-hidden transition-all hover:scale-105">
 <span class="text-[10px] font-bold text-[#c3c4c5] uppercase italic">{{ form.color }}</span>
 </div>
 </div>
 </div>
 
 <div class="flex flex-col sm:flex-row gap-4 pt-4">
 <button class="btn-secondary flex-1 py-5" @click="emit('update:modelValue', false)">
 Cancelar y Salir
 </button>
 <button 
 :disabled="submitting" 
 class="btn-primary flex-1 py-5"
 @click="emit('add-material')"
 >
 {{ submitting ? 'Guardando...' : 'Crear Ítem en Taller' }}
 </button>
 </div>
 </div>
 </div>
 </div>

</template>
