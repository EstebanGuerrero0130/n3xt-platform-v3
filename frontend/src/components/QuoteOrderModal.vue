<script setup lang="ts">
import { ref, watch } from 'vue'

const props = defineProps({
  show: { type: Boolean, default: false },
  models: { type: Array, default: () => [] },
  materials: { type: Array, default: () => [] },
  selectedTechnology: { type: String, default: 'FDM' },
  selectedMaterial: { type: String, default: '' },
  qty: { type: Number, default: 1 },
  breakdown: { type: Object, default: () => ({ total: 0, discount: 0 }) },
  activeCoupon: { type: Object, default: null },
  previousTotal: { type: Number, default: 0 },
  isSubmitting: { type: Boolean, default: false },
  notification: { type: Object, default: () => ({ show: false, message: '', type: 'info' }) }
})

const emit = defineEmits(['close', 'submit', 'update:show'])

const modalStep = ref(1)
const customerForm = ref({
  name: '', company: '', document: '', email: '', phone: '',
  city: '', address: '', use: 'Funcional', comments: ''
})
const captchaChallenge = ref({ a: 0, b: 0, result: 0 })
const captchaAnswer = ref('')

const generateCaptcha = () => {
  captchaChallenge.value.a = Math.floor(Math.random() * 10) + 1
  captchaChallenge.value.b = Math.floor(Math.random() * 10) + 1
  captchaChallenge.value.result = captchaChallenge.value.a + captchaChallenge.value.b
  captchaAnswer.value = ''
}

const handleClose = () => {
  modalStep.value = 1
  emit('close')
}

watch(() => props.show, (v) => {
  if (v) generateCaptcha()
})

const getMaterialName = (id) => {
  const mat = props.materials.find(m => m.id === id)
  return mat ? mat.name : id
}

const formatTime = (h: number) => {
  if (!h || h < 0) return '0m';
  const hours = Math.floor(h);
  const minutes = Math.round((h - hours) * 60);
  if (hours === 0) return `${minutes}m`;
  return `${hours}h ${minutes}m`;
}
</script>

