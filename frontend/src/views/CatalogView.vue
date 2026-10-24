<script setup lang="ts">

import { ref, onMounted, onUnmounted, computed, watch, nextTick } from 'vue'
import { parsePrice, formatCOP } from '../utils/format'
import { api } from '../services/api'
import AppNavbar from '../components/AppNavbar.vue'
import AppFooter from '../components/AppFooter.vue'
import logger from '../utils/logger'
import { useSplitTitle } from '../composables/useSplitTitle'
import { useSplitButton } from '../composables/useSplitButton'
import { usePageMeta } from '../composables/usePageMeta'

useSplitTitle()
const { applySplitBtn } = useSplitButton()

usePageMeta({
  title: 'Catálogo de Piezas 3D | N3XT 3D',
  description: 'Explora nuestra galería de piezas fabricadas con precisión industrial. Figuras, prototipos y coleccionables en 3D.',
  image: '/assets/n3xt_og_catalog.png',
})

const tickerSection = ref<HTMLElement | null>(null)
const tickerVisible = ref(true)

const loading = ref(true)
const loadError = ref(false)

// ─── TypeScript Interfaces ───
interface CatalogItem {
  name: string
  image: string
  category?: string
  subcategory?: string
  status?: string
  price?: string | number
  original_price?: string | number
  description?: string
  [key: string]: any
}

interface WebSettings {
  catalog: CatalogItem[]
  pdf_catalog_url: string
  pdf_catalog_desc: string
  cloudinary_name?: string
}

const webSettings = ref<WebSettings>({
  catalog: [],
  pdf_catalog_url: '',
  pdf_catalog_desc: '',
  cloudinary_name: ''
})

const activeCategory = ref('Todos')
const activeSubcategory = ref('Todos')

const categories = computed(() => {
  const cats = new Set(webSettings.value.catalog.map(i => i.category).filter(Boolean))
  return ['Todos', ...Array.from(cats)] as string[]
})

const availableSubcategories = computed(() => {
  if (activeCategory.value === 'Todos') return []
  const subs = [...new Set(
    webSettings.value.catalog
      .filter(i => i.category === activeCategory.value && i.subcategory)
      .map(i => i.subcategory)
  )]
  return subs.length > 0 ? ['Todos', ...subs] as string[] : []
})

const filteredItems = computed(() => {
  let all = webSettings.value.catalog.filter(i => i.status === 'active' || !i.status)
  if (activeCategory.value !== 'Todos') {
    all = all.filter(i => i.category === activeCategory.value)
  }
  if (activeSubcategory.value !== 'Todos') {
    all = all.filter(i => i.subcategory === activeSubcategory.value)
  }
  return all
})

const getDiscountPct = (item: CatalogItem) => {
  const p = parsePrice(item.price)
  const op = parsePrice(item.original_price)
  if (!p || !op || p >= op) return 0
  return Math.round((1 - p / op) * 100)
}

const isDiscounted = (item: CatalogItem) => {
  const p = parsePrice(item.price)
  const op = parsePrice(item.original_price)
  return p !== null && op !== null && p > 0 && p < op
}

const getOptimizedImage = (url: string) => {
  if (!url) return ''
  if (!webSettings.value.cloudinary_name || !url.includes('cloudinary.com')) return url
  // Si es cloudinary, forzar auto-format y auto-quality
  return url.replace('/upload/', '/upload/f_auto,q_auto,w_800/')
}

const CACHE_KEY = 'n3xt_catalog_cache'
const CACHE_TTL = 5 * 60 * 1000 // 5 minutos

const fetchSettings = async () => {
  loadError.value = false
  // Intentar caché primero
  try {
    const cached = localStorage.getItem(CACHE_KEY)
    if (cached) {
      const { data, timestamp } = JSON.parse(cached)
      if (Date.now() - timestamp < CACHE_TTL && data.web) {
        const newWeb = { ...data.web }
        if (!Array.isArray(newWeb.catalog)) newWeb.catalog = []
        webSettings.value = { ...webSettings.value, ...newWeb }
        loading.value = false
        return
      }
    }
  } catch { /* storage bloqueado o cache corrupto: ignorar */ }

  loading.value = true
  try {
    const data = await api.get('/settings')
    if (data?.web) {
      const newWeb = { ...data.web }
      if (!Array.isArray(newWeb.catalog)) {
          newWeb.catalog = []
      }
      webSettings.value = { ...webSettings.value, ...newWeb }
      // Guardar en caché
      try {
        localStorage.setItem(CACHE_KEY, JSON.stringify({ data, timestamp: Date.now() }))
      } catch { /* ignorar error de storage */ }
    }
  } catch (err) {
    logger.error('Error:', err)
    loadError.value = true
  } finally {
    loading.value = false
    nextTick(() => applySplitBtn())
  }
}

