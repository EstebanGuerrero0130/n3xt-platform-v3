<script setup>
import { ref, onMounted } from 'vue'

const props = defineProps({
  activeTab: String,
  subtext: { type: String, default: 'Industrial Workshop' }
})

const isDark = ref(localStorage.getItem('n3xt_theme') !== 'light')
const showMobileMenu = ref(false)

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

onMounted(() => {
  if (isDark.value) document.documentElement.classList.add('dark')
  isAdmin.value = !!localStorage.getItem('n3xt_admin_token')
})
</script>

<template>
  <header class="bg-white/80 dark:bg-[#0a0f14]/80 backdrop-blur-3xl px-6 md:px-12 py-5 flex justify-between items-center border-b border-gray-100 dark:border-white/5 sticky top-0 z-[100] w-full transition-colors duration-500">
    <router-link to="/" class="flex items-center gap-3 group">
      <svg class="h-8 md:h-9 w-auto cursor-pointer" viewBox="0 0 800 250" fill="none" xmlns="http://www.w3.org/2000/svg">
        <defs>
          <linearGradient id="rocketGrad" x1="1" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#ab5cff" />
            <stop offset="50%" stop-color="#4facfe" />
            <stop offset="100%" stop-color="#00f5ff" />
          </linearGradient>
        </defs>
        <!-- Letters N, 3, T in adaptive theme color -->
        <g stroke="currentColor" class="text-gray-900 dark:text-white transition-colors duration-500" stroke-width="14" stroke-linecap="round" stroke-linejoin="round" fill="none">
          <!-- N -->
          <path d="M 60,50 L 60,200" />
          <path d="M 82,50 L 82,200" />
          <path d="M 82,50 L 158,200" />
          <path d="M 60,50 L 136,200" />
          <path d="M 136,50 L 136,200" />
          <path d="M 158,50 L 158,200" />
          <!-- 3 -->
          <path d="M 210,50 L 290,50 C 320,50 330,75 310,95 L 290,115 C 280,125 265,125 250,125" />
          <path d="M 210,72 L 275,72 C 295,72 300,85 290,95 L 270,115" />
          <path d="M 250,125 C 265,125 280,125 290,135 L 310,155 C 330,175 320,200 290,200 L 210,200" />
          <path d="M 270,135 L 290,155 C 300,165 295,178 275,178 L 210,178" />
          <!-- T -->
          <path d="M 540,50 L 680,50" />
          <path d="M 540,72 L 680,72" />
          <path d="M 600,72 L 600,200" />
          <path d="M 622,72 L 622,200" />
        </g>
        <!-- The Rocket replacing 'X' -->
        <g transform="translate(260, -25) scale(0.6)">
          <g transform="rotate(45 256 256)">
            <!-- Speed lines -->
            <line x1="110" y1="60" x2="110" y2="160" stroke="url(#rocketGrad)" stroke-width="12" stroke-linecap="round" opacity="0.6" />
            <line x1="135" y1="85" x2="135" y2="185" stroke="url(#rocketGrad)" stroke-width="12" stroke-linecap="round" opacity="0.6" />
            <line x1="160" y1="110" x2="160" y2="210" stroke="url(#rocketGrad)" stroke-width="12" stroke-linecap="round" opacity="0.6" />
            <line x1="185" y1="135" x2="185" y2="235" stroke="url(#rocketGrad)" stroke-width="12" stroke-linecap="round" opacity="0.6" />
            <line x1="327" y1="277" x2="327" y2="377" stroke="url(#rocketGrad)" stroke-width="12" stroke-linecap="round" opacity="0.6" />
            <line x1="352" y1="302" x2="352" y2="402" stroke="url(#rocketGrad)" stroke-width="12" stroke-linecap="round" opacity="0.6" />
            <line x1="377" y1="327" x2="377" y2="427" stroke="url(#rocketGrad)" stroke-width="12" stroke-linecap="round" opacity="0.6" />
            <line x1="402" y1="352" x2="402" y2="452" stroke="url(#rocketGrad)" stroke-width="12" stroke-linecap="round" opacity="0.6" />
            <!-- Flame -->
            <path d="M236 370 C200 420 220 500 256 512 C292 500 312 420 276 370 Z" fill="url(#rocketGrad)" />
            <path d="M246 370 C225 410 235 460 256 475 C277 460 287 410 266 370 Z" fill="#ffffff" opacity="0.4" />
            <!-- Fins -->
            <path d="M200 280 C160 280 110 330 110 375 C110 400 130 405 155 405 C185 405 200 380 208 370 Z" fill="url(#rocketGrad)" />
            <path d="M312 280 C352 280 402 330 402 375 C402 400 382 405 357 405 C327 405 312 380 304 370 Z" fill="url(#rocketGrad)" />
            <!-- Rocket Body -->
            <path d="M256 50 C216 110 196 190 196 310 C196 345 204 370 216 370 L296 370 C308 370 316 345 316 310 C316 190 296 110 256 50 Z" fill="url(#rocketGrad)" />
            <!-- Highlights -->
            <path d="M256 70 C226 120 210 190 210 300 C210 330 216 350 220 350" stroke="#ffffff" stroke-width="6" stroke-linecap="round" opacity="0.4" />
            <!-- Window -->
            <circle cx="256" cy="170" r="28" fill="#ffffff" />
            <circle cx="256" cy="170" r="20" fill="url(#rocketGrad)" />
            <circle cx="256" cy="170" r="14" fill="#ffffff" opacity="0.9" />
            <!-- Center Line -->
            <path d="M256 210 L256 340" stroke="#ffffff" stroke-width="8" stroke-linecap="round" opacity="0.6" />
          </g>
        </g>
      </svg>
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
        <button @click="showMobileMenu = !showMobileMenu" class="w-10 h-10 flex items-center justify-center bg-gray-100 dark:bg-white/5 rounded-xl text-emerald-500 border border-gray-200 dark:border-white/10 relative z-[110]">
            <svg v-if="!showMobileMenu" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M4 12h16M4 18h16"/></svg>
            <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>

    <!-- Menú Móvil Overlay -->
    <transition 
      enter-active-class="transition duration-500 ease-[cubic-bezier(0.19,1,0.22,1)]"
      enter-from-class="opacity-0 translate-x-full"
      enter-to-class="opacity-100 translate-x-0"
      leave-active-class="transition duration-400 ease-[cubic-bezier(0.19,1,0.22,1)]"
      leave-from-class="opacity-100 translate-x-0"
      leave-to-class="opacity-0 translate-x-full"
    >
      <div v-if="showMobileMenu" class="fixed inset-0 z-[105] bg-white dark:bg-[#0a0f14]/95 backdrop-blur-3xl md:hidden flex flex-col items-center justify-center gap-8 p-12">
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
  </header>
</template>

<style scoped>
.technical-grid {
  background-size: 50px 50px;
  background-image: linear-gradient(to right, rgba(16, 185, 129, 0.05) 1px, transparent 1px), linear-gradient(to bottom, rgba(16, 185, 129, 0.05) 1px, transparent 1px);
}
</style>
