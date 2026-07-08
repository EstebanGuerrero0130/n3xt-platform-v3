<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, watch, nextTick } from 'vue'
import { api } from '../services/api'
import AppNavbar from '../components/AppNavbar.vue'
import AppFooter from '../components/AppFooter.vue'
import logger from '../utils/logger'
import { useSplitTitle } from '../composables/useSplitTitle'
import { useSplitButton } from '../composables/useSplitButton'
import { usePageMeta } from '../composables/usePageMeta'

useSplitTitle()

usePageMeta({
 title: 'Galería de Trabajos | N3XT 3D',
 description: 'Explora nuestra galería de piezas fabricadas con precisión industrial. Figuras, prototipos y coleccionables en 3D.',
 image: '/assets/n3xt_og_gallery.png',
})

const { applySplitBtn } = useSplitButton()

const loading = ref(true)
let _timerLoad: ReturnType<typeof setTimeout> | null = null

// ─── TypeScript interfaces ───
interface GalleryItem {
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

// eslint-disable-next-line @typescript-eslint/no-unused-vars
const getOptimizedImage = (url: string): string => {
 if (!url) return ''
 if (!webSettings.value.cloudinary_name || !url.includes('cloudinary.com')) return url
 return url.replace('/upload/', '/upload/f_auto,q_auto,w_800/')
}

const fetchSettings = async () => {
 try {
 const data = await api.get('/settings')
 if (data?.web?.gallery) {
 webSettings.value.gallery = data.web.gallery
 .map((item: any, idx: number) =>
 typeof item === 'string'
 ? { image: item, title: `Imagen ${idx + 1}`, category: 'General', technology: '', images: [], featured: false, tags: '' }
 : { images: [], tags: '', featured: false, ...item }
 )
 .filter((item: any) => item.image || item.title)
 }
 if (data?.web?.cloudinary_name) {
 webSettings.value.cloudinary_name = data.web.cloudinary_name
 }
 } catch (err) {
 logger.error('Error:', err)
 } finally {
 _timerLoad = setTimeout(() => {
 loading.value = false
 nextTick(() => {
 applySplitBtn()
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
 <div class="min-h-screen bg-[#f8fafc] dark:bg-[#0a0f14] text-[#ffffff] dark:text-white transition-colors duration-500 overflow-x-hidden">
 <AppNavbar active-tab="gallery" subtext="Galería de Trabajos" />

 <main class="max-w-7xl mx-auto px-4 md:px-6 py-20">
 <!-- Header -->
 <div class="relative text-center mb-16 overflow-hidden">
  <div class="inline-flex items-center gap-3 px-4 py-2 bg-[#08872b]/10 rounded-[60px] border border-primary/20 mb-6">
 <span class="w-2 h-2 bg-[#08872b] rounded-[60px] animate-pulse"></span>
 <span class="text-label text-[#8dd6ff]">Portafolio</span>
 </div>
 <h1 class="text-6xl md:text-8xl lg:text-9xl font-black text-slate-900 dark:text-white tracking-normal uppercase leading-[0.85] mb-6 animate-fade-in">
 NUESTROS <br class="md:hidden" />
 <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-500 via-primary to-emerald-300 dark:from-emerald-400 dark:via-primary dark:to-emerald-300 drop-shadow-[0_0_15px_rgba(16,185,129,0.3)] italic">TRABAJOS</span>
 </h1>
 <p class="text-caption uppercase max-w-xl mx-auto">
 Piezas fabricadas con precisión industrial. Cada proyecto cuenta una historia de detalle y calidad.
 </p>
 </div>

 <!-- Filters: Categorías -->
 <div class="flex flex-wrap gap-3 mb-4">
 <button
 v-for="cat in filters" :key="cat"
 :class="[activeFilter === cat
 ? 'bg-[#08872b] text-white -primary/20'
 : 'bg-[#151a22] dark:bg-[#151a22]/5 text-[#a4aea6] border border-[#21262d] dark:border-[#21262d] hover:border-primary/30 hover:text-[#8dd6ff]',
 'px-8 py-4 rounded-[30px] text-sm font-black uppercase tracking-widest transition-all']"
 @click="activeFilter = cat">
 {{ cat }}
 </button>
 </div>

 <!-- Filters: Tecnología -->
 <div v-if="techs.filter(t => t !== 'Todos' && t !== '' && t !== undefined).length > 0" class="flex flex-wrap gap-2 mb-12">
 <button
 v-for="t in techs" :key="t"
 :class="[activeTech === t
 ? 'bg-[#08872b]/10 text-[#8dd6ff] border-primary/30'
 : 'bg-transparent text-[#c3c4c5] border-[#21262d] dark:border-[#21262d] hover:border-primary/40 hover:text-[#8dd6ff]',
 'px-6 py-2.5 rounded-[12px] text-[10px] font-black uppercase tracking-[0.2em] border transition-all']"
 @click="activeTech = t">
 {{ t }}
 </button>
 </div>
 <div v-else class="mb-12"></div>

 <!-- Skeleton Loading -->
 <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-10">
 <div v-for="i in 6" :key="i" class="bg-[#151a22] dark:bg-[#0a0f14]/40 rounded-[3.5rem] overflow-hidden border border-[#21262d] dark:border-[#21262d] p-8 flex flex-col relative">
 <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/5 to-transparent skeleton-scan"></div>
 <div class="aspect-square bg-[#151a22] dark:bg-[#151a22]/5 rounded-[2.5rem] mb-8"></div>
 <div class="space-y-4">
 <div class="h-6 bg-[#151a22] dark:bg-[#151a22]/5 rounded-[6px] w-3/4"></div>
 <div class="h-4 bg-[#151a22] dark:bg-[#151a22]/5 rounded-[6px] w-1/2"></div>
 </div>
 </div>
 </div>

 <!-- Gallery Grid -->
 <div v-else-if="filtered.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-10">
 <router-link
 v-for="(item, idx) in filtered" :key="idx"
 :data-reveal-index="idx"
 :to="'/galeria/' + encodeURIComponent(item.title || String(idx))"
 :class="[
 'group bg-[#151a22] dark:bg-[#151a22] rounded-[3.5rem] overflow-hidden border border-[#21262d] dark:border-[#21262d] hover: hover:border-emerald-500/30 transition-all duration-500 hover:-translate-y-2 flex flex-col',
 visibleCards.includes(idx) ? 'card-revealed' : 'card-hidden'
 ]"
 :style="{ '--reveal-delay': Math.min(idx * 80, 500) + 'ms' }"
 >
 <!-- Image Container con aspect-ratio fijo -->
 <div class="relative overflow-hidden aspect-square bg-[#151a22] dark:bg-[#283041] shrink-0">
 <!-- Imagen Secundaria (crossfade al hover) -->
 <img
 v-if="item.images && item.images.length > 0"
 :src="item.images[0]"
 class="absolute inset-0 w-full h-full object-cover opacity-0 group-hover:opacity-100 transition-opacity duration-700 z-10"
 loading="lazy"
 :alt="item.title"
 />
 <!-- Imagen Principal -->
 <img
 v-if="item.image && !brokenImages[idx]"
 :src="item.image"
 :alt="'Trabajo de impresión 3D: ' + item.title"
 class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 z-0"
 loading="lazy"
 decoding="async"
 @error="brokenImages[idx] = true"
 />
 <!-- Fallback sin imagen -->
 <div v-else-if="!item.image || brokenImages[idx]" class="absolute inset-0 flex items-center justify-center bg-[#151a22] dark:bg-[#283041]">
 <svg class="w-16 h-16 text-gray-300 dark:text-[#a4aea6]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
 </svg>
 </div>

 <!-- Badges sobre la imagen -->
 <div class="absolute top-6 left-6 z-20 flex gap-3 flex-wrap">
 <span v-if="item.category" class="px-4 py-1.5 bg-emerald-500/90 backdrop-blur-sm text-white text-[9px] font-black rounded-[60px] uppercase tracking-[0.25em] ">{{ item.category }}</span>
 <span v-if="item.featured" class="px-4 py-1.5 bg-amber-400/90 backdrop-blur-sm text-black text-[9px] font-black rounded-[60px] uppercase tracking-[0.25em] ">⭐ Destacado</span>
 </div>
 <div v-if="item.technology" class="absolute top-6 right-6 z-20 px-4 py-1.5 bg-black/50 backdrop-blur-md text-white/80 text-[9px] font-black rounded-[60px] uppercase tracking-[0.2em] border border-white/10">{{ item.technology }}</div>

 <!-- Overlay de hover con contador de imágenes -->
 <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500 z-20 flex items-end p-8">
 <span v-if="item.images && item.images.length > 0" class="text-[10px] font-black text-white/80 uppercase tracking-widest bg-black/40 backdrop-blur-sm rounded-[60px] px-4 py-2">
 +{{ item.images.length }} fotos
 </span>
 </div>
 </div>

 <!-- Info -->
 <div class="p-8 flex flex-col flex-1">
 <div class="flex-1">
 <h2 class="text-3xl font-black text-[#ffffff] dark:text-white uppercase tracking-normal leading-tight group-hover:text-emerald-500 transition-colors line-clamp-2">
 {{ item.title || 'Sin Título' }}
 </h2>
 <p v-if="item.description" class="text-sm text-[#a4aea6] dark:text-[#c3c4c5] mt-3 line-clamp-2 leading-relaxed">{{ item.description }}</p>

 <!-- Tags -->
 <div v-if="normalizeTags(item.tags).length > 0" class="flex flex-wrap gap-2 mt-4">
 <span
 v-for="tag in normalizeTags(item.tags).slice(0, 3)" :key="tag"
 class="text-xs font-bold px-3 py-1 bg-[#151a22] dark:bg-[#151a22]/5 rounded-[60px] uppercase tracking-[0.1em] text-[#a4aea6]">
 {{ tag }}
 </span>
 </div>
 </div>

 <!-- CTA -->
 <div class="mt-8 w-full py-4 bg-[#151a22] text-white border border-[#21262d] rounded-[60px] shadow-md hover:shadow-lg text-[10px] md:text-xs font-black uppercase tracking-[0.4em] text-center hover:bg-emerald-500 transition-all duration-500">
 VER PROYECTO
 </div>
 </div>
 </router-link>
 </div>

 <!-- Empty state -->
 <div v-else class="py-40 text-center">
 <div class="w-20 h-20 bg-[#151a22] dark:bg-[#151a22]/5 rounded-[2rem] flex items-center justify-center mx-auto mb-6">
 <svg class="w-10 h-10 text-[#c3c4c5]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
 </div>
 <p class="text-xl font-black text-[#c3c4c5] uppercase tracking-widest italic">No hay trabajos en esta categoría todavía.</p>
 <button class="mt-6 px-8 py-3 bg-[#08872b]/10 text-[#8dd6ff] rounded-[24px] text-[10px] font-black uppercase tracking-widest hover:bg-[#08872b]/20 transition-all" @click="activeFilter = 'Todos'; activeTech = 'Todos'">Ver Todos</button>
 </div>
 </main>

 <AppFooter />
 </div>
</template>

<style scoped>
/* ═══════════════════════════════════════════
 PARTICLES HERO
 ═══════════════════════════════════════════ */

/* ═══════════════════════════════════════════
 SCROLL REVEAL
 ═══════════════════════════════════════════ */
.card-hidden {
 opacity: 0;
 transform: translateY(32px) scale(0.97);
 transition: opacity 0.7s cubic-bezier(0.22, 1, 0.36, 1),
 transform 0.7s cubic-bezier(0.22, 1, 0.36, 1);
}

.card-revealed {
 opacity: 1;
 transform: translateY(0) scale(1);
 transition: opacity 0.7s cubic-bezier(0.22, 1, 0.36, 1) var(--reveal-delay, 0ms),
 transform 0.7s cubic-bezier(0.22, 1, 0.36, 1) var(--reveal-delay, 0ms);
}

/* line-clamp utility */
.line-clamp-2 {
 display: -webkit-box;
 -webkit-line-clamp: 2;
 line-clamp: 2;
 -webkit-box-orient: vertical;
 overflow: hidden;
}
</style>



