<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { api } from '../services/api'
import { useCart } from '../composables/useCart'
import { formatCOP } from '../utils/format'
import { catalogWhatsAppUrl } from '../utils/whatsapp'
import { usePageMeta } from '../composables/usePageMeta'
import AppNavbar from '../components/AppNavbar.vue'
import AppFooter from '../components/AppFooter.vue'

usePageMeta({ title: 'Tu carrito | N3XT 3D', description: 'Productos del catálogo N3XT 3D que vas a pedir.', noIndex: true })

const { items, total, setQty, remove, clear, syncWithCatalog } = useCart()
const account = ref<{ role: string | null; verified: boolean }>({ role: null, verified: false })
const companyPhone = ref('')
const name = ref('')
const sending = ref(false)
const error = ref('')
const savedOrderId = ref<number | null>(null)

onMounted(async () => {
  const [auth, settings] = await Promise.all([api.checkAuth(), api.get('/settings').catch(() => null)])
  account.value = { role: auth.role, verified: !!auth.user?.email_verified }
  if (auth.user?.name) name.value = auth.user.name
  companyPhone.value = settings?.company?.phone || ''
  if (Array.isArray(settings?.web?.catalog)) syncWithCatalog(settings.web.catalog)
})

/**
 * Cliente verificado: el pedido queda guardado (precio del servidor) y el número
 * va en el mensaje. Visitante: solo el mensaje de WhatsApp.
 */
const checkout = async () => {
  if (!items.value.length || sending.value) return
  error.value = ''
  const canSave = account.value.role === 'customer' && account.value.verified
  // La pestaña se abre dentro del clic para que el navegador no la bloquee
  const win = window.open('', '_blank')
  // Sin acceso de la pestaña nueva a esta (window.opener)
  if (win) win.opener = null
  let lines = items.value.map(i => ({ name: i.name, qty: i.qty, price: i.price }))
  let orderId: number | null = null

  if (canSave) {
    sending.value = true
    try {
      const res = await api.post('/customer/catalog-orders', { items: items.value.map(i => ({ product: i.key, qty: i.qty })) })
      orderId = res?.order_id ?? null
      // El mensaje lleva los precios con los que el servidor guardó el pedido
      if (Array.isArray(res?.items) && res.items.length) lines = res.items
    } catch (err) {
      win?.close()
      error.value = err instanceof Error ? err.message : 'No pudimos guardar el pedido.'
      sending.value = false
      return
    }
    sending.value = false
  }

  const url = catalogWhatsAppUrl({ phone: companyPhone.value, name: name.value.trim(), items: lines, orderId })
  if (win) win.location.href = url
  else window.location.href = url
  savedOrderId.value = orderId
  clear()
}
</script>

