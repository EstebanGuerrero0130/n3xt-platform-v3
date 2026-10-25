<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../services/api'
import AppNavbar from '../components/AppNavbar.vue'
import AppFooter from '../components/AppFooter.vue'
import logger from '../utils/logger'
import { usePageMeta } from '../composables/usePageMeta'

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
}

const customer = ref<{ id: number; name: string; email: string } | null>(null)
const orders = ref<CustomerOrder[]>([])
const loading = ref(true)
const router = useRouter()

const fetchProfile = async () => {
    try {
        const data = await api.get('/customer/profile')
        customer.value = data
        fetchOrders()
    } catch (err) {
        logger.error('Error fetching profile:', err)
        logout()
    }
}

const fetchOrders = async () => {
    try {
        const data = await api.get('/customer/orders')
        orders.value = Array.isArray(data) ? data : []
    } catch (err) {
        logger.error('Error fetching orders:', err)
    } finally {
        loading.value = false
    }
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

      <!-- Resumen (solo datos reales de sus pedidos) -->
      <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-12">
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
        <section class="lg:col-span-8" aria-labelledby="orders-title">
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
            <li v-for="order in orders" :key="order.id">
              <router-link :to="trackLink(order)" class="group flex flex-col sm:flex-row sm:items-center gap-4 p-5 bg-surface rounded-[var(--radius-card)] hover:bg-surface-2 transition-colors">
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
                  <svg class="w-5 h-5 text-muted group-hover:text-ink group-hover:translate-x-0.5 transition" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </div>
              </router-link>
            </li>
          </ul>
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
