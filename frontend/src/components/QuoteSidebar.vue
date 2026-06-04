<script setup lang="ts">
import { ref, computed, watch } from 'vue'

const props = defineProps({
  models: { type: Array, default: () => [] },
  activeModelIdx: { type: Number, default: 0 },
  materials: { type: Array, default: () => [] },
  utilities: { type: Array, default: () => [] },
  selectedTechnology: { type: String, default: 'FDM' },
  selectedMaterial: { type: String, default: '' },
  qty: { type: Number, default: 1 },
  selectedExtras: { type: Array, default: () => [] },
  activeCoupon: { type: Object, default: null },
  couponCode: { type: String, default: '' },
  breakdown: { type: Object, default: () => ({ weight: 0, duration: 0, matCost: 0, infraCost: 0, utilityCost: 0, marginCost: 0, discount: 0, subtotal: 0, iva: 0, total: 0 }) },
  isSlicing: { type: Boolean, default: false },
  previousTotal: { type: Number, default: 0 }
})

const emit = defineEmits([
  'update:selectedTechnology',
  'update:selectedMaterial',
  'update:qty',
  'update:couponCode',
  'toggleExtra',
  'applyCoupon',
  'calculate',
  'requestQuote',
  'update:models'
])

const localQty = ref(props.qty)
const localCoupon = ref(props.couponCode)

watch(() => props.qty, (v) => { localQty.value = v })
watch(localQty, (v) => emit('update:qty', Math.max(1, Number(v) || 1)))
watch(localCoupon, (v) => emit('update:couponCode', v))

const filteredMaterials = computed(() =>
  props.materials.filter(m => m.category === props.selectedTechnology)
)

const formatTime = (h) => {
    if (!h || h < 0) return '0m';
    const hours = Math.floor(h);
    const minutes = Math.round((h - hours) * 60);
    if (hours === 0) return `${minutes}m`;
    return `${hours}h ${minutes}m`;
}

const hasSlicingOrSLA = computed(() => 
  props.models.some(m => m.hasSlicing) || props.selectedTechnology === 'SLA'
)
</script>

<template>
  <div class="p-8 lg:p-10 space-y-8">
    <!-- HEADER -->
    <div class="border-b border-gray-100 dark:border-white/5 pb-6">
      <p class="text-[9px] font-black text-primary uppercase tracking-[0.5em] mb-1">Motor OrcaEngine</p>
      <p class="text-[8px] font-black text-gray-400 uppercase tracking-[0.2em]">Configuracion de Fabricacion</p>
    </div>

    <!-- TECNOLOGIA -->
    <div>
      <label class="text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-3 block">Tecnologia</label>
      <div class="flex bg-gray-100 dark:bg-white/5 p-1.5 rounded-2xl">
        <button
v-for="t in ['FDM','SLA']" :key="t"
          :class="selectedTechnology === t ? 'bg-white dark:bg-primary text-gray-900 dark:text-white shadow-lg' : 'text-gray-400 hover:text-gray-600'"
          class="flex-1 py-3.5 text-[10px] font-black uppercase tracking-widest rounded-xl transition-all"
          @click="$emit('update:selectedTechnology', t)">{{ t }}</button>
      </div>
    </div>

    <!-- MATERIAL -->
    <div>
      <label class="text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-3 block">Material</label>
      <div class="grid grid-cols-2 gap-3">
        <button
