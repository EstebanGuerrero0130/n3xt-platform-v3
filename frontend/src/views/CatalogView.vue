<script setup lang="ts">

import { ref, onMounted, onUnmounted, computed, watch, nextTick } from 'vue'
import { sanitizeSVG } from '../utils/sanitize'
import { api } from '../services/api'
import { useRevealAnim } from '../composables/useRevealAnim'
import AppNavbar from '../components/AppNavbar.vue'
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

useRevealAnim()

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
const searchQuery = ref('')
const staggerKey = ref(0)

// Iconografía Técnica Industrial
const categoryIcons: Record<string, string> = {
 'Todos': `<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>`,
 'Mecanico': `<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>`,
 'Joyería': `<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>`,
 'Coleccionable': `<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>`,
 'Decoración': `<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>`,
}

const getIcon = (cat: string) => {
 return categoryIcons[cat] || categoryIcons['Todos']
}

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
  let items = webSettings.value.catalog.filter(i => i.status === 'active' || !i.status)
  if (activeCategory.value !== 'Todos') items = items.filter(i => i.category === activeCategory.value)
  if (activeSubcategory.value !== 'Todos') items = items.filter(i => i.subcategory === activeSubcategory.value)
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    items = items.filter(i => i.name?.toLowerCase().includes(q) || i.description?.toLowerCase().includes(q))
  }
  return items
})

const getDiscountPct = (item: CatalogItem) => {
 if (!item.price || !item.original_price) return 0
 const p = parseFloat(String(item.price).replace(/[^0-9.-]+/g,""))
 const op = parseFloat(String(item.original_price).replace(/[^0-9.-]+/g,""))
 if (!p || !op || p >= op) return 0
 return Math.round((1 - p / op) * 100)
}