<template>
<transition
enter-active-class="transition duration-300 ease-out" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100"
  leave-active-class="transition duration-200 ease-in" leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
  <div v-if="show" class="fixed inset-0 z-[100] flex items-center justify-center p-4 md:p-6 bg-black/80 backdrop-blur-md">
    <div class="bg-white dark:bg-[#0a0f14] w-full max-w-2xl rounded-[3.5rem] shadow-2xl border border-gray-100 dark:border-white/5 overflow-hidden flex flex-col relative max-h-[90vh]">
      
      <!-- Header -->
      <div class="bg-primary px-8 md:px-12 pt-10 pb-6 text-white relative shrink-0">
        <button class="absolute top-6 right-6 w-10 h-10 flex items-center justify-center bg-white/20 rounded-full hover:bg-white hover:text-primary transition-all text-lg" @click="handleClose">✕</button>
        <p class="text-[9px] font-black uppercase tracking-[0.5em] mb-2 text-white/80">N3XT Protocol</p>
        <h2 class="text-3xl md:text-4xl font-black tracking-tighter uppercase leading-none">Finalizar Solicitud</h2>
        
        <!-- Step Indicators -->
        <div class="flex items-center gap-2 mt-8">
          <button :class="modalStep >= 1 ? 'bg-white text-primary' : 'bg-white/20 text-white/60'" class="w-9 h-9 rounded-full flex items-center justify-center text-xs font-black transition-all" @click="modalStep = 1">1</button>
          <div :class="modalStep >= 2 ? 'bg-white/60' : 'bg-white/10'" class="flex-1 h-0.5 rounded transition-all"></div>
          <button :class="modalStep >= 2 ? 'bg-white text-primary' : 'bg-white/20 text-white/60'" class="w-9 h-9 rounded-full flex items-center justify-center text-xs font-black transition-all" @click="modalStep = 2">2</button>
          <div :class="modalStep >= 3 ? 'bg-white/60' : 'bg-white/10'" class="flex-1 h-0.5 rounded transition-all"></div>
          <button :class="modalStep >= 3 ? 'bg-white text-primary' : 'bg-white/20 text-white/60'" class="w-9 h-9 rounded-full flex items-center justify-center text-xs font-black transition-all" @click="modalStep = 3">3</button>
        </div>
        <div class="flex justify-between mt-2">
          <span class="text-[7px] font-black uppercase tracking-[0.3em]" :class="modalStep === 1 ? 'text-white' : 'text-white/50'">Datos</span>
          <span class="text-[7px] font-black uppercase tracking-[0.3em]" :class="modalStep === 2 ? 'text-white' : 'text-white/50'">Entrega</span>
          <span class="text-[7px] font-black uppercase tracking-[0.3em]" :class="modalStep === 3 ? 'text-white' : 'text-white/50'">Confirmar</span>
        </div>
      </div>

      <div class="p-6 md:p-10 space-y-6 overflow-y-auto flex-1">
        <!-- STEP 1: DATOS DEL CLIENTE -->
        <div v-show="modalStep === 1" class="space-y-4 animate-in fade-in slide-in-from-right-4 duration-500">
          <div class="flex items-center gap-3 mb-6">
            <div class="w-10 h-10 bg-primary/10 rounded-2xl flex items-center justify-center">
              <svg class="w-5 h-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            </div>
            <div>
              <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Paso 1 de 3</p>
              <p class="text-sm font-black text-gray-900 dark:text-white uppercase tracking-tight">Datos del Cliente</p>
            </div>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="text-[8px] font-black text-gray-400 uppercase tracking-widest mb-1.5 block ml-4">Nombre Completo *</label>
              <input v-model="customerForm.name" type="text" class="w-full bg-gray-50 dark:bg-[#0f172a] border border-transparent dark:border-white/5 rounded-2xl px-5 py-3.5 text-sm font-black text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary/20 transition-all" required>
            </div>
            <div>
              <label class="text-[8px] font-black text-gray-400 uppercase tracking-widest mb-1.5 block ml-4">Empresa / Razon Social</label>
              <input v-model="customerForm.company" type="text" class="w-full bg-gray-50 dark:bg-[#0f172a] border border-transparent dark:border-white/5 rounded-2xl px-5 py-3.5 text-sm font-black text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary/20 transition-all">
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="text-[8px] font-black text-gray-400 uppercase tracking-widest mb-1.5 block ml-4">NIT / Documento</label>
              <input v-model="customerForm.document" type="text" class="w-full bg-gray-50 dark:bg-[#0f172a] border border-transparent dark:border-white/5 rounded-2xl px-5 py-3.5 text-sm font-black text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary/20 transition-all">
            </div>
            <div>
              <label class="text-[8px] font-black text-gray-400 uppercase tracking-widest mb-1.5 block ml-4">Email Corporativo *</label>
              <input v-model="customerForm.email" type="email" class="w-full bg-gray-50 dark:bg-[#0f172a] border border-transparent dark:border-white/5 rounded-2xl px-5 py-3.5 text-sm font-black text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary/20 transition-all" required>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="text-[8px] font-black text-gray-400 uppercase tracking-widest mb-1.5 block ml-4">WhatsApp / Tel *</label>
              <input v-model="customerForm.phone" type="tel" class="w-full bg-gray-50 dark:bg-[#0f172a] border border-transparent dark:border-white/5 rounded-2xl px-5 py-3.5 text-sm font-black text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary/20 transition-all" required>
            </div>
            <div>
              <label class="text-[8px] font-black text-gray-400 uppercase tracking-widest mb-1.5 block ml-4">Ciudad / Ubicacion</label>
              <input v-model="customerForm.city" type="text" class="w-full bg-gray-50 dark:bg-[#0f172a] border border-transparent dark:border-white/5 rounded-2xl px-5 py-3.5 text-sm font-black text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary/20 transition-all">
            </div>
          </div>
          <div class="pt-4 flex justify-end">
            <button class="px-10 py-4 bg-primary text-white rounded-2xl font-black text-[10px] uppercase tracking-[0.3em] hover:bg-emerald-600 transition-all flex items-center gap-3" @click="modalStep = 2">
              Continuar <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 5l7 7-7 7"/></svg>
            </button>
          </div>
        </div>

        <!-- STEP 2: DIRECCION + USO -->
        <div v-show="modalStep === 2" class="space-y-4 animate-in fade-in slide-in-from-right-4 duration-500">
          <div class="flex items-center gap-3 mb-6">
            <div class="w-10 h-10 bg-primary/10 rounded-2xl flex items-center justify-center">
              <svg class="w-5 h-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
            <div>
              <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Paso 2 de 3</p>
              <p class="text-sm font-black text-gray-900 dark:text-white uppercase tracking-tight">Direccion y Uso</p>
            </div>
          </div>
          <div>
            <label class="text-[8px] font-black text-gray-400 uppercase tracking-widest mb-1.5 block ml-4">Direccion de Envio *</label>
            <input v-model="customerForm.address" type="text" class="w-full bg-gray-50 dark:bg-[#0f172a] border border-transparent dark:border-white/5 rounded-2xl px-5 py-3.5 text-sm font-black text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary/20 transition-all" required>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="text-[8px] font-black text-gray-400 uppercase tracking-widest mb-1.5 block ml-4">Uso de la Pieza</label>
              <select v-model="customerForm.use" class="w-full bg-gray-50 dark:bg-[#0f172a] border border-transparent dark:border-white/5 rounded-2xl px-5 py-3.5 text-sm font-black text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary/20 transition-all appearance-none cursor-pointer">
                <option value="Prototipo">Prototipo Rapido</option>
                <option value="Funcional">Pieza Mecanica / Funcional</option>
                <option value="Decorativo">Arte / Decorativo</option>
                <option value="Medico">Industrial / Medico</option>
                <option value="Joyeria">Joyeria / Dental</option>
              </select>
            </div>
            <div>
              <label class="text-[8px] font-black text-gray-400 uppercase tracking-widest mb-1.5 block ml-4">Notas Tecnicas</label>
              <textarea v-model="customerForm.comments" rows="2" class="w-full bg-gray-50 dark:bg-[#0f172a] border border-transparent dark:border-white/5 rounded-2xl px-5 py-3.5 text-sm font-black text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary/20 transition-all resize-none" placeholder="EJ. ACABADO ESPEJO..."></textarea>
            </div>
          </div>
          <div class="pt-4 flex justify-between">
            <button class="px-6 py-4 bg-gray-100 dark:bg-white/5 text-gray-500 rounded-2xl font-black text-[9px] uppercase tracking-[0.3em] hover:bg-gray-200 transition-all flex items-center gap-2" @click="modalStep = 1">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 19l-7-7 7-7"/></svg> Atras
            </button>
            <button class="px-10 py-4 bg-primary text-white rounded-2xl font-black text-[10px] uppercase tracking-[0.3em] hover:bg-emerald-600 transition-all flex items-center gap-3" @click="modalStep = 3">
              Revisar Pedido <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 5l7 7-7 7"/></svg>
            </button>
          </div>
        </div>

        <!-- STEP 3: RESUMEN + CONFIRMAR -->
        <div v-show="modalStep === 3" class="space-y-4 animate-in fade-in slide-in-from-right-4 duration-500">
          <div class="flex items-center gap-3 mb-6">
            <div class="w-10 h-10 bg-primary/10 rounded-2xl flex items-center justify-center">
              <svg class="w-5 h-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
              <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Paso 3 de 3</p>
              <p class="text-sm font-black text-gray-900 dark:text-white uppercase tracking-tight">Confirmar Pedido</p>
            </div>
          </div>

          <div class="bg-primary/5 rounded-[2rem] p-6 border border-primary/20">
            <p class="text-[8px] font-black text-primary uppercase tracking-[0.4em] mb-4">Resumen Industrial</p>
            <div class="grid grid-cols-2 gap-y-3">
              <div class="flex flex-col">
                <span class="text-[8px] font-black text-gray-400 uppercase">Tecnologia</span>
                <span class="text-xs font-black dark:text-white uppercase">{{ selectedTechnology }}</span>
              </div>
              <div class="flex flex-col">
                <span class="text-[8px] font-black text-gray-400 uppercase">Material</span>
                <span class="text-xs font-black dark:text-white uppercase">{{ getMaterialName(selectedMaterial) }}</span>
              </div>
              <div class="flex flex-col">
                <span class="text-[8px] font-black text-gray-400 uppercase">Modelos</span>
                <span class="text-xs font-black dark:text-white uppercase">{{ models.length }} {{ models.length === 1 ? 'Unidad' : 'Unidades' }}</span>
              </div>
              <div class="flex flex-col">
                <span class="text-[8px] font-black text-gray-400 uppercase text-right">Cant. Total</span>
                <span class="text-xs font-black dark:text-white text-right uppercase">{{ qty }} {{ qty === 1 ? 'Serie' : 'Series' }}</span>
              </div>
            </div>
            <div class="mt-4 pt-4 border-t border-primary/10">
              <div v-for="m in models" :key="m.id" class="flex justify-between items-center py-1.5">
                <span class="text-[8px] font-black text-gray-500">{{ m.name }} <span v-if="m.hasModel" class="text-emerald-500">✓</span></span>
                <span class="text-[9px] font-black dark:text-white">{{ m.hasModel ? m.dimensions.x.toFixed(0)+'x'+m.dimensions.y.toFixed(0)+'x'+m.dimensions.z.toFixed(0)+'mm' : 'Sin archivo' }}</span>
              </div>
            </div>
          </div>

          <div class="bg-gray-900 dark:bg-[#0f172a] rounded-[2rem] p-6 text-white border border-white/5">
            <div class="flex justify-between items-center mb-4">
              <p class="text-[8px] font-black text-primary uppercase tracking-[0.4em]">Total Estimado</p>
              <span class="bg-primary text-white text-[8px] font-black px-3 py-1 rounded-full uppercase tracking-widest">Listo</span>
            </div>
            <div class="flex items-end gap-4 mb-4">
              <p class="text-4xl md:text-5xl font-black tracking-tighter italic" :class="activeCoupon ? 'text-emerald-400 drop-shadow-[0_0_10px_rgba(52,211,153,0.3)]' : 'text-white'">${{ Math.round(breakdown.total).toLocaleString('es-CO') }}</p>
              <p v-if="activeCoupon && previousTotal > 0 && previousTotal !== breakdown.total" class="text-lg font-black text-gray-500 line-through opacity-60 leading-none mb-1">${{ previousTotal.toLocaleString('es-CO') }}</p>
            </div>
            <div v-if="activeCoupon" class="px-4 py-2 bg-emerald-500/10 rounded-xl border border-emerald-500/20 mb-4 flex items-center gap-3">
              <svg class="w-4 h-4 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              <span class="text-[8px] font-black text-emerald-400 uppercase tracking-widest">{{ activeCoupon.label }} aplicado — Ahorras ${{ Math.round(breakdown.discount).toLocaleString() }}</span>
            </div>
            <div class="space-y-2">
              <div class="flex justify-between items-center px-3 py-2 bg-white/5 rounded-xl">
                <span class="text-[8px] font-black text-white/40 uppercase tracking-widest">Masa total</span>
                <span class="text-xs font-black text-white">{{ models.reduce((s, m) => s + (m.weight || 0), 0).toFixed(2) }}g</span>
              </div>
              <div class="flex justify-between items-center px-3 py-2 bg-white/5 rounded-xl">
                <span class="text-[8px] font-black text-white/40 uppercase tracking-widest">Tiempo total</span>
                <span class="text-xs font-black text-white">{{ formatTime(models.reduce((s, m) => s + (m.duration || 0), 0)) }}</span>
              </div>
            </div>
          </div>

          <!-- Captcha -->
          <div class="bg-gray-100 dark:bg-white/5 p-5 rounded-3xl border border-dashed border-gray-200 dark:border-white/10">
            <div class="flex items-center gap-4">
              <div class="w-9 h-9 bg-primary/20 rounded-xl flex items-center justify-center shrink-0">
                <svg class="w-4 h-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
              </div>
              <div class="flex-1">
                <p class="text-[7px] font-black text-primary uppercase tracking-[0.3em]">Validacion Maker</p>
                <p class="text-[10px] font-black dark:text-white uppercase">{{ captchaChallenge.a }} + {{ captchaChallenge.b }} = ?</p>
              </div>
              <input v-model="captchaAnswer" type="number" class="w-20 bg-white dark:bg-white/10 border-none rounded-xl p-3 text-center text-sm font-black text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary/20 shadow-inner">
            </div>
          </div>

          <div class="pt-2 flex justify-between">
            <button class="px-6 py-4 bg-gray-100 dark:bg-white/5 text-gray-500 rounded-2xl font-black text-[9px] uppercase tracking-[0.3em] hover:bg-gray-200 transition-all flex items-center gap-2" @click="modalStep = 2">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 19l-7-7 7-7"/></svg> Atras
            </button>
            <button