v-for="mat in filteredMaterials" :key="mat.id"
          :class="[selectedMaterial === mat.id ? 'bg-primary/15 border-primary/40 ring-2 ring-primary/20 scale-[1.02]' : 'bg-gray-50 dark:bg-white/5 border-gray-100 dark:border-white/10 hover:border-primary/30 hover:bg-primary/5']"
          class="relative text-left p-5 rounded-2xl border-2 transition-all duration-300 group"
          @click="$emit('update:selectedMaterial', mat.id)"
        >
          <div v-if="selectedMaterial === mat.id" class="absolute -top-2 -right-2 w-6 h-6 bg-primary rounded-full flex items-center justify-center shadow-lg">
            <svg class="w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
          </div>
          <div class="w-8 h-8 rounded-xl bg-primary/10 flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
            <svg class="w-4 h-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.628.209a2 2 0 01-1.564 0l-.628-.209a6 6 0 00-3.86-.517L3.081 14.88a2 2 0 00-1.022.547l-.21.21a2 2 0 00.707 3.414l9.9 1.98a2 2 0 00.707 0l9.9-1.98a2 2 0 00.707-3.414l-.21-.21z"/></svg>
          </div>
          <p class="text-[10px] font-black text-gray-900 dark:text-white uppercase tracking-wider">{{ mat.name }}</p>
          <p class="text-[8px] font-bold text-gray-400 mt-1">${{ mat.cost_per_kg }}/kg</p>
          <p v-if="mat.density" class="text-[7px] text-gray-400/60 mt-0.5">{{ mat.density }}g/cm³</p>
        </button>
      </div>
    </div>

    <!-- PARAMETROS FDM -->
    <div v-if="selectedTechnology === 'FDM'" class="grid grid-cols-2 gap-4">
      <div>
        <label class="text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-2 block">Relleno %</label>
        <input type="number" :value="models[activeModelIdx]?.infill || 15" min="5" max="100" step="5" class="w-full bg-gray-100 dark:bg-white/5 border-none rounded-2xl px-6 py-4 text-sm font-black text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary/20" @input="$emit('update:models', models.map((m, i) => i === activeModelIdx ? { ...m, infill: Number($event.target.value) } : m))">
      </div>
      <div>
        <label class="text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-2 block">Capa (mm)</label>
        <select :value="models[activeModelIdx]?.layerHeight || 0.2" class="w-full bg-gray-100 dark:bg-[#0f172a] border border-transparent dark:border-white/5 rounded-2xl px-6 py-4 text-sm font-black text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary/20 appearance-none cursor-pointer transition-all" @change="$emit('update:models', models.map((m, i) => i === activeModelIdx ? { ...m, layerHeight: Number($event.target.value) } : m))">
          <option :value="0.12">0.12 Ultra</option>
          <option :value="0.16">0.16 Alta</option>
          <option :value="0.2">0.20 Estandar</option>
          <option :value="0.28">0.28 Rapida</option>
        </select>
      </div>
    </div>

    <!-- CANTIDAD -->
    <div>
      <label class="text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-3 block">Cantidad</label>
      <div class="flex items-center bg-gray-100 dark:bg-white/5 rounded-2xl overflow-hidden">
        <button class="px-6 py-4 text-gray-400 hover:text-primary font-black text-lg transition-colors" @click="localQty = Math.max(1, localQty - 1)">-</button>
        <input v-model.number="localQty" type="number" min="1" class="flex-1 bg-transparent text-center text-lg font-black text-gray-900 dark:text-white outline-none border-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
        <button class="px-6 py-4 text-gray-400 hover:text-primary font-black text-lg transition-colors" @click="localQty++">+</button>
      </div>
    </div>

    <!-- EXTRAS -->
    <div v-if="utilities.length > 0">
      <label class="text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-3 block">Extras / Consumibles</label>
      <div class="space-y-2">
        <button
v-for="u in utilities" :key="u.id" :class="selectedExtras.find(e => e.id === u.id) ? 'bg-primary/10 border-primary/30 text-primary' : 'bg-gray-50 dark:bg-white/5 border-transparent text-gray-500'"
          class="w-full flex items-center justify-between px-5 py-3.5 rounded-2xl border-2 text-[10px] font-black uppercase tracking-widest transition-all hover:border-primary/20"
          @click="$emit('toggleExtra', u.id)">
          <span>{{ u.name }}</span>
          <span class="text-[9px]">${{ u.cost_per_kg }}/u</span>
        </button>
      </div>
    </div>

    <!-- CUPON -->
    <div>
      <label class="text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-3 block">Cupon de Descuento</label>
      <div class="flex gap-2">
        <input v-model="localCoupon" type="text" placeholder="Codigo..." class="flex-1 bg-gray-100 dark:bg-[#0f172a] border border-transparent dark:border-white/5 rounded-2xl px-6 py-4 text-[11px] font-black text-gray-900 dark:text-white uppercase outline-none focus:ring-2 focus:ring-primary/20 transition-all">
        <button class="px-6 py-4 bg-gray-900 dark:bg-white/10 text-white rounded-2xl text-[9px] font-black uppercase tracking-widest hover:bg-primary transition-all" @click="$emit('applyCoupon')">Aplicar</button>
      </div>
      <p v-if="activeCoupon" class="text-[9px] font-black text-primary uppercase tracking-widest mt-2 ml-2">{{ activeCoupon.label }} (-{{ (activeCoupon.discount * 100) }}%)</p>
    </div>

    <!-- BOTON CALCULAR (FDM) -->
    <button
v-if="selectedTechnology === 'FDM'" :disabled="isSlicing || !models[activeModelIdx]?.hasModel" :class="!models[activeModelIdx]?.hasModel ? 'opacity-40 cursor-not-allowed' : 'hover:bg-emerald-600 active:scale-95'"
      class="w-full py-5 bg-gray-900 dark:bg-white/10 text-white rounded-[2rem] font-black text-[10px] uppercase tracking-[0.3em] transition-all flex items-center justify-center gap-3"
      @click="$emit('calculate')">
      <span v-if="isSlicing" class="w-5 h-5 border-3 border-white/30 border-t-white rounded-full animate-spin"></span>
      <span>{{ isSlicing ? 'Procesando...' : 'Calcular Cotizacion' }}</span>
    </button>

    <!-- RESULTADO -->
    <div v-if="hasSlicingOrSLA" class="bg-gray-900 dark:bg-[#0f172a] rounded-[3rem] p-8 text-white relative overflow-hidden border border-white/5">
      <div class="absolute -top-10 -right-10 w-48 h-48 bg-primary/20 rounded-full blur-[80px]"></div>
      
      <div class="flex justify-between items-center mb-6 relative z-10">
        <p class="text-[9px] font-black text-primary uppercase tracking-[0.5em]">Total Estimado</p>
        <span class="bg-primary text-white text-[8px] font-black px-4 py-1.5 rounded-full uppercase tracking-widest">Listo</span>
      </div>

      <h3
