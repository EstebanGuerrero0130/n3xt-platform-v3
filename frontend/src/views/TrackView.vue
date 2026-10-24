<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, nextTick } from 'vue'
import { useRoute, type LocationQueryValue } from 'vue-router'
import { api } from '../services/api'
import type { Html5QrcodeScanner } from 'html5-qrcode'
import QrcodeVue from 'qrcode.vue'
import AppNavbar from '../components/AppNavbar.vue'
import AppFooter from '../components/AppFooter.vue'
import logger from '../utils/logger'
import { useSplitTitle } from '../composables/useSplitTitle'
import { useSplitButton } from '../composables/useSplitButton'
import { useParticles } from '../composables/useParticles'
import { usePageMeta } from '../composables/usePageMeta'

useSplitTitle()
useSplitButton()

usePageMeta({
  title: 'Rastrear Pedido 3D | N3XT 3D',
  description: 'Consulta el estado de tu pedido de impresion 3D en tiempo real. Rastreo por proyecto o envio.',
  image: '/assets/n3xt_og_track.png',
})

const { particlesRef: heroParticlesRef } = useParticles({
  count: 30,
  zIndex: 1,
})

// ─── SEO via @unhead/vue (usePageMeta at setup) ───

const route = useRoute()

interface TrackedOrder {
  id: number | string
  status?: string
  customer_name?: string
  customer_email?: string
  technology?: string
  material?: string | number | null
  material_name?: string
  created_at?: string
  tracking_guide?: string | null
  tracking_carrier?: string | null
  is_paid?: boolean
}

const orderId = ref('')
const email = ref('')
const order = ref<TrackedOrder | null>(null)
const loading = ref(false)
const error = ref('')
const showScanner = ref(false)
let scanner: Html5QrcodeScanner | null = null
// Invalida arranques pendientes (import dinámico / nextTick) si el modal se cierra antes
let scannerSession = 0

const firstQuery = (v: LocationQueryValue | LocationQueryValue[] | undefined): string =>
  (Array.isArray(v) ? v[0] : v) || ''

const onScannerKey = (e: KeyboardEvent) => {
  if (e.key === 'Escape') stopScanner()
}

const onScanSuccess = async (decodedText: string) => {
  try {
    if (decodedText.includes('order_id=')) {
      // Soporta QRs antiguos con hash (/#/track?...) y nuevos (/track?...)
      const url = new URL(decodedText.replace('#/', ''))
      orderId.value = url.searchParams.get('order_id') || ''
      email.value = url.searchParams.get('email') || ''
    } else {
      orderId.value = decodedText
    }
    searchMode.value = 'project'
    await stopScanner()
    trackOrder()
  } catch (e) {
    logger.error('Error al procesar QR:', e)
  }
}

const startScanner = async () => {
  const session = ++scannerSession
  showScanner.value = true
  window.addEventListener('keydown', onScannerKey)
  try {
    // Carga diferida: html5-qrcode solo se descarga al abrir el escáner
    const { Html5QrcodeScanner: Scanner } = await import('html5-qrcode')
    await nextTick()
    if (session !== scannerSession || !showScanner.value) return
    scanner = new Scanner('reader', {
      fps: 10,
      qrbox: { width: 250, height: 250 },
      aspectRatio: 1.0
    }, false)
    scanner.render(onScanSuccess, () => {})
  } catch (err) {
    logger.error('Error iniciando escáner:', err)
    error.value = 'No se pudo iniciar el escáner QR.'
    stopScanner()
  }
}

const stopScanner = async () => {
  scannerSession++
  window.removeEventListener('keydown', onScannerKey)
  const current = scanner
  scanner = null
  if (current) {
    // Esperar a que la cámara se detenga antes de retirar el nodo #reader
    try {
      await current.clear()
    } catch (err) {
      logger.error('Error clearing scanner:', err)
    }
  }
  showScanner.value = false
}

onMounted(() => {
  // Si viene de QR o link directo, cargamos datos.
  // Permitimos email vacío para que trackOrder maneje la validación o el error amigable.
  const qOrder = firstQuery(route.query.order_id)
  if (qOrder) {
    orderId.value = qOrder
    email.value = firstQuery(route.query.email)
    trackOrder()
  }
})

