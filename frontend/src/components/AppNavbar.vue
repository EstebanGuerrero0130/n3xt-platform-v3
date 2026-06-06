<script setup lang="ts">
import { ref, onMounted, onUnmounted, watch } from 'vue'

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
  const { api } = await import('../services/api')
  const auth = await api.checkAuth()
  isAdmin.value = auth.authenticated && auth.role === 'admin'
})

onUnmounted(() => {
  document.body.classList.remove('overflow-hidden')
})
</script>

<template>
  <header role="banner" class="bg-white/80 dark:bg-[#0d1117]/80 backdrop-blur-3xl px-6 md:px-12 py-4 flex justify-between items-center border-b border-gray-100 dark:border-[#21262d]/50 sticky top-0 w-full z-[1000] transition-colors duration-500">
    <router-link to="/" class="flex items-center gap-3 group">
      <div class="flex flex-col text-left">
        <span class="text-lg md:text-xl font-black text-gray-900 dark:text-white uppercase tracking-tighter leading-none">N3XT <span class="text-emerald-400">3D</span></span>
        <span class="text-[7px] md:text-[8px] font-black text-gray-400 dark:text-[#8dd6ff] uppercase tracking-[0.4em] leading-none">{{ subtext }}</span>
      </div>
    </router-link>

    <nav role="navigation" aria-label="Navegación principal" class="hidden md:flex gap-4 items-center">
      <router-link to="/" :class="[activeTab === 'home' ? 'text-white bg-white/10 border border-white/10 px-4 py-2 rounded-[60px]' : 'text-gray-400 hover:text-[#8dd6ff] px-3 py-2', 'text-[10px] font-black uppercase tracking-[0.2em] transition-all']">Inicio</router-link>
      <router-link to="/galeria" :class="[activeTab === 'gallery' ? 'text-white bg-white/10 border border-white/10 px-4 py-2 rounded-[60px]' : 'text-gray-400 hover:text-[#8dd6ff] px-3 py-2', 'text-[10px] font-black uppercase tracking-[0.2em] transition-all']">Galería</router-link>
      <router-link to="/catalog" :class="[activeTab === 'catalog' ? 'text-white bg-white/10 border border-white/10 px-4 py-2 rounded-[60px]' : 'text-gray-400 hover:text-[#8dd6ff] px-3 py-2', 'text-[10px] font-black uppercase tracking-[0.2em] transition-all']">Catálogo</router-link>
      <router-link to="/quote" :class="[activeTab === 'quote' ? 'text-white bg-white/10 border border-white/10 px-4 py-2 rounded-[60px]' : 'text-gray-400 hover:text-[#8dd6ff] px-3 py-2', 'text-[10px] font-black uppercase tracking-[0.2em] transition-all']">Cotizador</router-link>
      <router-link to="/track" :class="[activeTab === 'track' ? 'text-white bg-white/10 border border-white/10 px-4 py-2 rounded-[60px]' : 'text-gray-400 hover:text-[#8dd6ff] px-3 py-2', 'text-[10px] font-black uppercase tracking-[0.2em] transition-all']">Rastrear</router-link>
      <router-link to="/project/init" :class="[activeTab === 'contact' ? 'text-white bg-white/10 border border-white/10 px-4 py-2 rounded-[60px]' : 'text-gray-400 hover:text-[#8dd6ff] px-3 py-2', 'text-[10px] font-black uppercase tracking-[0.2em] transition-all']">Iniciar Proyecto 3D</router-link>
      

    </nav>

    <router-link :to="isAdmin ? '/admin' : '/admin/login'" class="hidden md:block btn-primary text-[10px] font-black uppercase tracking-[0.2em]">
      {{ isAdmin ? 'Ir al Taller' : 'Acceso Taller' }}
    </router-link>

    <!-- Mobile Menu Icon -->
    <div class="md:hidden flex items-center gap-4">
        <button :aria-expanded="showMobileMenu" aria-label="Menú de navegación" class="w-10 h-10 flex items-center justify-center bg-gray-100 dark:bg-white/5 rounded-xl text-[#8dd6ff] border border-gray-200 dark:border-white/10 relative z-[1010]" @click="showMobileMenu = !showMobileMenu">
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
    <div v-if="showMobileMenu" class="fixed inset-0 z-[999] bg-white dark:bg-[#0a0f14] backdrop-blur-3xl md:hidden flex flex-col items-center justify-start overflow-y-auto gap-8 pt-28 pb-12 px-12">
      <div class="absolute inset-0 technical-grid opacity-10 pointer-events-none"></div>
      
      <router-link to="/" class="text-xl font-black text-gray-900 dark:text-white hover:text-emerald-500 transition-all uppercase tracking-[0.4em]" @click="showMobileMenu = false">Inicio</router-link>
      <router-link to="/galeria" class="text-xl font-black text-gray-900 dark:text-white hover:text-emerald-500 transition-all uppercase tracking-[0.4em]" @click="showMobileMenu = false">Galería</router-link>
      <router-link to="/catalog" class="text-xl font-black text-gray-900 dark:text-white hover:text-emerald-500 transition-all uppercase tracking-[0.4em]" @click="showMobileMenu = false">Catálogo</router-link>
      <router-link to="/quote" class="text-xl font-black text-gray-900 dark:text-white hover:text-emerald-500 transition-all uppercase tracking-[0.4em]" @click="showMobileMenu = false">Cotizador</router-link>
      <router-link to="/track" class="text-xl font-black text-gray-900 dark:text-white hover:text-emerald-500 transition-all uppercase tracking-[0.4em]" @click="showMobileMenu = false">Rastrear</router-link>
      <router-link to="/project/init" class="text-xl font-black text-gray-900 dark:text-white hover:text-emerald-500 transition-all uppercase tracking-[0.4em]" @click="showMobileMenu = false">Iniciar Proyecto 3D</router-link>
      
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
