<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, watch, nextTick } from 'vue'
import { api } from '../services/api'
import AppNavbar from '../components/AppNavbar.vue'
import AppFooter from '../components/AppFooter.vue'
import logger from '../utils/logger'
import { useSplitTitle } from '../composables/useSplitTitle'
import { usePageMeta } from '../composables/usePageMeta'

useSplitTitle()

usePageMeta({
  title: 'Galería de Trabajos | N3XT 3D',
  description: 'Explora nuestra galería de piezas fabricadas con precisión industrial. Figuras, prototipos y coleccionables en 3D.',
  image: '/assets/n3xt_og_gallery.png',
})


const loading = ref(true)
const loadError = ref(false)
let _timerLoad: ReturnType<typeof setTimeout> | null = null

// ─── TypeScript interfaces ───
interface GalleryItem {
  uid: number
  image: string
  title: string
  category?: string
  technology?: string
  images?: string[]
  featured?: boolean
  tags?: string
  subtitle?: string
  description?: string
  stats?: { val: string; label: string }[]
}

interface GallerySettings {
  gallery: GalleryItem[]
  cloudinary_name?: string
}

// ─── Scroll Reveal ───
const visibleCards = ref<number[]>([])
let revealObserver: IntersectionObserver | null = null
const brokenImages = ref<Record<number, boolean>>({})

const observeCards = () => {
  if (revealObserver) revealObserver.disconnect()

  revealObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      const el = entry.target as HTMLElement
      const idx = Number(el.dataset.revealIndex)
      if (entry.isIntersecting) {
        if (!visibleCards.value.includes(idx)) {
          visibleCards.value.push(idx)
        }
        revealObserver!.unobserve(entry.target)
      }
    })
  }, {
    threshold: 0.05,
    rootMargin: '0px 0px -20px 0px'
  })

  nextTick(() => {
    const cards = document.querySelectorAll('[data-reveal-index]')
    cards.forEach(card => revealObserver!.observe(card))
  })
}

// ─── Parallax (disabled — using grid now, not masonry) ───
let parallaxRAF: number | null = null

const stopParallax = () => {
  if (parallaxRAF !== null) {
    cancelAnimationFrame(parallaxRAF)
    parallaxRAF = null
  }
}

const normalizeTags = (tags: any): string[] =>
  Array.isArray(tags) ? tags : (typeof tags === 'string' ? tags.split(',').map((t: string) => t.trim()).filter(Boolean) : [])

const webSettings = ref<GallerySettings>({ gallery: [], cloudinary_name: '' })
const activeFilter = ref('Todos')
const activeTech = ref('Todos')

const filters = computed(() => {
  const cats = new Set(webSettings.value.gallery.map(i => i.category).filter(Boolean))
  return ['Todos', ...Array.from(cats)] as string[]
})

// Contador por categoría (sobre toda la galería cargada)
const categoryCounts = computed(() => {
  const counts: Record<string, number> = { Todos: webSettings.value.gallery.length }
  webSettings.value.gallery.forEach(i => {
    if (i.category) counts[i.category] = (counts[i.category] || 0) + 1
  })
  return counts
})

const techs = computed(() => {
  const t = new Set(webSettings.value.gallery.map(i => i.technology).filter(Boolean))
  return ['Todos', ...Array.from(t)] as string[]
})

const filtered = computed(() => {
  let items = webSettings.value.gallery
  if (activeFilter.value !== 'Todos') items = items.filter(i => i.category === activeFilter.value)
  if (activeTech.value !== 'Todos') items = items.filter(i => i.technology === activeTech.value)
  return items
})

watch(filtered, () => {
  visibleCards.value = []
  brokenImages.value = {}
  nextTick(() => observeCards())
}, { deep: false })