onUnmounted(() => {
  scannerSession++
  window.removeEventListener('keydown', onScannerKey)
  if (scanner) {
    scanner.clear().catch(err => logger.error('Error clearing scanner:', err))
    scanner = null
  }
})

const searchMode = ref<'project' | 'shipping'>('project')
const trackingGuide = ref('')
const carrier = ref('')

const friendlyHttpError = (status: number, message?: string): string => {
  if (status === 404) return message || 'Pedido no encontrado con esos datos.'
  if (status === 429) return 'Demasiadas consultas seguidas. Espera un minuto e inténtalo de nuevo.'
  if (status >= 500) return 'El servidor no está disponible en este momento. Inténtalo más tarde.'
  return message || 'Error al localizar el pedido.'
}

const trackOrder = async () => {
  if (searchMode.value === 'project') {
    if (!orderId.value) return
  } else {
    if (!trackingGuide.value) return
  }

  loading.value = true
  error.value = ''
  order.value = null
  try {
    const params = new URLSearchParams()
    if (searchMode.value === 'project') {
      // Se permite consultar sin email (fallback para QRs sin email)
      params.set('order_id', orderId.value.trim())
      if (email.value) params.set('email', email.value.trim())
    } else {
      params.set('tracking_guide', trackingGuide.value.trim())
      if (carrier.value) params.set('carrier', carrier.value.trim())
    }

    let res: Response
    try {
      res = await fetch(`${api.baseUrl}/orders/track?${params.toString()}`, { headers: { Accept: 'application/json' } })
    } catch {
      throw new Error('No se pudo conectar con el servidor. Revisa tu conexión e inténtalo de nuevo.')
    }

    // 429/500 pueden llegar como HTML: solo parsear si es JSON
    let data: { data?: TrackedOrder; message?: string } | null = null
    if ((res.headers.get('content-type') || '').includes('application/json')) {
      try {
        data = await res.json()
      } catch {
        data = null
      }
    }

    if (!res.ok) {
      // Si el error es por falta de email, mostrar mensaje amigable
      if (res.status === 422 || data?.message?.toLowerCase().includes('email')) {
        throw new Error('Para rastrear este pedido ingresa tu correo electrónico en el campo de abajo.')
      }
      throw new Error(friendlyHttpError(res.status, data?.message))
    }
    if (!data) throw new Error('Respuesta inesperada del servidor. Inténtalo más tarde.')
    order.value = (data.data || data) as TrackedOrder
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Error al localizar el pedido.'
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

const STATUS_INDEX: Record<string, number> = {
  'pending': 0,
  'printing': 1,
  'post-processing': 2,
  'completed': 3,
  'shipped': 3,
  'delivered': 3
}
const getStatusIndex = (status: string | undefined): number => (status ? STATUS_INDEX[status] ?? 0 : 0)
const isCancelled = computed(() => order.value?.status === 'cancelled')
const currentStatusIndex = computed(() => getStatusIndex(order.value?.status))
const progressWidth = computed(() => (currentStatusIndex.value / (statusSteps.length - 1)) * 100)

const shareUrl = computed(() => {
  if (!order.value) return ''
  const params = new URLSearchParams({ order_id: String(order.value.id) })
  const shareEmail = order.value.customer_email || email.value
  if (shareEmail) params.set('email', shareEmail)
  return `${window.location.origin}/track?${params.toString()}`
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
                                    <label for="track-order-id" class="text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-[0.3em] ml-1">ID DE PROYECTO</label>
                                    <div class="relative">
                                        <input id="track-order-id" v-model="orderId" type="text" inputmode="numeric" placeholder="EJ: 1024" class="w-full bg-gray-100 dark:bg-black/40 border-2 border-transparent dark:border-white/5 rounded-[1.5rem] p-5 text-gray-900 dark:text-white font-bold text-lg focus:border-primary/50 transition-all outline-none">
                                        <button class="absolute right-4 top-1/2 -translate-y-1/2 w-12 h-12 bg-gray-200 dark:bg-white/5 rounded-xl flex items-center justify-center hover:bg-primary transition-all text-gray-600 dark:text-white" aria-label="Escanear código QR del pedido" @click="startScanner">
                                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" /></svg>
                                        </button>
                                    </div>
                                </div>
                                <div class="space-y-4">
                                    <label for="track-email" class="text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-[0.3em] ml-1">IDENTIDAD DEL CLIENTE (EMAIL)</label>
                                    <input id="track-email" v-model="email" autocomplete="email" type="email" placeholder="USUARIO@N3XT3D.COM" class="w-full bg-gray-100 dark:bg-black/40 border-2 border-transparent dark:border-white/5 rounded-[1.5rem] p-5 text-gray-900 dark:text-white font-bold text-lg focus:border-primary/50 transition-all outline-none">
                                </div>
                            </template>

                            <!-- MODO ENVÍO -->
                            <template v-else>
                                <div class="space-y-4">
                                    <label for="track-guide" class="text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-[0.3em] ml-1">Nº DE GUÍA / TRACKING</label>
                                    <input id="track-guide" v-model="trackingGuide" type="text" placeholder="EJ: 982347123" class="w-full bg-gray-100 dark:bg-black/40 border-2 border-transparent dark:border-white/5 rounded-[1.5rem] p-5 text-gray-900 dark:text-white font-bold text-lg focus:border-primary/50 transition-all outline-none">
                                </div>
                                <div class="space-y-4">
                                    <label for="track-carrier" class="text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-[0.3em] ml-1">TRANSPORTADORA (OPCIONAL)</label>
                                    <input id="track-carrier" v-model="carrier" type="text" placeholder="EJ: SERVIENTREGA" class="w-full bg-gray-100 dark:bg-black/40 border-2 border-transparent dark:border-white/5 rounded-[1.5rem] p-5 text-gray-900 dark:text-white font-bold text-lg focus:border-primary/50 transition-all outline-none">
                                </div>
                            </template>

                        </div>
                        <button :disabled="loading" class="split-btn w-full mt-10 bg-primary text-white py-6 rounded-[1.5rem] font-black text-sm shadow-xl hover:shadow-primary/30 hover:-translate-y-1 transition-all flex items-center justify-center gap-4 active:scale-95 uppercase tracking-[0.3em] relative z-10" @click="trackOrder">
                            <span v-if="loading" class="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                            <span>{{ loading ? 'Buscando...' : 'Buscar pedido' }}</span>
                        </button>
                        <div v-if="error" role="alert" class="mt-8 text-center bg-rose-500/10 p-6 rounded-2xl border border-rose-500/20">
                            <p class="text-rose-500 font-black text-[10px] uppercase tracking-widest mb-2">No se encontro el pedido</p>
                            <p class="text-gray-600 dark:text-gray-400 text-xs font-bold">{{ error }}</p>
                        </div>
                        <div v-if="orderId && !email && searchMode === 'project'" class="mt-8 text-center bg-primary/5 p-6 rounded-2xl border border-primary/10">
                            <p class="text-primary font-bold text-[10px] uppercase tracking-widest mb-2">Verificacion necesaria</p>
                            <p class="text-gray-600 dark:text-gray-400 text-xs font-medium mb-4">Ingresa tu correo para consultar el avance de la Orden #{{ orderId }}.</p>
                            <div class="flex gap-3 max-w-sm mx-auto">
                              <input v-model="email" type="email" aria-label="Correo electrónico" autocomplete="email" placeholder="tu@email.com" class="flex-1 bg-white dark:bg-black/40 border border-gray-200 dark:border-white/10 rounded-xl px-4 py-3 text-sm font-bold text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary/20 transition-all">
                              <button class="px-6 py-3 bg-primary text-white rounded-xl font-black text-[9px] uppercase tracking-widest hover:bg-emerald-600 transition-all active:scale-95" @click="trackOrder">
                                Buscar
                              </button>
                            </div>
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
                                <p class="text-gray-500 dark:text-gray-400 font-bold mt-4 uppercase text-[10px] tracking-[0.4em]">TECNOLOGÍA: {{ order.technology }} • MATERIAL: {{ order.material || order.material_name || 'ESTÁNDAR' }}</p>
                            </div>
                            <div class="bg-white p-4 rounded-[2.5rem] shadow-2xl border-8 border-primary/10 flex flex-col items-center gap-4 group hover:scale-105 transition-transform">
                                <qrcode-vue :value="shareUrl" :size="120" level="H" :render-as="'svg'" background="#ffffff" foreground="#000000" />
                                <span class="text-[8px] font-black text-black uppercase tracking-widest opacity-40">Compartir enlace</span>
                            </div>
                        </div>

                        <!-- Pedido cancelado: sin progreso -->
                        <div v-if="isCancelled" role="status" class="mb-12 text-center bg-rose-500/10 p-8 rounded-[2rem] border border-rose-500/20">
                            <p class="text-rose-500 font-black text-sm uppercase tracking-[0.3em] mb-2">Pedido cancelado</p>
                            <p class="text-gray-600 dark:text-gray-400 text-xs font-bold">Este pedido fue cancelado. Si tienes dudas, contáctanos por WhatsApp.</p>
                        </div>

                        <!-- Timeline HUD -->
                        <div v-else class="relative py-12 mb-20 px-4">
                            <div class="absolute top-1/2 left-0 w-full h-2 bg-gray-100 dark:bg-white/5 -translate-y-1/2 rounded-full overflow-hidden">
                                <div class="h-full bg-primary transition-all duration-[2000ms] shadow-[0_0_20px_#1e3a34]" :style="{ width: progressWidth + '%' }"></div>
                            </div>
                            <div class="flex justify-between relative">
                                <div v-for="(step, index) in statusSteps" :key="step.id" class="flex flex-col items-center relative">
                                    <div class="w-12 h-12 sm:w-16 sm:h-16 md:w-24 md:h-24 rounded-[1.2rem] sm:rounded-[1.5rem] md:rounded-[2.5rem] flex items-center justify-center transition-all duration-700 z-10 border-4" :class="index <= currentStatusIndex ? 'bg-primary text-white border-primary shadow-[0_0_30px_#1e3a34]' : 'bg-gray-100 dark:bg-white/5 border-gray-200 dark:border-white/10 text-gray-400 dark:text-gray-500 backdrop-blur-xl'">
                                        <svg class="w-5 h-5 sm:w-6 sm:h-6 md:w-10 md:h-10 relative z-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" :d="step.icon" />
                                        </svg>
                                    </div>
                                    <div class="absolute mt-20 sm:mt-24 md:mt-32 text-center w-16 sm:w-32">
                                        <p class="text-[8px] sm:text-[9px] md:text-[10px] font-black uppercase tracking-[0.05em] sm:tracking-[0.2em] mb-2" :class="index <= currentStatusIndex ? 'text-primary' : 'text-gray-400 dark:text-gray-600'">{{ step.label }}</p>
                                        <div v-if="index === currentStatusIndex" class="p-1.5 sm:p-3 bg-primary/5 rounded-xl border border-primary/10 animate-pulse"><p class="text-[7px] sm:text-[8px] text-primary font-bold leading-relaxed uppercase tracking-normal sm:tracking-widest break-words">{{ step.desc }}</p></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div :class="isCancelled ? 'pt-12' : 'mt-24 pt-12'" class="border-t border-gray-100 dark:border-white/5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
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
            <div v-if="showScanner" class="fixed inset-0 z-[1100] flex items-center justify-center p-6 md:p-12" role="dialog" aria-modal="true" aria-labelledby="scanner-title">
                <div class="absolute inset-0 bg-black/90 backdrop-blur-2xl" @click="stopScanner"></div>
                <div class="bg-white dark:bg-[#0a0f14] w-full max-w-2xl rounded-[3rem] border border-gray-200 dark:border-white/10 relative overflow-hidden shadow-2xl p-8 md:p-12">
                    <div class="flex justify-between items-center mb-8">
                        <h3 id="scanner-title" class="text-xl font-black uppercase tracking-[0.3em] italic text-gray-900 dark:text-white">Escaner QR</h3>
                        <button class="w-10 h-10 bg-gray-100 dark:bg-white/5 rounded-full flex items-center justify-center text-gray-400 hover:text-white transition-all" aria-label="Cerrar escáner" @click="stopScanner">✕</button>
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
