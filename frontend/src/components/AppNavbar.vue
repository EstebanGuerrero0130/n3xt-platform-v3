<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue'

const props = defineProps({
  activeTab: String,
  subtext: { type: String, default: 'Industrial Workshop' }
})

const isDark = ref(localStorage.getItem('n3xt_theme') !== 'light')
const showMobileMenu = ref(false)

watch(showMobileMenu, (isOpen) => {
  if (isOpen) {
    document.body.classList.add('overflow-hidden')
  } else {
    document.body.classList.remove('overflow-hidden')
  }
})

const toggleDarkMode = () => {
  isDark.value = !isDark.value
  const theme = isDark.value ? 'dark' : 'light'
  localStorage.setItem('n3xt_theme', theme)
  if (isDark.value) {
    document.documentElement.classList.add('dark')
  } else {
    document.documentElement.classList.remove('dark')
  }
}

const isAdmin = ref(false)
const logoSrc = ref('/logo.png')

const handleLogoError = () => {
  if (logoSrc.value === '/logo.png') {
    logoSrc.value = '/logo.png.png'
  }
}

onMounted(() => {
  if (isDark.value) document.documentElement.classList.add('dark')
  isAdmin.value = !!localStorage.getItem('n3xt_admin_token')
})

onUnmounted(() => {
  document.body.classList.remove('overflow-hidden')
})
</script>

<template>
  <header class="bg-white/80 dark:bg-[#0a0f14]/80 backdrop-blur-3xl px-6 md:px-12 py-5 flex justify-between items-center border-b border-gray-100 dark:border-white/5 sticky top-0 w-full z-[1000] transition-colors duration-500">
    <router-link to="/" class="flex items-center gap-3 group">
      <img :src="logoSrc" @error="handleLogoError" alt="N3XT 3D Logo" class="h-14 md:h-16 w-auto cursor-pointer" />
      <div class="hidden xs:flex flex-col text-left">
        <span class="text-[7px] md:text-[8px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-[0.4em] leading-none">{{ subtext }}</span>
      </div>
    </router-link>

    <nav class="hidden md:flex gap-10 items-center">
      <router-link to="/" :class="['text-[10px] font-black uppercase tracking-[0.2em] transition-all pb-1 border-b-2', activeTab === 'home' ? 'text-emerald-500 border-emerald-500' : 'text-gray-400 border-transparent hover:text-emerald-500']">Inicio</router-link>
      <router-link to="/catalog" :class="['text-[10px] font-black uppercase tracking-[0.2em] transition-all pb-1 border-b-2', activeTab === 'catalog' ? 'text-emerald-500 border-emerald-500' : 'text-gray-400 border-transparent hover:text-emerald-500']">Catálogo</router-link>
      <router-link to="/quote" :class="['text-[10px] font-black uppercase tracking-[0.2em] transition-all pb-1 border-b-2', activeTab === 'quote' ? 'text-emerald-500 border-emerald-500' : 'text-gray-400 border-transparent hover:text-emerald-500']">Cotizador</router-link>
      <router-link to="/track" :class="['text-[10px] font-black uppercase tracking-[0.2em] transition-all pb-1 border-b-2', activeTab === 'track' ? 'text-emerald-500 border-emerald-500' : 'text-gray-400 border-transparent hover:text-emerald-500']">Rastrear</router-link>
      <router-link to="/project/init" :class="['text-[10px] font-black uppercase tracking-[0.2em] transition-all pb-1 border-b-2', activeTab === 'contact' ? 'text-emerald-500 border-emerald-500' : 'text-gray-400 border-transparent hover:text-emerald-500']">Contacto</router-link>
      
      <!-- Dark Mode Toggle -->
      <button 
          @click="toggleDarkMode" 
          class="w-10 h-10 bg-gray-100 dark:bg-white/5 text-gray-400 dark:text-emerald-500 rounded-xl flex items-center justify-center transition-all active:scale-90 border border-gray-200 dark:border-white/10 ml-4"
      >
          <svg v-if="isDark" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 9H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m12.728 0l-.707-.707M6.343 6.343l-.707-.707M12 8a4 4 0 100 8 4 4 0 000-8z"/></svg>
          <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
      </button>
    </nav>

    <router-link :to="isAdmin ? '/admin' : '/admin/login'" class="hidden md:block text-[10px] font-black uppercase tracking-[0.3em] bg-[#1e3a34] text-white px-8 py-3.5 rounded-2xl hover:bg-emerald-800 transition-all shadow-xl shadow-emerald-900/20">
      {{ isAdmin ? 'Ir al Taller' : 'Acceso Taller' }}
    </router-link>

    <!-- Mobile Menu Icon -->
    <div class="md:hidden flex items-center gap-4">
        <button @click="toggleDarkMode" class="w-10 h-10 bg-gray-100 dark:bg-white/5 text-emerald-500 rounded-xl flex items-center justify-center border border-gray-200 dark:border-white/10">
            <svg v-if="isDark" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 9H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m12.728 0l-.707-.707M6.343 6.343l-.707-.707M12 8a4 4 0 100 8 4 4 0 000-8z"/></svg>
            <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
        </button>
        <button @click="showMobileMenu = !showMobileMenu" class="w-10 h-10 flex items-center justify-center bg-gray-100 dark:bg-white/5 rounded-xl text-emerald-500 border border-gray-200 dark:border-white/10 relative z-[1010]">
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
      
      <router-link @click="showMobileMenu = false" to="/" class="text-xl font-black text-gray-900 dark:text-white hover:text-emerald-500 transition-all uppercase tracking-[0.4em]">Inicio</router-link>
      <router-link @click="showMobileMenu = false" to="/catalog" class="text-xl font-black text-gray-900 dark:text-white hover:text-emerald-500 transition-all uppercase tracking-[0.4em]">Catálogo</router-link>
      <router-link @click="showMobileMenu = false" to="/quote" class="text-xl font-black text-gray-900 dark:text-white hover:text-emerald-500 transition-all uppercase tracking-[0.4em]">Cotizador</router-link>
      <router-link @click="showMobileMenu = false" to="/track" class="text-xl font-black text-gray-900 dark:text-white hover:text-emerald-500 transition-all uppercase tracking-[0.4em]">Rastrear</router-link>
      <router-link @click="showMobileMenu = false" to="/project/init" class="text-xl font-black text-emerald-500 uppercase tracking-[0.4em]">Contacto</router-link>
      
      <router-link @click="showMobileMenu = false" :to="isAdmin ? '/admin' : '/admin/login'" class="mt-8 text-sm font-black uppercase tracking-[0.3em] bg-[#1e3a34] text-white px-12 py-5 rounded-3xl shadow-2xl">
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