const staggerKey = ref(0)

// ─── MARCAS / FRANQUICIAS ───
const brands = [
  { name: 'Pokémon', url: '/assets/brands/pokemon.svg', className: 'h-8 md:h-10' },
  { name: 'Warhammer 40k', url: '/assets/brands/warhammer-40k.svg', className: 'h-10 md:h-12' },
  { name: 'Dungeons & Dragons', url: '/assets/brands/dungeons-dragons.svg', className: 'h-8 md:h-10' },
  { name: 'Star Wars', url: '/assets/brands/star-wars.svg', className: 'h-8 md:h-10' },
  { name: 'Marvel', url: '/assets/brands/marvel.svg', className: 'h-7 md:h-9' },
  { name: 'Bandai Namco', url: '/assets/brands/bandai-namco.svg', className: 'h-6 md:h-8' },
  { name: 'Nintendo', url: '/assets/brands/nintendo.svg', className: 'h-7 md:h-9' },
  { name: 'LEGO', url: '/assets/brands/lego.svg', className: 'h-8 md:h-10' },
  { name: 'DC Comics', url: '/assets/brands/dc-comics.svg', className: 'h-8 md:h-10' },
  { name: 'One Piece', url: '/assets/brands/one-piece.svg', className: 'h-8 md:h-10' },
  { name: 'Dragon Ball Z', url: '/assets/brands/dragon-ball-z.svg', className: 'h-8 md:h-10' },
  { name: 'Sonic', url: '/assets/brands/sonic.svg', className: 'h-7 md:h-9' },
  { name: 'Minecraft', url: '/assets/brands/minecraft.svg', className: 'h-8 md:h-10' },
  { name: 'Harry Potter', url: '/assets/brands/harry-potter.svg', className: 'h-7 md:h-9' },
]

// Intercalar marcas: crear un array plano con las marcas mezcladas, repetido 3 veces
const tickerBrands = computed(() => {
  const result: any[] = []
  for (let rep = 0; rep < 3; rep++) {
    // En cada repetición, rotamos el orden para que no sea idéntico
    const shifted = [...brands.slice(rep % brands.length), ...brands.slice(0, rep % brands.length)]
    shifted.forEach((brand, i) => {
      result.push({ ...brand, key: brand.name + '-' + rep + '-' + i })
    })
  }
  return result
})

// Watch filter changes to trigger stagger animation
watch([activeCategory, activeSubcategory], () => {
  staggerKey.value++
})

onMounted(() => {
  fetchSettings()  

  // Pausar el ticker de marcas cuando no está visible (ahorra CPU/GPU)
  const tickerObserver = new IntersectionObserver(
    ([entry]) => {
      tickerVisible.value = entry.isIntersecting
    },
    { threshold: 0 }
  )
  if (tickerSection.value) tickerObserver.observe(tickerSection.value)
  onUnmounted(() => tickerObserver.disconnect())
})
</script>

