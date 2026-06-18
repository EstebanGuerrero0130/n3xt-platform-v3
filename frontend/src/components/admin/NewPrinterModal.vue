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

// Local copy for editing (props are readonly in Vue 3)
const form = reactive({ ...(props.newPrinter || {}) })

watch(() => props.newPrinter, (val) => {
 if (val) Object.assign(form, val)
}, { deep: true })

watch(form, (val) => {
 emit("update:newPrinter", { ...val })
}, { deep: true })
</script>

<template>
 <div v-if="modelValue" class="fixed inset-0 bg-[#090d0a]/60 backdrop-blur-md z-[500] flex items-center justify-center p-4">
 <div class="bg-[#151a22] dark:bg-[#151a22] rounded-[3rem] w-full max-w-xl overflow-hidden animate-in zoom-in duration-300">
 <div class="p-6 md:p-10 max-h-[90vh] overflow-y-auto no-scrollbar">
 <div class="flex justify-between items-center mb-6 md:mb-8">
 <h3 class="text-xl md:text-2xl font-black text-[#ffffff] dark:text-white uppercase tracking-tighter">Nueva Impresora</h3>
 <button class="text-[#c3c4c5] hover:text-[#a4aea6] transition-colors text-2xl" aria-label="Botón interactivo" @click="emit('update:modelValue', false)">
 ✕
 </button>
 </div>
 <div class="space-y-4">
 <input v-model="form.name" type="text" placeholder="Nombre Identificador" class="w-full bg-[#151a22] dark:bg-[#151a22]/5 border-none rounded-[24px] px-6 py-4 text-sm font-bold text-[#ffffff] dark:text-white focus:ring-2 focus:ring-primary outline-none">
 <input v-model="form.model" type="text" placeholder="Modelo (ej: Ender 3, Mars 4)" class="w-full bg-[#151a22] dark:bg-[#151a22]/5 border-none rounded-[24px] px-6 py-4 text-sm font-bold text-[#ffffff] dark:text-white focus:ring-2 focus:ring-primary outline-none">
 <select v-model="form.technology" class="w-full bg-[#151a22] dark:bg-[#151a22]/5 border-none rounded-[24px] px-6 py-4 text-sm font-bold text-[#ffffff] dark:text-white outline-none focus:ring-2 focus:ring-primary">
 <option value="FDM">Tecnología FDM</option>
 <option value="SLA">Tecnología SLA</option>
 </select>
 </div>
 <div class="mt-10 flex flex-col gap-3">
 <button 
 :disabled="submitting" 
 class="w-full py-4 bg-[#08872b] text-white rounded-[24px] font-black text-xs uppercase tracking-widest -primary/20 hover:-translate-y-1 transition-all disabled:opacity-50"
 @click="emit('add-printer')"
 >
 {{ submitting ? 'Registrando...' : 'Registrar Máquina' }}
 </button>
 <button class="w-full py-4 bg-[#151a22] dark:bg-[#151a22]/5 text-[#a4aea6] rounded-[24px] font-black text-xs uppercase tracking-widest hover:bg-gray-200 transition-all" @click="emit('update:modelValue', false)">Cerrar</button>
 </div>
 </div>
 </div>
 </div>

</template>