const fetchSettings = async () => {
  loading.value = true
  loadError.value = false
  if (_timerLoad !== null) clearTimeout(_timerLoad)
  try {
    const data = await api.get('/settings')
    if (data?.web?.gallery) {
      webSettings.value.gallery = data.web.gallery
        .map((item: any, idx: number) =>
          typeof item === 'string'
            ? { uid: idx, image: item, title: `Imagen ${idx + 1}`, category: 'General', technology: '', images: [], featured: false, tags: '' }
            : { images: [], tags: '', featured: false, ...item, uid: idx }
        )
        .filter((item: any) => item.image || item.title)
    }
    if (data?.web?.cloudinary_name) {
      webSettings.value.cloudinary_name = data.web.cloudinary_name
    }
  } catch (err) {
    logger.error('Error:', err)
    loadError.value = true
  } finally {
    _timerLoad = setTimeout(() => {
      loading.value = false
      nextTick(() => {
        observeCards()
      })
    }, 400)
  }
}

onMounted(() => {
  fetchSettings()
})

onUnmounted(() => {
  if (_timerLoad !== null) clearTimeout(_timerLoad)
  stopParallax()
  if (revealObserver) revealObserver.disconnect()
})
</script>


<template>
  <div class="min-h-screen bg-canvas text-ink overflow-x-hidden">
    <AppNavbar active-tab="gallery" subtext="Galería de trabajos" />

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 md:py-16">
      <!-- Encabezado -->
      <header class="max-w-2xl mb-10">
        <h1 class="split-title text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight text-ink">Nuestros trabajos</h1>
        <p class="mt-4 text-lg md:text-xl text-muted">
          Figuras, prototipos y piezas a medida que hemos impreso en resina y filamento.
        </p>
      </header>

      <!-- Filtros: categorías -->
      <div v-if="!loading && !loadError && filters.length > 1" class="flex flex-wrap gap-2 sm:gap-3" role="group" aria-label="Filtrar por categoría">
        <button
          v-for="cat in filters" :key="cat"
          type="button"
          :aria-pressed="activeFilter === cat"
          :class="[
            'inline-flex items-center gap-2 h-9 px-4 rounded-full border text-sm font-medium transition-colors',
            activeFilter === cat ? 'bg-ink text-canvas border-ink' : 'border-line text-muted hover:text-ink'
          ]"
          @click="activeFilter = cat">
          {{ cat }}
          <span v-if="categoryCounts[cat] !== undefined" class="tabular-nums opacity-70">{{ categoryCounts[cat] }}</span>
        </button>
      </div>

      <!-- Filtros: tecnología -->
      <div v-if="!loading && !loadError && techs.filter(t => t !== 'Todos' && t !== '' && t !== undefined).length > 0" class="mt-4 flex flex-wrap items-center gap-2 sm:gap-3" role="group" aria-label="Filtrar por tecnología">
        <span class="text-sm text-muted mr-1">Tecnología</span>
        <button
          v-for="t in techs" :key="t"
          type="button"
          :aria-pressed="activeTech === t"
          :class="[
            'h-9 px-4 rounded-full border text-sm font-medium transition-colors',
            activeTech === t ? 'bg-ink text-canvas border-ink' : 'border-line text-muted hover:text-ink'
          ]"
          @click="activeTech = t">
          {{ t }}
        </button>
      </div>

      <div class="mt-10">
        <!-- Skeleton -->
        <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6" aria-hidden="true">
          <div v-for="i in 6" :key="i" class="bg-surface border border-line rounded-[var(--radius-card)] overflow-hidden">
            <div class="aspect-[4/3] bg-surface-2 animate-pulse"></div>
            <div class="p-5 space-y-3">
              <div class="h-5 bg-surface-2 animate-pulse rounded w-3/4"></div>
              <div class="h-4 bg-surface-2 animate-pulse rounded w-1/3"></div>
              <div class="flex gap-2 pt-1">
                <div class="h-6 w-16 bg-surface-2 animate-pulse rounded-md"></div>
                <div class="h-6 w-12 bg-surface-2 animate-pulse rounded-md"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Error -->
        <div v-else-if="loadError" class="py-24 text-center max-w-md mx-auto" role="alert">
          <h2 class="text-2xl font-extrabold tracking-tight text-ink">No pudimos cargar la galería</h2>
          <p class="mt-3 text-muted">Revisa tu conexión e inténtalo de nuevo.</p>
          <button type="button" class="btn-primary mt-6" @click="fetchSettings">Reintentar</button>
        </div>

        <!-- Grid -->
        <div v-else-if="filtered.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
          <router-link
            v-for="(item, idx) in filtered" :key="item.uid"
            :data-reveal-index="idx"
            :to="'/galeria/' + encodeURIComponent(item.title || String(idx))"
            :class="[
              'group flex flex-col bg-surface border border-line rounded-[var(--radius-card)] overflow-hidden transition-colors hover:border-primary',
              visibleCards.includes(idx) ? 'card-revealed' : 'card-hidden'
            ]"
            :style="{ '--reveal-delay': Math.min(idx * 60, 360) + 'ms' }"
          >
            <div class="relative aspect-[4/3] bg-surface-2 overflow-hidden">
              <!-- Imagen secundaria (se muestra al pasar el cursor) -->
              <img
                v-if="item.images && item.images.length > 0 && !brokenImages[idx]"
                :src="item.images[0]"
                class="absolute inset-0 w-full h-full object-cover opacity-0 group-hover:opacity-100 transition-opacity duration-500 z-10"
                loading="lazy"
                decoding="async"
                alt=""
                aria-hidden="true"
              />
              <img
                v-if="item.image && !brokenImages[idx]"
                :src="item.image"
                :alt="'Trabajo de impresión 3D: ' + item.title"
                class="absolute inset-0 w-full h-full object-cover"
                loading="lazy"
                decoding="async"
                @error="brokenImages[idx] = true"
              />
              <div v-else class="absolute inset-0 flex items-center justify-center text-muted/60">
                <svg class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.25" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
              </div>
              <span v-if="item.featured" class="absolute top-3 left-3 z-20 inline-flex items-center h-6 px-2 rounded-md text-xs font-medium bg-surface text-ink border border-line">Destacado</span>
              <span v-if="item.images && item.images.length > 0" class="absolute bottom-3 right-3 z-20 inline-flex items-center h-6 px-2 rounded-md text-xs font-medium bg-black/60 text-white">+{{ item.images.length }} fotos</span>
            </div>

            <div class="flex flex-col flex-1 p-5">
              <h2 class="text-lg md:text-xl font-bold tracking-tight text-ink group-hover:text-brand transition-colors line-clamp-2">
                {{ item.title || 'Sin título' }}
              </h2>
              <p v-if="item.category || item.technology" class="mt-1.5 flex flex-wrap gap-x-3 text-sm text-muted">
                <span v-if="item.category">{{ item.category }}</span>
                <span v-if="item.technology">{{ item.technology }}</span>
              </p>
              <div v-if="normalizeTags(item.tags).length > 0" class="mt-4 flex flex-wrap gap-2">
                <span
                  v-for="tag in normalizeTags(item.tags).slice(0, 2)" :key="tag"
                  class="inline-flex items-center h-6 px-2 rounded-md text-xs font-medium bg-surface-2 text-ink">
                  {{ tag }}
                </span>
              </div>
            </div>
          </router-link>
        </div>

        <!-- Vacío -->
        <div v-else class="py-24 text-center max-w-md mx-auto">
          <h2 class="text-2xl font-extrabold tracking-tight text-ink">Aún no hay trabajos en este filtro</h2>
          <p class="mt-3 text-muted">Prueba con otra categoría o mira todo el portafolio.</p>
          <button type="button" class="btn-secondary mt-6" @click="activeFilter = 'Todos'; activeTech = 'Todos'">Ver todos los trabajos</button>
        </div>
      </div>
    </main>

    <AppFooter />
  </div>
</template>

<style scoped>
/* Entrada suave al hacer scroll (solo opacidad y un leve desplazamiento) */
.card-hidden {
  opacity: 0;
  transform: translateY(12px);
}

.card-revealed {
  opacity: 1;
  transform: translateY(0);
  transition: opacity 0.5s cubic-bezier(0.22, 1, 0.36, 1) var(--reveal-delay, 0ms),
              transform 0.5s cubic-bezier(0.22, 1, 0.36, 1) var(--reveal-delay, 0ms),
              border-color 0.2s ease;
}

.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
