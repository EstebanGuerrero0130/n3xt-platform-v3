<script setup lang="ts">
import { reactive, watch } from "vue"

const props = defineProps<{
  modelValue: boolean
  editingMaterial: any
  submitting: boolean
}>()

const emit = defineEmits<{
  (e: "update:modelValue", val: boolean): void
  (e: "update-material"): void
  (e: "update:editingMaterial", val: any): void
}>()

// Local copy for editing (props are readonly in Vue 3)
const form = reactive({ ...(props.editingMaterial || {}) })

watch(() => props.editingMaterial, (val) => {
  if (val) Object.assign(form, val)
}, { deep: true })

watch(form, (val) => {
  emit("update:editingMaterial", { ...val })
}, { deep: true })
</script>

<template>
    <div v-if="modelValue" class="fixed inset-0 bg-gray-950/60 backdrop-blur-md z-[500] flex items-center justify-center p-4 overflow-y-auto">
      <div class="bg-white dark:bg-gray-900 rounded-[2.5rem] w-full max-w-lg shadow-2xl overflow-hidden animate-in zoom-in duration-300 my-auto">
        <div class="p-10 max-h-[90vh] overflow-y-auto no-scrollbar">
          <div class="flex justify-between items-center mb-8">
            <h3 class="text-2xl font-black text-gray-900 dark:text-white uppercase tracking-tighter">Editar Ítem</h3>
            <button class="text-gray-400 hover:text-gray-600 transition-colors text-2xl" @click="emit('update:modelValue', false)">
              ✕
            </button>
          </div>
          
          <div class="space-y-6">
            <div class="space-y-2">
              <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Nombre del Material/Ítem</label>
              <input v-model="form.name" type="text" class="w-full bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/10 rounded-2xl px-6 py-4 text-sm font-bold text-gray-900 dark:text-white focus:ring-2 focus:ring-primary outline-none transition-all">
            </div>
            
            <div class="grid grid-cols-2 gap-6">
              <div class="space-y-2">
                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Costo Unitario ($)</label>
                <input v-model.number="form.cost_per_kg" type="number" class="w-full bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/10 rounded-2xl px-6 py-4 text-sm font-bold text-gray-900 dark:text-white focus:ring-2 focus:ring-primary outline-none transition-all">
              </div>
              <div class="space-y-2 col-span-2 sm:col-span-1">
                <label class="text-[10px] font-black text-rose-400 uppercase tracking-widest">Umbral Alerta ({{ form.unit }})</label>
                <input v-model.number="form.low_stock_threshold" type="number" class="w-full bg-rose-50 dark:bg-rose-500/10 border border-rose-100 dark:border-rose-500/20 rounded-2xl px-6 py-4 text-sm font-bold text-gray-900 dark:text-white focus:ring-2 focus:ring-rose-500 outline-none transition-all">
              </div>
            </div>
          </div>
          
          <div class="mt-10 flex flex-col sm:flex-row gap-4">
            <button class="btn-secondary flex-1 px-8 py-4" @click="emit('update:modelValue', false)">
                Salir sin Guardar
            </button>
            <button :disabled="submitting" class="btn-primary flex-2 px-12 py-4" @click="emit('update-material')">
              {{ submitting ? 'Guardando...' : 'Guardar Cambios' }}
            </button>
          </div>
        </div>
      </div>
    </div>

</template>
