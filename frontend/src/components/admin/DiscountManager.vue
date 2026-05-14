<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  discounts: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['update-discounts'])

const isAdding = ref(false)
const newDiscount = ref({
  code: '',
  type: 'percentage',
  value: 0,
  max_uses: null,
  status: 'active',
  uses: 0
})

const generateCode = () => {
  const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'
  let code = 'N3XT-'
  for (let i = 0; i < 6; i++) {
    code += chars.charAt(Math.floor(Math.random() * chars.length))
  }
  newDiscount.value.code = code
}

const addDiscount = () => {
  if (!newDiscount.value.code || newDiscount.value.value <= 0) return

  const updatedDiscounts = [...(props.discounts || [])]
  updatedDiscounts.push({ 
    ...newDiscount.value, 
    code: newDiscount.value.code.toUpperCase().trim(),
    created_at: new Date().toISOString()
  })
  
  emit('update-discounts', updatedDiscounts)
  
  newDiscount.value = { code: '', type: 'percentage', value: 0, max_uses: null, status: 'active', uses: 0 }
  isAdding.value = false
}

const toggleStatus = (index) => {
  const updatedDiscounts = [...(props.discounts || [])]
  updatedDiscounts[index].status = updatedDiscounts[index].status === 'active' ? 'inactive' : 'active'
  emit('update-discounts', updatedDiscounts)
}

const deleteDiscount = (index) => {
  if (!confirm('¿Estás seguro de eliminar este código de descuento?')) return
  const updatedDiscounts = [...(props.discounts || [])]
  updatedDiscounts.splice(index, 1)
  emit('update-discounts', updatedDiscounts)
}

</script>

