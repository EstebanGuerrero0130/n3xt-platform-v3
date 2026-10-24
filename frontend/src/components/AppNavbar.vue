<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRoute } from 'vue-router'

// Dos nodos raíz (header + overlay móvil): evitamos warnings de atributos heredados
defineOptions({ inheritAttrs: false })

const props = defineProps<{ activeTab?: string; subtext?: string }>()

const route = useRoute()

const routeTabs: Record<string, string> = {
  'home': 'home',
  'gallery': 'gallery',
  'gallery-detail': 'gallery',
  'catalog': 'catalog',
  'product-detail': 'catalog',
  'quote': 'quote',
  'track': 'track',
  'project-init': 'contact'
}

// La ruta actual manda; el prop se mantiene por compatibilidad
const currentTab = computed(() => routeTabs[String(route.name ?? '')] ?? props.activeTab ?? '')

const navLinks = [
  { tab: 'home', to: '/', label: 'Inicio' },
  { tab: 'gallery', to: '/galeria', label: 'Galería' },
  { tab: 'catalog', to: '/catalog', label: 'Catálogo' },
  { tab: 'quote', to: '/quote', label: 'Cotizador' },
  { tab: 'track', to: '/track', label: 'Rastrear' },
  { tab: 'contact', to: '/project/init', label: 'Contáctanos' }
]

const showMobileMenu = ref(false)

watch(showMobileMenu, (isOpen) => {
  if (isOpen) {
    document.body.classList.add('overflow-hidden')
  } else {
    document.body.classList.remove('overflow-hidden')
  }
})

const onKeydown = (e: KeyboardEvent) => {
  if (e.key === 'Escape' && showMobileMenu.value) showMobileMenu.value = false
}

const isAdmin = ref(false)

onMounted(async () => {
  window.addEventListener('keydown', onKeydown)
  try {
    const { api } = await import('../services/api')
    const auth = await api.checkAuth()
    isAdmin.value = !!auth?.authenticated && auth.role === 'admin'
  } catch {
    isAdmin.value = false
  }
})

onUnmounted(() => {
  window.removeEventListener('keydown', onKeydown)
  document.body.classList.remove('overflow-hidden')
})
</script>

<template>
  <header v-bind="$attrs" role="banner" class="glass-panel !border-x-0 !border-t-0 sticky top-0 w-full z-[1000] transition-colors duration-500">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-6">
      <router-link to="/" class="flex items-baseline gap-2 shrink-0" aria-label="N3XT 3D, ir al inicio">
        <span class="font-display text-xl font-extrabold tracking-tight text-ink [font-stretch:118%]">N3XT<span class="text-brand">3D</span></span>
        <span v-if="subtext" class="hidden lg:inline text-xs text-muted">{{ subtext }}</span>
      </router-link>

      <nav role="navigation" aria-label="Navegación principal" class="hidden md:flex items-center gap-1 h-full">
        <router-link
          v-for="link in navLinks"
          :key="link.tab"
          :to="link.to"
          :aria-current="currentTab === link.tab ? 'page' : undefined"
          :class="[
            'relative h-full inline-flex items-center px-3 text-sm font-medium transition-colors',
            currentTab === link.tab
              ? 'text-ink after:absolute after:inset-x-3 after:bottom-0 after:h-0.5 after:bg-primary'
              : 'text-muted hover:text-ink'
          ]"
        >{{ link.label }}</router-link>
      </nav>

      <router-link :to="isAdmin ? '/admin' : '/admin/login'" class="hidden md:inline-flex btn-secondary btn-sm">
        {{ isAdmin ? 'Ir al taller' : 'Acceso taller' }}
      </router-link>

      <button
        :aria-expanded="showMobileMenu"
        aria-controls="mobile-menu"
        :aria-label="showMobileMenu ? 'Cerrar menú' : 'Abrir menú'"
        class="md:hidden w-11 h-11 -mr-2 flex items-center justify-center rounded-[var(--radius-control)] text-ink hover:bg-surface-2 relative z-[1010]"
        @click="showMobileMenu = !showMobileMenu"
      >
        <svg v-if="!showMobileMenu" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-width="2" d="M4 7h16M4 12h16M4 17h16"/></svg>
        <svg v-else class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
  </header>

  <!-- Menú móvil -->
  <transition
    enter-active-class="transition duration-300 ease-out"
    enter-from-class="opacity-0 -translate-y-2"
    enter-to-class="opacity-100 translate-y-0"
    leave-active-class="transition duration-200 ease-in"
    leave-from-class="opacity-100 translate-y-0"
    leave-to-class="opacity-0 -translate-y-2"
  >
    <nav v-if="showMobileMenu" id="mobile-menu" aria-label="Navegación móvil" class="fixed inset-x-0 top-16 bottom-0 z-[999] bg-canvas md:hidden overflow-y-auto px-4 pt-4 pb-10 flex flex-col">
      <router-link
        v-for="link in navLinks"
        :key="link.tab"
        :to="link.to"
        :aria-current="currentTab === link.tab ? 'page' : undefined"
        :class="[
          'flex items-center justify-between py-4 border-b border-line text-2xl font-semibold [font-stretch:110%]',
          currentTab === link.tab ? 'text-brand' : 'text-ink'
        ]"
        @click="showMobileMenu = false"
      >{{ link.label }}</router-link>

      <router-link :to="isAdmin ? '/admin' : '/admin/login'" class="mt-8 btn-secondary btn-lg w-full" @click="showMobileMenu = false">
        {{ isAdmin ? 'Ir al taller' : 'Acceso taller' }}
      </router-link>
    </nav>
  </transition>
</template>
