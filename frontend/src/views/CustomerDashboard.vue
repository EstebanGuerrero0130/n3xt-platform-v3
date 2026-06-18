<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
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

const customer = ref(null)
const orders = ref([])
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
 // En un sistema real, filtraríamos por email en el backend
 // Por ahora usamos el endpoint de tracking o uno nuevo
 const data = await api.get(`/orders/track?email=${customer.value.email}`)
 orders.value = Array.isArray(data) ? data : [data]
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

onUnmounted(() => {
 // @unhead/vue handles meta cleanup automatically
})

const getStatusColor = (status) => {
 switch (status) {
 case 'pending': return 'bg-amber-500'
 case 'printing': return 'bg-[#08872b]'
 case 'completed': return 'bg-emerald-500'
 case 'delivered': return 'bg-[#151a22]'
 default: return 'bg-gray-400'
 }
}

const getStatusLabel = (status) => {
 switch (status) {
 case 'pending': return 'Pendiente'
 case 'printing': return 'En Impresión'
 case 'completed': return 'Terminado'
 case 'delivered': return 'Entregado'
 default: return 'Desconocido'
 }
}
</script>

<template>
 <div class="min-h-screen bg-[#f8fafc] dark:bg-[#0a0f14] transition-colors duration-500 relative overflow-hidden">
 <AppNavbar active-tab="dashboard" subtext="Terminal del Usuario" />
 
 <!-- Background Industrial Engine -->
 <div class="fixed inset-0 technical-grid opacity-20 dark:opacity-10 pointer-events-none z-0"></div>
 <div class="fixed top-[-10%] right-[-10%] w-[600px] h-[600px] bg-[#08872b]/10 rounded-[60px] blur-[180px] pointer-events-none z-0"></div>
 
 <div class="max-w-7xl mx-auto py-6 px-4 md:py-10 md:px-6 relative z-10">
 <!-- Header Industrial Deluxe -->
 <header class="flex flex-col md:flex-row justify-between items-center mb-12 gap-6 bg-[#151a22]/80 dark:bg-black/60 backdrop-blur-3xl p-6 md:p-10 rounded-[3rem] border border-[#21262d] dark:border-[#21262d] ">
 <div class="flex items-center gap-6">
 <div class="w-16 h-16 md:w-20 md:h-20 bg-[#08872b] rounded-[24px] flex items-center justify-center text-white text-3xl -[0_0_20px_#1e3a34] font-black italic">
 {{ customer?.name?.charAt(0) || 'U' }}
 </div>
 <div>
 <p class="text-[10px] font-black text-[#8dd6ff] uppercase tracking-[0.3em] mb-1">Acceso Autorizado</p>
 <h1 class="text-2xl md:text-4xl font-black text-[#ffffff] dark:text-white tracking-tighter uppercase italic leading-none">
 Misión: <span class="text-[#8dd6ff]">{{ customer?.name?.split(' ')[0] || 'Maker' }}</span>
 </h1>
 </div>
 </div>
 
 <div class="flex items-center gap-4">
 <router-link to="/quote" class="px-8 py-4 bg-[#151a22] dark:bg-[#151a22] text-white dark:text-[#ffffff] rounded-[24px] text-[10px] font-black uppercase tracking-widest hover:bg-[#08872b] dark:hover:bg-[#08872b] dark:hover:text-white transition-all ">Nueva Cotización</router-link>
 <button class="p-4 bg-rose-500/10 border border-rose-500/20 text-rose-500 rounded-[24px] hover:bg-rose-500 hover:text-white transition-all" @click="logout">
 <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
 </button>
 </div>
 </header>

 <!-- Stats / Overview -->
 <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12 reveal">
 <div class="bg-[#151a22]/60 dark:bg-[#151a22]/5 backdrop-blur-xl p-8 rounded-[3rem] border border-white dark:border-[#21262d] animate-in slide-in-from-bottom-2 duration-500">
 <p class="text-[10px] font-black text-[#c3c4c5] dark:text-[#a4aea6] uppercase tracking-widest mb-4">Pedidos Activos</p>
 <div class="flex items-end gap-3">
 <h3 class="text-4xl font-black text-[#ffffff] dark:text-white tracking-tighter">{{ orders.filter(o => o.status !== 'delivered').length }}</h3>
 <div class="w-2 h-2 bg-emerald-500 rounded-[60px] animate-pulse mb-2"></div>
 </div>
 </div>
 <div class="bg-[#151a22]/60 dark:bg-[#151a22]/5 backdrop-blur-xl p-8 rounded-[3rem] border border-white dark:border-[#21262d] animate-in slide-in-from-bottom-2 duration-700">
 <p class="text-[10px] font-black text-[#c3c4c5] dark:text-[#a4aea6] uppercase tracking-widest mb-4">Material Consumido</p>
 <h3 class="text-4xl font-black text-[#ffffff] dark:text-white tracking-tighter">-- <span class="text-sm font-bold opacity-40">g</span></h3>
 </div>
 <div class="bg-[#151a22]/60 dark:bg-[#151a22]/5 backdrop-blur-xl p-8 rounded-[3rem] border border-white dark:border-[#21262d] animate-in slide-in-from-bottom-2 duration-1000">
 <p class="text-[10px] font-black text-[#c3c4c5] dark:text-[#a4aea6] uppercase tracking-widest mb-4">Ahorro Estimado</p>
 <div class="flex items-center gap-3">
 <h3 class="text-4xl font-black text-emerald-500 tracking-tighter">15%</h3>
 <span class="text-[8px] font-black text-emerald-600 bg-emerald-500/10 px-2 py-1 rounded-[6px]">TIER 1</span>
 </div>
 </div>
 </div>

 <!-- Main Content -->
 <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
 <!-- Orders List -->
 <div class="lg:col-span-2 space-y-6">
 <div class="flex justify-between items-center mb-4">
 <h2 class="text-xl font-black text-[#ffffff] dark:text-white uppercase tracking-tighter italic">Mis Impresiones 3D</h2>
 </div>

 <div v-if="loading" class="flex justify-center py-20">
 <div class="w-12 h-12 border-4 border-primary border-t-transparent rounded-[60px] animate-spin"></div>
 </div>

 <div v-else-if="orders.length === 0" class="bg-[#151a22]/40 dark:bg-[#151a22]/5 backdrop-blur-xl p-20 rounded-[4rem] border border-white dark:border-[#21262d] text-center relative overflow-hidden group">
 <div class="absolute inset-0 bg-gradient-to-br from-primary/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
 <svg class="w-16 h-16 mb-8 text-gray-300 animate-bounce" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
 <h3 class="text-2xl font-black text-[#ffffff] dark:text-white uppercase mb-3 tracking-tighter">Aún no tienes pedidos</h3>
 <p class="text-[#c3c4c5] dark:text-[#a4aea6] text-sm font-bold uppercase tracking-tight mb-10 leading-relaxed">Sube tu primer diseño y comienza a fabricar<br>con precisión quirúrgica.</p>
 <router-link to="/quote" class="group relative px-12 py-5 bg-[#08872b] text-white rounded-[24px] font-black text-xs uppercase tracking-widest -primary/40 hover:scale-105 active:scale-95 transition-all overflow-hidden">
 <span class="relative z-10">Iniciar Proyecto</span>
 <div class="absolute inset-0 bg-gradient-to-r from-emerald-600 to-primary opacity-0 group-hover:opacity-100 transition-opacity"></div>
 </router-link>
 </div>

 <div v-else class="space-y-6">
 <div v-for="order in orders" :key="order.id" class="group bg-[#151a22]/60 dark:bg-[#151a22]/5 backdrop-blur-xl p-8 rounded-[3rem] border border-white dark:border-[#21262d] hover:-primary/10 hover:border-primary/30 transition-all duration-500 relative overflow-hidden">
 <div class="absolute top-0 right-0 w-32 h-32 bg-[#08872b]/5 rounded-[60px] blur-3xl -mr-16 -mt-16 group-hover:bg-[#08872b]/10 transition-colors"></div>
 
 <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 md:gap-8 relative z-10">
 <div class="flex items-center gap-4 sm:gap-8">
 <div class="w-14 h-14 sm:w-20 sm:h-20 bg-[#151a22] dark:bg-[#151a22] rounded-[1.5rem] sm:rounded-[2rem] flex items-center justify-center group-hover:bg-[#08872b] group-hover:text-white transition-all duration-700 group-hover:rotate-6 shrink-0">
 <svg class="w-6 h-6 sm:w-8 sm:h-8 text-[#c3c4c5] group-hover:text-white transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
 </div>
 <div class="min-w-0">
 <div class="flex flex-wrap items-center gap-2 sm:gap-4 mb-1 sm:mb-2">
 <h4 class="text-base sm:text-xl font-black text-[#ffffff] dark:text-white uppercase tracking-tighter truncate group-hover:text-[#8dd6ff] transition-colors">{{ order.project_name || 'Proyecto 3D' }}</h4>
 <span class="text-[8px] sm:text-[9px] font-black px-2 sm:px-3 py-1 bg-[#151a22] dark:bg-[#151a22]/10 text-[#a4aea6] dark:text-[#c3c4c5] rounded-[6px] sm:rounded-[6px] tracking-widest shrink-0">#{{ order.id }}</span>
 </div>
 <div class="flex flex-wrap items-center gap-2 sm:gap-3">
 <span class="text-[9px] sm:text-[10px] font-black text-[#8dd6ff] uppercase tracking-widest">{{ order.material_name }}</span>
 <span class="w-1 h-1 bg-gray-300 dark:bg-[#151a22]/20 rounded-[60px]"></span>
 <span class="text-[9px] sm:text-[10px] font-black text-[#c3c4c5] dark:text-[#a4aea6] uppercase tracking-widest">{{ order.estimated_weight_g }}g de masa</span>
 </div>
 </div>
 </div>
 
 <div class="flex items-center gap-4 sm:gap-8 w-full md:w-auto justify-between md:justify-end border-t md:border-0 pt-6 md:pt-0 border-[#21262d] dark:border-[#21262d]">
 <div class="text-left sm:text-right">
 <p class="text-[9px] font-black text-[#c3c4c5] dark:text-[#a4aea6] uppercase tracking-widest mb-1 sm:mb-2">Estado</p>
 <div class="flex items-center gap-2 sm:gap-3 sm:justify-end">
 <span class="w-2 h-2 sm:w-2.5 sm:h-2.5 rounded-[60px] animate-pulse -[0_0_8px_currentColor]" :class="getStatusColor(order.status)"></span>
 <span class="text-[9px] sm:text-[11px] font-black uppercase tracking-[0.2em]" :class="order.status === 'printing' ? 'text-[#8dd6ff]' : 'text-[#ffffff] dark:text-white'">{{ getStatusLabel(order.status) }}</span>
 </div>
 </div>
 <div class="flex flex-col items-end gap-1">
 <p class="text-[9px] font-black text-gray-300 dark:text-[#a4aea6] uppercase tracking-widest">Inversión</p>
 <div class="text-lg sm:text-2xl font-black text-[#ffffff] dark:text-white tracking-tighter leading-none">
 ${{ Number(order.total_price).toLocaleString(undefined, {maximumFractionDigits: 0}) }}
 </div>
 </div>
 <router-link :to="'/track?id=' + order.id" class="w-10 h-10 sm:w-14 sm:h-14 bg-[#151a22] dark:bg-emerald-600 text-white rounded-[6px] sm:rounded-[24px] hover:bg-[#08872b] transition-all flex items-center justify-center hover:-primary/30 group-hover:translate-x-1 duration-500 shrink-0">
 <svg class="w-4 h-4 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
 </router-link>
 </div>
 </div>
 </div>
 </div>
 </div>

 <!-- Sidebar / Promotions -->
 <div class="space-y-6">
 <div class="bg-gradient-to-br from-primary to-emerald-600 p-10 rounded-[3rem] text-white -primary/20 relative overflow-hidden group">
 <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:scale-150 transition-transform"><svg class="w-10 h-10 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg></div>
 <div class="relative z-10">
 <p class="text-[9px] font-black uppercase tracking-[0.3em] mb-4 opacity-80 text-white">Beneficio Exclusivo</p>
 <h3 class="text-2xl font-black uppercase leading-tight mb-6 italic">Usa tus cupones y obtén descuentos especiales</h3>
 <router-link to="/quote" class="inline-block bg-[#151a22] text-[#8dd6ff] px-8 py-4 rounded-[24px] text-[10px] font-black uppercase tracking-widest hover:scale-105 transition-transform">Ver Promociones</router-link>
 </div>
 <div class="absolute -bottom-10 -right-10 w-32 h-32 bg-[#151a22]/10 rounded-[60px] blur-3xl group-hover:bg-[#151a22]/20 transition-all"></div>
 </div>

 <div class="bg-[#151a22]/60 dark:bg-[#151a22]/5 backdrop-blur-xl p-8 rounded-[3rem] border border-white dark:border-[#21262d] ">
 <h4 class="text-sm font-black text-[#ffffff] dark:text-white uppercase tracking-widest mb-8">Soporte Técnico</h4>
 <div class="space-y-4">
 <a href="https://wa.me/573118796416" target="_blank" class="flex items-center gap-5 p-5 bg-[#151a22]/50 dark:bg-[#151a22]/5 rounded-[24px] hover:bg-emerald-500/10 transition-all border border-transparent hover:border-emerald-500/20 group">
 <svg class="w-6 h-6 text-emerald-500 group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
 <div>
 <p class="text-[10px] font-black text-[#ffffff] dark:text-white uppercase tracking-widest">WhatsApp Directo</p>
 <p class="text-[8px] text-emerald-500 font-bold uppercase tracking-widest mt-0.5">Respuesta inmediata</p>
 </div>
 </a>
 <a href="mailto:servicion3xt@gmail.com" class="flex items-center gap-5 p-5 bg-[#151a22]/50 dark:bg-[#151a22]/5 rounded-[24px] hover:bg-[#08872b]/10 transition-all border border-transparent hover:border-primary/20 group">
 <svg class="w-6 h-6 text-[#8dd6ff] group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
 <div>
 <p class="text-[10px] font-black text-[#ffffff] dark:text-white uppercase tracking-widest">Correo Oficial</p>
 <p class="text-[8px] text-[#8dd6ff] font-bold uppercase tracking-widest mt-0.5">servicion3xt@gmail.com</p>
 </div>
 </a>
 <router-link to="/catalog" class="flex items-center gap-5 p-5 bg-[#151a22]/50 dark:bg-[#151a22]/5 rounded-[24px] hover:bg-gray-500/10 transition-all border border-transparent hover:border-gray-500/20 group">
 <svg class="w-6 h-6 text-[#a4aea6] group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
 <div>
 <p class="text-[10px] font-black text-[#ffffff] dark:text-white uppercase tracking-widest">Guía de Materiales</p>
 <p class="text-[8px] text-[#c3c4c5] font-bold uppercase tracking-widest mt-0.5">¿Cuál elegir?</p>
 </div>
 </router-link>
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
 linear-gradient(to right, rgba(30, 58, 52, 0.05) 1px, transparent 1px),
 linear-gradient(to bottom, rgba(30, 58, 52, 0.05) 1px, transparent 1px);
}


/* --- Scroll Reveal --- */
.reveal {
 opacity: 0;
 transform: translateY(30px);
 transition: opacity 0.8s cubic-bezier(0.22, 1, 0.36, 1),
 transform 0.8s cubic-bezier(0.22, 1, 0.36, 1);
}
.reveal.revealed {
 opacity: 1;
 transform: translateY(0);
}
</style>
