<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '../services/api'
import { Html5QrcodeScanner } from 'html5-qrcode'
import QrcodeVue from 'qrcode.vue'
import AppNavbar from '../components/AppNavbar.vue'
import AppFooter from '../components/AppFooter.vue'
import logger from '../utils/logger'
import { useSplitTitle } from '../composables/useSplitTitle'
import { useSplitButton } from '../composables/useSplitButton'
import { useParticles } from '../composables/useParticles'

useSplitTitle()
useSplitButton()

const { particlesRef: heroParticlesRef } = useParticles({
  count: 30,
  zIndex: 1,
})

// --- SEO Meta Tags ---
const seoMeta = {
  title: 'Rastrear Pedido 3D | N3XT 3D',
  description: 'Consulta el estado de tu pedido de impresion 3D en tiempo real. Rastreo por proyecto o envio.',
  image: '/assets/n3xt_og_track.png'
}

let injectedMetaEls: any[] = []

const setMetaTags = () => {
  document.title = seoMeta.title
  injectedMetaEls.forEach(el => el.remove())
  injectedMetaEls = []
  const metas = [
    { name: 'og:title', prop: true, content: seoMeta.title },
    { name: 'og:description', prop: true, content: seoMeta.description },
    { name: 'og:image', prop: true, content: seoMeta.image },
    { name: 'og:type', prop: true, content: 'website' },
    { name: 'twitter:card', prop: false, content: 'summary_large_image' },
    { name: 'twitter:title', prop: false, content: seoMeta.title },
    { name: 'twitter:description', prop: false, content: seoMeta.description },
    { name: 'twitter:image', prop: false, content: seoMeta.image },
    { name: 'description', prop: false, content: seoMeta.description }
  ]
  metas.forEach(({ name, prop, content }) => {
    const el = document.createElement('meta')
    if (prop) el.setAttribute('property', name)
    el.setAttribute('name', name)
    el.setAttribute('content', content)
    document.head.appendChild(el)
    injectedMetaEls.push(el)
  })
}

const route = useRoute()
const orderId = ref('')
const email = ref('')
const order = ref<any>(null)
const loading = ref(false)
const error = ref('')
const showScanner = ref(false)
let scanner: any = null

// --- THEME PROTOCOL ---
// Modo oscuro permanente

const fetchSettings = async () => {
  try {
    await api.get('/settings')
  } catch (err) {
    logger.error('Error fetching settings:', err)
  }
}

const startScanner = () => {
  showScanner.value = true
  setTimeout(() => {
    scanner = new Html5QrcodeScanner("reader", { 
        fps: 10, 
        qrbox: { width: 250, height: 250 },
        aspectRatio: 1.0
    });
    
    scanner.render((decodedText) => {
        try {
            if (decodedText.includes('order_id=')) {
                const url = new URL(decodedText.replace('#/', ''));
                orderId.value = url.searchParams.get('order_id')
                email.value = url.searchParams.get('email')
            } else {
                orderId.value = decodedText
            }
            stopScanner()
            trackOrder()
        } catch (e) {
            logger.error("Error al procesar QR:", e)
        }
    }, (_error) => {});
  }, 100);
}

const stopScanner = () => {
  if (scanner) {
    scanner.clear().catch(err => logger.error("Error clearing scanner:", err))
    scanner = null
  }
  showScanner.value = false
}

onMounted(() => {
  setMetaTags()
  fetchSettings()
  // Si viene de QR o link directo, cargamos datos. 
  // Permitimos email vacío para que trackOrder maneje la validación o el error amigable.
  if (route.query.order_id) {
    orderId.value = route.query.order_id
    email.value = route.query.email || ''
    trackOrder()
  }
})

onUnmounted(() => {
  if (scanner) scanner.clear()
  injectedMetaEls.forEach(el => el.remove())
  injectedMetaEls = []
})

const searchMode = ref('project') // 'project' or 'shipping'
const trackingGuide = ref('')
const carrier = ref('')

