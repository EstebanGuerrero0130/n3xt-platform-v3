<script setup lang="ts">
const props = defineProps<{
  modelValue: boolean
  orderToShip: any
  trackingGuide: string
  trackingCarrier: string
}>()

const emit = defineEmits<{
  (e: "update:modelValue", val: boolean): void
  (e: "update:trackingGuide", val: string): void
  (e: "update:trackingCarrier", val: string): void
  (e: "confirm-shipping"): void
}>()

const onTrackingGuide = (e: Event) => {
  emit("update:trackingGuide", (e.target as HTMLInputElement).value)
}

const onTrackingCarrier = (e: Event) => {
  emit("update:trackingCarrier", (e.target as HTMLSelectElement).value)
}
</script>

<template>
    <div v-if="modelValue" class="fixed inset-0 bg-gray-950/60 backdrop-blur-md flex items-center justify-center p-4 z-[500]">
      <div class="bg-white dark:bg-gray-900 rounded-[3rem] shadow-2xl max-w-md w-full p-10 animate-in zoom-in-95 duration-200 border border-gray-100 dark:border-white/5">
            <div class="w-16 h-16 bg-emerald-100 dark:bg-emerald-500/10 text-emerald-500 rounded-2xl flex items-center justify-center mb-6 mx-auto">
              <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" /></svg>
            </div>
            <h3 class="text-3xl font-black text-gray-900 dark:text-white text-center uppercase tracking-tighter mb-2">Despachar Orden</h3>
            <div class="space-y-4 mb-8">
                <select 
                    :value="trackingCarrier"
                    @change="onTrackingCarrier"
                    class="w-full bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-2xl px-6 py-4 text-xs font-bold text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-emerald-500 appearance-none cursor-pointer"
                >
                    <option value="" class="bg-white dark:bg-gray-800 text-gray-900 dark:text-white">Seleccionar Transportadora...</option>
                    <option value="Servientrega" class="bg-white dark:bg-gray-800 text-gray-900 dark:text-white">Servientrega</option>
                    <option value="Interrapidisimo" class="bg-white dark:bg-gray-800 text-gray-900 dark:text-white">Interrapidísimo</option>
                    <option value="Envia" class="bg-white dark:bg-gray-800 text-gray-900 dark:text-white">Envía</option>
                    <option value="Coordinadora" class="bg-white dark:bg-gray-800 text-gray-900 dark:text-white">Coordinadora</option>
                    <option value="TCC" class="bg-white dark:bg-gray-800 text-gray-900 dark:text-white">TCC</option>
                    <option value="Recoge en Taller" class="bg-white dark:bg-gray-800 text-gray-900 dark:text-white">Recoge en Taller</option>
                    <option value="Personalizado" class="bg-white dark:bg-gray-800 text-gray-900 dark:text-white">Otro / Personalizado</option>
                </select>

                <input 
                    :value="trackingGuide" 
                    @input="onTrackingGuide" 
                    type="text" 
                    placeholder="Ej: GUIA-123456" 
                    class="w-full bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 text-gray-900 dark:text-white text-lg font-bold rounded-2xl px-6 py-4 outline-none focus:border-emerald-500 text-center tracking-widest uppercase shadow-inner"
                >
            </div>
            
            <div class="flex gap-4">
              <button class="btn-secondary flex-1 py-4" @click="emit('update:modelValue', false)">Cancelar</button>
              <button class="btn-primary flex-1 py-4 !bg-emerald-500 hover:!bg-emerald-600 shadow-emerald-500/20" @click="emit('confirm-shipping')">Confirmar</button>
            </div>
          </div>
        </div>

</template>