:disabled="isSubmitting" class="px-10 py-4 bg-primary hover:bg-emerald-600 text-white rounded-2xl font-black text-[10px] uppercase tracking-[0.3em] transition-all flex items-center justify-center gap-3 active:scale-95 disabled:opacity-50 shadow-xl shadow-primary/20"
              @click="$emit('submit', { customerForm, captchaAnswer: captchaChallenge.result === parseInt(captchaAnswer), captchaChallenge })">
              <span v-if="isSubmitting" class="w-5 h-5 border-3 border-white/30 border-t-white rounded-full animate-spin"></span>
              <span>{{ isSubmitting ? 'PROCESANDO...' : 'CONFIRMAR PEDIDO' }}</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</transition>
<transition
enter-active-class="transition transform duration-500 ease-out" enter-from-class="translate-y-10 opacity-0" enter-to-class="translate-y-0 opacity-100"
  leave-active-class="transition transform duration-400 ease-in" leave-from-class="translate-y-0 opacity-100" leave-to-class="translate-y-10 opacity-0">
  <div
v-if="props.notification?.show" :class="props.notification?.type === 'success' ? 'bg-primary' : 'bg-rose-600'"
    class="fixed bottom-10 left-1/2 -translate-x-1/2 z-[200] px-10 py-5 rounded-full shadow-2xl flex items-center gap-4 border border-white/20">
    <div class="w-8 h-8 bg-white/20 rounded-full flex items-center justify-center">
      <span v-if="props.notification?.type === 'success'" class="text-white font-black">✓</span>
      <span v-else class="text-white font-black">!</span>
    </div>
    <p class="text-[10px] font-black text-white uppercase tracking-widest">{{ props.notification?.message }}</p>
  </div>
</transition>
</template>