const trackOrder = async () => {
  if (searchMode.value === 'project') {
    if (!orderId.value) return
    if (!email.value) {
      error.value = 'Se requiere el email del cliente para el rastreo público.'
      return
    }
  } else {
    if (!trackingGuide.value) return
  }

  loading.value = true
  error.value = ''
  order.value = null
  try {
    let url = ''

    if (searchMode.value === 'project') {
        url = `${api.baseUrl}/orders/track?order_id=${orderId.value}&email=${email.value}`
    } else {
        url = `${api.baseUrl}/orders/track?tracking_guide=${trackingGuide.value}&carrier=${carrier.value}`
    }

    const res = await fetch(url)
    if (!res.ok) {
      const data = await res.json()
      throw new Error(data.message || 'Error al localizar el pedido.')
    }
    const data = await res.json()
    order.value = data.data || data
  } catch (err) {
    error.value = err.message
  } finally {
    loading.value = false
  }
}

const statusSteps = [
  { id: 'pending', label: 'Recibido', desc: 'Tu orden esta en cola de revision.', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
  { id: 'printing', label: 'Impresion', desc: 'Tus piezas estan en la impresora.', icon: 'M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z' },
  { id: 'post-processing', label: 'Acabado', desc: 'Limpieza, curado y post-procesado.', icon: 'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.618.309a6 6 0 01-3.86.517l-2.387-.477a2 2 0 00-1.022.547l-1.16 1.16a2 2 0 00.442 3.102l1.644.822a6 6 0 005.366 0l1.644-.822a2 2 0 00.442-3.102l-1.16-1.16zM15.75 9a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z' },
  { id: 'completed', label: 'Terminado', desc: 'Listo para entrega o envio.', icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' }
]

const getStatusIndex = (status: any) => {
  const mapping = { 
    'pending': 0, 
    'printing': 1, 
    'post-processing': 2, 
    'completed': 3, 
    'shipped': 3,
    'delivered': 3 
  }
  return mapping[status] ?? 0
}
const currentStatusIndex = computed(() => getStatusIndex(order.value?.status))
const progressWidth = computed(() => (currentStatusIndex.value / (statusSteps.length - 1)) * 100)

const shareUrl = computed(() => {
    if (!order.value) return ''
    return `${window.location.origin}/#/track?order_id=${order.value.id}&email=${order.value.customer_email || email.value}`
})
</script>

<template>
  <div class="min-h-screen bg-[#f8fafc] dark:bg-[#0a0f14] text-gray-900 dark:text-white font-sans selection:bg-primary/30 transition-colors duration-500">
    <AppNavbar active-tab="track" subtext="Centro de Precisión Industrial" />

        <main class="relative py-12 px-6 flex flex-col items-center overflow-x-hidden">
            <div class="fixed inset-0 technical-grid opacity-20 dark:opacity-10 pointer-events-none"></div>
            <div class="fixed top-1/4 left-1/4 w-[500px] h-[500px] bg-primary/10 rounded-full blur-[150px] pointer-events-none"></div>

            <div class="w-full max-w-4xl z-10">
                <!-- Titular HUD -->
                <div class="relative mb-16 text-center animate-in fade-in slide-in-from-top-4 duration-1000 overflow-hidden">
                    <div ref="heroParticlesRef" class="track-hero-particles" aria-hidden="true"></div>
                    <div class="inline-flex items-center gap-3 px-4 py-2 bg-primary/10 rounded-full border border-primary/20 mb-6">
                        <span class="w-2 h-2 bg-primary rounded-full animate-pulse shadow-[0_0_10px_#1e3a34]"></span>
                        <span class="text-[10px] font-black text-primary uppercase tracking-[0.4em]">Seguimiento de pedidos</span>
                    </div>
                    <h1 class="split-title text-5xl md:text-7xl font-black tracking-tighter uppercase leading-[0.9] text-gray-900 dark:text-white">
                        Rastreo <span class="text-primary">3D</span>
                    </h1>
                    <p class="text-gray-500 dark:text-gray-400 mt-6 font-bold uppercase tracking-[0.3em] text-[10px] md:text-xs">Consulta el estado de tu pedido en tiempo real</p>
                </div>

                <!-- Selector de Modo de Búsqueda -->
                <div v-if="!order" class="max-w-xl mx-auto mb-10 flex p-2 bg-gray-100 dark:bg-white/5 rounded-3xl border border-gray-200 dark:border-white/10">
                    <button :class="searchMode === 'project' ? 'bg-primary text-white shadow-lg' : 'text-gray-400 hover:text-primary'" class="flex-1 py-4 rounded-2xl font-black text-[10px] uppercase tracking-widest transition-all duration-500" @click="searchMode = 'project'">Rastreo por Proyecto</button>
                    <button :class="searchMode === 'shipping' ? 'bg-primary text-white shadow-lg' : 'text-gray-400 hover:text-primary'" class="flex-1 py-4 rounded-2xl font-black text-[10px] uppercase tracking-widest transition-all duration-500" @click="searchMode = 'shipping'">Rastreo por Envío</button>
                </div>

                <!-- Selector de Entrada (Formulario) -->
                <div v-if="!order" class="max-w-xl mx-auto space-y-8">
                    <div class="bg-white dark:bg-white/5 backdrop-blur-3xl p-10 rounded-[3rem] border border-gray-200 dark:border-white/10 shadow-2xl relative overflow-hidden group">
                        <div class="grid grid-cols-1 gap-8 relative z-10 text-left">
                            
                            <!-- MODO PROYECTO -->
                            <template v-if="searchMode === 'project'">
                                <div class="space-y-4">
                                    <label class="text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-[0.3em] ml-1">ID DE PROYECTO</label>
                                    <div class="relative">
                                        <input v-model="orderId" type="text" placeholder="EJ: 1024" class="w-full bg-gray-100 dark:bg-black/40 border-2 border-transparent dark:border-white/5 rounded-[1.5rem] p-5 text-gray-900 dark:text-white font-bold text-lg focus:border-primary/50 transition-all outline-none">
                                        <button class="absolute right-4 top-1/2 -translate-y-1/2 w-12 h-12 bg-gray-200 dark:bg-white/5 rounded-xl flex items-center justify-center hover:bg-primary transition-all text-gray-600 dark:text-white" @click="startScanner">
                                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" /></svg>
                                        </button>
                                    </div>
                                </div>
                                <div class="space-y-4">
                                    <label class="text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-[0.3em] ml-1">IDENTIDAD DEL CLIENTE (EMAIL)</label>
                                    <input v-model="email" type="email" placeholder="USUARIO@N3XT3D.COM" class="w-full bg-gray-100 dark:bg-black/40 border-2 border-transparent dark:border-white/5 rounded-[1.5rem] p-5 text-gray-900 dark:text-white font-bold text-lg focus:border-primary/50 transition-all outline-none">
                                </div>
                            </template>

                            <!-- MODO ENVÍO -->
                            <template v-else>
                                <div class="space-y-4">
                                    <label class="text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-[0.3em] ml-1">Nº DE GUÍA / TRACKING</label>
                                    <input v-model="trackingGuide" type="text" placeholder="EJ: 982347123" class="w-full bg-gray-100 dark:bg-black/40 border-2 border-transparent dark:border-white/5 rounded-[1.5rem] p-5 text-gray-900 dark:text-white font-bold text-lg focus:border-primary/50 transition-all outline-none">
                                </div>
                                <div class="space-y-4">
                                    <label class="text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-[0.3em] ml-1">TRANSPORTADORA (OPCIONAL)</label>
                                    <input v-model="carrier" type="text" placeholder="EJ: SERVIENTREGA" class="w-full bg-gray-100 dark:bg-black/40 border-2 border-transparent dark:border-white/5 rounded-[1.5rem] p-5 text-gray-900 dark:text-white font-bold text-lg focus:border-primary/50 transition-all outline-none">
                                </div>
                            </template>

                        </div>
                        <button :disabled="loading" class="split-btn w-full mt-10 bg-primary text-white py-6 rounded-[1.5rem] font-black text-sm shadow-xl hover:shadow-primary/30 hover:-translate-y-1 transition-all flex items-center justify-center gap-4 active:scale-95 uppercase tracking-[0.3em] relative z-10" @click="trackOrder">
                            <span v-if="loading" class="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                            <span>{{ loading ? 'Buscando...' : 'Buscar pedido' }}</span>
                        </button>
                        <div v-if="error" class="mt-8 text-center bg-rose-500/10 p-6 rounded-2xl border border-rose-500/20">
                            <p class="text-rose-500 font-black text-[10px] uppercase tracking-widest mb-2">No se encontro el pedido</p>
                            <p class="text-gray-600 dark:text-gray-400 text-xs font-bold">{{ error }}</p>
                            <p v-if="error.includes('fetch')" class="mt-4 text-[9px] text-rose-400 font-medium uppercase leading-relaxed">
                                Nota: Si estas en celular, verifica que el PC <br>
                                no este usando 'localhost' y que el firewall permita el puerto 8000.
                            </p>
                        </div>
                        <div v-if="!error && orderId && !email && searchMode === 'project'" class="mt-8 text-center bg-primary/5 p-6 rounded-2xl border border-primary/10">
                            <p class="text-primary font-bold text-[10px] uppercase tracking-widest mb-2">Verificacion necesaria</p>
                            <p class="text-gray-600 dark:text-gray-400 text-xs font-medium">Por favor ingresa tu correo electronico para consultar el avance de la Orden #{{ orderId }}.</p>
                        </div>
                    </div>
                </div>

                <!-- Resultados Industriales -->
                <div v-else class="space-y-10 animate-in fade-in slide-in-from-bottom-10 duration-1000">
                    <div class="bg-white dark:bg-white/5 backdrop-blur-3xl p-8 md:p-12 rounded-[4rem] border border-gray-200 dark:border-white/10 shadow-2xl relative overflow-hidden text-left">
                        <div class="flex flex-col md:flex-row justify-between items-start gap-10 mb-16 relative z-10">
                            <div>
                                <div class="flex items-center gap-3 mb-4">
                                    <span class="text-[9px] font-black text-primary bg-primary/10 px-3 py-1.5 rounded-full uppercase tracking-[0.3em] border border-primary/20">Monitor de Estado</span>
                                    <span class="text-[9px] font-black text-gray-400 dark:text-gray-600 uppercase tracking-widest">ID: #{{ order.id }}</span>
                                </div>
                                <h2 class="text-4xl md:text-5xl font-black text-gray-900 dark:text-white tracking-tighter leading-tight uppercase italic">Hola, <span class="text-primary not-italic">{{ order.customer_name }}</span></h2>
                                <p class="text-gray-500 dark:text-gray-400 font-bold mt-4 uppercase text-[10px] tracking-[0.4em]">TECNOLOGÍA: {{ order.technology }} • MATERIAL: {{ order.material_name || 'ESTÁNDAR' }}</p>
                            </div>
                            <div class="bg-white p-4 rounded-[2.5rem] shadow-2xl border-8 border-primary/10 flex flex-col items-center gap-4 group hover:scale-105 transition-transform">
                                <qrcode-vue :value="shareUrl" :size="120" level="H" :render-as="'svg'" background="#ffffff" foreground="#000000" />
                                <span class="text-[8px] font-black text-black uppercase tracking-widest opacity-40">Compartir enlace</span>
                            </div>
                        </div>

                        <!-- Timeline HUD -->
                        <div class="relative py-12 mb-20 px-4">
                            <div class="absolute top-1/2 left-0 w-full h-2 bg-gray-100 dark:bg-white/5 -translate-y-1/2 rounded-full overflow-hidden">
                                <div class="h-full bg-primary transition-all duration-[2000ms] shadow-[0_0_20px_#1e3a34]" :style="{ width: progressWidth + '%' }"></div>
                            </div>
                            <div class="flex justify-between relative">
                                <div v-for="(step, index) in statusSteps" :key="step.id" class="flex flex-col items-center relative">
                                    <div class="w-16 h-16 md:w-24 md:h-24 rounded-[1.5rem] md:rounded-[2.5rem] flex items-center justify-center transition-all duration-700 z-10 border-4" :class="index <= currentStatusIndex ? 'bg-primary text-white border-primary shadow-[0_0_30px_#1e3a34]' : 'bg-gray-100 dark:bg-white/5 border-gray-200 dark:border-white/10 text-gray-400 dark:text-gray-500 backdrop-blur-xl'">
                                        <svg class="w-6 h-6 md:w-10 md:h-10 relative z-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" :d="step.icon" />
                                        </svg>
                                    </div>
                                    <div class="absolute mt-24 md:mt-32 text-center w-32">
                                        <p class="text-[9px] md:text-[10px] font-black uppercase tracking-[0.2em] mb-2" :class="index <= currentStatusIndex ? 'text-primary' : 'text-gray-400 dark:text-gray-600'">{{ step.label }}</p>
                                        <div v-if="index === currentStatusIndex" class="p-3 bg-primary/5 rounded-xl border border-primary/10 animate-pulse"><p class="text-[8px] text-primary font-bold leading-relaxed uppercase tracking-widest">{{ step.desc }}</p></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-24 pt-12 border-t border-gray-100 dark:border-white/5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                            <div
v-for="info in [
                                {l: 'TRANSPORTADORA', v: order.tracking_carrier || 'TALLER N3XT', c: 'text-primary'},
                                {l: 'Nº DE GUÍA', v: order.tracking_guide || 'PENDIENTE', c: 'text-primary'}, 
                                {l: 'FECHA INGRESO', v: (order.created_at ? new Date(order.created_at).toLocaleDateString() : 'Pendiente'), c: 'text-gray-900 dark:text-white'}, 
                                {l: 'ESTADO PAGO', v: order.is_paid ? 'COMPLETO' : 'PENDIENTE', c: order.is_paid ? 'text-emerald-500' : 'text-amber-500'}
                            ]" :key="info.l" class="bg-gray-50 dark:bg-white/5 p-6 rounded-[2rem] border border-gray-100 dark:border-white/5">
                                <p class="text-[8px] font-black text-gray-400 dark:text-gray-600 uppercase tracking-[0.3em] mb-2">{{ info.l }}</p>
                                <p :class="info.c" class="text-xs md:text-sm font-black uppercase tracking-widest break-all">{{ info.v }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-col items-center gap-6">
                        <button class="px-10 py-4 rounded-full border border-gray-200 dark:border-white/10 text-[10px] font-black text-gray-400 dark:text-gray-600 uppercase tracking-[0.3em] hover:bg-primary hover:text-white transition-all" @click="order = null">Nueva Consulta</button>
                    </div>
                </div>
            </div>
        </main>

        <!-- Scanner Modal -->
        <transition enter-active-class="transition duration-300 ease-out" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100" leave-active-class="transition duration-200 ease-in" leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
            <div v-if="showScanner" class="fixed inset-0 z-[200] flex items-center justify-center p-6 md:p-12">
                <div class="absolute inset-0 bg-black/90 backdrop-blur-2xl" @click="stopScanner"></div>
                <div class="bg-white dark:bg-[#0a0f14] w-full max-w-2xl rounded-[3rem] border border-gray-200 dark:border-white/10 relative overflow-hidden shadow-2xl p-8 md:p-12">
                    <div class="flex justify-between items-center mb-8">
                        <h3 class="text-xl font-black uppercase tracking-[0.3em] italic text-gray-900 dark:text-white">Escaner QR</h3>
                        <button class="w-10 h-10 bg-gray-100 dark:bg-white/5 rounded-full flex items-center justify-center text-gray-400 hover:text-white transition-all" @click="stopScanner">✕</button>
                    </div>
                    <div id="reader" class="rounded-3xl overflow-hidden border-4 border-primary/20"></div>
                </div>
            </div>
        </transition>
        <AppFooter />
    </div>
</template>

<style scoped>
.track-hero-particles {
  position: absolute;
  inset: 0;
  overflow: hidden;
  pointer-events: none;
  z-index: -1;
}

.technical-grid {
  background-size: 50px 50px;
  background-image: linear-gradient(to right, rgba(30, 58, 52, 0.08) 1px, transparent 1px), linear-gradient(to bottom, rgba(30, 58, 52, 0.08) 1px, transparent 1px);
}
.dark .technical-grid {
  background-image: linear-gradient(to right, rgba(30, 58, 52, 0.15) 1px, transparent 1px), linear-gradient(to bottom, rgba(30, 58, 52, 0.15) 1px, transparent 1px);
}
</style>
