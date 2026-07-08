<script setup lang="ts">
import { ref, onMounted, onUnmounted, watch } from 'vue'

/**
 * Valores válidos para activeTab.
 * Cada vista debe pasar el valor correspondiente para que el nav link
 * se muestre como activo (background + border).
 */
export type NavbarTab = 'home' | 'gallery' | 'catalog' | 'quote' | 'track' | 'contact' | 'dashboard' | ''

/**
 * Evita warning de fallthrough attributes en componentes con múltiples
 * root elements (header + mobile menu transition).
 */
defineOptions({ inheritAttrs: false })

export interface NavLink {
  to: string
  tab: NavbarTab
  label: string
}

const navLinks: NavLink[] = [
  { to: '/', tab: 'home', label: 'Inicio' },
  { to: '/galeria', tab: 'gallery', label: 'Galería' },
  { to: '/catalog', tab: 'catalog', label: 'Catálogo' },
  { to: '/quote', tab: 'quote', label: 'Cotizador' },
  { to: '/track', tab: 'track', label: 'Rastrear' },
  { to: '/project/init', tab: 'contact', label: 'Contáctanos' },
]

defineProps<{
  /** Tab activa: resalta el link correspondiente en el navbar */
  activeTab?: NavbarTab
  /** Texto secundario mostrado bajo el logo N3XT 3D */
  subtext?: string
}>()

const showMobileMenu = ref(false)

watch(showMobileMenu, (isOpen) => {
 if (isOpen) {
 document.body.classList.add('overflow-hidden')
 } else {
 document.body.classList.remove('overflow-hidden')
 }
})

const isAdmin = ref(false)

onMounted(async () => {
  try {
    const mod = await import('../services/api')
    if (mod?.api?.checkAuth) {
      const auth = await mod.api.checkAuth()
      isAdmin.value = auth.authenticated && auth.role === 'admin'
    }
  } catch {
    // Auth check no crítico — el navbar muestra "Acceso Taller" por defecto
  }
})

onUnmounted(() => {
 document.body.classList.remove('overflow-hidden')
})
</script>

<template>
 <header role="banner" class="bg-[#151a22]/80 dark:bg-[#0d1117]/80 backdrop-blur-3xl px-6 md:px-12 py-4 flex justify-between items-center border-b border-[#21262d] dark:border-[#21262d]/50 sticky top-0 w-full z-[1000] transition-colors duration-500">
 <router-link to="/" class="flex items-center gap-3 group">
 <div class="flex flex-col text-left">
 <span class="text-lg md:text-xl font-black text-[#ffffff] dark:text-white uppercase tracking-tighter leading-none">N3XT <span class="text-emerald-400">3D</span></span>
 <span class="text-[7px] md:text-[8px] font-black text-[#c3c4c5] dark:text-[#8dd6ff] uppercase tracking-[0.4em] leading-none">{{ subtext ?? '' }}</span>
 </div>
 </router-link>

 <nav role="navigation" aria-label="Navegación principal" class="hidden md:flex gap-4 items-center">
 <router-link
 v-for="link in navLinks"
 :key="link.tab"
 :to="link.to"
 :class="[
 activeTab === link.tab
 ? 'text-white bg-[#151a22]/10 border border-white/10 px-4 py-2 rounded-[60px]'
 : 'text-[#c3c4c5] hover:text-[#8dd6ff] px-3 py-2',
 'text-[10px] font-black uppercase tracking-[0.2em] transition-all'
 ]"
 >{{ link.label }}</router-link>
 </nav>

 <router-link :to="isAdmin ? '/admin' : '/admin/login'" class="hidden md:block btn-primary text-[10px] font-black uppercase tracking-[0.2em]">
 {{ isAdmin ? 'Ir al Taller' : 'Acceso Taller' }}
 </router-link>

 <!-- Mobile Menu Icon -->
 <div class="md:hidden flex items-center gap-4">
 <button :aria-expanded="showMobileMenu" aria-label="Menú de navegación" class="w-10 h-10 flex items-center justify-center bg-[#151a22] dark:bg-[#151a22]/5 rounded-[6px] text-[#8dd6ff] border border-[#21262d] dark:border-[#21262d] relative z-[1010]" @click="showMobileMenu = !showMobileMenu">
 <svg v-if="!showMobileMenu" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M4 12h16M4 18h16"/></svg>
 <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
 </button>
 </div>
 </header>

 <!-- Menú Móvil Overlay -->
 <transition 
 enter-active-class="transition duration-500 ease-[cubic-bezier(0.19,1,0.22,1)]"
 enter-from-class="opacity-0 translate-x-full"
 enter-to-class="opacity-100 translate-x-0"
 leave-active-class="transition duration-400 ease-[cubic-bezier(0.19,1,0.22,1)]"
 leave-from-class="opacity-100 translate-x-0"
 leave-to-class="opacity-0 translate-x-full"
 >
 <div v-if="showMobileMenu" class="fixed inset-0 z-[999] bg-[#151a22] dark:bg-[#0a0f14] backdrop-blur-3xl md:hidden flex flex-col items-center justify-start overflow-y-auto gap-8 pt-28 pb-12 px-12">
 <div class="absolute inset-0 technical-grid opacity-10 pointer-events-none"></div>
 
 <router-link
 v-for="link in navLinks"
 :key="link.tab"
 :to="link.to"
 class="text-xl font-black text-[#ffffff] dark:text-white hover:text-emerald-500 transition-all uppercase tracking-[0.4em]"
 @click="showMobileMenu = false"
 >{{ link.label }}</router-link>
 
 <router-link :to="isAdmin ? '/admin' : '/admin/login'" class="mt-8 text-sm font-bold uppercase tracking-[0.2em] btn-primary px-12 py-4" @click="showMobileMenu = false">
 {{ isAdmin ? 'Ir al Taller' : 'Acceso Taller' }}
 </router-link>
 </div>
 </transition>
</template>

<style scoped>
.technical-grid {
 background-size: 50px 50px;
 background-image: linear-gradient(to right, rgba(16, 185, 129, 0.05) 1px, transparent 1px), linear-gradient(to bottom, rgba(16, 185, 129, 0.05) 1px, transparent 1px);
}
</style>