<template>
  <div class="space-y-8">
    <!-- Header -->
    <div class="flex flex-col md:flex-row items-center justify-between gap-6 bg-white/40 dark:bg-gray-950/40 backdrop-blur-md p-8 md:p-10 rounded-[3rem] border border-white/60 dark:border-white/5 shadow-2xl shadow-gray-200/20 dark:shadow-none relative overflow-hidden group">
      <div class="absolute -right-20 -top-20 w-64 h-64 bg-primary/5 rounded-full blur-3xl group-hover:bg-primary/10 transition-all duration-1000 pointer-events-none"></div>
      
      <div class="relative z-10 text-center md:text-left">
        <h2 class="text-3xl md:text-5xl font-black text-gray-900 dark:text-white tracking-tighter uppercase italic leading-none">
          Gestión de <span class="text-primary">Descuentos</span>
        </h2>
        <p class="text-[10px] md:text-xs text-gray-400 font-bold uppercase tracking-[0.4em] mt-4 flex items-center justify-center md:justify-start gap-3">
          <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 shadow-[0_0_8px_#10b981]"></span>
          Control de Promociones
        </p>
      </div>

      <button @click="isAdding = true" class="btn-primary py-4 px-8 !rounded-2xl relative z-10 w-full md:w-auto">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        <span>Nuevo Código</span>
      </button>
    </div>

    <!-- Formulario Agregar Nuevo -->
    <div v-if="isAdding" class="bg-white dark:bg-gray-900 p-8 rounded-[2.5rem] border border-gray-100 dark:border-white/5 shadow-sm animate-slide-up">
      <div class="flex items-center justify-between mb-6 border-b border-gray-100 dark:border-white/5 pb-4">
        <h3 class="text-sm font-black text-gray-900 dark:text-white uppercase tracking-widest">Crear Código Promocional</h3>
        <button @click="isAdding = false" class="text-gray-400 hover:text-red-500 transition-colors">
          <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Código -->
        <div>
          <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Código</label>
          <div class="flex gap-2">
            <input v-model="newDiscount.code" type="text" class="n3xt-input uppercase font-mono" placeholder="EJ: VIP10">
            <button @click="generateCode" class="bg-primary/10 text-primary p-3 rounded-xl hover:bg-primary hover:text-white transition-colors" title="Generar Aleatorio">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            </button>
          </div>
        </div>

        <!-- Tipo -->
        <div>
          <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Tipo de Descuento</label>
          <select v-model="newDiscount.type" class="n3xt-input">
            <option value="percentage">Porcentaje (%)</option>
            <option value="fixed">Monto Fijo ($)</option>
          </select>
        </div>

        <!-- Valor -->
        <div>
          <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Valor</label>
          <div class="relative">
            <div v-if="newDiscount.type === 'fixed'" class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
              <span class="text-gray-500 font-bold">$</span>
            </div>
            <input v-model.number="newDiscount.value" type="number" min="0" :class="['n3xt-input font-bold', newDiscount.type === 'fixed' ? 'pl-8' : '']" placeholder="0">
            <div v-if="newDiscount.type === 'percentage'" class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none">
              <span class="text-gray-500 font-bold">%</span>
            </div>
          </div>
        </div>

        <!-- Límite de Usos -->
        <div>
          <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Límite de Usos (Opcional)</label>
          <input v-model.number="newDiscount.max_uses" type="number" min="1" class="n3xt-input" placeholder="Ilimitado">
        </div>
      </div>

      <div class="mt-8 flex justify-end">
        <button @click="addDiscount" class="bg-gray-900 dark:bg-primary text-white px-8 py-3 rounded-xl font-bold text-sm tracking-widest uppercase hover:scale-105 active:scale-95 transition-all shadow-xl">
          Guardar Código
        </button>
      </div>
    </div>

    <!-- Lista de Códigos -->
    <div v-if="discounts && discounts.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <div 
        v-for="(discount, index) in discounts" 
        :key="index"
        class="bg-white dark:bg-gray-900 p-6 rounded-[2rem] border relative overflow-hidden group transition-all"
        :class="discount.status === 'active' ? 'border-primary/20 dark:border-white/10 shadow-lg' : 'border-gray-200 dark:border-white/5 opacity-60 grayscale'"
      >
        <div class="flex justify-between items-start mb-4 relative z-10">
          <div>
            <div class="flex items-center gap-2 mb-1">
              <span class="text-xl font-black font-mono text-gray-900 dark:text-white tracking-widest">{{ discount.code }}</span>
              <span v-if="discount.status === 'active'" class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
            </div>
            <span class="text-[10px] font-bold uppercase tracking-widest" :class="discount.type === 'percentage' ? 'text-primary' : 'text-blue-500'">
              {{ discount.type === 'percentage' ? `${discount.value}% DE DESCUENTO` : `$${discount.value} DE DESCUENTO` }}
            </span>
          </div>
          
          <!-- Acciones -->
          <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
            <button @click="toggleStatus(index)" class="w-8 h-8 rounded-lg flex items-center justify-center transition-colors" :class="discount.status === 'active' ? 'bg-amber-100 text-amber-600 hover:bg-amber-200' : 'bg-emerald-100 text-emerald-600 hover:bg-emerald-200'" :title="discount.status === 'active' ? 'Desactivar' : 'Activar'">
              <svg v-if="discount.status === 'active'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
              <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </button>
            <button @click="deleteDiscount(index)" class="w-8 h-8 rounded-lg bg-red-100 text-red-600 flex items-center justify-center hover:bg-red-200 transition-colors" title="Eliminar">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
            </button>
          </div>
        </div>

        <div class="flex items-center justify-between text-[10px] font-bold text-gray-400 uppercase tracking-widest relative z-10 border-t border-gray-100 dark:border-white/5 pt-4 mt-4">
          <div class="flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
            <span>Usos: {{ discount.uses || 0 }} {{ discount.max_uses ? `/ ${discount.max_uses}` : '(Ilimitado)' }}</span>
          </div>
          <span :class="discount.status === 'active' ? 'text-emerald-500' : 'text-gray-500'">{{ discount.status === 'active' ? 'ACTIVO' : 'INACTIVO' }}</span>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="flex flex-col items-center justify-center p-12 bg-white/50 dark:bg-gray-900/50 rounded-[3rem] border-2 border-dashed border-gray-200 dark:border-white/10 text-center">
      <div class="w-20 h-20 bg-gray-100 dark:bg-white/5 rounded-full flex items-center justify-center mb-6">
        <svg class="w-10 h-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
        </svg>
      </div>
      <h3 class="text-xl font-black text-gray-900 dark:text-white uppercase tracking-tighter mb-2">No hay códigos activos</h3>
      <p class="text-xs text-gray-500 font-medium max-w-sm">Crea códigos de descuento para impulsar tus ventas. Los códigos se aplican en la pasarela de pagos.</p>
    </div>

  </div>
</template>
