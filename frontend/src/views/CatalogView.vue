<script setup lang="ts">

import { ref, onMounted, onUnmounted, computed, watch } from 'vue'
import { parsePrice, formatCOP } from '../utils/format'
import { api } from '../services/api'
import AppNavbar from '../components/AppNavbar.vue'
import AppFooter from '../components/AppFooter.vue'
import logger from '../utils/logger'
import { usePageMeta } from '../composables/usePageMeta'

usePageMeta({
  title: 'Catálogo de Piezas 3D | N3XT 3D',
  description: 'Piezas impresas en 3D listas para pedir: figuras coleccionables, accesorios y repuestos en resina y filamento, con envío a toda Colombia.',
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
  }
}

const staggerKey = ref(0)

// ─── MARCAS / FRANQUICIAS ───
const brands = [
  { name: 'Pokémon', url: '/assets/brands/pokemon.svg', className: 'h-10 md:h-14' },
  { name: 'Warhammer 40k', url: '/assets/brands/warhammer-40k.svg', className: 'h-12 md:h-16' },
  { name: 'Dungeons & Dragons', url: '/assets/brands/dungeons-dragons.svg', className: 'h-10 md:h-14' },
  { name: 'Star Wars', url: '/assets/brands/star-wars.svg', className: 'h-10 md:h-14' },
  { name: 'Marvel', url: '/assets/brands/marvel.svg', className: 'h-9 md:h-12' },
  { name: 'Bandai Namco', url: '/assets/brands/bandai-namco.svg', className: 'h-8 md:h-11' },
  { name: 'Nintendo', url: '/assets/brands/nintendo.svg', className: 'h-9 md:h-12' },
  { name: 'LEGO', url: '/assets/brands/lego.svg', className: 'h-10 md:h-14' },
  { name: 'DC Comics', url: '/assets/brands/dc-comics.svg', className: 'h-10 md:h-14' },
  { name: 'One Piece', url: '/assets/brands/one-piece.svg', className: 'h-10 md:h-14' },
  { name: 'Dragon Ball Z', url: '/assets/brands/dragon-ball-z.svg', className: 'h-10 md:h-14' },
  { name: 'Sonic', url: '/assets/brands/sonic.svg', className: 'h-9 md:h-12' },
  { name: 'Minecraft', url: '/assets/brands/minecraft.svg', className: 'h-10 md:h-14' },
  { name: 'Harry Potter', url: '/assets/brands/harry-potter.svg', className: 'h-9 md:h-12' },
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
  <div class="min-h-screen bg-canvas text-ink overflow-x-clip">
    <AppNavbar active-tab="catalog" subtext="Catálogo de piezas" />

    <main id="main" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-16 pb-24 md:pt-24 md:pb-36">
      <!-- Encabezado -->
      <header class="grid gap-6 lg:grid-cols-12 lg:items-end">
        <h1 class="section-title lg:col-span-7">Catálogo <span class="section-title-accent">N3XT</span></h1>
        <div class="lg:col-span-5">
          <p class="section-lead">Piezas listas para pedir: figuras, coleccionables y accesorios impresos en nuestro taller.</p>
          <a
            v-if="webSettings.pdf_catalog_url"
            :href="webSettings.pdf_catalog_url" target="_blank" rel="noopener"
            class="btn-secondary mt-6"
          >
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16" /></svg>
            Descargar catálogo en PDF
          </a>
          <p v-if="webSettings.pdf_catalog_url && webSettings.pdf_catalog_desc" class="mt-2 text-sm text-muted">{{ webSettings.pdf_catalog_desc }}</p>
        </div>
      </header>

      <!-- Condiciones de compra -->
      <ul class="mt-12 grid gap-4 sm:grid-cols-3" aria-label="Condiciones de compra">
        <li v-for="c in [
          { t: 'Envíos a todo Colombia', d: 'Empaque protegido para piezas delicadas.' },
          { t: 'Garantía de 6 meses', d: 'Si algo falla por fabricación, lo reponemos.' },
          { t: 'Asesoría por WhatsApp', d: 'Te ayudamos a elegir tamaño, color y acabado.' },
        ]" :key="c.t" class="rounded-[var(--radius-card)] bg-surface p-5">
          <p class="text-lg font-black uppercase tracking-tight [font-stretch:115%]">{{ c.t }}</p>
          <p class="mt-1 text-base text-muted">{{ c.d }}</p>
        </li>
      </ul>

      <!-- Filtros -->
      <div v-if="categories.length > 1" class="mt-12 flex flex-wrap gap-2 sm:gap-3" role="group" aria-label="Filtrar por categoría">
        <button
          v-for="cat in categories" :key="cat"
          type="button" class="chip"
          :aria-pressed="activeCategory === cat"
          @click="activeCategory = cat; activeSubcategory = 'Todos'"
        >{{ cat }}</button>
      </div>
      <div v-if="availableSubcategories.length > 0" class="mt-3 flex flex-wrap items-center gap-2 sm:gap-3" role="group" aria-label="Filtrar por subcategoría">
        <span class="text-base font-semibold text-muted mr-1">{{ activeCategory }}</span>
        <button
          v-for="sub in availableSubcategories" :key="sub"
          type="button" class="chip"
          :aria-pressed="activeSubcategory === sub"
          @click="activeSubcategory = sub"
        >{{ sub }}</button>
      </div>

      <div class="mt-12">
        <!-- Esqueleto -->
        <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6" role="status">
          <span class="sr-only">Cargando catálogo…</span>
          <div v-for="i in 6" :key="i" class="aspect-[4/5] rounded-[var(--radius-panel)] bg-surface-2 animate-pulse" aria-hidden="true"></div>
        </div>

        <!-- Error -->
        <div v-else-if="loadError" class="py-24 text-center max-w-md mx-auto" role="alert">
          <h2 class="text-3xl font-black uppercase tracking-tight">No pudimos cargar el catálogo</h2>
          <p class="mt-3 text-lg text-muted">Revisa tu conexión e inténtalo de nuevo.</p>
          <button type="button" class="btn-primary mt-6" @click="fetchSettings">Reintentar</button>
        </div>

        <!-- Productos: foto de fondo, nombre y precio encima -->
        <div v-else-if="filteredItems.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
          <router-link
            v-for="(item, index) in filteredItems"
            :key="'cat-' + staggerKey + '-' + index"
            :to="'/catalog/' + encodeURIComponent(item.name)"
            :style="{ '--stagger-delay': Math.min(index * 50, 400) + 'ms' }"
            class="product-card group relative flex aspect-[4/5] flex-col justify-end overflow-hidden rounded-[var(--radius-panel)] bg-surface-2 p-6 stagger-item"
          >
            <img
              v-if="item.image"
              :src="getOptimizedImage(item.image)"
              :alt="item.name"
              :fetchpriority="index === 0 ? 'high' : 'auto'"
              :loading="index < 3 ? 'eager' : 'lazy'"
              decoding="async"
              class="absolute inset-0 w-full h-full object-cover transition-transform duration-700 group-hover:scale-[1.04]"
              @error="(e: any) => e.target.style.display='none'"
            />
            <span class="photo-scrim absolute inset-0" aria-hidden="true"></span>
            <span v-if="isDiscounted(item)" class="absolute left-4 top-4 rounded-full bg-[#f0a63c] px-3 py-1 text-sm font-bold text-[#1a1206] tabular-nums">
              -{{ getDiscountPct(item) }}%
            </span>
            <div class="relative">
              <p v-if="item.category" class="text-sm font-semibold text-[#3fcf72]">{{ [item.category, item.subcategory].filter(Boolean).join(' · ') }}</p>
              <h2 class="product-card-title mt-1 font-black uppercase tracking-tight leading-[0.95] text-white line-clamp-2">{{ item.name }}</h2>
              <p class="mt-4 flex flex-wrap items-baseline gap-x-3">
                <span class="text-2xl font-black tabular-nums text-white">{{ formatCOP(item.price) }}</span>
                <span v-if="isDiscounted(item)" class="text-base tabular-nums text-white/70 line-through">
                  <span class="sr-only">Antes </span>{{ formatCOP(item.original_price) }}
                </span>
              </p>
              <span class="mt-4 inline-flex items-center gap-2 text-base font-semibold text-white">
                Ver pieza
                <svg class="btn-arrow w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14m-6-6 6 6-6 6"/></svg>
              </span>
            </div>
          </router-link>
        </div>

        <!-- Vacío -->
        <div v-else class="grid gap-8 rounded-[var(--radius-panel)] bg-surface p-8 md:p-14 lg:grid-cols-12 lg:items-center">
          <h2 class="section-title lg:col-span-7">Pronto, <span class="section-title-accent">nuevas piezas</span></h2>
          <div class="lg:col-span-5">
            <p class="section-lead">{{ activeCategory === 'Todos' ? 'Estamos preparando el catálogo. Mientras tanto, cotiza la pieza que necesitas.' : 'Aún no hay piezas en esta categoría.' }}</p>
            <div class="mt-6 flex flex-col gap-3 sm:flex-row">
              <router-link to="/quote" class="btn-primary btn-lg">Cotizar una pieza</router-link>
              <button v-if="activeCategory !== 'Todos'" type="button" class="btn-secondary btn-lg" @click="activeCategory = 'Todos'; activeSubcategory = 'Todos'">Ver todo el catálogo</button>
              <router-link v-else to="/galeria" class="btn-secondary btn-lg">Ver trabajos</router-link>
            </div>
          </div>
        </div>
      </div>
    </main>

    <!-- Franquicias -->
    <section ref="tickerSection" class="bg-surface py-24 md:py-36 overflow-hidden" aria-labelledby="brands-heading">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-12 md:mb-16 grid gap-6 lg:grid-cols-12 lg:items-end">
        <h2 id="brands-heading" class="section-title lg:col-span-7">Universos <span class="section-title-accent">que imprimimos</span></h2>
        <p class="section-lead lg:col-span-5">Figuras y accesorios inspirados en las franquicias que más piden nuestros clientes.</p>
      </div>

      <div class="relative w-full overflow-hidden ticker-wrap-catalog">
        <div class="ticker-track-catalog flex" :style="{ animationPlayState: tickerVisible ? 'running' : 'paused' }">
          <div
            v-for="(brand, bi) in tickerBrands"
            :key="brand.key"
            class="flex items-center justify-center shrink-0 h-20 px-8 md:px-12"
            :aria-hidden="bi >= brands.length ? 'true' : undefined"
          >
            <img
              :src="brand.url"
              :alt="bi >= brands.length ? '' : brand.name"
              :class="[brand.className, 'w-auto', 'brand-logo']"
              loading="lazy"
              decoding="async"
              @error="(e: any) => e.target.style.display='none'"
            />
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

/* Nombre del producto: escala con el ancho de la tarjeta */
.product-card { container-type: inline-size; }
.product-card-title {
  font-size: clamp(1.5rem, 8cqi, 2.5rem);
  font-stretch: 112%;
}

.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
