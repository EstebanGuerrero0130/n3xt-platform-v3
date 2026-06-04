<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, watch, nextTick } from 'vue'
import { api } from '../services/api'
import AppNavbar from '../components/AppNavbar.vue'
import AppFooter from '../components/AppFooter.vue'
import logger from '../utils/logger'
import { useSplitTitle } from '../composables/useSplitTitle'
import { useSplitButton } from '../composables/useSplitButton'
import { useParticles } from '../composables/useParticles'

useSplitTitle()

const { particlesRef: heroParticlesRef } = useParticles({
  count: 30,
  zIndex: 1,
})
const { applySplitBtn } = useSplitButton()

const loading = ref(true)
let _timerLoad: any = null

// ─── SEO (OG / Meta tags) ───
const seoMeta = {
  title: 'Galería de Trabajos | N3XT 3D',
  description: 'Explora nuestra galería de piezas fabricadas con precisión industrial. Figuras, prototipos y coleccionables en 3D.',
  image: '/assets/n3xt_og_gallery.png'
}

let injectedMetaEls: any[] = []

const setMetaTags = () => {
  document.title = seoMeta.title
  injectedMetaEls.forEach(el => el.remove())
  injectedMetaEls = []

  const metas = [
    { name: 'og:title', prop: true, content: seoMeta.title },
    { name: 'og:description', prop: true, content: seoMeta.description },
    { name: 'og:image', prop: true, content: seoMeta.image },
    { name: 'og:type', prop: true, content: 'website' },
    { name: 'twitter:card', prop: false, content: 'summary_large_image' },
    { name: 'twitter:title', prop: false, content: seoMeta.title },
    { name: 'twitter:description', prop: false, content: seoMeta.description },
    { name: 'twitter:image', prop: false, content: seoMeta.image },
    { name: 'description', prop: false, content: seoMeta.description }
  ]

  metas.forEach(({ name, prop, content }) => {
    const el = document.createElement('meta')
    if (prop) el.setAttribute('property', name)
    el.setAttribute('name', name)
    el.setAttribute('content', content)
    document.head.appendChild(el)
    injectedMetaEls.push(el)
  })
}

// ─── Scroll Reveal + Parallax ───
const visibleCards = ref<any[]>([])
let revealObserver = null
const brokenImages = ref({})

const observeCards = () => {
  if (revealObserver) revealObserver.disconnect()

  revealObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      const idx = Number(entry.target.dataset.revealIndex)
      if (entry.isIntersecting) {
        if (!visibleCards.value.includes(idx)) {
          visibleCards.value.push(idx)
        }
        revealObserver.unobserve(entry.target)
      }
    })
  }, {
    threshold: 0.08,
    rootMargin: '0px 0px -40px 0px'
  })

  nextTick(() => {
    const cards = document.querySelectorAll('.masonry-grid > [data-reveal-index]')
    cards.forEach(card => revealObserver.observe(card))
    updateParallaxCache()
  })
}

// ─── Scroll-driven Parallax (cached DOM) ───
let parallaxRAF = null
let cachedParallaxEls: any[] = []

const updateParallaxCache = () => {
  cachedParallaxEls = [...document.querySelectorAll('.masonry-grid .parallax-img')]
}

const applyParallax = () => {
  const winH = window.innerHeight
  const center = winH / 2

  for (let i = 0; i < cachedParallaxEls.length; i++) {
    const rect = cachedParallaxEls[i].getBoundingClientRect()
    const imgCenter = rect.top + rect.height / 2
    const dist = (imgCenter - center) / (winH * 0.6)
    const offset = Math.max(-1, Math.min(1, dist)) * 8
    cachedParallaxEls[i].style.transform = `translateY(${offset}px)`
  }

  parallaxRAF = requestAnimationFrame(applyParallax)
}

const startParallax = () => {
  updateParallaxCache()
  if (parallaxRAF) cancelAnimationFrame(parallaxRAF)
  parallaxRAF = requestAnimationFrame(applyParallax)
}

const stopParallax = () => {
  if (parallaxRAF) {
    cancelAnimationFrame(parallaxRAF)
    parallaxRAF = null
  }
}

const normalizeTags = (tags) => Array.isArray(tags) ? tags : (typeof tags === 'string' ? tags.split(',').map(t => t.trim()).filter(Boolean) : [])
const webSettings = ref({ gallery: [] })
const activeFilter = ref('Todos')
const activeTech = ref('Todos')

const filters = computed(() => {
  const cats = new Set(webSettings.value.gallery.map(i => i.category))
  return ['Todos', ...Array.from(cats)]
})

