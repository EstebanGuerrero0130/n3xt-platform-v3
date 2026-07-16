<script setup lang="ts">
import { ref, onMounted, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../services/api'
import { useRevealAnim } from '../composables/useRevealAnim'
import AppNavbar from '../components/AppNavbar.vue'
import AppFooter from '../components/AppFooter.vue'
import logger from '../utils/logger'
import { usePageMeta } from '../composables/usePageMeta'

usePageMeta({
  title: 'Panel del Cliente | N3XT 3D',
  description: 'Gestiona tus pedidos de impresion 3D. Consulta el estado de tus proyectos y solicita nuevas cotizaciones.',
  image: '/assets/n3xt_og_dashboard.png',
  noIndex: true,
})

useRevealAnim({ delay: 200 })

interface Customer {
  name?: string; email?: string; phone?: string; company?: string
  address_full?: string; city_dept_country?: string; zip_code?: string; location_reference?: string
}
interface Order {
  id: string | number; project_name?: string; material_name?: string
  estimated_weight_g?: number | string; status?: string; total_price?: string | number
}

const customer = ref<Customer | null>(null)
const orders = ref<Order[]>([])
const loading = ref(true)
const savingProfile = ref(false)
const profileSaved = ref(false)
const router = useRouter()

const shippingForm = reactive({
  phone: '', company: '', address_full: '', city_dept_country: '', zip_code: '', location_reference: ''
})

const fetchProfile = async () => {
  try {
    const data = await api.get('/customer/profile')
    customer.value = data
    shippingForm.phone = data.phone || ''
    shippingForm.company = data.company || ''
    shippingForm.address_full = data.address_full || ''
    shippingForm.city_dept_country = data.city_dept_country || ''
    shippingForm.zip_code = data.zip_code || ''
    shippingForm.location_reference = data.location_reference || ''
    fetchOrders()
  } catch (err) {
    logger.error('Error fetching profile:', err)
    logout()
  }
}

const fetchOrders = async () => {
  try {
    const data = await api.get(`/orders/track?email=${customer.value?.email ?? ''}`)
    orders.value = Array.isArray(data) ? data : (data ? [data] : [])
  } catch (err) {
    logger.error('Error fetching orders:', err)
    orders.value = []
  } finally {
    loading.value = false
  }
}

const saveShipping = async () => {
  if (savingProfile.value) return
  savingProfile.value = true
  try {
    const updated = await api.put('/customer/profile', shippingForm)
    customer.value = { ...customer.value, ...updated }
    profileSaved.value = true
    setTimeout(() => { profileSaved.value = false }, 3000)
  } catch (err) {
    logger.error('Error saving profile:', err)
  } finally {
    savingProfile.value = false
  }
}

const logout = async () => {
  try { await api.post('/customer/logout') } catch { /* silent */ }
  router.push('/')
}

onMounted(() => { fetchProfile() })

const getStatusColor = (status: string): string => {
  const map: Record<string, string> = {
    pending: 'bg-amber-500', printing: 'bg-[#08872b]', 'post-processing': 'bg-blue-500',
    completed: 'bg-emerald-500', shipped: 'bg-indigo-500', cancelled: 'bg-red-500'
  }
  return map[status] || 'bg-gray-400'
}
const getStatusLabel = (status: string): string => {
  const map: Record<string, string> = {
    pending: 'Pendiente', printing: 'En Impresión', 'post-processing': 'Post-Proceso',
    completed: 'Terminado', shipped: 'Enviado', cancelled: 'Cancelado'
  }
  return map[status] || 'Desconocido'
}
</script>

<template>
  <div class="min-h-screen bg-[#0a0f14] relative overflow-hidden">
    <AppNavbar active-tab="dashboard" subtext="Terminal del Usuario" />
    
    <div class="fixed inset-0 technical-grid opacity-10 pointer-events-none z-0"></div>
    <div class="fixed top-[-10%] right-[-10%] w-[600px] h-[600px] bg-[#08872b]/10 rounded-[60px] blur-[180px] pointer-events-none z-0"></div>
    
    <div class="max-w-7xl mx-auto py-6 px-4 md:py-10 md:px-6 relative z-10">
      <!-- Header -->
      <header class="flex flex-col md:flex-row justify-between items-center mb-10 gap-6 bg-[#151a22]/80 backdrop-blur-3xl p-6 md:p-10 rounded-[3rem] border border-[#21262d]">
        <div class="flex items-center gap-6">
          <div class="w-16 h-16 md:w-20 md:h-20 bg-gradient-to-br from-[#08872b] to-emerald-600 rounded-[24px] flex items-center justify-center text-white text-3xl font-black italic shadow-[0_0_30px_rgba(8,135,43,0.4)]">
            {{ customer?.name?.charAt(0) || 'U' }}
          </div>
          <div>
            <p class="text-[10px] font-black text-[#8dd6ff] uppercase tracking-[0.3em] mb-1">Acceso Autorizado</p>
            <h1 class="text-5xl md:text-7xl font-black text-white tracking-tight uppercase italic leading-[0.85]">
              Misión: <span class="text-[#8dd6ff]">{{ customer?.name?.split(' ')[0] || 'Maker' }}</span>
            </h1>
            <p class="text-[10px] text-[#a4aea6] font-bold mt-2">{{ customer?.email }}</p>
          </div>
        </div>
        <div class="flex items-center gap-4">
          <router-link to="/quote" class="px-8 py-4 bg-[#08872b] text-white rounded-[24px] text-[10px] font-black uppercase tracking-widest hover:bg-emerald-600 transition-all">Nueva Cotización</router-link>
          <button class="p-4 bg-rose-500/10 border border-rose-500/20 text-rose-500 rounded-[24px] hover:bg-rose-500 hover:text-white transition-all" @click="logout">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
          </button>
        </div>
      </header>

      <!-- Stats -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10 reveal">
        <div class="bg-[#151a22]/60 backdrop-blur-xl p-8 rounded-[3rem] border border-[#21262d]">
          <p class="text-[10px] font-black text-[#a4aea6] uppercase tracking-widest mb-4">Pedidos Activos</p>
          <div class="flex items-end gap-3">
            <h3 class="text-4xl font-black text-white">{{ orders.filter(o => !['shipped','cancelled'].includes(o.status ?? '')).length }}</h3>
            <div class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse mb-2"></div>
          </div>
        </div>
        <div class="bg-[#151a22]/60 backdrop-blur-xl p-8 rounded-[3rem] border border-[#21262d]">
          <p class="text-[10px] font-black text-[#a4aea6] uppercase tracking-widest mb-4">Total Proyectos</p>
          <h3 class="text-4xl font-black text-white">{{ orders.length }}</h3>
        </div>
        <div class="bg-[#151a22]/60 backdrop-blur-xl p-8 rounded-[3rem] border border-[#21262d]">
          <p class="text-[10px] font-black text-[#a4aea6] uppercase tracking-widest mb-4">Estado Cuenta</p>
          <div class="flex items-center gap-3">
            <h3 class="text-2xl font-black text-emerald-500">ACTIVA</h3>
            <span class="text-[8px] font-black text-emerald-600 bg-emerald-500/10 px-2 py-1 rounded-[6px]">MAKER</span>
          </div>
        </div>
      </div>

      <!-- Main Content -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Orders -->
        <div class="lg:col-span-2 space-y-6">
          <h2 class="text-4xl font-black text-white uppercase tracking-tight italic">Mis Impresiones 3D</h2>

          <div v-if="loading" class="flex justify-center py-20">
            <div class="w-12 h-12 border-4 border-[#08872b] border-t-transparent rounded-full animate-spin"></div>
          </div>

          <div v-else-if="orders.length === 0" class="bg-[#151a22]/40 p-20 rounded-[4rem] border border-[#21262d] text-center">
            <svg class="w-16 h-16 mb-8 text-gray-500 mx-auto animate-bounce" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            <h3 class="text-2xl font-black text-white uppercase mb-3">Aún no tienes pedidos</h3>
            <p class="text-[#a4aea6] text-sm font-bold mb-10">Sube tu primer diseño y comienza a fabricar<br>con precisión quirúrgica.</p>
            <router-link to="/quote" class="inline-block px-12 py-5 bg-[#08872b] text-white rounded-[24px] font-black text-xs uppercase tracking-widest hover:bg-emerald-600 transition-all">Iniciar Proyecto</router-link>
          </div>

          <div v-else class="space-y-5">
            <div v-for="order in orders" :key="order.id" class="group bg-[#151a22]/60 p-6 rounded-[2rem] border border-[#21262d] hover:border-[#08872b]/40 transition-all duration-500 relative overflow-hidden">
              <div class="absolute top-0 right-0 w-32 h-32 bg-[#08872b]/5 rounded-full blur-3xl -mr-16 -mt-16 group-hover:bg-[#08872b]/10 transition-colors"></div>
              <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-5 relative z-10">
                <div class="flex items-center gap-5">
                  <div class="w-14 h-14 bg-[#21262d] rounded-[1.5rem] flex items-center justify-center group-hover:bg-[#08872b] transition-all duration-500 shrink-0">
                    <svg class="w-6 h-6 text-[#a4aea6] group-hover:text-white transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                  </div>
                  <div class="min-w-0">
                    <div class="flex items-center gap-3 mb-1">
                      <h3 class="text-base font-black text-white uppercase truncate group-hover:text-[#8dd6ff] transition-colors">{{ order.project_name || 'Proyecto 3D' }}</h3>
                      <span class="text-[8px] font-black px-2 py-0.5 bg-[#21262d] text-[#a4aea6] rounded-[6px] shrink-0">#{{ order.id }}</span>
                    </div>
                    <div class="flex items-center gap-3">
                      <span class="text-[9px] font-black text-[#8dd6ff] uppercase tracking-widest">{{ order.material_name }}</span>
                      <span class="w-1 h-1 bg-[#21262d] rounded-full"></span>
                      <span class="text-[9px] font-black text-[#a4aea6] uppercase">{{ order.estimated_weight_g }}g</span>
                    </div>
                  </div>
                </div>
                <div class="flex items-center gap-6 w-full md:w-auto justify-between md:justify-end border-t md:border-0 pt-5 md:pt-0 border-[#21262d]">
                  <div>
                    <p class="text-[9px] font-black text-[#a4aea6] uppercase tracking-widest mb-1">Estado</p>
                    <div class="flex items-center gap-2">
                      <span class="w-2 h-2 rounded-full animate-pulse" :class="getStatusColor(order.status ?? '')"></span>
                      <span class="text-[10px] font-black uppercase tracking-wide text-white">{{ getStatusLabel(order.status ?? '') }}</span>
                    </div>
                  </div>
                  <div class="text-right">
                    <p class="text-[9px] font-black text-[#a4aea6] uppercase tracking-widest">Inversión</p>
                    <div class="text-xl font-black text-white">${{ Number(order.total_price).toLocaleString(undefined, {maximumFractionDigits: 0}) }}</div>
                  </div>
                  <router-link :to="'/track?id=' + order.id" class="w-10 h-10 bg-[#21262d] text-white rounded-[6px] hover:bg-[#08872b] transition-all flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                  </router-link>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
          <!-- Datos de envío -->
          <div class="bg-[#151a22]/60 backdrop-blur-xl p-8 rounded-[3rem] border border-[#21262d]">
            <div class="flex items-center gap-3 mb-6">
              <div class="w-8 h-8 bg-[#08872b]/20 rounded-[10px] flex items-center justify-center">
                <svg class="w-4 h-4 text-[#08872b]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
              </div>
              <h3 class="text-sm font-black text-white uppercase tracking-widest">Datos de Envío</h3>
            </div>
            <div class="space-y-3">
              <div>
                <label class="block text-[9px] font-black text-[#a4aea6] uppercase tracking-widest mb-1">Teléfono / WhatsApp</label>
                <input v-model="shippingForm.phone" placeholder="+57 300 000 0000" class="w-full bg-[#21262d] border border-[#2d3542] rounded-[12px] px-4 py-3 text-white text-sm font-semibold focus:ring-2 focus:ring-[#08872b] outline-none transition-all placeholder:text-[#4a5568]">
              </div>
              <div>
                <label class="block text-[9px] font-black text-[#a4aea6] uppercase tracking-widest mb-1">Empresa (Opcional)</label>
                <input v-model="shippingForm.company" placeholder="N3XT Industries SAS" class="w-full bg-[#21262d] border border-[#2d3542] rounded-[12px] px-4 py-3 text-white text-sm font-semibold focus:ring-2 focus:ring-[#08872b] outline-none transition-all placeholder:text-[#4a5568]">
              </div>
              <div>
                <label class="block text-[9px] font-black text-[#a4aea6] uppercase tracking-widest mb-1">Dirección de Entrega</label>
                <input v-model="shippingForm.address_full" placeholder="Calle 123 # 45-67" class="w-full bg-[#21262d] border border-[#2d3542] rounded-[12px] px-4 py-3 text-white text-sm font-semibold focus:ring-2 focus:ring-[#08872b] outline-none transition-all placeholder:text-[#4a5568]">
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-[9px] font-black text-[#a4aea6] uppercase tracking-widest mb-1">Ciudad / Dpto</label>
                  <input v-model="shippingForm.city_dept_country" placeholder="Bogotá, D.C." class="w-full bg-[#21262d] border border-[#2d3542] rounded-[12px] px-3 py-3 text-white text-xs font-semibold focus:ring-2 focus:ring-[#08872b] outline-none transition-all placeholder:text-[#4a5568]">
                </div>
                <div>
                  <label class="block text-[9px] font-black text-[#a4aea6] uppercase tracking-widest mb-1">Código Postal</label>
                  <input v-model="shippingForm.zip_code" placeholder="110111" class="w-full bg-[#21262d] border border-[#2d3542] rounded-[12px] px-3 py-3 text-white text-xs font-semibold focus:ring-2 focus:ring-[#08872b] outline-none transition-all placeholder:text-[#4a5568]">
                </div>
              </div>
              <div>
                <label class="block text-[9px] font-black text-[#a4aea6] uppercase tracking-widest mb-1">Referencia / Punto de Entrega</label>
                <input v-model="shippingForm.location_reference" placeholder="Apt 301, Portería principal..." class="w-full bg-[#21262d] border border-[#2d3542] rounded-[12px] px-4 py-3 text-white text-sm font-semibold focus:ring-2 focus:ring-[#08872b] outline-none transition-all placeholder:text-[#4a5568]">
              </div>
              <button
                :class="['w-full py-4 rounded-[16px] font-black text-sm uppercase tracking-widest transition-all duration-300', profileSaved ? 'bg-emerald-500 text-white' : 'bg-[#08872b] text-white hover:bg-emerald-600 hover:scale-[1.02] active:scale-95']"
                :disabled="savingProfile"
                @click="saveShipping"
              >
                <span v-if="savingProfile" class="flex items-center justify-center gap-2">
                  <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                  Guardando...
                </span>
                <span v-else-if="profileSaved">✓ Datos Guardados</span>
                <span v-else>Guardar Dirección</span>
              </button>
            </div>
          </div>

          <!-- Soporte -->
          <div class="bg-[#151a22]/60 backdrop-blur-xl p-8 rounded-[3rem] border border-[#21262d]">
            <h3 class="text-sm font-black text-white uppercase tracking-widest mb-6">Soporte Técnico</h3>
            <div class="space-y-3">
              <a href="https://wa.me/573118796416" target="_blank" class="flex items-center gap-4 p-4 bg-[#21262d]/50 rounded-[20px] hover:bg-emerald-500/10 transition-all border border-transparent hover:border-emerald-500/20">
                <svg class="w-5 h-5 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                <div><p class="text-[10px] font-black text-white uppercase tracking-widest">WhatsApp Directo</p><p class="text-[8px] text-emerald-500 font-bold mt-0.5">Respuesta inmediata</p></div>
              </a>
              <a href="mailto:servicion3xt@gmail.com" class="flex items-center gap-4 p-4 bg-[#21262d]/50 rounded-[20px] hover:bg-[#08872b]/10 transition-all border border-transparent hover:border-[#08872b]/20">
                <svg class="w-5 h-5 text-[#8dd6ff] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                <div><p class="text-[10px] font-black text-white uppercase tracking-widest">Correo Oficial</p><p class="text-[8px] text-[#8dd6ff] font-bold mt-0.5">servicion3xt@gmail.com</p></div>
              </a>
            </div>
          </div>

          <!-- Promo -->
          <div class="bg-gradient-to-br from-[#08872b] to-emerald-700 p-8 rounded-[3rem] text-white relative overflow-hidden group">
            <div class="relative z-10">
              <p class="text-[9px] font-black uppercase tracking-[0.3em] mb-3 opacity-80">Beneficio Exclusivo</p>
              <h3 class="text-xl font-black uppercase leading-tight mb-5 italic">Usa tus cupones y obtén descuentos especiales</h3>
              <router-link to="/quote" class="inline-block bg-[#151a22] text-[#8dd6ff] px-6 py-3 rounded-[20px] text-[10px] font-black uppercase tracking-widest hover:scale-105 transition-transform">Ver Promociones</router-link>
            </div>
          </div>
        </div>
      </div>
    </div>
    <AppFooter />
  </div>
</template>

<style scoped>
.technical-grid {
  background-size: 50px 50px;
  background-image: 
    linear-gradient(to right, rgba(8, 135, 43, 0.08) 1px, transparent 1px),
    linear-gradient(to bottom, rgba(8, 135, 43, 0.08) 1px, transparent 1px);
}
.reveal { opacity: 0; transform: translateY(30px); transition: opacity 0.8s cubic-bezier(0.22, 1, 0.36, 1), transform 0.8s cubic-bezier(0.22, 1, 0.36, 1); }
.reveal.revealed { opacity: 1; transform: translateY(0); }
</style>