<template>
  <div class="min-h-screen flex flex-col bg-canvas text-ink overflow-x-clip">
    <AppNavbar active-tab="catalog" />

    <main id="main" class="flex-1 w-full max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-12 md:py-16">
      <h1 class="section-title">Tu <span class="section-title-accent">carrito</span></h1>

      <!-- Pedido enviado -->
      <div v-if="savedOrderId" role="status" class="mt-10 admin-card text-center py-12">
        <p class="text-xl font-bold">Guardamos tu pedido #{{ savedOrderId }}</p>
        <p class="mt-2 text-muted">Termina la compra en WhatsApp. Te avisaremos por correo cada avance.</p>
        <div class="mt-6 flex flex-wrap justify-center gap-3">
          <router-link to="/customer/dashboard" class="btn-primary">Ver mis pedidos</router-link>
          <router-link to="/catalog" class="btn-outlined">Seguir comprando</router-link>
        </div>
      </div>

      <!-- Vacío -->
      <div v-else-if="!items.length" class="mt-10 admin-card text-center py-12">
        <p class="text-xl font-bold">Tu carrito está vacío</p>
        <p class="mt-2 text-muted">Agrega piezas del catálogo o cotiza una a tu medida.</p>
        <div class="mt-6 flex flex-wrap justify-center gap-3">
          <router-link to="/catalog" class="btn-primary">Ver el catálogo</router-link>
          <router-link to="/quote" class="btn-outlined">Cotizar mi pieza</router-link>
        </div>
      </div>

      <div v-else class="mt-10 grid gap-8 lg:grid-cols-12 items-start">
        <ul class="lg:col-span-7 space-y-3" aria-label="Productos del carrito">
          <li v-for="item in items" :key="item.key" class="admin-card !p-4 flex gap-4">
            <router-link :to="`/catalog/${encodeURIComponent(item.key)}`" class="w-20 h-20 shrink-0 rounded-[var(--radius-control)] bg-surface-2 overflow-hidden">
              <img v-if="item.image" :src="item.image" :alt="item.name" class="w-full h-full object-cover" loading="lazy" @error="(e: any) => e.target.style.display = 'none'">
            </router-link>
            <div class="min-w-0 flex-1">
              <p class="font-semibold leading-snug">{{ item.name }}</p>
              <p class="text-sm text-muted tabular-nums">{{ formatCOP(item.price) }} c/u</p>
              <div class="mt-3 flex items-center justify-between gap-3">
                <div class="inline-flex items-center h-10 rounded-full border border-line" role="group" :aria-label="`Cantidad de ${item.name}`">
                  <button type="button" class="w-10 h-full" :aria-label="`Menos ${item.name}`" :disabled="item.qty <= 1" @click="setQty(item.key, item.qty - 1)">−</button>
                  <span class="w-8 text-center tabular-nums font-semibold" aria-live="polite">{{ item.qty }}</span>
                  <button type="button" class="w-10 h-full" :aria-label="`Más ${item.name}`" @click="setQty(item.key, item.qty + 1)">+</button>
                </div>
                <span class="font-bold tabular-nums">{{ formatCOP(item.price * item.qty) }}</span>
              </div>
            </div>
            <button type="button" class="icon-btn shrink-0 -mr-2 -mt-1 text-muted hover:text-danger hover:bg-danger/10" :aria-label="`Quitar ${item.name}`" @click="remove(item.key)">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </li>
        </ul>

        <aside class="lg:col-span-5 admin-card lg:sticky lg:top-24" aria-labelledby="cart-summary">
          <h2 id="cart-summary" class="admin-h2">Resumen</h2>
          <div class="mt-4 flex items-baseline justify-between">
            <span class="text-muted">Total estimado</span>
            <span class="text-2xl font-extrabold tabular-nums">{{ formatCOP(total) }}</span>
          </div>
          <p class="mt-1 text-sm text-muted">El envío se calcula según tu ciudad.</p>

          <div v-if="account.role !== 'customer'" class="mt-5">
            <label for="cart-name" class="block text-sm font-medium mb-2">Tu nombre <span class="font-normal text-muted">(para saludarte)</span></label>
            <input id="cart-name" v-model="name" type="text" autocomplete="name" class="field">
          </div>

          <p v-if="error" role="alert" class="mt-4 text-sm text-danger">{{ error }}</p>
          <button type="button" class="mt-5 btn-primary btn-lg w-full" :disabled="sending" @click="checkout">
            {{ sending ? 'Guardando pedido…' : 'Pedir por WhatsApp' }}
          </button>

          <p v-if="account.role === 'customer' && account.verified" class="mt-3 text-sm text-muted">Se guarda en Mis pedidos y te avisamos por correo cada avance.</p>
          <p v-else-if="account.role === 'customer'" class="mt-3 text-sm text-muted"><router-link to="/customer/dashboard" class="font-semibold text-brand hover:underline">Verifica tu correo</router-link> para guardar el pedido en tu cuenta.</p>
          <p v-else class="mt-3 text-sm text-muted"><router-link to="/admin/login?registro=1" class="font-semibold text-brand hover:underline">Crea tu cuenta</router-link> para guardar tus pedidos y recibir un cupón de bienvenida.</p>
        </aside>
      </div>
    </main>

    <AppFooter />
  </div>
</template>
