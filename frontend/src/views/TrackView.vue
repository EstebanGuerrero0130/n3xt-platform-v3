<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, nextTick } from 'vue'
import { useRoute, type LocationQueryValue } from 'vue-router'
import { api } from '../services/api'
import type { Html5QrcodeScanner } from 'html5-qrcode'
import QrcodeVue from 'qrcode.vue'
import AppNavbar from '../components/AppNavbar.vue'
import AppFooter from '../components/AppFooter.vue'
import logger from '../utils/logger'
import { usePageMeta } from '../composables/usePageMeta'
import { useFocusTrap } from '../composables/useFocusTrap'

usePageMeta({
  title: 'Rastrear Pedido 3D | N3XT 3D',
  description: 'Consulta el estado de tu pedido de impresión 3D en tiempo real. Rastreo por proyecto o por guía de envío.',
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

// Foco atrapado en el modal del escáner, Escape cierra y devuelve el foco al botón
const scannerPanel = ref<HTMLElement | null>(null)
useFocusTrap(scannerPanel, showScanner, { onEscape: () => { stopScanner() } })

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
        throw new Error('Para rastrear este pedido escribe el correo electrónico con el que lo hiciste.')
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
  { id: 'pending', label: 'Recibido', desc: 'Tu pedido está en cola de revisión.', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
  { id: 'printing', label: 'En impresión', desc: 'Tus piezas están en la impresora.', icon: 'M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z' },
  { id: 'post-processing', label: 'Acabado', desc: 'Limpieza, curado y post-procesado.', icon: 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-7.714 2.143L11 21l-2.286-6.857L1 12l7.714-2.143L11 3z' },
  { id: 'completed', label: 'Listo', desc: 'Listo para entrega o envío.', icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4' }
]

// Solo presentación: nombre visible del estado actual
const STATUS_LABEL: Record<string, string> = {
  'pending': 'Recibido',
  'printing': 'En impresión',
  'post-processing': 'En acabado',
  'completed': 'Listo',
  'shipped': 'Enviado',
  'delivered': 'Entregado',
  'cancelled': 'Cancelado'
}

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
const statusLabel = computed(() => STATUS_LABEL[order.value?.status ?? ''] ?? statusSteps[currentStatusIndex.value].label)
const orderDate = computed(() =>
  order.value?.created_at
    ? new Date(order.value.created_at).toLocaleDateString('es-CO', { day: 'numeric', month: 'long', year: 'numeric' })
    : 'Pendiente'
)

const shareUrl = computed(() => {
  if (!order.value) return ''
  const params = new URLSearchParams({ order_id: String(order.value.id) })
  const shareEmail = order.value.customer_email || email.value
  if (shareEmail) params.set('email', shareEmail)
  return `${window.location.origin}/track?${params.toString()}`
})
</script>

<template>
  <div class="min-h-screen flex flex-col bg-canvas text-ink">
    <AppNavbar active-tab="track" />

    <main id="main" class="flex-1 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 md:py-16">
      <header class="mb-10 md:mb-14 grid lg:grid-cols-12 gap-6 lg:items-end">
        <h1 class="section-title lg:col-span-7">Rastrea tu <span class="section-title-accent">pedido</span></h1>
        <p class="section-lead lg:col-span-5">Consulta en qué paso va tu pedido con el número y el correo con el que lo hiciste.</p>
      </header>

      <!-- Formulario + guía de estados -->
      <div v-if="!order" class="grid lg:grid-cols-12 gap-6 lg:gap-10 items-start">
      <section class="lg:col-span-7 bg-surface rounded-[var(--radius-panel)] p-5 sm:p-8" aria-label="Buscar pedido">
        <div class="flex flex-wrap gap-2 mb-6" role="group" aria-label="Tipo de búsqueda">
          <button type="button" class="chip" :aria-pressed="searchMode === 'project'" @click="searchMode = 'project'">Número de pedido</button>
          <button type="button" class="chip" :aria-pressed="searchMode === 'shipping'" @click="searchMode = 'shipping'">Guía de envío</button>
        </div>

        <form class="space-y-5" novalidate @submit.prevent="trackOrder">
          <template v-if="searchMode === 'project'">
            <div>
              <label for="track-order-id" class="block text-sm font-medium text-ink mb-2">Número de pedido</label>
              <input id="track-order-id" v-model="orderId" type="text" inputmode="numeric" placeholder="Ej: 1024" class="w-full h-11 px-4 rounded-[var(--radius-control)] border border-line bg-canvas text-ink placeholder:text-muted/70 focus:outline-none focus:border-primary focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-[var(--brand-text)]">
            </div>
            <div>
              <label for="track-email" class="block text-sm font-medium text-ink mb-2">Correo electrónico</label>
              <input id="track-email" v-model="email" autocomplete="email" type="email" placeholder="tu@correo.com" aria-describedby="track-email-hint" class="w-full h-11 px-4 rounded-[var(--radius-control)] border border-line bg-canvas text-ink placeholder:text-muted/70 focus:outline-none focus:border-primary focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-[var(--brand-text)]">
              <p id="track-email-hint" class="mt-1.5 text-sm text-muted">
                <template v-if="orderId && !email">Escribe el correo del pedido #{{ orderId }} para ver su avance.</template>
                <template v-else>El mismo correo que usaste al hacer el pedido.</template>
              </p>
            </div>
          </template>

          <template v-else>
            <div>
              <label for="track-guide" class="block text-sm font-medium text-ink mb-2">Número de guía</label>
              <input id="track-guide" v-model="trackingGuide" type="text" placeholder="Ej: 982347123" class="w-full h-11 px-4 rounded-[var(--radius-control)] border border-line bg-canvas text-ink placeholder:text-muted/70 focus:outline-none focus:border-primary focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-[var(--brand-text)]">
            </div>
            <div>
              <label for="track-carrier" class="block text-sm font-medium text-ink mb-2">Transportadora <span class="font-normal text-muted">(opcional)</span></label>
              <input id="track-carrier" v-model="carrier" type="text" placeholder="Ej: Servientrega" class="w-full h-11 px-4 rounded-[var(--radius-control)] border border-line bg-canvas text-ink placeholder:text-muted/70 focus:outline-none focus:border-primary focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-[var(--brand-text)]">
            </div>
          </template>

          <div class="flex flex-col sm:flex-row gap-3 pt-1">
            <button type="submit" :disabled="loading" class="btn-primary btn-lg sm:flex-1">
              <span v-if="loading" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" aria-hidden="true"></span>
              <span>{{ loading ? 'Buscando…' : 'Rastrear pedido' }}</span>
            </button>
            <button v-if="searchMode === 'project'" type="button" class="btn-secondary btn-lg" @click="startScanner">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" /></svg>
              Escanear QR
            </button>
          </div>
        </form>

        <div v-if="error" role="alert" class="mt-6 p-4 rounded-[var(--radius-control)] border border-danger/30 bg-danger/5">
          <p class="font-semibold text-danger">No pudimos mostrar el pedido</p>
          <p class="mt-1 text-sm text-ink">{{ error }}</p>
        </div>
      </section>

      <!-- Qué significa cada estado -->
      <aside class="lg:col-span-5" aria-labelledby="track-steps-title">
        <h2 id="track-steps-title" class="text-xl md:text-2xl font-extrabold tracking-tight">Así avanza <span class="text-brand">tu pedido</span></h2>
        <ol class="mt-6 space-y-3">
          <li v-for="(step, index) in statusSteps" :key="step.id" class="flex gap-4 p-4 bg-surface rounded-[var(--radius-card)]">
            <span class="shrink-0 w-10 h-10 rounded-full bg-primary/10 text-brand flex items-center justify-center" aria-hidden="true">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="step.icon" /></svg>
            </span>
            <div>
              <p class="font-semibold text-ink"><span class="text-muted tabular-nums mr-1.5">{{ index + 1 }}.</span>{{ step.label }}</p>
              <p class="mt-0.5 text-sm text-muted">{{ step.desc }}</p>
            </div>
          </li>
        </ol>
        <p class="mt-5 text-sm text-muted">¿No encuentras tu número de pedido? Está en el correo de confirmación, o escríbenos por WhatsApp.</p>
      </aside>
      </div>

      <!-- Resultado -->
      <div v-else class="space-y-6 max-w-4xl">
        <section class="bg-surface rounded-[var(--radius-panel)] p-5 sm:p-8" aria-labelledby="track-result-title">
          <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 pb-6 border-b border-line">
            <div>
              <p v-if="order.customer_name" class="text-sm text-muted">Hola, {{ order.customer_name }}</p>
              <h2 id="track-result-title" class="mt-1 text-3xl md:text-4xl font-extrabold tracking-tight">Pedido #{{ order.id }}</h2>
            </div>
            <span
              role="status"
              :class="isCancelled ? 'bg-danger/10 text-danger' : 'bg-primary text-white'"
              class="self-start inline-flex items-center h-8 px-3.5 rounded-full text-sm font-semibold"
            >{{ statusLabel }}</span>
          </div>

          <dl class="grid grid-cols-2 lg:grid-cols-4 gap-x-6 gap-y-4 py-6 border-b border-line">
            <div>
              <dt class="text-xs text-muted">Fecha</dt>
              <dd class="mt-1 font-medium">{{ orderDate }}</dd>
            </div>
            <div>
              <dt class="text-xs text-muted">Material</dt>
              <dd class="mt-1 font-medium break-words">{{ order.material || order.material_name || 'Estándar' }}</dd>
            </div>
            <div v-if="order.technology">
              <dt class="text-xs text-muted">Tecnología</dt>
              <dd class="mt-1 font-medium break-words">{{ order.technology }}</dd>
            </div>
            <div>
              <dt class="text-xs text-muted">Pago</dt>
              <dd class="mt-1 font-medium" :class="order.is_paid ? 'text-brand' : 'text-amber-700 dark:text-amber-400'">{{ order.is_paid ? 'Completo' : 'Pendiente' }}</dd>
            </div>
          </dl>

          <!-- Pedido cancelado: sin progreso -->
          <div v-if="isCancelled" class="mt-6 p-4 rounded-[var(--radius-control)] border border-danger/30 bg-danger/5">
            <p class="font-semibold text-danger">Pedido cancelado</p>
            <p class="mt-1 text-sm text-ink">Este pedido fue cancelado. Si tienes dudas, escríbenos por WhatsApp.</p>
          </div>

          <!-- Línea de tiempo: vertical en móvil, horizontal desde md -->
          <ol v-else class="mt-8 flex flex-col md:flex-row" aria-label="Progreso del pedido">
            <li
              v-for="(step, index) in statusSteps"
              :key="step.id"
              class="relative flex md:flex-col md:flex-1 gap-4 md:gap-3 pb-8 md:pb-0 last:pb-0"
              :aria-current="index === currentStatusIndex ? 'step' : undefined"
            >
              <span
                v-if="index < statusSteps.length - 1"
                aria-hidden="true"
                :class="index < currentStatusIndex ? 'bg-primary' : 'bg-line'"
                class="absolute left-4 top-9 bottom-1 w-px md:left-11 md:right-3 md:top-4 md:bottom-auto md:w-auto md:h-px"
              ></span>
              <span
                :class="index < currentStatusIndex
                  ? 'bg-surface border-primary text-brand'
                  : index === currentStatusIndex
                    ? 'bg-primary border-primary text-white'
                    : 'bg-surface border-line text-muted'"
                class="relative shrink-0 w-8 h-8 rounded-full border flex items-center justify-center"
              >
                <svg v-if="index < currentStatusIndex" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="step.icon" /></svg>
              </span>
              <div class="md:pr-4">
                <p :class="index <= currentStatusIndex ? 'text-ink font-semibold' : 'text-muted font-medium'">
                  {{ step.label }}
                  <span v-if="index < currentStatusIndex" class="sr-only">(completado)</span>
                </p>
                <p class="mt-0.5 text-sm" :class="index === currentStatusIndex ? 'text-muted' : 'text-muted/80'">{{ step.desc }}</p>
              </div>
            </li>
          </ol>
        </section>

        <div class="grid gap-6" :class="order.tracking_guide ? 'sm:grid-cols-2' : ''">
          <!-- Envío -->
          <section v-if="order.tracking_guide" class="bg-surface rounded-[var(--radius-card)] p-5 sm:p-6" aria-labelledby="track-shipping-title">
            <h3 id="track-shipping-title" class="text-lg md:text-xl font-extrabold tracking-tight">Envío</h3>
            <dl class="mt-4 space-y-3">
              <div>
                <dt class="text-xs text-muted">Transportadora</dt>
                <dd class="mt-1 font-medium">{{ order.tracking_carrier || 'Por confirmar' }}</dd>
              </div>
              <div>
                <dt class="text-xs text-muted">Número de guía</dt>
                <dd class="mt-1 font-medium tabular-nums break-all">{{ order.tracking_guide }}</dd>
              </div>
            </dl>
            <p class="mt-4 text-sm text-muted">Consulta este número en la página de la transportadora para ver la entrega.</p>
          </section>

          <!-- Compartir -->
          <section class="bg-surface rounded-[var(--radius-card)] p-5 sm:p-6 flex items-center gap-5" aria-labelledby="track-share-title">
            <div class="shrink-0 p-2 bg-white rounded-[var(--radius-control)] border border-line">
              <qrcode-vue :value="shareUrl" :size="96" level="H" :render-as="'svg'" background="#ffffff" foreground="#101814" />
            </div>
            <div>
              <h3 id="track-share-title" class="font-semibold">Seguir desde otro dispositivo</h3>
              <p class="mt-1 text-sm text-muted">Escanea este código para abrir el seguimiento del pedido.</p>
            </div>
          </section>
        </div>

        <button type="button" class="btn-outlined" @click="order = null">Consultar otro pedido</button>
      </div>
    </main>

    <!-- Escáner QR -->
    <transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition duration-150 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0">
      <div v-if="showScanner" class="fixed inset-0 z-[1100] flex items-center justify-center p-4 sm:p-6" role="dialog" aria-modal="true" aria-labelledby="scanner-title">
        <div class="absolute inset-0 bg-black/70" @click="stopScanner"></div>
        <div ref="scannerPanel" class="relative w-full max-w-lg bg-surface border border-line rounded-[var(--radius-panel)] shadow-[0_24px_48px_-12px_rgba(0,0,0,0.35)] p-5 sm:p-6 outline-none">
          <div class="flex items-center justify-between gap-4 mb-4">
            <div>
              <h3 id="scanner-title" class="text-lg md:text-xl font-extrabold tracking-tight">Escanear QR del pedido</h3>
              <p class="text-sm text-muted">Apunta la cámara al código de tu comprobante.</p>
            </div>
            <button type="button" class="ghost-header-btn shrink-0" aria-label="Cerrar escáner" @click="stopScanner">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
          </div>
          <div id="reader" class="rounded-[var(--radius-card)] overflow-hidden border border-line"></div>
        </div>
      </div>
    </transition>

    <AppFooter />
  </div>
</template>
