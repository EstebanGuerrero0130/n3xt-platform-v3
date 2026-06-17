<script setup lang="ts">
defineProps<{
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
 <div v-if="modelValue" class="fixed inset-0 bg-[#090d0a]/60 backdrop-blur-md flex items-center justify-center p-4 z-[500]">
 <div class="bg-[#151a22] dark:bg-[#151a22] rounded-[2rem] md:rounded-[3rem] max-w-md w-full p-6 md:p-10 animate-in zoom-in-95 duration-200 border border-[#21262d] dark:border-[#21262d]">
 <div class="w-12 md:w-16 h-12 md:h-16 bg-emerald-100 dark:bg-emerald-500/10 text-emerald-500 rounded-[24px] flex items-center justify-center mb-4 md:mb-6 mx-auto">
 <svg class="w-6 md:w-8 h-6 md:h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" /></svg>
 </div>
 <h3 class="text-2xl md:text-3xl font-black text-[#ffffff] dark:text-white text-center uppercase tracking-tighter mb-2">Despachar Orden</h3>
 <div class="space-y-3 md:space-y-4 mb-6 md:mb-8">
 <select 
 :value="trackingCarrier"
 class="w-full bg-[#151a22] dark:bg-[#151a22]/5 border border-[#21262d] dark:border-[#21262d] rounded-[6px] md:rounded-[24px] px-4 md:px-6 py-3 md:py-4 text-xs font-bold text-[#ffffff] dark:text-white outline-none focus:ring-2 focus:ring-emerald-500 appearance-none cursor-pointer"
 @change="onTrackingCarrier"
 >
 <option value="" class="bg-[#151a22] dark:bg-[#283041] text-[#ffffff] dark:text-white">Seleccionar Transportadora...</option>
 <option value="Servientrega" class="bg-[#151a22] dark:bg-[#283041] text-[#ffffff] dark:text-white">Servientrega</option>
 <option value="Interrapidisimo" class="bg-[#151a22] dark:bg-[#283041] text-[#ffffff] dark:text-white">Interrapidísimo</option>
 <option value="Envia" class="bg-[#151a22] dark:bg-[#283041] text-[#ffffff] dark:text-white">Envía</option>
 <option value="Coordinadora" class="bg-[#151a22] dark:bg-[#283041] text-[#ffffff] dark:text-white">Coordinadora</option>
 <option value="TCC" class="bg-[#151a22] dark:bg-[#283041] text-[#ffffff] dark:text-white">TCC</option>
 <option value="Recoge en Taller" class="bg-[#151a22] dark:bg-[#283041] text-[#ffffff] dark:text-white">Recoge en Taller</option>
 <option value="Personalizado" class="bg-[#151a22] dark:bg-[#283041] text-[#ffffff] dark:text-white">Otro / Personalizado</option>
 </select>

 <input 
 :value="trackingGuide" 
 type="text" 
 placeholder="Ej: GUIA-123456" 
 class="w-full bg-[#151a22] dark:bg-[#151a22]/5 border border-[#21262d] dark:border-[#21262d] text-[#ffffff] dark:text-white text-base md:text-lg font-bold rounded-[6px] md:rounded-[24px] px-4 md:px-6 py-3 md:py-4 outline-none focus:border-emerald-500 text-center tracking-widest uppercase " 
 @input="onTrackingGuide"
 >
 </div>
 
 <div class="flex flex-col sm:flex-row gap-3 md:gap-4">
 <button class="btn-secondary flex-1 py-3 md:py-4" @click="emit('update:modelValue', false)">Cancelar</button>
 <button class="btn-primary flex-1 py-3 md:py-4 !bg-emerald-500 hover:!bg-emerald-600 -emerald-500/20" @click="emit('confirm-shipping')">Confirmar</button>
 </div>
 </div>
 </div>

</template>