const techs = computed(() => {
  const t = new Set(webSettings.value.gallery.map(i => i.technology))
  return ['Todos', ...Array.from(t)]
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

const getOptimizedImage = (url) => {
  if (!url) return ''
  if (!webSettings.value.cloudinary_name || !url.includes('cloudinary.com')) return url
  return url.replace('/upload/', '/upload/f_auto,q_auto,w_800/')
}

const fetchSettings = async () => {
  try {
    const data = await api.get('/settings')
    if (data?.web?.gallery) {
      webSettings.value.gallery = data.web.gallery
    }
  } catch (err) {
    logger.error('Error:', err)
  } finally {
    _timerLoad = setTimeout(() => { 
      loading.value = false
      nextTick(() => applySplitBtn())
    }, 400)
  }
}

onMounted(() => {
  setMetaTags()
  fetchSettings()
  observeCards()
  startParallax()
})

onUnmounted(() => {
  clearTimeout(_timerLoad)
  stopParallax()
  if (revealObserver) revealObserver.disconnect()
  injectedMetaEls.forEach(el => el.remove())
  injectedMetaEls = []
})
</script>

<template>
  <div class="min-h-screen bg-[#f8fafc] dark:bg-[#0a0f14] text-gray-900 dark:text-white transition-colors duration-500 overflow-x-hidden">
    <AppNavbar active-tab="gallery" subtext="Galería de Trabajos" />

    <main class="max-w-7xl mx-auto px-6 py-20">
      <!-- Header -->
      <div class="relative text-center mb-20 overflow-hidden">
        <div ref="heroParticlesRef" class="gallery-hero-particles" aria-hidden="true"></div>
        <div class="inline-flex items-center gap-3 px-4 py-2 bg-primary/10 rounded-full border border-primary/20 mb-6">
          <span class="w-2 h-2 bg-primary rounded-full animate-pulse"></span>
          <span class="text-[10px] font-black text-primary uppercase tracking-[0.4em]">Portafolio</span>
        </div>
        <h1 class="split-title text-5xl md:text-7xl font-black tracking-tighter uppercase leading-[0.9] mb-6">
          Nuestros <span class="text-primary">Trabajos</span>
        </h1>
        <p class="text-gray-500 dark:text-gray-400 text-xs md:text-sm font-bold uppercase tracking-[0.3em] max-w-xl mx-auto">
          Piezas fabricadas con precisión industrial. Cada proyecto cuenta una historia de detalle y calidad.
        </p>
      </div>

      <!-- Filters -->
      <div class="flex flex-wrap gap-3 mb-6">
        <button
v-for="cat in filters" :key="cat" :class="[activeFilter === cat ? 'bg-primary text-white shadow-lg shadow-primary/20' : 'bg-white dark:bg-white/5 text-gray-500 border border-gray-100 dark:border-white/10 hover:border-primary/30', 'px-6 py-3 rounded-2xl text-[8px] font-black uppercase tracking-widest transition-all']"
          @click="activeFilter = cat">
          {{ cat }}
        </button>
      </div>
      <div v-if="techs.length > 1" class="flex flex-wrap gap-2 mb-16">
        <button
v-for="t in techs" :key="t" :class="[activeTech === t ? 'bg-primary/10 text-primary border-primary/30' : 'bg-transparent text-gray-400 border-gray-200 dark:border-white/10 hover:border-primary/40', 'px-4 py-1.5 rounded-xl text-[7px] font-black uppercase tracking-[0.2em] border transition-all']"
          @click="activeTech = t">
          {{ t }}
        </button>
      </div>

      <!-- Skeleton -->
      <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-10">
        <div v-for="i in 6" :key="i" class="bg-white dark:bg-white/5 rounded-[3.5rem] overflow-hidden border border-gray-100 dark:border-white/5">
          <div class="aspect-square bg-gray-50 dark:bg-white/5"></div>
          <div class="p-8 space-y-4">
            <div class="h-6 bg-gray-50 dark:bg-white/5 rounded-xl w-3/4"></div>
            <div class="h-4 bg-gray-50 dark:bg-white/5 rounded-xl w-1/2"></div>
          </div>
        </div>
      </div>

      <!-- Masonry Grid -->
      <div v-else class="masonry-grid">
        <router-link
v-for="(item, idx) in filtered" :key="item.id || idx"
          :data-reveal-index="idx"
          :to="'/galeria/' + encodeURIComponent(item.title || idx)"
          :class="['group bg-white dark:bg-[#0a0f14]/80 backdrop-blur-xl rounded-[3rem] overflow-hidden border border-gray-100 dark:border-white/5 shadow-sm hover:shadow-2xl hover:border-emerald-500/30 transition-all duration-700', visibleCards.includes(idx) ? 'card-revealed' : 'card-hidden']"
          :style="{ '--reveal-delay': Math.min(idx * 80, 600) + 'ms',
                    '--reveal-x': (idx % 4 === 1 ? '-30px' : idx % 4 === 2 ? '30px' : '0px'),
                    '--reveal-y': (idx % 4 === 0 ? '40px' : idx % 4 === 3 ? '25px' : '0px') }">
          
          <!-- Image with Parallax -->
          <div class="overflow-hidden relative p-6 pb-0">
            <div class="w-full rounded-2xl overflow-hidden bg-gray-50 dark:bg-gray-800 relative parallax-img">
              <img
v-if="!brokenImages[idx]" :src="getOptimizedImage(item.image)" :alt="item.title"
                class="w-full h-auto object-cover group-hover:scale-110 transition-transform duration-1000"
                loading="lazy"
                @error="brokenImages[idx] = true" />
              <div v-if="!item.image || brokenImages[idx]" class="aspect-square flex items-center justify-center">
                <svg class="w-16 h-16 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
              </div>
              <!-- Category badge -->
              <div class="absolute top-3 left-3 px-3 py-1 bg-emerald-500/90 text-white text-[6px] font-black rounded-full uppercase tracking-[0.25em] shadow-lg">{{ item.category }}</div>
              <!-- Tech badge -->
              <div v-if="item.technology" class="absolute top-3 right-3 px-3 py-1 bg-black/50 backdrop-blur-md text-white/80 text-[6px] font-black rounded-full uppercase tracking-[0.2em] border border-white/10">{{ item.technology }}</div>
            </div>
          </div>

          <!-- Info -->
          <div class="px-8 pb-8 pt-5 text-center">
            <h3 class="text-xl font-black text-gray-900 dark:text-white uppercase tracking-tighter leading-tight group-hover:text-emerald-400 transition-colors">{{ item.title }}</h3>
            <p v-if="item.subtitle" class="text-[9px] font-black text-gray-400 uppercase tracking-[0.2em] mt-1">{{ item.subtitle }}</p>
            
            <!-- Tags -->
            <div v-if="normalizeTags(item.tags).length > 0" class="flex flex-wrap justify-center gap-1.5 mt-4">
              <span v-for="tag in normalizeTags(item.tags).slice(0, 3)" :key="tag" class="text-[6px] font-black px-2 py-0.5 bg-gray-100 dark:bg-white/5 rounded-full uppercase tracking-[0.15em] text-gray-500">{{ tag }}</span>
            </div>

            <!-- Stats row -->
            <div v-if="item.stats && item.stats.length" class="flex items-center justify-around mt-5 py-3 bg-gray-50 dark:bg-white/[0.03] rounded-2xl border border-gray-100 dark:border-white/5">
              <template v-for="(stat, si) in item.stats" :key="si">
                <div v-if="si > 0" class="w-px h-6 bg-gray-200 dark:bg-white/10"></div>
                <div class="flex flex-col items-center gap-0.5">
                  <p class="text-xs font-black text-gray-900 dark:text-white">{{ stat.val }}</p>
                  <span class="text-[6px] font-bold text-gray-400 uppercase tracking-[0.15em]">{{ stat.label }}</span>
                </div>
              </template>
            </div>

            <!-- CTA -->
            <div class="split-btn mt-5 w-full py-3.5 bg-gray-950 dark:bg-white/10 text-white rounded-full text-[8px] font-black uppercase tracking-[0.3em] group-hover:bg-emerald-500 transition-all duration-500 shadow-lg">
              Ver Proyecto
            </div>
          </div>
        </router-link>
      </div>

      <!-- Empty state -->
      <div v-if="!loading && filtered.length === 0" class="py-40 text-center">
        <p class="text-xl font-black text-gray-400 uppercase tracking-widest italic">No hay trabajos en esta categoría todavía.</p>
      </div>
    </main>

    <AppFooter />
  </div>
</template>

<style scoped>
/* ═══════════════════════════════════════════
   MASONRY LAYOUT — CSS columns
   ═══════════════════════════════════════════ */
.masonry-grid {
  column-count: 1;
  column-gap: 2rem;
}
@media (min-width: 768px) {
  .masonry-grid { column-count: 2; }
}
@media (min-width: 1024px) {
  .masonry-grid { column-count: 3; }
}

.masonry-grid > * {
  break-inside: avoid;
  margin-bottom: 2rem;
}

/* ═══════════════════════════════════════════
   SCROLL REVEAL — IntersectionObserver
   Cards fade in from different directions:
   idx%4=0 → slide up from below  (0, 40px)
   idx%4=1 → slide in from left   (-30px, 0)
   idx%4=2 → slide in from right  (30px, 0)
   idx%4=3 → slide up slightly    (0, 25px)
   ═══════════════════════════════════════════ */
.gallery-hero-particles {
  position: absolute;
  inset: 0;
  overflow: hidden;
  pointer-events: none;
  z-index: -1;
}

.card-hidden {
  opacity: 0;
  transition: opacity 1s cubic-bezier(0.22, 1, 0.36, 1),
              transform 1s cubic-bezier(0.22, 1, 0.36, 1);
}

.card-hidden[style*="--reveal-x"] {
  transform: translate(var(--reveal-x, 0px), var(--reveal-y, 40px)) scale(0.96);
}

.card-revealed {
  opacity: 1;
  transform: translate(0, 0) scale(1);
  transition: opacity 0.9s cubic-bezier(0.22, 1, 0.36, 1) var(--reveal-delay, 0ms),
              transform 0.9s cubic-bezier(0.22, 1, 0.36, 1) var(--reveal-delay, 0ms);
}

/* ═══════════════════════════════════════════
   PARALLAX — scroll-driven via JS
   ═══════════════════════════════════════════ */
.parallax-img {
  will-change: transform;
}

/* Hover zoom still works independently */
.masonry-grid > *:hover .parallax-img img {
  transform: scale(1.1) !important;
  transition: transform 0.8s cubic-bezier(0.22, 1, 0.36, 1);
}
</style>
