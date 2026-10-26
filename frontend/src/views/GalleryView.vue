<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, watch, nextTick } from 'vue'
import { api } from '../services/api'
import AppNavbar from '../components/AppNavbar.vue'
import AppFooter from '../components/AppFooter.vue'
import logger from '../utils/logger'
import { srcsetFor } from '../utils/images'
import { usePageMeta } from '../composables/usePageMeta'

usePageMeta({
  title: 'Galería de Trabajos | N3XT 3D',
  description: 'Trabajos reales de nuestro taller: figuras pintadas a mano, prototipos funcionales y piezas a medida impresas en resina y filamento.',
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
  sample?: boolean
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
  <div class="min-h-screen bg-canvas text-ink overflow-x-clip">
    <AppNavbar active-tab="gallery" subtext="Galería de trabajos" />

    <main id="main" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-16 pb-24 md:pt-24 md:pb-36">
      <!-- Encabezado -->
      <header class="grid gap-6 lg:grid-cols-12 lg:items-end">
        <h1 class="section-title lg:col-span-7">Nuestros <span class="section-title-accent">trabajos</span></h1>
        <p class="section-lead lg:col-span-5">Figuras, prototipos y piezas a medida que hemos impreso en resina y filamento.</p>
      </header>

      <!-- Filtros -->
      <div v-if="!loading && !loadError && filters.length > 2" class="mt-12 flex flex-wrap gap-2 sm:gap-3" role="group" aria-label="Filtrar por categoría">
        <button
          v-for="cat in filters" :key="cat"
          type="button" class="chip"
          :aria-pressed="activeFilter === cat"
          @click="activeFilter = cat">
          {{ cat }}
          <span v-if="categoryCounts[cat] !== undefined" class="tabular-nums opacity-70">{{ categoryCounts[cat] }}</span>
        </button>
      </div>
      <div v-if="!loading && !loadError && techs.filter(t => t !== 'Todos' && t).length > 1" class="mt-3 flex flex-wrap items-center gap-2 sm:gap-3" role="group" aria-label="Filtrar por tecnología">
        <span class="text-base font-semibold text-muted mr-1">Tecnología</span>
        <button
          v-for="t in techs" :key="t"
          type="button" class="chip"
          :aria-pressed="activeTech === t"
          @click="activeTech = t">{{ t }}</button>
      </div>

      <div class="mt-12">
        <!-- Esqueleto -->
        <div v-if="loading" class="grid auto-rows-[16rem] gap-4 sm:grid-cols-2 lg:grid-cols-4" role="status">
          <span class="sr-only">Cargando trabajos…</span>
          <div v-for="i in 6" :key="i" class="rounded-[var(--radius-card)] bg-surface-2 animate-pulse" :class="i === 1 ? 'sm:col-span-2 sm:row-span-2' : ''" aria-hidden="true"></div>
        </div>

        <!-- Error -->
        <div v-else-if="loadError" class="py-24 text-center max-w-md mx-auto" role="alert">
          <h2 class="text-3xl font-black uppercase tracking-tight">No pudimos cargar la galería</h2>
          <p class="mt-3 text-lg text-muted">Revisa tu conexión e inténtalo de nuevo.</p>
          <button type="button" class="btn-primary mt-6" @click="fetchSettings">Reintentar</button>
        </div>

        <!-- Mosaico: cada 6 trabajos, uno protagonista -->
        <div v-else-if="filtered.length > 0" class="grid auto-rows-[16rem] gap-4 sm:grid-cols-2 md:auto-rows-[18rem] lg:grid-cols-4">
          <component
            :is="item.sample ? 'div' : 'router-link'"
            v-for="(item, idx) in filtered" :key="item.uid"
            :data-reveal-index="idx"
            :to="item.sample ? undefined : '/galeria/' + encodeURIComponent(item.title || String(idx))"
            :class="[
              'group relative overflow-hidden rounded-[var(--radius-card)] bg-surface-2 [container-type:inline-size]',
              idx % 6 === 0 ? 'sm:col-span-2 sm:row-span-2' : '',
              visibleCards.includes(idx) ? 'card-revealed' : 'card-hidden'
            ]"
            :style="{ '--reveal-delay': Math.min(idx * 60, 360) + 'ms' }"
          >
            <img
              v-if="item.image && !brokenImages[idx]"
              :src="item.image"
              :srcset="srcsetFor(item.image)"
              :sizes="idx % 6 === 0 ? '(min-width: 640px) 50vw, 100vw' : '(min-width: 1024px) 25vw, (min-width: 640px) 50vw, 100vw'"
              :alt="item.title"
              class="absolute inset-0 w-full h-full object-cover transition-transform duration-700 group-hover:scale-[1.04]"
              loading="lazy" decoding="async"
              @error="brokenImages[idx] = true"
            />
            <!-- Segunda foto al pasar el cursor -->
            <img
              v-if="item.images && item.images.length > 0 && !brokenImages[idx]"
              :src="item.images[0]" alt="" aria-hidden="true"
              class="absolute inset-0 w-full h-full object-cover opacity-0 group-hover:opacity-100 transition-opacity duration-500"
              loading="lazy" decoding="async"
            />
            <span class="photo-scrim absolute inset-0" aria-hidden="true"></span>
            <span v-if="item.featured" class="absolute left-4 top-4 rounded-full bg-primary px-3 py-1 text-sm font-semibold text-white">Destacado</span>
            <span v-if="item.images && item.images.length > 0" class="absolute right-4 top-4 rounded-full bg-black/60 px-3 py-1 text-sm font-semibold text-white">+{{ item.images.length }} fotos</span>
            <span class="absolute inset-x-0 bottom-0 p-5 md:p-6">
              <h2 class="font-black uppercase tracking-tight leading-none text-white [font-stretch:115%] hyphens-auto" :class="idx % 6 === 0 ? 'text-[clamp(1.75rem,6.5cqi,3rem)]' : 'text-[clamp(1.0625rem,6.8cqi,1.5rem)]'">{{ item.title || 'Sin título' }}</h2>
              <span class="mt-2 block text-sm font-semibold text-[#3fcf72]">{{ [item.category, item.technology].filter(Boolean).join(' · ') }}</span>
              <span v-if="idx % 6 === 0 && (item.subtitle || item.description)" class="mt-3 block max-w-md text-base leading-relaxed text-white/85 line-clamp-2">{{ item.subtitle || item.description }}</span>
            </span>
          </component>
        </div>

        <!-- Aún sin trabajos publicados -->
        <div v-else-if="!webSettings.gallery.length" class="py-24 text-center max-w-md mx-auto">
          <h2 class="text-3xl font-black uppercase tracking-tight">Pronto, nuevos trabajos</h2>
          <p class="mt-3 text-lg text-muted">Estamos preparando el portafolio. Mientras tanto, cotiza tu pieza o escríbenos.</p>
          <router-link to="/quote" class="btn-primary mt-6">Cotizar mi pieza</router-link>
        </div>

        <!-- Vacío -->
        <div v-else class="py-24 text-center max-w-md mx-auto">
          <h2 class="text-3xl font-black uppercase tracking-tight">Nada en este filtro</h2>
          <p class="mt-3 text-lg text-muted">Prueba con otra categoría o mira todo el portafolio.</p>
          <button type="button" class="btn-secondary mt-6" @click="activeFilter = 'Todos'; activeTech = 'Todos'">Ver todos los trabajos</button>
        </div>
      </div>

      <!-- Cierre -->
      <section class="mt-24 md:mt-36 grid gap-8 rounded-[var(--radius-panel)] bg-surface p-8 md:p-14 lg:grid-cols-12 lg:items-center">
        <h2 class="section-title lg:col-span-7">¿Tienes una <span class="section-title-accent">idea?</span></h2>
        <div class="lg:col-span-5">
          <p class="section-lead">Envíanos tu archivo 3D o una referencia y te cotizamos la pieza.</p>
          <div class="mt-6 flex flex-col gap-3 sm:flex-row">
            <router-link to="/quote" class="btn-primary btn-lg">Cotizar mi pieza</router-link>
            <router-link to="/project/init" class="btn-secondary btn-lg">Sin archivo, solo la idea</router-link>
          </div>
        </div>
      </section>
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
