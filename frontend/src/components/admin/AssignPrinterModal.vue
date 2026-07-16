<script setup lang="ts">
import { ref, watch, computed } from "vue"

const props = defineProps<{
  modelValue: boolean
  order: any
  printers: any[]
}>()

const emit = defineEmits<{
  (e: "update:modelValue", val: boolean): void
  (e: "assign", data: { orderId: number, printerId: number, status: string }): void
}>()

const selectedPrinter = ref<number | null>(null)

watch(() => props.modelValue, (val) => {
  if (val) {
    selectedPrinter.value = null
  }
})

const availablePrinters = computed(() => {
  return props.printers.filter(p => p.status === 'idle')
})

const confirmAssignment = () => {
  if (!selectedPrinter.value || !props.order) return
  emit("assign", {
    orderId: props.order.id,
    printerId: selectedPrinter.value,
    status: 'printing'
  })
}
</script>

<template>
  <div v-if="modelValue" class="fixed inset-0 bg-[#090d0a]/60 backdrop-blur-md z-[500] flex items-center justify-center p-4">
    <div class="bg-[#151a22] rounded-[2.5rem] w-full max-w-md overflow-hidden animate-in zoom-in duration-300">
      <div class="p-8">
        <div class="flex justify-between items-center mb-6">
          <h3 class="text-xl font-black text-white uppercase tracking-tighter">Asignar Impresora</h3>
          <button class="text-[#c3c4c5] hover:text-[#a4aea6] transition-colors text-2xl" @click="emit('update:modelValue', false)">
            ✕
          </button>
        </div>

        <div class="mb-6 bg-primary/10 p-4 rounded-xl border border-primary/20">
          <p class="text-[10px] font-bold text-primary uppercase tracking-widest mb-1">Orden seleccionada</p>
          <p class="text-white font-bold">{{ order?.job_name || 'Proyecto 3D' }}</p>
        </div>

        <div class="space-y-4">
          <label class="block text-[10px] font-black text-[#c3c4c5] uppercase tracking-widest">Seleccionar Máquina Disponible</label>
          <div v-if="availablePrinters.length === 0" class="text-rose-400 text-sm font-bold p-4 bg-rose-500/10 rounded-xl border border-rose-500/20">
            No hay impresoras libres en este momento.
          </div>
          <select v-else v-model="selectedPrinter" class="w-full bg-[#090d0a] border border-[#21262d] rounded-[16px] px-4 py-4 text-sm font-bold text-white focus:ring-2 focus:ring-primary outline-none transition-all">
            <option :value="null">-- Seleccionar --</option>
            <option v-for="p in availablePrinters" :key="p.id" :value="p.id">
              {{ p.name }} ({{ p.model }})
            </option>
          </select>
        </div>

        <div class="mt-8 flex gap-3">
          <button class="flex-1 py-4 bg-[#21262d] text-white rounded-[16px] font-bold text-sm hover:bg-[#2a3038] transition-colors" @click="emit('update:modelValue', false)">
            Cancelar
          </button>
          <button 
            :disabled="!selectedPrinter"
            class="flex-1 py-4 bg-primary text-[#090d0a] rounded-[16px] font-black uppercase text-sm hover:bg-emerald-400 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            @click="confirmAssignment"
          >
            Asignar y Empezar
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
