<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../services/api'
import AppNavbar from '../components/AppNavbar.vue'
import AppFooter from '../components/AppFooter.vue'
import logger from '../utils/logger'
import { usePageMeta } from '../composables/usePageMeta'
import { useCart } from '../composables/useCart'

usePageMeta({
  title: 'Panel del Cliente | N3XT 3D',
  description: 'Gestiona tus pedidos de impresión 3D. Consulta el estado de tus proyectos y solicita nuevas cotizaciones.',
  noIndex: true,
})

interface CustomerOrder {
  id: number
  project_name?: string | null
  status: string
  material_name?: string | null
  estimated_weight_g?: number | null
  total_price: number
  is_paid?: boolean
  created_at?: string
  technology?: string | null
  qty?: number | null
  infill?: number | null
  estimated_duration_h?: number | null
  extras_cost?: number | null
  tracking_guide?: string | null
  tracking_carrier?: string | null
  models?: { name: string | null; qty: number; dimensions_mm?: string | null; weight_g?: number | null; product_id?: string | number | null; unit_price?: number | null; image?: string | null }[]
  extras?: { name: string; qty: number }[]
  review?: { rating: number; status: string } | null
}

interface CustomerProfile {
  id: number
  name: string
  email: string
  email_verified: boolean
  welcome_coupon?: string | null
  welcome_coupon_pct?: number
}

const customer = ref<CustomerProfile | null>(null)
const orders = ref<CustomerOrder[]>([])
const loading = ref(true)
const router = useRouter()
const cart = useCart()

// ─── Cotizaciones guardadas ───
interface SavedQuote { id: number; name: string; technology: string; estimated_total: number | null; created_at: string; models: { name: string; qty: number }[] }
const quotes = ref<SavedQuote[]>([])
const fetchQuotes = async () => {
  try {
    const data = await api.get('/customer/quotes', true)
    quotes.value = Array.isArray(data) ? data : []
  } catch (err) {
    logger.error('Error fetching quotes:', err)
  }
}
const deleteQuote = async (q: SavedQuote) => {
  if (!confirm(`¿Borrar la cotización «${q.name}»? Se borran también sus archivos.`)) return
  try {
    await api.delete(`/customer/quotes/${q.id}`)
    quotes.value = quotes.value.filter(x => x.id !== q.id)
  } catch (err) {
    alert(err instanceof Error ? err.message : 'No se pudo borrar.')
  }
}

// ─── Reseñas: se califican pedidos listos o enviados ───
const REVIEWABLE = ['completed', 'shipped']
const reviewFor = ref<number | null>(null)
const review = ref({ rating: 5, comment: '', photo: null as File | null })
const reviewError = ref('')
const sendingReview = ref(false)
const openReview = (id: number) => {
  reviewFor.value = id
  review.value = { rating: 5, comment: '', photo: null }
  reviewError.value = ''
}
const sendReview = async (order: CustomerOrder) => {
  reviewError.value = ''
  if (review.value.comment.trim().length < 10) {
    reviewError.value = 'Cuéntanos un poco más (mínimo 10 caracteres).'
    return
  }
  sendingReview.value = true
  try {
    const fd = new FormData()
    fd.append('rating', String(review.value.rating))
    fd.append('comment', review.value.comment.trim())
    if (review.value.photo) fd.append('photo', review.value.photo)
    await api.post(`/customer/orders/${order.id}/review`, fd)
    order.review = { rating: review.value.rating, status: 'pending' }
    reviewFor.value = null
  } catch (err) {
    reviewError.value = err instanceof Error ? err.message : 'No pudimos enviar la reseña.'
  } finally {
    sendingReview.value = false
  }
}