const isDiscounted = (item: CatalogItem) => {
 if (!item.price || !item.original_price) return false
 const p = parseFloat(String(item.price).replace(/[^0-9.-]+/g,""))
 const op = parseFloat(String(item.original_price).replace(/[^0-9.-]+/g,""))
 return p < op && p > 0
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
 // Intentar caché primero
 const cached = localStorage.getItem(CACHE_KEY)
 if (cached) {
 try {
 const { data, timestamp } = JSON.parse(cached)
 if (Date.now() - timestamp < CACHE_TTL && data.web) {
 const newWeb = { ...data.web }
 if (!Array.isArray(newWeb.catalog)) newWeb.catalog = []
 webSettings.value = { ...webSettings.value, ...newWeb }
 loading.value = false
 return
 }
 } catch { /* ignorar cache corrupto */ }
 }

 loading.value = true
 try {
 const data = await api.get('/settings')
 if (data.web) {
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
 } finally {
 loading.value = false
 nextTick(() => applySplitBtn())
 }
}

const quickViewItem = ref<any>(null)



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
watch([activeCategory, activeSubcategory, searchQuery], () => {
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

onUnmounted(() => {
})
</script>

<template>
 <div class="min-h-screen bg-[#f8fafc] dark:bg-[#0a0f14] text-[#ffffff] dark:text-white transition-colors duration-500 overflow-x-hidden">
 <AppNavbar active-tab="catalog" subtext="Catálogo Maestro" />

 <!-- Partículas ambientales flotantes -->
 <main class="max-w-7xl mx-auto px-6 py-20 relative">
 <!-- Catalog Header -->
 <div class="relative flex flex-col md:flex-row justify-between items-end gap-10 mb-20">

 <div class="max-w-2xl text-left">
 <div class="inline-flex items-center gap-3 px-4 py-2 bg-[#08872b]/10 rounded-[60px] border border-primary/20 mb-6">
 <span class="w-2 h-2 bg-[#08872b] rounded-[60px] animate-pulse"></span>
 <span class="text-label text-[#8dd6ff]">Coleccion de piezas</span>
 </div>
 <h1 class="text-6xl md:text-8xl lg:text-9xl font-black text-slate-900 dark:text-white tracking-normal uppercase leading-[0.85] mb-6 animate-fade-in">
 NUESTRO <br class="md:hidden" />
 <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-500 via-primary to-emerald-300 dark:from-emerald-400 dark:via-primary dark:to-emerald-300 drop-shadow-[0_0_15px_rgba(16,185,129,0.3)] italic">CATÁLOGO</span>
 </h1>
 <p class="text-caption uppercase mt-6">Explora las piezas creadas en nuestros videos y proyectos industriales.</p>
 </div>

 <div class="flex flex-col items-start md:items-end gap-6">
 <p class="text-subtitle text-[#a4aea6] dark:text-[#c3c4c5] max-w-[200px] text-left md:text-right">
 {{ webSettings.pdf_catalog_desc }}
 </p>
 <a 
 :href="webSettings.pdf_catalog_url" 
 target="_blank"
 class="split-btn group relative px-10 py-6 bg-gray-100 dark:bg-white text-gray-900 dark:text-black border border-gray-200 dark:border-white/20 shadow-md hover:shadow-[0_0_20px_rgba(255,255,255,0.3)] rounded-[24px] font-black text-[10px] uppercase tracking-[0.3em] transition-all hover:scale-105 active:scale-95 flex items-center gap-4"
 >
 <span>Descargar Catálogo PDF</span>
 <svg class="w-5 h-5 transition-transform group-hover:translate-y-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" /></svg>
 </a>
 </div>
 </div>

 <!-- Búsqueda y Filtros -->
  <div class="flex flex-col md:flex-row gap-6 mb-8 items-center">
    <!-- Buscador -->
    <div class="relative w-full md:w-96 flex-shrink-0 animate-fade-in">
      <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
        <svg class="w-5 h-5 text-[#a4aea6]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
      </div>
      <input 
        v-model="searchQuery" 
        type="text" 
        placeholder="Buscar modelo, pieza o palabra clave..." 
        class="w-full bg-[#151a22] dark:bg-[#151a22]/5 border border-[#21262d] dark:border-[#21262d] text-white rounded-[24px] pl-12 pr-4 py-4 text-sm font-bold uppercase tracking-wide focus:outline-none focus:border-emerald-500/50 focus:ring-1 focus:ring-emerald-500/50 transition-all placeholder:text-[#a4aea6]/50"
      />
    </div>
  </div>

 <div class="flex flex-wrap gap-4 mb-16 bg-[#151a22] dark:bg-[#151a22]/5 reveal p-4 rounded-[2rem] border border-[#21262d] dark:border-[#21262d] backdrop-blur-sm">
 <button 
 v-for="cat in categories" 
 :key="cat"
 :class="[
 'group px-8 py-4 rounded-[24px] text-[10px] font-black uppercase tracking-widest transition-all flex items-center gap-3',
 activeCategory === cat 
 ? 'bg-[#08872b] text-white -primary/20 scale-105' 
 : 'bg-transparent text-[#a4aea6] hover:text-[#8dd6ff]'
 ]"
 @click="activeCategory = cat; activeSubcategory = 'Todos'"
 >
 <div :innerHTML="sanitizeSVG(getIcon(cat))" class="opacity-80 group-hover:scale-110 transition-transform"></div>
 {{ cat }}
 </button>
 </div>

 <!-- Subcategory Filters -->
 <div v-if="availableSubcategories.length > 0" class="flex flex-wrap gap-3 mb-16 animate-in fade-in slide-in-from-top-4 duration-700">
 <button 
 v-for="sub in availableSubcategories" 
 :key="sub"
 :class="[
 'px-6 py-2 rounded-[6px] text-[8px] font-black uppercase tracking-[0.2em] transition-all',
 activeSubcategory === sub 
 ? 'bg-[#08872b]/10 text-[#8dd6ff] border border-primary/30' 
 : 'bg-transparent text-[#c3c4c5] border border-[#21262d] dark:border-[#21262d] hover:border-primary/50'
 ]"
 @click="activeSubcategory = sub"
 >
 {{ sub }}
 </button>
 </div>

 <!-- Skeleton Loading State -->
 <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-10">
 <div v-for="i in 6" :key="i" class="bg-[#151a22] dark:bg-[#0a0f14]/40 rounded-[3.5rem] overflow-hidden border border-[#21262d] dark:border-[#21262d] p-12 flex flex-col relative overflow-hidden">
 <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/5 to-transparent skeleton-scan"></div>
 <div class="aspect-square bg-[#151a22] dark:bg-[#151a22]/5 rounded-[2.5rem] mb-10"></div>
 <div class="h-8 bg-[#151a22] dark:bg-[#151a22]/5 rounded-[6px] w-3/4 mx-auto mb-6"></div>
 <div class="h-20 bg-[#151a22] dark:bg-[#151a22]/5 rounded-[24px] w-full mt-auto"></div>
 </div>
 </div>

 <!-- Real Items Grid -->
 <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-10">
 <router-link 
 v-for="(item, index) in filteredItems" 
 :key="'cat-' + staggerKey + '-' + index"
 :to="'/catalog/' + encodeURIComponent(item.name)"
 :style="{ '--stagger-delay': index * 80 + 'ms' }"
 class="group bg-[#151a22] dark:bg-[#151a22] rounded-[3.5rem] overflow-hidden border border-[#21262d] dark:border-[#21262d] hover: hover:border-emerald-500/30 transition-all duration-500 hover:-translate-y-2 flex flex-col relative stagger-item"
 >
 <!-- Image Container con aspect-ratio fijo (fondo claro estilo Galería) -->
  <div class="relative overflow-hidden aspect-square bg-white dark:bg-[#f0f0f0] shrink-0 flex items-center justify-center p-4">
  <!-- Imagen Secundaria (crossfade al hover) -->
  <img 
  v-if="item.images && item.images.length > 0" 
  :src="getOptimizedImage(item.images[1] || item.images[0])" 
  class="absolute inset-0 w-full h-full object-contain p-4 transition-opacity duration-700 opacity-0 group-hover:opacity-100" 
  :alt="item.name + ' - Vista alternativa'" 
  loading="lazy" 
  decoding="async" 
  @error="(e: any) => e.target.style.display='none'" />
  
  <!-- Imagen Principal -->
  <img 
  :src="getOptimizedImage(item.image)" 
  :alt="'Miniatura de ' + item.name + ' | N3XT 3D Shop'" 
  class="absolute inset-0 w-full h-full object-contain p-4 transition-all duration-700 group-hover:opacity-0 group-hover:scale-105" 
  loading="lazy" 
  decoding="async" 
  @error="(e: any) => e.target.style.display='none'" />

 <!-- Badges sobre la imagen (Top Left) -->
 <div class="absolute top-6 left-6 z-20 flex gap-3 flex-wrap">
 <span v-if="item.category" class="px-4 py-1.5 bg-emerald-500/90 backdrop-blur-sm text-white text-[9px] font-black rounded-[60px] uppercase tracking-[0.25em] ">
 {{ item.category }}
 </span>
 </div>

 <!-- Badges de Estado (Top Right) -->
 <div v-if="isDiscounted(item)" class="absolute top-5 right-5 z-20">
 <div class="relative w-20 h-20 group-hover:scale-110 transition-transform duration-500">
 <svg class="w-full h-full drop--[0_0_20px_rgba(239,68,68,0.6)]" viewBox="0 0 100 100">
 <defs>
 <linearGradient id="fireGradCat" x1="0%" y1="0%" x2="100%" y2="100%">
 <stop offset="0%" stop-color="#ef4444"/>
 <stop offset="50%" stop-color="#f97316"/>
 <stop offset="100%" stop-color="#dc2626"/>
 </linearGradient>
 </defs>
 <polygon points="50,2 96,20 96,60 50,98 4,60 4,20" fill="url(#fireGradCat)" opacity="0.95"/>
 </svg>
 <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
 <span class="text-[6px] font-black text-white uppercase tracking-[0.1em] leading-tight drop-">OFERTA</span>
 <span class="text-[9px] font-black text-yellow-200 leading-none drop- mt-0.5">{{ getDiscountPct(item) }}%</span>
 </div>
 </div>
 </div>
 <div v-else class="absolute top-6 right-6 z-20 px-4 py-1.5 bg-black/60 backdrop-blur-md text-emerald-400 text-[8px] font-black rounded-[60px] uppercase tracking-[0.2em] border border-white/10 flex items-center gap-2 ">
 <span class="w-1.5 h-1.5 bg-emerald-400 rounded-[60px] animate-pulse"></span>
 Disponible
 </div>
 </div>

 <!-- Info (Estilo Galería) -->
 <div class="p-8 flex flex-col flex-1 relative z-10">
 <div class="flex-1">
 <!-- Trust Badges -->
 <div class="flex items-center gap-3 mb-6 flex-wrap">
 <div class="inline-flex items-center gap-1.5 px-3 py-1 bg-blue-500/10 rounded-[60px] border border-blue-500/20">
 <svg class="w-3 h-3 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0zM5 11V5a1 1 0 011-1h6l4 4v7"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8h5.5a1 1 0 01.8.4l2.5 3.5a1 1 0 01.2.6V15h-2"/></svg>
 <span class="text-micro text-blue-400">Envío Gratis</span>
 </div>
 <div class="inline-flex items-center gap-1.5 px-3 py-1 bg-orange-500/10 rounded-[60px] border border-orange-500/20">
 <svg class="w-3 h-3 text-orange-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
 <span class="text-micro text-orange-400">Garantía 6M</span>
 </div>
 </div>

 <!-- Titulo -->
 <h2 class="text-4xl font-black text-[#ffffff] dark:text-white uppercase tracking-normal mb-4 italic leading-none group-hover:text-emerald-400 transition-colors line-clamp-2">{{ item.name }}</h2>
 
 <!-- Divider -->
 <div class="w-12 h-1 bg-gradient-to-r from-emerald-500 to-emerald-400 rounded-[60px] mb-6"></div>
 </div>
 
 <div class="mt-auto">
 <div class="flex flex-col gap-1 mb-8">
 <p v-if="isDiscounted(item)" class="text-[9px] font-black text-rose-500 dark:text-rose-400 uppercase tracking-[0.4em] mb-1 italic flex items-center gap-2">
 Precio Oferta
 </p>
 <p v-else class="text-[9px] font-black text-[#c3c4c5] dark:text-[#a4aea6] uppercase tracking-[0.4em] mb-1 italic">Precio de Venta</p>
 
 <div class="flex items-center gap-4">
 <p :class="['text-5xl font-black tracking-tighter leading-none italic drop--[0_0_10px_rgba(16,185,129,0.2)]', isDiscounted(item) ? 'text-rose-600 dark:text-rose-500' : 'text-emerald-500 dark:text-emerald-400']">
 ${{ Math.round(parseFloat(String(item.price).replace(/[^0-9]+/g,"")) || 0).toLocaleString(undefined, {maximumFractionDigits: 0}) }}
 </p>
 <p v-if="isDiscounted(item)" class="text-lg font-black text-[#c3c4c5] line-through opacity-50 leading-none italic tracking-tight">
 ${{ Math.round(parseFloat(String(item.original_price).replace(/[^0-9]+/g,"")) || 0).toLocaleString(undefined, {maximumFractionDigits: 0}) }}
 </p>
 </div>
 
 <div v-if="isDiscounted(item) && item.original_price" class="mt-3 self-start px-3 py-1 bg-rose-500/10 rounded-[60px] border border-rose-500/20 inline-block">
 <span class="text-[8px] font-black text-rose-500 dark:text-rose-400 uppercase tracking-widest">
 Ahorras ${{ (Math.round(parseFloat(String(item.original_price).replace(/[^0-9]+/g,"")) || 0) - Math.round(parseFloat(String(item.price).replace(/[^0-9]+/g,"")) || 0)).toLocaleString(undefined, {maximumFractionDigits: 0}) }}
 </span>
 </div>
 </div>
 
 <!-- CTA (Estilo Galeria) -->
 <div :class="['w-full py-4 rounded-[24px] text-[10px] font-black uppercase tracking-[0.4em] transition-all duration-500 flex items-center justify-center gap-4 cursor-pointer', isDiscounted(item) ? 'bg-rose-600 text-white border border-rose-500 hover:bg-rose-500 hover:-rose-500/40' : 'bg-gray-100 dark:bg-white text-gray-900 dark:text-black border border-gray-200 dark:border-white/20 shadow-md hover:shadow-lg hover:bg-emerald-500 hover:text-white hover:border-emerald-500 hover:-emerald-500/30']" @click.prevent.stop="quickViewItem = item">
 <span>{{ isDiscounted(item) ? 'Aprovechar Oferta' : 'Analizar Pieza →' }}</span>
 </div>
 </div>
 </div>
 </router-link>
 </div>

 <!-- No items fallback -->
 <div v-if="filteredItems.length === 0" class="py-40 text-center">
 <p class="text-xl font-black text-[#c3c4c5] uppercase tracking-widest italic">No hay piezas en esta categoría todavía.</p>
 </div>
 </main>

 <!-- Footer Simple -->
 <footer class="py-20 border-t border-[#21262d] dark:border-[#21262d] text-center">
 <p class="text-[10px] font-black text-[#c3c4c5] uppercase tracking-[0.5em]">N3XT 3D — Catalogo de piezas 2026</p>
 </footer>
 </div>

 <!-- Quick View Modal -->
 <teleport to="body">
 <transition name="quickview">
 <div v-if="quickViewItem" class="fixed inset-0 z-[9999] flex items-center justify-center p-4 md:p-8">
 <!-- Backdrop -->
 <div class="absolute inset-0 bg-black/70 backdrop-blur-md" @click="quickViewItem = null"></div>
 
 <!-- Modal Content -->
 <div class="relative w-full max-w-4xl max-h-[90vh] bg-[#151a22] border border-white/10 rounded-[3rem] overflow-y-auto -black/50 animate-in fade-in zoom-in-95 duration-500">
 <!-- Close Button -->
 <button aria-label="Cerrar vista rápida" class="absolute top-6 right-6 z-20 w-12 h-12 bg-[#151a22]/5 hover:bg-[#151a22]/10 rounded-[24px] flex items-center justify-center transition-all group border border-white/10" @click="quickViewItem = null">
 <svg class="w-5 h-5 text-[#c3c4c5] group-hover:text-white transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
 </button>

 <div class="grid grid-cols-1 md:grid-cols-2 gap-0">
 <!-- Left: Image -->
 <div class="aspect-square md:aspect-auto md:h-full bg-[#151a22]/50 p-10 md:p-14 flex items-center justify-center relative min-h-[300px]">
 <div class="absolute inset-0 bg-radial-gradient from-emerald-500/5 to-transparent"></div>
 <img :src="getOptimizedImage(quickViewItem.image)" :alt="'Vista rápida: ' + quickViewItem.name + ' - Catálogo N3XT 3D'" class="max-w-full max-h-full object-contain relative z-10 hover:scale-105 transition-transform duration-700" loading="lazy" decoding="async" @error="(e: any) => e.target.style.display='none'" />
 </div>

 <!-- Right: Info -->
 <div class="p-8 md:p-12 flex flex-col justify-between">
 <div class="space-y-6">
 <!-- Category Badge -->
 <div class="inline-flex items-center gap-2 px-4 py-1.5 bg-emerald-500/20 rounded-[60px] border border-emerald-500/30">
 <span class="w-1.5 h-1.5 bg-emerald-400 rounded-[60px]"></span>
 <span class="text-[8px] font-black text-emerald-400 uppercase tracking-[0.3em]">{{ quickViewItem.category }}</span>
 </div>

 <!-- Name -->
 <h2 class="text-4xl md:text-5xl font-black text-white uppercase tracking-normal leading-tight italic">{{ quickViewItem.name }}</h2>

 <!-- Description -->
 <p v-if="quickViewItem.description" class="text-sm text-[#c3c4c5] leading-relaxed font-medium">{{ quickViewItem.description }}</p>
 <p v-else class="text-sm text-[#a4aea6] italic">Sin descripción disponible.</p>

 <!-- Price Section -->
 <div class="flex items-end gap-4">
 <div>
 <p class="text-[9px] font-black text-[#a4aea6] uppercase tracking-[0.3em] mb-1">Precio</p>
 <p :class="['text-4xl font-black tracking-tighter leading-none italic', isDiscounted(quickViewItem) ? 'text-rose-400 drop--[0_0_15px_rgba(239,68,68,0.3)]' : 'text-emerald-400 drop--[0_0_15px_rgba(16,185,129,0.2)]']">
 ${{ Math.round(parseFloat(String(quickViewItem.price).replace(/[^0-9]+/g,"")) || 0).toLocaleString(undefined, {maximumFractionDigits: 0}) }}
 </p>
 </div>
 <p v-if="isDiscounted(quickViewItem)" class="text-lg font-black text-[#a4aea6] line-through opacity-50 leading-none mb-0.5">
 ${{ Math.round(parseFloat(String(quickViewItem.original_price).replace(/[^0-9]+/g,"")) || 0).toLocaleString(undefined, {maximumFractionDigits: 0}) }}
 </p>
 </div>

 <!-- Trust Badges -->
 <div class="flex flex-wrap gap-3 pt-2">
 <div class="inline-flex items-center gap-1.5 px-3 py-1 bg-blue-500/10 rounded-[60px] border border-blue-500/20">
 <svg class="w-3 h-3 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0zM5 11V5a1 1 0 011-1h6l4 4v7"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8h5.5a1 1 0 01.8.4l2.5 3.5a1 1 0 01.2.6V15h-2"/></svg>
 <span class="text-micro text-blue-400">Envío Gratis</span>
 </div>
 <div class="inline-flex items-center gap-1.5 px-3 py-1 bg-orange-500/10 rounded-[60px] border border-orange-500/20">
 <svg class="w-3 h-3 text-orange-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
 <span class="text-micro text-orange-400">Garantía 6M</span>
 </div>
 </div>
 </div>

 <!-- Actions -->
 <div class="mt-8 space-y-4">
 <router-link
:to="'/catalog/' + encodeURIComponent(quickViewItem.name)" class="split-btn w-full block text-center py-5 bg-emerald-500 text-white rounded-[24px] font-black text-[10px] uppercase tracking-[0.3em] hover:bg-emerald-400 hover:scale-[1.02] active:scale-95 transition-all -emerald-500/20" 
 @click="quickViewItem = null">
 Ver Detalle Completo
 </router-link>
 <button
class="w-full py-4 bg-[#151a22]/5 text-[#c3c4c5] rounded-[24px] font-black text-[9px] uppercase tracking-[0.3em] hover:bg-[#151a22]/10 hover:text-white transition-all border border-white/5" 
 @click="quickViewItem = null">
 Seguir Explorando
 </button>
 </div>
 </div>
 </div>
 </div>
 </div>
 </transition>
 </teleport>
 <!-- ─── MARQUES DE FABRICACIÓN ─── -->
 <section ref="tickerSection" class="w-full py-20 md:py-24 overflow-hidden relative bg-[#151a22] dark:bg-[#05080b] border-t border-[#21262d] dark:border-[#21262d]">
 <div class="absolute inset-0 technical-grid opacity-5 pointer-events-none"></div>
 
 <div class="max-w-7xl mx-auto px-6 mb-12 md:mb-16 text-center relative">
 <div class="inline-flex items-center gap-3 px-4 py-2 bg-emerald-500/10 rounded-[60px] border border-emerald-500/20 mb-6">
 <span class="w-2 h-2 bg-emerald-400 rounded-[60px] animate-pulse"></span>
 <span class="text-label text-emerald-400">FRANQUICIAS</span>
 </div>
 <h2 class="text-5xl md:text-7xl font-black text-[#ffffff] dark:text-white uppercase tracking-normal leading-[0.9] fade-title">
 <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-600 to-emerald-400">Universos icónicos</span><br/>
 que cobran vida en nuestras manos
 </h2>
 </div>

 <div class="relative">
 <div class="absolute inset-y-0 left-0 w-16 md:w-32 z-10 pointer-events-none bg-gradient-to-r from-gray-50 dark:from-[#05080b] to-transparent"></div>
 <div class="absolute inset-y-0 right-0 w-16 md:w-32 z-10 pointer-events-none bg-gradient-to-l from-gray-50 dark:from-[#05080b] to-transparent"></div>

 <div class="relative w-full overflow-hidden ticker-wrap-catalog">
 <div class="ticker-track-catalog flex gap-0" :style="{ animationPlayState: tickerVisible ? 'running' : 'paused' }">
 <div 
 v-for="brand in tickerBrands" 
 :key="brand.key" 
 class="ticker-item-catalog flex items-center justify-center shrink-0 px-5 md:px-8" 
 style="height: 64px;"
 >
 <div class="relative h-full inline-flex items-center gap-4" style="width: auto;">
 <img 
 :src="brand.url" 
 :alt="brand.name" 
 :class="[brand.className, 'w-auto', 'brand-logo']"
 loading="lazy"
 decoding="async"
 @error="(e: any) => e.target.style.display='none'" 
 />
 <span class="w-px h-10 bg-emerald-500/20"></span>
 </div>
 </div>
 </div>
 </div>
 </div>
 </section>

</template>

<style scoped>
.technical-grid {
 background-size: 40px 40px;
 background-image: 
 linear-gradient(to right, rgba(30, 58, 52, 0.05) 1px, transparent 1px),
 linear-gradient(to bottom, rgba(30, 58, 52, 0.05) 1px, transparent 1px);
}

/* Stagger animation for catalog cards */
.stagger-item {
 opacity: 0;
 transform: translateY(30px);
 animation: cardStaggerIn 0.6s cubic-bezier(0.22, 1, 0.36, 1) forwards;
 animation-delay: var(--stagger-delay, 0ms);
}

/* ─── CATALOG BRAND TICKER ─── */
@keyframes catalogScroll {
 0% {
 transform: translateX(0);
 }
 100% {
 transform: translateX(-50%);
 }
}

.ticker-track-catalog {
 display: flex;
 width: max-content;
 animation: catalogScroll 50s linear infinite;
 will-change: transform;
}

.brand-logo {
 filter: grayscale(1) contrast(0.8);
 transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
 opacity: 0.7;
}

:is(.dark) .brand-logo {
 filter: grayscale(1) brightness(1.4) contrast(0.9);
 opacity: 0.85;
}

.ticker-item-catalog {
 transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
 transform-origin: center;
}

@media (hover: hover) {
 .ticker-wrap-catalog:hover .ticker-track-catalog {
 animation-play-state: paused;
 }

 .ticker-wrap-catalog:hover .ticker-item-catalog .brand-logo {
 filter: grayscale(0.3) contrast(0.9);
 opacity: 0.85;
 }

 :is(.dark) .ticker-wrap-catalog:hover .brand-logo {
 filter: grayscale(0.3) brightness(1.2) contrast(0.9);
 opacity: 1;
 }

 .ticker-item-catalog:hover .brand-logo {
 transform: scale(1.15);
 opacity: 1;
 filter: grayscale(0) contrast(1) drop-(0 0 20px rgba(16, 185, 129, 0.4));
 }

 :is(.dark) .ticker-item-catalog:hover .brand-logo {
 filter: grayscale(0) contrast(1) brightness(1) drop-(0 0 25px rgba(16, 185, 129, 0.5));
 }
}

@keyframes cardStaggerIn {
 0% {
 opacity: 0;
 transform: translateY(30px) scale(0.97);
 }
 100% {
 opacity: 1;
 transform: translateY(0) scale(1);
 }
}

/* Quick View Modal transitions */
.quickview-enter-active {
 transition: all 0.3s cubic-bezier(0.22, 1, 0.36, 1);
}
.quickview-leave-active {
 transition: all 0.2s cubic-bezier(0.55, 0, 1, 0.45);
}
.quickview-enter-from,
.quickview-leave-to {
 opacity: 0;
}
.quickview-enter-from > div > div,
.quickview-leave-to > div > div {
 transform: scale(0.95) translateY(20px);
 opacity: 0;
}


/* ═══════════════════════════════════════════
 SCROLL REVEAL
 ═══════════════════════════════════════════ */
.reveal {
 opacity: 0;
 transform: translateY(30px);
 transition: opacity 0.8s cubic-bezier(0.22, 1, 0.36, 1),
 transform 0.8s cubic-bezier(0.22, 1, 0.36, 1);
}
.reveal.revealed {
 opacity: 1;
 transform: translateY(0);
}


/* Animación para títulos con gradiente (sin SplitText) */
.fade-title {
 opacity: 0;
 animation: titleFadeIn 0.8s cubic-bezier(0.22, 1, 0.36, 1) forwards;
}

@keyframes titleFadeIn {
 0% {
 opacity: 0;
 transform: translateY(30px);
 }
 100% {
 opacity: 1;
 transform: translateY(0);
 }
}
</style>