class="text-6xl lg:text-7xl font-black tracking-tighter italic text-white relative z-10 mb-8 price-count"
        :class="{ 'bump': previousTotal !== breakdown.total && previousTotal > 0 }">
        ${{ Math.round(breakdown.total).toLocaleString('es-CO') }}
      </h3>

      <div class="space-y-3 relative z-10">
        <div class="flex justify-between items-center bg-white/5 px-4 py-3 rounded-xl">
          <span class="text-[9px] font-black text-white/40 uppercase tracking-widest">Masa unitaria</span>
          <span class="text-sm font-black text-white">{{ breakdown.weight.toFixed(2) }}g</span>
        </div>
        <div class="flex justify-between items-center bg-white/5 px-4 py-3 rounded-xl">
          <span class="text-[9px] font-black text-white/40 uppercase tracking-widest">Tiempo estimado</span>
          <span class="text-sm font-black text-white">{{ formatTime(breakdown.duration) }}</span>
        </div>
        <div class="flex justify-between items-center px-4 py-2">
          <span class="text-[9px] font-black text-white/30 uppercase tracking-widest">Material</span>
          <span class="text-xs font-black text-white/60">${{ Math.round(breakdown.matCost).toLocaleString() }}</span>
        </div>
        <div class="flex justify-between items-center px-4 py-2">
          <span class="text-[9px] font-black text-white/30 uppercase tracking-widest">Infraestructura</span>
          <span class="text-xs font-black text-white/60">${{ Math.round(breakdown.infraCost).toLocaleString() }}</span>
        </div>
        <div class="flex justify-between items-center px-4 py-2">
          <span class="text-[9px] font-black text-white/30 uppercase tracking-widest">Extras</span>
          <span class="text-xs font-black text-white/60">${{ Math.round(breakdown.utilityCost).toLocaleString() }}</span>
        </div>
        <div class="flex justify-between items-center px-4 py-2">
          <span class="text-[9px] font-black text-white/30 uppercase tracking-widest">Margenes Operativos</span>
          <span class="text-xs font-black text-white/60">${{ Math.round(breakdown.marginCost).toLocaleString() }}</span>
        </div>
        <div v-if="breakdown.discount > 0" class="flex justify-between items-center px-4 py-2">
          <span class="text-[9px] font-black text-emerald-400 uppercase tracking-widest">Descuento</span>
          <span class="text-xs font-black text-emerald-400">-${{ Math.round(breakdown.discount).toLocaleString() }}</span>
        </div>
        <div class="border-t border-white/10 pt-3 mt-2">
          <div class="flex justify-between items-center px-4 py-2">
            <span class="text-[9px] font-black text-white/50 uppercase tracking-widest">Subtotal</span>
            <span class="text-sm font-black text-white">${{ Math.round(breakdown.subtotal).toLocaleString() }}</span>
          </div>
          <div class="flex justify-between items-center px-4 py-2">
            <span class="text-[9px] font-black text-white/30 uppercase tracking-widest">IVA (19%)</span>
            <span class="text-xs font-black text-white/60">${{ Math.round(breakdown.iva).toLocaleString() }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- DIMENSIONES -->
    <div v-if="models[activeModelIdx]?.hasModel" class="grid grid-cols-3 gap-3">
      <div
v-for="(val, axis) in models[activeModelIdx]?.dimensions || { x: 0, y: 0, z: 0 }" :key="axis"
        class="bg-gray-50 dark:bg-white/5 p-4 rounded-2xl text-center border border-gray-100 dark:border-white/5">
        <p class="text-[8px] font-black text-gray-400 uppercase tracking-widest mb-1">{{ axis.toUpperCase() }}</p>
        <p class="text-sm font-black text-gray-900 dark:text-white">{{ Number(val).toFixed(1) }}mm</p>
      </div>
    </div>

    <!-- CTA -->
    <button
class="w-full bg-primary hover:bg-emerald-600 text-white font-black py-7 rounded-[2.5rem] shadow-2xl shadow-primary/20 uppercase tracking-[0.3em] text-[11px] transition-all active:scale-[0.98]"
      @click="$emit('requestQuote')">
      Solicitar Cotizacion
    </button>
  </div>
</template>

<style scoped>
.price-count {
  display: inline-block;
  transition: all 0.5s cubic-bezier(0.22, 1, 0.36, 1);
}
.price-count.bump {
  animation: priceBump 0.4s cubic-bezier(0.22, 1, 0.36, 1);
}
@keyframes priceBump {
  0% { transform: scale(1); }
  50% { transform: scale(1.08); }
  100% { transform: scale(1); }
}
</style>