// ─── Repetir pedido ───
const repeating = ref<number | null>(null)
const repeatMsg = ref<{ id: number; text: string; ok: boolean } | null>(null)
const repeatOrder = async (order: CustomerOrder) => {
  repeatMsg.value = null
  // Catálogo: los productos vuelven al carrito; al abrirse, el carrito pone el precio de hoy
  if (order.technology === 'CATALOGO') {
    for (const m of order.models || []) {
      if (m.name) cart.add({ id: m.product_id ?? undefined, name: m.name, price: m.unit_price ?? 0, image: m.image ?? undefined }, m.qty || 1)
    }
    router.push('/carrito')
    return
  }
  if (!confirm(`¿Repetir el pedido #${order.id}? Usamos los mismos archivos y material; el taller te confirma el precio.`)) return
  repeating.value = order.id
  try {
    const res = await api.post(`/customer/orders/${order.id}/repeat`)
    repeatMsg.value = { id: order.id, ok: true, text: `Creamos el pedido #${res?.order_id}. Te llegará un correo de confirmación.` }
    await fetchOrders()
  } catch (err) {
    repeatMsg.value = { id: order.id, ok: false, text: err instanceof Error ? err.message : 'No pudimos repetir el pedido.' }
  } finally {
    repeating.value = null
  }
}

const fetchProfile = async () => {
    try {
        const data = await api.get('/customer/profile', true)
        customer.value = data
        if (data?.email_verified) { fetchOrders(); fetchQuotes() }
        else loading.value = false
    } catch (err) {
        logger.error('Error fetching profile:', err)
        logout()
    }
}

const fetchOrders = async () => {
    try {
        const data = await api.get('/customer/orders', true)
        orders.value = Array.isArray(data) ? data : []
    } catch (err) {
        logger.error('Error fetching orders:', err)
    } finally {
        loading.value = false
    }
}

// ─── Verificación del correo ───
const code = ref('')
const verifying = ref(false)
const verifyError = ref('')
const resendMsg = ref('')
const resending = ref(false)

const submitCode = async () => {
    verifyError.value = ''
    if (!/^\d{6}$/.test(code.value.trim())) {
        verifyError.value = 'El código tiene 6 números.'
        return
    }
    verifying.value = true
    try {
        await api.post('/customer/verify', { code: code.value.trim() })
        code.value = ''
        loading.value = true
        await fetchProfile()
    } catch (err) {
        verifyError.value = err instanceof Error ? err.message : 'No pudimos verificar el código.'
    } finally {
        verifying.value = false
    }
}

const resendCode = async () => {
    resendMsg.value = ''
    verifyError.value = ''
    resending.value = true
    try {
        await api.post('/customer/verify/resend')
        resendMsg.value = 'Te enviamos un código nuevo. Revisa también la carpeta de spam.'
    } catch (err) {
        verifyError.value = err instanceof Error ? err.message : 'No pudimos enviar el código.'
    } finally {
        resending.value = false
    }
}

const couponCopied = ref(false)
const copyCoupon = async () => {
    if (!customer.value?.welcome_coupon) return
    try {
        await navigator.clipboard.writeText(customer.value.welcome_coupon)
        couponCopied.value = true
        setTimeout(() => { couponCopied.value = false }, 2000)
    } catch { /* sin portapapeles: el código queda visible para copiarlo a mano */ }
}

const logout = async () => {
    try {
        await api.post('/customer/logout')
    } catch {
        // Silent fail — session might already be invalid
    }
    router.push('/')
}

onMounted(() => {
  fetchProfile()
})

// Estados en los que el pedido ya no avanza
const CLOSED = ['completed', 'shipped', 'cancelled']
const activeCount = computed(() => orders.value.filter(o => !CLOSED.includes(o.status)).length)
const doneCount = computed(() => orders.value.filter(o => o.status === 'completed' || o.status === 'shipped').length)
const totalSpent = computed(() => orders.value
  .filter(o => o.status !== 'cancelled')
  .reduce((sum, o) => sum + Number(o.total_price || 0), 0))