<template>
  <div class="min-h-screen bg-canvas text-ink overflow-x-hidden">
    <AppNavbar active-tab="catalog" subtext="Catálogo de piezas" />

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 md:py-16">
      <!-- Encabezado -->
      <header class="flex flex-col md:flex-row md:items-end md:justify-between gap-6">
        <div class="max-w-2xl">
          <h1 class="split-title text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight text-ink">Catálogo</h1>
          <p class="mt-4 text-lg md:text-xl text-muted">Piezas listas para pedir: figuras, coleccionables y accesorios impresos en nuestro taller.</p>
        </div>
        <div v-if="webSettings.pdf_catalog_url" class="flex flex-col items-start md:items-end gap-2 shrink-0">
          <a
            :href="webSettings.pdf_catalog_url"
            target="_blank"
            rel="noopener"
            class="split-btn btn-secondary"
          >
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16" /></svg>
            <span>Descargar catálogo en PDF</span>
          </a>
          <p v-if="webSettings.pdf_catalog_desc" class="text-sm text-muted md:text-right max-w-xs">{{ webSettings.pdf_catalog_desc }}</p>
        </div>
      </header>

      <!-- Franja de confianza -->
      <ul class="mt-8 flex flex-col sm:flex-row sm:flex-wrap gap-x-8 gap-y-3 py-4 border-y border-line text-sm text-ink" aria-label="Condiciones de compra">
        <li class="inline-flex items-center gap-2">
          <svg class="w-5 h-5 text-brand shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0zM5 17H3V6a1 1 0 011-1h9v12M13 9h4l3 4v4h-1"/></svg>
          Envíos a todo Colombia
        </li>
        <li class="inline-flex items-center gap-2">
          <svg class="w-5 h-5 text-brand shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
          Garantía de 6 meses
        </li>
        <li class="inline-flex items-center gap-2">
          <svg class="w-5 h-5 text-brand shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
          Pedidos y asesoría por WhatsApp
        </li>
      </ul>

      <!-- Filtros: categorías -->
      <div v-if="categories.length > 1" class="mt-8 flex flex-wrap gap-2 sm:gap-3" role="group" aria-label="Filtrar por categoría">
        <button
          v-for="cat in categories"
          :key="cat"
          type="button"
          :aria-pressed="activeCategory === cat"
          :class="[
            'h-9 px-4 rounded-full border text-sm font-medium transition-colors',
            activeCategory === cat ? 'bg-ink text-canvas border-ink' : 'border-line text-muted hover:text-ink'
          ]"
          @click="activeCategory = cat; activeSubcategory = 'Todos'"
        >
          {{ cat }}
        </button>
      </div>

      <!-- Filtros: subcategorías -->
      <div v-if="availableSubcategories.length > 0" class="mt-4 flex flex-wrap items-center gap-2 sm:gap-3" role="group" aria-label="Filtrar por subcategoría">
        <span class="text-sm text-muted mr-1">{{ activeCategory }}</span>
        <button
          v-for="sub in availableSubcategories"
          :key="sub"
          type="button"
          :aria-pressed="activeSubcategory === sub"
          :class="[
            'h-9 px-4 rounded-full border text-sm font-medium transition-colors',
            activeSubcategory === sub ? 'bg-ink text-canvas border-ink' : 'border-line text-muted hover:text-ink'
          ]"
          @click="activeSubcategory = sub"
        >
          {{ sub }}
        </button>
      </div>

      <div class="mt-10">
        <!-- Skeleton -->
        <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6" aria-hidden="true">
          <div v-for="i in 6" :key="i" class="bg-surface border border-line rounded-[var(--radius-card)] overflow-hidden">
            <div class="aspect-square bg-surface-2 animate-pulse"></div>
            <div class="p-5 space-y-3">
              <div class="h-3 bg-surface-2 animate-pulse rounded w-1/4"></div>
              <div class="h-5 bg-surface-2 animate-pulse rounded w-3/4"></div>
              <div class="h-6 bg-surface-2 animate-pulse rounded w-1/3"></div>
            </div>
          </div>
        </div>

        <!-- Error -->
        <div v-else-if="loadError" class="py-24 text-center max-w-md mx-auto" role="alert">
          <h2 class="text-2xl font-extrabold tracking-tight text-ink">No pudimos cargar el catálogo</h2>
          <p class="mt-3 text-muted">Revisa tu conexión e inténtalo de nuevo.</p>
          <button type="button" class="btn-primary mt-6" @click="fetchSettings">Reintentar</button>
        </div>

        <!-- Productos -->
        <div v-else-if="filteredItems.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
          <router-link
            v-for="(item, index) in filteredItems"
            :key="'cat-' + staggerKey + '-' + index"
            :to="'/catalog/' + encodeURIComponent(item.name)"
            :style="{ '--stagger-delay': Math.min(index * 50, 400) + 'ms' }"
            class="group flex flex-col bg-surface border border-line rounded-[var(--radius-card)] overflow-hidden transition-colors hover:border-primary stagger-item"
          >
            <div class="relative aspect-square bg-surface-2 overflow-hidden">
              <img
                v-if="item.image"
                :src="getOptimizedImage(item.image)"
                :alt="'Catálogo N3XT 3D — ' + item.name"
                :fetchpriority="index === 0 ? 'high' : 'auto'"
                class="absolute inset-0 w-full h-full object-cover"
                :loading="index === 0 ? 'eager' : 'lazy'"
                decoding="async"
                @error="(e: any) => e.target.style.display='none'"
              />
              <span v-if="isDiscounted(item)" class="absolute top-3 left-3 inline-flex items-center h-6 px-2 rounded-md text-xs font-semibold bg-surface text-resin border border-line tabular-nums">
                -{{ getDiscountPct(item) }}%
              </span>
            </div>

            <div class="flex flex-col flex-1 p-5">
              <p v-if="item.category" class="text-xs text-muted">{{ item.category }}</p>
              <h2 class="mt-1 text-lg md:text-xl font-bold tracking-tight text-ink group-hover:text-brand transition-colors line-clamp-2">{{ item.name }}</h2>
              <p class="mt-auto pt-4 flex flex-wrap items-baseline gap-x-2">
                <span class="text-xl font-semibold tabular-nums text-resin">{{ formatCOP(item.price) }}</span>
                <span v-if="isDiscounted(item)" class="text-sm tabular-nums text-muted line-through">
                  <span class="sr-only">Antes </span>{{ formatCOP(item.original_price) }}
                </span>
              </p>
            </div>
          </router-link>
        </div>

        <!-- Vacío -->
        <div v-else class="py-24 text-center max-w-md mx-auto">
          <h2 class="text-2xl font-extrabold tracking-tight text-ink">Aún no hay piezas en esta categoría</h2>
          <p class="mt-3 text-muted">Prueba con otra categoría o cotiza una pieza a medida.</p>
          <button type="button" class="btn-secondary mt-6" @click="activeCategory = 'Todos'; activeSubcategory = 'Todos'">Ver todo el catálogo</button>
        </div>
      </div>
    </main>

    <!-- Franquicias -->
    <section ref="tickerSection" class="py-16 md:py-20 overflow-hidden bg-surface border-t border-line" aria-labelledby="brands-heading">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-10">
        <h2 id="brands-heading" class="text-3xl md:text-4xl font-extrabold tracking-tight text-ink">Universos que imprimimos</h2>
        <p class="mt-3 text-muted max-w-2xl">Figuras y accesorios inspirados en las franquicias que más piden nuestros clientes.</p>
      </div>

      <div class="relative">
        <div class="absolute inset-y-0 left-0 w-12 md:w-24 z-10 pointer-events-none bg-gradient-to-r from-surface to-transparent"></div>
        <div class="absolute inset-y-0 right-0 w-12 md:w-24 z-10 pointer-events-none bg-gradient-to-l from-surface to-transparent"></div>

        <div class="relative w-full overflow-hidden ticker-wrap-catalog">
          <div class="ticker-track-catalog flex" :style="{ animationPlayState: tickerVisible ? 'running' : 'paused' }">
            <div
              v-for="brand in tickerBrands"
              :key="brand.key"
              class="flex items-center justify-center shrink-0 h-16 px-6 md:px-10"
            >
              <img
                :src="brand.url"
                :alt="brand.name"
                :class="[brand.className, 'w-auto', 'brand-logo']"
                loading="lazy"
                decoding="async"
                @error="(e: any) => e.target.style.display='none'"
              />
            </div>
          </div>
        </div>
      </div>
    </section>

    <AppFooter class="!mt-0" />
  </div>
