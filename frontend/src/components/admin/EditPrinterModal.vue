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

// Local copy for editing (props are readonly in Vue 3)
const form = reactive({ ...(props.editingPrinter || {}) })

watch(() => props.editingPrinter, (val) => {
  if (val) Object.assign(form, val)
}, { deep: true })

watch(form, (val) => {
  emit("update:editingPrinter", { ...val })
}, { deep: true })
</script>

<template>
    <div v-if="modelValue" class="fixed inset-0 bg-gray-950/60 backdrop-blur-md z-[500] flex items-center justify-center p-4">
      <div class="bg-white dark:bg-gray-900 rounded-[3rem] w-full max-w-xl shadow-2xl overflow-hidden animate-in zoom-in duration-300">
        <div class="p-10">
          <div class="flex justify-between items-center mb-8">
            <h3 class="text-2xl font-black text-gray-900 dark:text-white uppercase tracking-tighter">Detalles de Máquina</h3>
            <button class="text-gray-400 hover:text-gray-600 transition-colors text-2xl" @click="emit('update:modelValue', false)">
              ✕
            </button>
          </div>
          
          <div class="grid grid-cols-2 gap-6 mb-8">
            <div class="space-y-2">
                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Nombre</label>
                <input v-model="form.name" type="text" class="w-full bg-gray-50 dark:bg-white/5 border-none rounded-2xl p-4 font-bold text-sm text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary/20">
            </div>
            <div class="space-y-2">
                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Modelo</label>
                <input v-model="form.model" type="text" class="w-full bg-gray-50 dark:bg-white/5 border-none rounded-2xl p-4 font-bold text-sm text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary/20">
            </div>
            <div class="space-y-2">
                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Horas de Uso</label>
                <input v-model.number="form.total_hours_run" type="number" class="w-full bg-gray-50 border-none rounded-2xl p-4 font-bold text-sm outline-none focus:ring-2 focus:ring-primary/20">
            </div>
            <div class="space-y-2">
                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Próximo Mantenimiento</label>
                <input v-model="form.next_maintenance" type="date" class="w-full bg-gray-50 border-none rounded-2xl p-4 font-bold text-sm outline-none focus:ring-2 focus:ring-primary/20">
            </div>
          </div>

          <div class="space-y-2 mb-10">
            <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Notas de Mantenimiento</label>
            <textarea v-model="form.maintenance_notes" rows="4" class="w-full bg-gray-50 border-none rounded-2xl p-6 font-medium text-sm outline-none focus:ring-2 focus:ring-primary/20" placeholder="Historial de cambios de boquilla, limpieza, etc."></textarea>
          </div>
          
          <!-- Acciones de Mantenimiento -->
          <div class="grid grid-cols-3 gap-3 mb-8">
            <button class="py-3 bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-500/20 rounded-2xl font-black text-[8px] uppercase tracking-widest hover:scale-105 transition-all flex items-center justify-center gap-2" @click="emit('maintenance-complete', form.id || form._id)">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
              Mantenimiento
            </button>
            <button class="py-3 bg-blue-50 dark:bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-500/20 rounded-2xl font-black text-[8px] uppercase tracking-widest hover:scale-105 transition-all flex items-center justify-center gap-2" @click="emit('reset-printer', form.id || form._id)">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
              Reset
            </button>
            <button class="py-3 bg-rose-50 dark:bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-500/20 rounded-2xl font-black text-[8px] uppercase tracking-widest hover:scale-105 hover:bg-rose-500 hover:text-white transition-all flex items-center justify-center gap-2" @click="emit('delete-printer', form.id || form._id)">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
              Eliminar
            </button>
          </div>
          
          <div class="flex gap-4">
            <button class="flex-1 py-4 bg-gray-100 dark:bg-white/5 text-gray-500 dark:text-gray-400 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-gray-200 dark:hover:bg-white/10 transition-all" @click="emit('update:modelValue', false)">Cerrar</button>
            <button class="flex-2 py-4 bg-primary text-white rounded-2xl font-black text-xs uppercase tracking-widest shadow-xl shadow-primary/20 hover:-translate-y-0.5 transition-all" @click="emit('update-printer')">Guardar Cambios</button>
          </div>
        </div>
      </div>
    </div>

</template>
