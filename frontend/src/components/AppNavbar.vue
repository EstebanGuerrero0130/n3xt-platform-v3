<script lang="ts">
// Compartido entre instancias (cada vista monta su propia navbar)
let lastIndicator: { x: number; w: number } | null = null
</script>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'
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

const menuToggle = ref<HTMLButtonElement | null>(null)
const mobileMenu = ref<HTMLElement | null>(null)

watch(showMobileMenu, (isOpen) => {
  if (isOpen) {
    document.body.classList.add('overflow-hidden')
    // Foco al primer enlace del menú; al cerrar vuelve al botón que lo abrió
    nextTick(() => mobileMenu.value?.querySelector<HTMLElement>('a, button')?.focus())
  } else {
    document.body.classList.remove('overflow-hidden')
    if (mobileMenu.value?.contains(document.activeElement) || document.activeElement === document.body) menuToggle.value?.focus()
  }
})

const onKeydown = (e: KeyboardEvent) => {
  if (e.key === 'Escape' && showMobileMenu.value) showMobileMenu.value = false
}

const isAdmin = ref(false)

// ─── Barra compacta al hacer scroll ───
const scrolled = ref(false)
let scrollRaf = 0
const onScroll = () => {
  if (scrollRaf) return
  scrollRaf = requestAnimationFrame(() => {
    scrollRaf = 0
    scrolled.value = window.scrollY > 12
  })
}

// ─── Indicador de pestaña activa que se desliza entre enlaces ───
// La navbar se monta de nuevo en cada vista: guardamos la última posición a
// nivel de módulo para que el indicador salga del enlace anterior y se deslice.
const navRef = ref<HTMLElement | null>(null)
const indicator = ref({ x: 0, w: 0, visible: false, animate: false })
const updateIndicator = (animate = true) => {
  const nav = navRef.value
  const el = nav?.querySelector<HTMLElement>('[aria-current="page"]')
  if (!nav || !el || el.offsetWidth === 0) {
    indicator.value = { ...indicator.value, visible: false }
    return
  }
  const pad = 12 // igual al px-3 de los enlaces
  const next = { x: el.offsetLeft + pad, w: Math.max(el.offsetWidth - pad * 2, 0) }
  indicator.value = { ...next, visible: true, animate }
  lastIndicator = next
}
const onResize = () => updateIndicator(false)

watch(currentTab, () => nextTick(() => updateIndicator(true)))

onMounted(async () => {
  window.addEventListener('keydown', onKeydown)
  window.addEventListener('scroll', onScroll, { passive: true })
  window.addEventListener('resize', onResize, { passive: true })
  scrolled.value = window.scrollY > 12
  // Arranca donde estaba el indicador en la vista anterior y se desliza al actual
  if (lastIndicator) indicator.value = { ...lastIndicator, visible: true, animate: false }
  requestAnimationFrame(() => requestAnimationFrame(() => updateIndicator(!!lastIndicator)))
  document.fonts?.ready.then(() => updateIndicator(false)).catch(() => {})
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
  window.removeEventListener('scroll', onScroll)
  window.removeEventListener('resize', onResize)
  if (scrollRaf) cancelAnimationFrame(scrollRaf)
  document.body.classList.remove('overflow-hidden')
})
</script>

<template>
  <header v-bind="$attrs" role="banner" :class="['app-navbar sticky top-0 w-full z-[1000]', { 'is-scrolled': scrolled }]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-6">
      <router-link to="/" class="navbar-brand flex items-baseline gap-2 shrink-0" aria-label="N3XT 3D, ir al inicio">
        <span class="font-display text-xl font-extrabold tracking-tight text-ink [font-stretch:118%]">N3XT<span class="text-brand">3D</span></span>
        <span v-if="subtext" class="navbar-subtext hidden lg:inline text-xs text-muted">{{ subtext }}</span>
      </router-link>

      <nav ref="navRef" role="navigation" aria-label="Navegación principal" class="relative hidden lg:flex items-center gap-1 h-full">
        <router-link
          v-for="link in navLinks"
          :key="link.tab"
          :to="link.to"
          :aria-current="currentTab === link.tab ? 'page' : undefined"
          :class="[
            'relative h-full inline-flex items-center px-3 text-sm font-medium transition-colors',
            currentTab === link.tab ? 'text-ink' : 'text-muted hover:text-ink'
          ]"
        >{{ link.label }}</router-link>
        <span
          class="nav-indicator"
          :class="{ 'is-visible': indicator.visible, 'is-animated': indicator.animate }"
          :style="{ transform: `translateX(${indicator.x}px) scaleX(${indicator.w})` }"
          aria-hidden="true"
        ></span>
      </nav>

      <router-link :to="isAdmin ? '/admin' : '/admin/login'" class="hidden lg:inline-flex btn-secondary btn-sm">
        {{ isAdmin ? 'Ir al taller' : 'Acceso taller' }}
      </router-link>

      <button
        ref="menuToggle"
        :aria-expanded="showMobileMenu"
        aria-controls="mobile-menu"
        :aria-label="showMobileMenu ? 'Cerrar menú' : 'Abrir menú'"
        class="lg:hidden w-11 h-11 -mr-2 flex items-center justify-center rounded-[var(--radius-control)] text-ink hover:bg-surface-2 relative z-[1010]"
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
    <nav v-if="showMobileMenu" id="mobile-menu" ref="mobileMenu" aria-label="Navegación móvil" class="fixed inset-x-0 top-16 bottom-0 z-[999] bg-canvas lg:hidden overflow-y-auto px-4 pt-4 pb-10 flex flex-col">
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

<style scoped>
/* Fondo/borde/sombra aparecen al hacer scroll. La altura no cambia (sin saltos
   de layout); la "compactación" es visual: marca un poco más pequeña. */
.app-navbar {
  border-bottom: 1px solid transparent;
  background-color: color-mix(in srgb, var(--bg-main) 72%, transparent);
  backdrop-filter: blur(10px) saturate(1.2);
  -webkit-backdrop-filter: blur(10px) saturate(1.2);
  transition:
    background-color 0.35s var(--ease-premium),
    border-color 0.35s var(--ease-premium),
    box-shadow 0.35s var(--ease-premium);
}
.app-navbar.is-scrolled {
  background-color: var(--glass-bg);
  border-bottom-color: var(--border-main);
  box-shadow: 0 1px 0 var(--card-inset) inset, 0 10px 30px -18px rgba(0, 0, 0, 0.35);
}
.navbar-brand {
  transform-origin: left center;
  transition: transform 0.35s var(--ease-premium);
}
.is-scrolled .navbar-brand {
  transform: scale(0.94);
}

/* Indicador: 1px de ancho escalado (solo transform → 60fps) */
.nav-indicator {
  position: absolute;
  left: 0;
  bottom: 0;
  width: 1px;
  height: 2px;
  border-radius: 2px;
  background: var(--color-primary);
  transform-origin: left center;
  opacity: 0;
  pointer-events: none;
}
.dark .nav-indicator {
  background: var(--brand-text);
}
.nav-indicator.is-visible {
  opacity: 1;
}
.nav-indicator.is-animated {
  transition: transform 0.45s var(--ease-premium), opacity 0.2s ease;
}
</style>