</template>

<style scoped>
/* Entrada de tarjetas al cambiar de filtro: solo opacidad y un leve desplazamiento */
.stagger-item {
  opacity: 0;
  animation: cardStaggerIn 0.45s cubic-bezier(0.22, 1, 0.36, 1) forwards;
  animation-delay: var(--stagger-delay, 0ms);
}

@keyframes cardStaggerIn {
  from { opacity: 0; transform: translateY(8px); }
  to { opacity: 1; transform: translateY(0); }
}

/* ─── Ticker de franquicias ─── */
@keyframes catalogScroll {
  from { transform: translateX(0); }
  to { transform: translateX(-50%); }
}

.ticker-track-catalog {
  width: max-content;
  animation: catalogScroll 50s linear infinite;
  will-change: transform;
}

.brand-logo {
  filter: grayscale(1) contrast(0.8);
  opacity: 0.7;
  transition: filter 0.3s ease, opacity 0.3s ease;
}

:is(.dark) .brand-logo {
  filter: grayscale(1) brightness(1.4) contrast(0.9);
  opacity: 0.8;
}

@media (hover: hover) {
  .ticker-wrap-catalog:hover .ticker-track-catalog {
    animation-play-state: paused;
  }

  .brand-logo:hover {
    filter: none;
    opacity: 1;
  }
}

.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