// Pedido desplegado (uno a la vez)
const openId = ref<number | null>(null)
const toggle = (id: number) => { openId.value = openId.value === id ? null : id }
const hours = (h?: number | null) => {
  if (!h) return ''
  const total = Math.round(h * 60)
  return total < 60 ? `${total} min` : `${Math.floor(total / 60)} h ${total % 60 ? (total % 60) + ' min' : ''}`.trim()
}

const money = (n: number) => '$' + Math.round(n).toLocaleString('es-CO')
const orderDate = (iso?: string) => iso
  ? new Date(iso).toLocaleDateString('es-CO', { day: 'numeric', month: 'short', year: 'numeric' })
  : ''
const trackLink = (o: CustomerOrder) => ({ path: '/track', query: { order_id: String(o.id), email: customer.value?.email ?? '' } })

const getStatusClass = (status: string) => {
    switch (status) {
        case 'pending': return 'bg-warning/10 text-warning'
        case 'printing':
        case 'post-processing': return 'bg-primary text-white'
        case 'completed':
        case 'shipped': return 'bg-success/10 text-success'
        case 'cancelled': return 'bg-danger/10 text-danger'
        default: return 'bg-surface-2 text-muted'
    }
}

const getStatusLabel = (status: string) => {
    switch (status) {
        case 'pending': return 'Recibido'
        case 'printing': return 'En impresión'
        case 'post-processing': return 'En acabado'
        case 'completed': return 'Listo'
        case 'shipped': return 'Enviado'
        case 'cancelled': return 'Cancelado'
        default: return 'Sin estado'
    }
}
</script>

