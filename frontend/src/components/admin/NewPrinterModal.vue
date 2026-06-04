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
    <div v-if="modelValue" class="fixed inset-0 bg-gray-950/60 backdrop-blur-md z-[500] flex items-center justify-center p-4">
      <div class="bg-white dark:bg-gray-900 rounded-[3rem] w-full max-w-xl shadow-2xl overflow-hidden animate-in zoom-in duration-300">
        <div class="p-10">
          <div class="flex justify-between items-center mb-8">
            <h3 class="text-2xl font-black text-gray-900 dark:text-white uppercase tracking-tighter">Nueva Impresora</h3>
            <button class="text-gray-400 hover:text-gray-600 transition-colors text-2xl" @click="emit('update:modelValue', false)">
              ✕
            </button>
          </div>
          <div class="space-y-4">
            <input v-model="form.name" type="text" placeholder="Nombre Identificador" class="w-full bg-gray-50 dark:bg-white/5 border-none rounded-2xl px-6 py-4 text-sm font-bold text-gray-900 dark:text-white focus:ring-2 focus:ring-primary outline-none">
            <input v-model="form.model" type="text" placeholder="Modelo (ej: Ender 3, Mars 4)" class="w-full bg-gray-50 dark:bg-white/5 border-none rounded-2xl px-6 py-4 text-sm font-bold text-gray-900 dark:text-white focus:ring-2 focus:ring-primary outline-none">
            <select v-model="form.technology" class="w-full bg-gray-50 dark:bg-white/5 border-none rounded-2xl px-6 py-4 text-sm font-bold text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary">
              <option value="FDM">Tecnología FDM</option>
              <option value="SLA">Tecnología SLA</option>
            </select>
          </div>
          <div class="mt-10 flex flex-col gap-3">
            <button 
                :disabled="submitting" 
                class="w-full py-4 bg-primary text-white rounded-2xl font-black text-xs uppercase tracking-widest shadow-xl shadow-primary/20 hover:-translate-y-1 transition-all disabled:opacity-50"
                @click="emit('add-printer')"
            >
                {{ submitting ? 'Registrando...' : 'Registrar Máquina' }}
            </button>
            <button class="w-full py-4 bg-gray-100 dark:bg-white/5 text-gray-500 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-gray-200 transition-all" @click="emit('update:modelValue', false)">Cerrar</button>
          </div>
        </div>
      </div>
    </div>

</template>