<template>
  <div class="min-h-screen flex flex-col bg-canvas text-ink overflow-x-clip">
    <AppNavbar active-tab="dashboard" subtext="Mi cuenta" />

    <main id="main" class="flex-1 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 md:py-16">
      <header class="mb-10 md:mb-14 flex flex-col lg:flex-row lg:items-end lg:justify-between gap-6">
        <div>
          <p class="text-muted">{{ customer?.email }}</p>
          <h1 class="mt-2 section-title">Hola, <span class="section-title-accent">{{ customer?.name?.split(' ')[0] || 'maker' }}</span></h1>
        </div>
        <div class="flex flex-wrap gap-3">
          <router-link to="/quote" class="btn-primary btn-lg">Nueva cotización</router-link>
          <button type="button" class="btn-outlined btn-lg" @click="logout">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            Cerrar sesión
          </button>
        </div>
      </header>

      <!-- Verificar el correo: sin esto no se muestran pedidos -->
      <section v-if="customer && !customer.email_verified" class="mb-12 bg-surface rounded-[var(--radius-panel)] p-6 md:p-8 grid lg:grid-cols-12 gap-6 lg:items-center" aria-labelledby="verify-title">
        <div class="lg:col-span-6">
          <h2 id="verify-title" class="text-xl md:text-2xl font-extrabold tracking-tight">Confirma tu <span class="text-brand">correo</span></h2>
          <p class="mt-2 text-muted">Te enviamos un código de 6 números a <strong class="text-ink">{{ customer.email }}</strong>. Al confirmarlo verás tus pedidos y recibirás tu cupón de bienvenida.</p>
        </div>
        <form class="lg:col-span-6" novalidate @submit.prevent="submitCode">
          <label for="verify-code" class="block text-sm font-medium mb-2">Código de verificación</label>
          <div class="flex flex-col sm:flex-row gap-3">
            <input id="verify-code" v-model="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="000000" class="field sm:max-w-[12rem] text-center text-lg tracking-[0.4em] tabular-nums" :aria-invalid="!!verifyError" aria-describedby="verify-help">
            <button type="submit" class="btn-primary btn-lg" :disabled="verifying">{{ verifying ? 'Verificando…' : 'Confirmar' }}</button>
          </div>
          <p id="verify-help" class="mt-3 text-sm" :class="verifyError ? 'text-danger' : 'text-muted'" :role="verifyError ? 'alert' : undefined">
            <template v-if="verifyError">{{ verifyError }}</template>
            <template v-else-if="resendMsg">{{ resendMsg }}</template>
            <template v-else>El código vence en 30 minutos.</template>
          </p>
          <button type="button" class="mt-2 text-sm font-semibold text-brand hover:underline disabled:opacity-50" :disabled="resending" @click="resendCode">{{ resending ? 'Enviando…' : 'Enviar un código nuevo' }}</button>
        </form>
      </section>

      <!-- Cupón de bienvenida (solo verificado y sin usar) -->
      <section v-if="customer?.welcome_coupon" class="mb-12 rounded-[var(--radius-panel)] p-6 md:p-8 bg-primary/10 flex flex-col md:flex-row md:items-center gap-6" aria-labelledby="coupon-title">
        <div class="flex-1">
          <h2 id="coupon-title" class="text-xl md:text-2xl font-extrabold tracking-tight">{{ customer.welcome_coupon_pct ?? 10 }}% en tu <span class="text-brand">primer pedido</span></h2>
          <p class="mt-2 text-muted">Úsalo en el cotizador con el correo de tu cuenta. Es de un solo uso.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
          <span class="inline-flex items-center h-12 px-5 rounded-[var(--radius-control)] border-2 border-dashed border-primary font-mono text-lg font-bold tracking-wider text-brand">{{ customer.welcome_coupon }}</span>
          <button type="button" class="btn-outlined btn-lg" @click="copyCoupon">{{ couponCopied ? 'Copiado' : 'Copiar' }}</button>
          <router-link to="/quote" class="btn-primary btn-lg">Cotizar</router-link>
        </div>
      </section>

      <!-- Resumen (solo datos reales de sus pedidos) -->
      <dl v-if="customer?.email_verified" class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-12">
        <div class="bg-surface p-6 rounded-[var(--radius-card)]">
          <dt class="text-sm text-muted">Pedidos en curso</dt>
          <dd class="mt-2 text-4xl font-extrabold tabular-nums">{{ loading ? '—' : activeCount }}</dd>
        </div>
        <div class="bg-surface p-6 rounded-[var(--radius-card)]">
          <dt class="text-sm text-muted">Pedidos terminados</dt>
          <dd class="mt-2 text-4xl font-extrabold tabular-nums">{{ loading ? '—' : doneCount }}</dd>
        </div>
        <div class="bg-surface p-6 rounded-[var(--radius-card)]">
          <dt class="text-sm text-muted">Total en pedidos</dt>
          <dd class="mt-2 text-4xl font-extrabold tabular-nums">{{ loading ? '—' : money(totalSpent) }}</dd>
        </div>
      </dl>

      <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-start">
        <!-- Pedidos -->
        <section v-if="!customer || customer.email_verified" class="lg:col-span-8" aria-labelledby="orders-title">
          <h2 id="orders-title" class="text-xl md:text-2xl font-extrabold tracking-tight">Mis <span class="text-brand">pedidos</span></h2>

          <div v-if="loading" class="mt-6 space-y-3" aria-hidden="true">
            <div v-for="n in 3" :key="n" class="h-24 bg-surface rounded-[var(--radius-card)] animate-pulse"></div>
          </div>
          <p v-if="loading" class="sr-only" role="status">Cargando pedidos…</p>

          <div v-else-if="orders.length === 0" class="mt-6 bg-surface rounded-[var(--radius-card)] p-8 md:p-12 text-center">
            <svg class="w-12 h-12 mx-auto text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            <h3 class="mt-5 text-xl font-extrabold">Aún no tienes pedidos</h3>
            <p class="mt-2 text-muted">Sube tu archivo 3D y te damos un precio en minutos.</p>
            <router-link to="/quote" class="mt-6 btn-primary btn-lg">Cotizar mi primera pieza</router-link>
          </div>

          <ul v-else class="mt-6 space-y-3">
            <li v-for="order in orders" :key="order.id" class="bg-surface rounded-[var(--radius-card)] overflow-hidden">
              <button
                type="button"
                class="w-full text-left flex flex-col sm:flex-row sm:items-center gap-4 p-5 hover:bg-surface-2 transition-colors"
                :aria-expanded="openId === order.id"
                :aria-controls="`order-detail-${order.id}`"
                @click="toggle(order.id)"
              >
                <div class="min-w-0 flex-1">
                  <p class="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <span class="font-semibold text-ink truncate">{{ order.project_name || 'Pedido de impresión 3D' }}</span>
                    <span class="text-sm text-muted tabular-nums">#{{ order.id }}</span>
                  </p>
                  <p class="mt-1 text-sm text-muted">
                    <template v-if="order.material_name">{{ order.material_name }}</template>
                    <template v-if="order.material_name && order.created_at"> · </template>
                    <template v-if="order.created_at">{{ orderDate(order.created_at) }}</template>
                  </p>
                </div>
                <div class="flex items-center justify-between sm:justify-end gap-4 sm:gap-6">
                  <span class="inline-flex items-center h-8 px-3.5 rounded-full text-sm font-semibold" :class="getStatusClass(order.status)">{{ getStatusLabel(order.status) }}</span>
                  <span class="font-extrabold tabular-nums">{{ money(Number(order.total_price)) }}</span>
                  <svg class="w-5 h-5 text-muted transition-transform" :class="openId === order.id ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </div>
              </button>

              <!-- Detalle de la pieza -->
              <div v-if="openId === order.id" :id="`order-detail-${order.id}`" class="px-5 pb-5">
                <div v-if="order.models?.length" class="rounded-[var(--radius-control)] bg-canvas p-4">
                  <p class="text-sm font-semibold mb-3">{{ order.models.length === 1 ? 'Pieza' : `Piezas (${order.models.length})` }}</p>
                  <ul class="space-y-3">
                    <li v-for="(m, mi) in order.models" :key="mi" class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                      <span class="min-w-0 font-medium break-all">{{ m.name || `Modelo ${mi + 1}` }} <span class="text-muted tabular-nums">× {{ m.qty }}</span></span>
                      <span class="text-sm text-muted tabular-nums">
                        <template v-if="m.dimensions_mm">{{ m.dimensions_mm }} mm</template>
                        <template v-if="m.dimensions_mm && m.weight_g"> · </template>
                        <template v-if="m.weight_g">{{ Math.round(m.weight_g) }} g</template>
                      </span>
                    </li>
                  </ul>
                </div>

                <dl class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-x-6 gap-y-4 text-sm">
                  <div v-if="order.technology"><dt class="text-muted">Tecnología</dt><dd class="mt-0.5 font-medium">{{ order.technology === 'CATALOGO' ? 'Producto de catálogo' : order.technology }}</dd></div>
                  <div v-if="order.material_name"><dt class="text-muted">Material</dt><dd class="mt-0.5 font-medium">{{ order.material_name }}</dd></div>
                  <div v-if="order.infill"><dt class="text-muted">Relleno</dt><dd class="mt-0.5 font-medium tabular-nums">{{ order.infill }} %</dd></div>
                  <div v-if="order.qty"><dt class="text-muted">Unidades</dt><dd class="mt-0.5 font-medium tabular-nums">{{ order.qty }}</dd></div>
                  <div v-if="order.estimated_weight_g"><dt class="text-muted">Peso estimado</dt><dd class="mt-0.5 font-medium tabular-nums">{{ Math.round(order.estimated_weight_g) }} g</dd></div>
                  <div v-if="order.estimated_duration_h"><dt class="text-muted">Tiempo de impresión</dt><dd class="mt-0.5 font-medium tabular-nums">{{ hours(order.estimated_duration_h) }}</dd></div>
                  <div><dt class="text-muted">Pago</dt><dd class="mt-0.5 font-medium" :class="order.is_paid ? 'text-success' : 'text-warning'">{{ order.is_paid ? 'Completo' : 'Pendiente' }}</dd></div>
                  <div v-if="order.tracking_guide"><dt class="text-muted">Guía de envío</dt><dd class="mt-0.5 font-medium tabular-nums break-words">{{ order.tracking_carrier ? order.tracking_carrier + ' · ' : '' }}{{ order.tracking_guide }}</dd></div>
                </dl>

                <p v-if="order.extras?.length" class="mt-4 text-sm"><span class="text-muted">Extras:</span> {{ order.extras.map(e => e.qty > 1 ? `${e.name} × ${e.qty}` : e.name).join(', ') }}</p>

                <div class="mt-5 flex flex-wrap gap-3">
                  <router-link :to="trackLink(order)" class="btn-outlined">
                    Ver seguimiento
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                  </router-link>
                  <button type="button" class="btn-outlined" :disabled="repeating === order.id" @click="repeatOrder(order)">
                    {{ repeating === order.id ? 'Repitiendo…' : order.technology === 'CATALOGO' ? 'Volver a pedir' : 'Repetir pedido' }}
                  </button>
                </div>
                <p v-if="repeatMsg?.id === order.id" :role="repeatMsg.ok ? 'status' : 'alert'" :class="['mt-3 text-sm', repeatMsg.ok ? 'text-success' : 'text-danger']">{{ repeatMsg.text }}</p>

                <!-- Reseña -->
                <div v-if="REVIEWABLE.includes(order.status)" class="mt-5 pt-5 border-t border-line">
                  <p v-if="order.review" class="text-sm">
                    Tu calificación: <span class="text-warning tracking-wider" :aria-label="`${order.review.rating} de 5 estrellas`">{{ '★'.repeat(order.review.rating) }}<span class="text-muted">{{ '★'.repeat(5 - order.review.rating) }}</span></span>
                    <span class="ml-2 text-muted">{{ order.review.status === 'approved' ? '· publicada' : order.review.status === 'rejected' ? '· no publicada' : '· en revisión' }}</span>
                  </p>
                  <button v-else-if="reviewFor !== order.id" type="button" class="btn-outlined btn-sm" @click="openReview(order.id)">Calificar este pedido</button>
                  <form v-else class="space-y-4" novalidate @submit.prevent="sendReview(order)">
                    <fieldset>
                      <legend class="block text-sm font-medium mb-2">¿Cómo te fue?</legend>
                      <div class="flex gap-1" role="radiogroup">
                        <label v-for="n in 5" :key="n" class="cursor-pointer">
                          <input v-model="review.rating" type="radio" name="review-rating" :value="n" class="sr-only peer">
                          <span :class="['text-3xl leading-none peer-focus-visible:outline-2 peer-focus-visible:outline-[var(--brand-text)] rounded', n <= review.rating ? 'text-warning' : 'text-line']" :aria-label="`${n} ${n === 1 ? 'estrella' : 'estrellas'}`">★</span>
                        </label>
                      </div>
                    </fieldset>
                    <div>
                      <label :for="`review-comment-${order.id}`" class="block text-sm font-medium mb-2">Tu comentario</label>
                      <textarea :id="`review-comment-${order.id}`" v-model="review.comment" rows="3" maxlength="1000" class="field !h-auto py-2.5" placeholder="¿Qué te pareció la pieza, el acabado y la atención?"></textarea>
                    </div>
                    <div>
                      <label :for="`review-photo-${order.id}`" class="block text-sm font-medium mb-2">Foto de tu pieza <span class="font-normal text-muted">(opcional)</span></label>
                      <input :id="`review-photo-${order.id}`" type="file" accept="image/jpeg,image/png,image/webp" class="text-sm" @change="(e: any) => review.photo = e.target.files?.[0] || null">
                    </div>
                    <p v-if="reviewError" role="alert" class="text-sm text-danger">{{ reviewError }}</p>
                    <div class="flex gap-2">
                      <button type="submit" class="btn-primary btn-sm" :disabled="sendingReview">{{ sendingReview ? 'Enviando…' : 'Enviar reseña' }}</button>
                      <button type="button" class="btn-outlined btn-sm" :disabled="sendingReview" @click="reviewFor = null">Cancelar</button>
                    </div>
                  </form>
                </div>
              </div>
            </li>
          </ul>

          <!-- Cotizaciones guardadas -->
          <section v-if="quotes.length" class="mt-12" aria-labelledby="quotes-title">
            <h2 id="quotes-title" class="text-xl md:text-2xl font-extrabold tracking-tight">Cotizaciones <span class="text-brand">guardadas</span></h2>
            <ul class="mt-6 space-y-3">
              <li v-for="q in quotes" :key="q.id" class="p-5 bg-surface rounded-[var(--radius-card)] flex flex-col sm:flex-row sm:items-center gap-4">
                <div class="min-w-0 flex-1">
                  <p class="font-semibold truncate">{{ q.name }}</p>
                  <p class="mt-1 text-sm text-muted">
                    {{ q.models.map(m => m.qty > 1 ? `${m.name} × ${m.qty}` : m.name).join(', ') }} · {{ q.technology }} · {{ orderDate(q.created_at) }}
                  </p>
                </div>
                <span v-if="q.estimated_total" class="font-bold tabular-nums">{{ money(q.estimated_total) }}</span>
                <div class="flex gap-2">
                  <router-link :to="{ path: '/quote', query: { cotizacion: q.id } }" class="btn-primary btn-sm">Abrir en el cotizador</router-link>
                  <button type="button" class="icon-btn text-muted hover:text-danger hover:bg-danger/10" :aria-label="`Borrar ${q.name}`" @click="deleteQuote(q)">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                  </button>
                </div>
              </li>
            </ul>
          </section>
        </section>

        <!-- Ayuda -->
        <aside class="lg:col-span-4" aria-labelledby="help-title">
          <h2 id="help-title" class="text-xl md:text-2xl font-extrabold tracking-tight">¿Necesitas <span class="text-brand">ayuda?</span></h2>
          <ul class="mt-6 space-y-3">
            <li>
              <a href="https://wa.me/573118796416" target="_blank" rel="noopener" class="flex items-center gap-4 p-4 bg-surface rounded-[var(--radius-card)] hover:bg-surface-2 transition-colors">
                <span class="shrink-0 w-10 h-10 rounded-full bg-primary/10 text-brand flex items-center justify-center" aria-hidden="true">
                  <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                </span>
                <span><span class="block font-semibold">WhatsApp</span><span class="block text-sm text-muted">Escríbenos por tu pedido</span></span>
              </a>
            </li>
            <li>
              <a href="mailto:servicion3xt@gmail.com" class="flex items-center gap-4 p-4 bg-surface rounded-[var(--radius-card)] hover:bg-surface-2 transition-colors">
                <span class="shrink-0 w-10 h-10 rounded-full bg-primary/10 text-brand flex items-center justify-center" aria-hidden="true">
                  <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </span>
                <span class="min-w-0"><span class="block font-semibold">Correo</span><span class="block text-sm text-muted break-all">servicion3xt@gmail.com</span></span>
              </a>
            </li>
            <li>
              <router-link :to="{ path: '/quote', hash: '#material-guide-title' }" class="flex items-center gap-4 p-4 bg-surface rounded-[var(--radius-card)] hover:bg-surface-2 transition-colors">
                <span class="shrink-0 w-10 h-10 rounded-full bg-primary/10 text-brand flex items-center justify-center" aria-hidden="true">
                  <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                </span>
                <span><span class="block font-semibold">Guía de materiales</span><span class="block text-sm text-muted">¿PLA, PETG o resina?</span></span>
              </router-link>
            </li>
          </ul>
        </aside>
      </div>
    </main>

    <AppFooter />
  </div>
</template>
