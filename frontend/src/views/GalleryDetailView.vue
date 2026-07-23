<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../services/api'
import AppNavbar from '../components/AppNavbar.vue'
import AppFooter from '../components/AppFooter.vue'
import logger from '../utils/logger'
import { useSplitTitle } from '../composables/useSplitTitle'
import { useSplitButton } from '../composables/useSplitButton'
import { usePageMeta } from '../composables/usePageMeta'

const { applySplitTitle } = useSplitTitle()
const { applySplitBtn } = useSplitButton()

usePageMeta({
 title: 'Detalle del Proyecto | N3XT 3D',
 description: 'Explora en detalle nuestros proyectos de fabricación digital. Piezas únicas creadas con precisión industrial.',
 image: '/assets/n3xt_og_gallery_detail.png',
})

const route = useRoute()
const router = useRouter()
const loading = ref(true)
const lightboxRef = ref<any>(null)
const relatedGridRef = ref<any>(null)

// ─── Scroll reveal for related projects ───
const relatedVisible = ref(false)
let relatedObserver: IntersectionObserver | null = null
let _timerFocus: ReturnType<typeof setTimeout> | null = null
let _timerLoad: ReturnType<typeof setTimeout> | null = null

const normalizeTags = (tags: any): string[] => Array.isArray(tags) ? tags : (typeof tags === 'string' ? tags.split(',').map((t: string) => t.trim()).filter(Boolean) : [])
const normalizeImages = (images: any): string[] => Array.isArray(images) ? images : (typeof images === 'string' ? images.split(',').map((s: string) => s.trim()).filter(Boolean) : [])
const item = ref<any>(null)
const selectedImage = ref('')
interface WebSettingsGallery { gallery: any[]; cloudinary_name?: string }
const webSettings = ref<WebSettingsGallery>({ gallery: [] })
const lightboxOpen = ref(false)
const lightboxIdx = ref(0)
const mainImageFailed = ref(false)

const allImages = computed(() => {
 if (!item.value) return []
 const imgs = [item.value.image]
 const extraImgs = normalizeImages(item.value.images)
 extraImgs.forEach(img => {
 if (img && !imgs.includes(img)) imgs.push(img)
 })
 return imgs.filter(Boolean)
})

const openLightbox = (idx: number) => {
 lightboxIdx.value = idx
 lightboxOpen.value = true
 _timerFocus = setTimeout(() => { lightboxRef.value?.focus() }, 100)
}

const nextImage = () => {
 if (lightboxIdx.value < allImages.value.length - 1) lightboxIdx.value++
}

const prevImage = () => {
 if (lightboxIdx.value > 0) lightboxIdx.value--
}

const formatDate = (d: string): string => {
 try {
 const dt = new Date(d)
 if (isNaN(dt.getTime())) return d
 return dt.toLocaleDateString('es-CO', { year: 'numeric', month: 'long' })
 } catch {
 return d || ''
 }
}

const getOptimizedImage = (url: string): string => {
 if (!url) return ''
 if (!webSettings.value.cloudinary_name || !url.includes('cloudinary.com')) return url
 return url.replace('/upload/', '/upload/f_auto,q_auto,w_1200/')
}

const fetchData = async () => {
 try {
 const data = await api.get('/settings')
 if (data?.web?.gallery) {
 webSettings.value.gallery = data.web.gallery.map((item: any, idx: number) =>
 typeof item === 'string' ? { image: item, title: `Imagen ${idx + 1}` } : item
 )
 const rawSlug = Array.isArray(route.params.id) ? route.params.id[0] : route.params.id
 const decodedSlug = decodeURIComponent(rawSlug)
 item.value = webSettings.value.gallery.find(g =>
 encodeURIComponent(g.title || '') === rawSlug ||
 g.title === rawSlug ||
 g.title === decodedSlug
 ) || null
 if (item.value) {
 selectedImage.value = item.value.image
 }
 } else {
 item.value = null
 }
 } catch (err) {
 logger.error('Error:', err)
 item.value = null } finally {
 _timerLoad = setTimeout(() => {
 loading.value = false
 setupRelatedReveal()
 nextTick(() => {
 applySplitTitle()
 applySplitBtn()
 })
 }, 300)
 }
}

// ─── Scroll reveal for related projects ───
const setupRelatedReveal = () => {
 nextTick(() => {
 if (!relatedGridRef.value) return
 relatedObserver = new IntersectionObserver((entries) => {
 entries.forEach(entry => {
 if (entry.isIntersecting) {
  relatedVisible.value = true
  relatedObserver?.unobserve(entry.target)
 }
 })
 }, { threshold: 0.15 })
 relatedObserver.observe(relatedGridRef.value)
 })
}

onMounted(() => {
 fetchData()
})

onUnmounted(() => {
 if (_timerFocus) clearTimeout(_timerFocus)
 if (_timerLoad) clearTimeout(_timerLoad)
 if (relatedObserver) relatedObserver.disconnect()
})
</script>

<template>
 <div class="min-h-screen bg-[#f8fafc] dark:bg-[#0a0f14] text-[#ffffff] dark:text-white transition-colors duration-500 overflow-x-hidden">
 <AppNavbar active-tab="gallery" subtext="Galería de Trabajos" />

 <main class="max-w-7xl mx-auto px-6 py-20">
 <!-- Loading -->
 <div v-if="loading" class="text-center py-40">
 <div class="w-16 h-16 border-4 border-emerald-500/30 border-t-emerald-500 rounded-[60px] animate-spin mx-auto mb-6"></div>
 <p class="text-[10px] font-black text-[#c3c4c5] uppercase tracking-widest">Cargando proyecto...</p>
 </div>

 <!-- Not Found -->
 <div v-else-if="!item" class="text-center py-40 space-y-8">
 <div class="text-8xl font-black text-gray-200 dark:text-white/10">404</div>
 <p class="text-lg font-black text-[#c3c4c5] uppercase tracking-widest italic">Proyecto no encontrado</p>
 <router-link to="/galeria" class="inline-flex items-center gap-3 px-10 py-4 bg-emerald-500 text-white rounded-[24px] font-black text-[10px] uppercase tracking-widest hover:bg-emerald-400 transition-all ">
 ← Volver a la Galería
 </router-link>
 </div>

 <!-- Content -->
 <template v-else>
 <!-- Back link -->
 <button class="group flex items-center gap-3 text-[#c3c4c5] hover:text-emerald-500 transition-colors mb-12 text-[10px] font-black uppercase tracking-widest" @click="router.push('/galeria')">
 <svg class="w-4 h-4 group-hover:-translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
 Volver a la Galería
 </button>

 <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-start">
        <!-- Left: Image Gallery (Estilo Catálogo) -->
        <div class="space-y-5 lg:sticky lg:top-24">
          <!-- Visor Principal (Estilo Catálogo - fondo claro premium) -->
          <div
            class="relative aspect-square bg-white dark:bg-[#f0f0f0] rounded-[3rem] overflow-hidden border border-gray-200 dark:border-gray-300 shadow-xl group flex items-center justify-center p-8 cursor-pointer"
            @click="openLightbox(allImages.indexOf(selectedImage || item.image))"
          >
            <transition name="img-fade" mode="out-in">
              <img
                :key="selectedImage || item.image"
                v-if="!mainImageFailed"
                :src="getOptimizedImage(selectedImage || item.image)"
                :alt="'Proyecto de impresión 3D: ' + item.title + (item.category ? ' (' + item.category + ')' : '') + ' — N3XT 3D Galería'"
                class="max-w-full max-h-full object-contain transition-transform duration-700 group-hover:scale-105"
                fetchpriority="high"
                decoding="async"
                @error="mainImageFailed = true"
              />
            </transition>

            <div v-if="!item.image || mainImageFailed" class="absolute inset-0 flex items-center justify-center">
              <svg class="w-24 h-24 text-gray-300 dark:text-[#a4aea6]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>

            <!-- Badge Alta Definición -->
            <div class="absolute top-5 right-5 px-3 py-1.5 bg-black/60 backdrop-blur-md rounded-[6px] border border-white/10 z-10">
              <span class="text-[8px] font-black text-emerald-400 uppercase tracking-[0.2em]">Alta Definición</span>
            </div>

            <!-- Zoom hint icon -->
            <div class="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-colors flex items-center justify-center pointer-events-none">
              <div class="w-14 h-14 bg-black/70 backdrop-blur-md rounded-[20px] flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all transform scale-75 group-hover:scale-100 shadow-xl">
                <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/></svg>
              </div>
            </div>
          </div>

          <!-- Miniaturas (estilo Catálogo: pequeñas, redondeadas, hover cambia imagen) -->
          <div v-if="allImages.length > 1" class="flex gap-3 flex-wrap">
            <div
              v-for="(img, idx) in allImages"
              :key="idx"
              :class="[
                (selectedImage || item.image) === img
                  ? 'ring-2 ring-emerald-500 border-emerald-400 opacity-100 shadow-lg shadow-emerald-500/20'
                  : 'border-gray-200 dark:border-gray-300 opacity-70 hover:opacity-100 hover:border-emerald-400'
              ]"
              class="w-20 h-20 bg-white dark:bg-[#f0f0f0] rounded-[18px] border-2 overflow-hidden cursor-pointer transition-all duration-300 flex items-center justify-center p-1.5"
              @mouseenter="selectedImage = img"
              @click="selectedImage = img"
            >
              <img
                :src="getOptimizedImage(img)"
                :alt="'Vista previa ' + (idx + 1) + ' del proyecto: ' + item.title"
                class="w-full h-full object-contain"
                loading="lazy"
                decoding="async"
                @error="(e: any) => e.target.style.display='none'"
              />
            </div>
          </div>
        </div>

 <!-- Right: Info -->
 <div class="space-y-8">
 <!-- Category + Tech badges -->
 <div class="flex flex-wrap gap-3">
 <span class="px-4 py-1.5 bg-emerald-500/10 text-emerald-500 text-[8px] font-black rounded-[60px] uppercase tracking-[0.25em] border border-emerald-500/20">{{ item.category }}</span>
 <span v-if="item.technology" class="px-4 py-1.5 bg-black/5 dark:bg-[#151a22]/5 text-[#a4aea6] text-[8px] font-black rounded-[60px] uppercase tracking-[0.25em] border border-[#21262d] dark:border-[#21262d]">{{ item.technology }}</span>
 <span v-if="item.material" class="px-4 py-1.5 bg-black/5 dark:bg-[#151a22]/5 text-[#a4aea6] text-[8px] font-black rounded-[60px] uppercase tracking-[0.25em] border border-[#21262d] dark:border-[#21262d]">{{ item.material }}</span>
 </div>

 <!-- Title -->
 <div>
 <h1 class="split-title text-3xl md:text-4xl lg:text-5xl font-black text-slate-900 dark:text-white tracking-normal uppercase leading-[1.0] mb-6 animate-fade-in">{{ item.title }}</h1>
 <p v-if="item.subtitle" class="text-sm font-black text-[#c3c4c5] uppercase tracking-[0.2em] mt-3">{{ item.subtitle }}</p>
 </div>

 <!-- Description -->
 <p v-if="item.description" class="text-caption uppercase">
 {{ item.description }}
 </p>

 <!-- Client -->
 <div v-if="item.client" class="flex items-center gap-3">
 <svg class="w-5 h-5 text-[#c3c4c5]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
 <span class="text-[10px] font-black text-[#c3c4c5] uppercase tracking-widest">Cliente: <span class="text-[#ffffff] dark:text-white">{{ item.client }}</span></span>
 </div>

 <!-- Date -->
 <div v-if="item.date" class="flex items-center gap-3">
 <svg class="w-5 h-5 text-[#c3c4c5]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
 <span class="text-[10px] font-black text-[#c3c4c5] uppercase tracking-widest">Fecha: <span class="text-[#ffffff] dark:text-white">{{ formatDate(item.date) }}</span></span>
 </div>

 <!-- Dimensions -->
 <div v-if="item.dimensions" class="flex items-center gap-3">
 <svg class="w-5 h-5 text-[#c3c4c5]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
 <span class="text-[10px] font-black text-[#c3c4c5] uppercase tracking-widest">Dimensiones: <span class="text-[#ffffff] dark:text-white">{{ item.dimensions }}</span></span>
 </div>

 <!-- Tags -->
 <div v-if="normalizeTags(item.tags).length > 0" class="flex flex-wrap gap-2">
 <span v-for="tag in normalizeTags(item.tags)" :key="tag" class="px-4 py-2 bg-[#151a22] dark:bg-[#151a22]/5 text-[#a4aea6] text-[8px] font-black rounded-[60px] uppercase tracking-[0.15em] border border-[#21262d] dark:border-[#21262d]">{{ tag }}</span>
 </div>

 <!-- Stats -->
 <div v-if="item.stats && item.stats.length" class="grid grid-cols-2 sm:grid-cols-3 gap-4">
 <div
v-for="stat in item.stats" :key="stat.label"
 class="bg-[#151a22] dark:bg-[#151a22]/[0.03] rounded-[24px] p-5 text-center border border-[#21262d] dark:border-[#21262d]">
 <p class="text-2xl font-black text-[#ffffff] dark:text-white">{{ stat.val }}</p>
 <p class="text-[8px] font-bold text-[#c3c4c5] uppercase tracking-[0.15em] mt-1">{{ stat.label }}</p>
 </div>
 </div>

 <!-- CTA -->
 <div class="flex flex-wrap gap-4 pt-4">
 <router-link
to="/project/init"
 class="split-btn px-10 py-5 bg-emerald-500 text-white rounded-[24px] font-black text-[10px] uppercase tracking-widest hover:bg-emerald-400 transition-all -emerald-500/20 hover:scale-[1.02] active:scale-95">
 Contáctanos →
 </router-link>
 <router-link
to="/catalog"
 class="px-10 py-5 bg-[#151a22] dark:bg-[#151a22]/5 border border-[#21262d] dark:border-[#21262d] text-[#ffffff] dark:text-white rounded-[24px] font-black text-[10px] uppercase tracking-widest hover:border-emerald-500/30 transition-all">
 Explorar Catálogo
 </router-link>
 </div>
 </div>
 </div>

 <!-- Related projects -->
 <div v-if="webSettings.gallery.length > 1" ref="relatedGridRef" class="mt-32 related-grid">
 <h2 class="split-title text-4xl md:text-6xl font-black tracking-normal uppercase leading-[0.9] mb-12">Proyectos <span class="text-emerald-500">Relacionados</span></h2>
 <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
 <router-link
v-for="(related, idx) in webSettings.gallery.filter(g => g.title !== item.title).slice(0, 3)" :key="idx"
 :to="'/galeria/' + encodeURIComponent(related.title || idx)"
 :class="['group bg-[#151a22] dark:bg-[#151a22]/5 rounded-[2rem] overflow-hidden border border-[#21262d] dark:border-[#21262d] hover:border-emerald-500/30 transition-all hover:', relatedVisible ? 'related-revealed' : 'related-hidden']"
 :style="{ '--rel-delay': (idx * 150) + 'ms' }">
 <div class="aspect-square overflow-hidden">
 <img
:src="getOptimizedImage(related.image)" :alt="'Proyecto relacionado: ' + related.title + ' — N3XT 3D Galería'"
 class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700" loading="lazy" decoding="async" />
 </div>
 <div class="p-6 text-center">
 <h3 class="text-lg md:text-xl font-black tracking-tighter uppercase group-hover:text-emerald-500 transition-colors">{{ related.title }}</h3>
 <p class="text-[8px] font-black text-[#c3c4c5] uppercase tracking-[0.2em] mt-1">{{ related.category }}</p>
 </div>
 </router-link>
 </div>
 </div>
 </template>
 </main>

 <!-- Lightbox Modal -->
 <transition name="fade">
 <div
v-if="lightboxOpen && allImages.length" ref="lightboxRef" class="fixed inset-0 z-[100] flex items-center justify-center p-4 md:p-12"
 tabindex="0" @keydown.escape="lightboxOpen = false" @keydown.right="nextImage" @keydown.left="prevImage">
 <div class="absolute inset-0 bg-black/95 backdrop-blur-2xl" @click="lightboxOpen = false"></div>

 <!-- Close -->
 <button
class="absolute top-6 right-6 z-10 w-14 h-14 bg-[#151a22]/10 hover:bg-[#151a22]/20 text-white rounded-[24px] flex items-center justify-center transition-all backdrop-blur-xl border border-white/10"
 @click="lightboxOpen = false">
 <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
 </button>

 <!-- Counter -->
 <div class="absolute top-6 left-6 z-10 px-4 py-2 bg-black/40 backdrop-blur-xl rounded-[6px] border border-white/10 text-white text-[10px] font-black tracking-widest">
 {{ lightboxIdx + 1 }} / {{ allImages.length }}
 </div>

 <!-- Prev -->
 <button
v-if="lightboxIdx > 0" class="absolute left-6 z-10 w-14 h-14 bg-[#151a22]/10 hover:bg-[#151a22]/20 text-white rounded-[24px] flex items-center justify-center transition-all backdrop-blur-xl border border-white/10"
 @click="prevImage">
 <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
 </button>

 <!-- Next -->
 <button
v-if="lightboxIdx < allImages.length - 1" class="absolute right-6 z-10 w-14 h-14 bg-[#151a22]/10 hover:bg-[#151a22]/20 text-white rounded-[24px] flex items-center justify-center transition-all backdrop-blur-xl border border-white/10"
 @click="nextImage">
 <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
 </button>

 <!-- Image -->
 <div class="relative max-w-5xl max-h-[85vh] w-full h-full flex items-center justify-center">
 <img
:key="lightboxIdx"
 :src="getOptimizedImage(allImages[lightboxIdx])"
 :alt="'Imagen ' + (lightboxIdx + 1) + ' de ' + allImages.length + ' — ' + (item ? item.title : 'Proyecto N3XT 3D')"
 class="max-w-full max-h-full object-contain rounded-[24px] animate-in fade-in zoom-in-95 duration-500"
 loading="lazy"
 decoding="async" />
 </div>

 <!-- Thumbnails at bottom -->
 <div class="absolute bottom-6 left-1/2 -translate-x-1/2 flex gap-2 z-10">
 <button
v-for="(img, idx) in allImages" :key="idx"
 class="w-12 h-12 rounded-[6px] overflow-hidden border-2 transition-all"
 :class="idx === lightboxIdx ? 'border-emerald-500 scale-110' : 'border-white/20 opacity-50 hover:opacity-100'"
 @click="lightboxIdx = idx">
 <img :src="getOptimizedImage(img)" :alt="'Miniatura ' + (idx + 1) + ' — ' + (item ? item.title : 'Proyecto') + ' | N3XT 3D'" class="w-full h-full object-cover" loading="lazy" decoding="async" @error="(e: any) => e.target.style.display='none'" />
 </button>
 </div>
 </div>
 </transition>

 <AppFooter />
 </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active {
 transition: opacity 0.4s cubic-bezier(0.22, 1, 0.36, 1), transform 0.4s cubic-bezier(0.22, 1, 0.36, 1);
}
.fade-enter-from {
 opacity: 0;
 transform: scale(0.96);
}
.fade-leave-to {
 opacity: 0;
 transform: scale(1.04);
}

/* Scroll reveal for related projects */
.related-hidden {
  opacity: 0;
  transform: translateY(30px);
  transition: opacity 0.6s cubic-bezier(0.22, 1, 0.36, 1),
  transform 0.6s cubic-bezier(0.22, 1, 0.36, 1);
}
.related-revealed {
  opacity: 1;
  transform: translateY(0);
  transition: opacity 0.6s cubic-bezier(0.22, 1, 0.36, 1) var(--rel-delay, 0ms),
  transform 0.6s cubic-bezier(0.22, 1, 0.36, 1) var(--rel-delay, 0ms);
}

/* --- Image Fade Transition (thumbnail hover) --- */
.img-fade-enter-active,
.img-fade-leave-active {
  transition: opacity 0.35s ease, transform 0.35s ease;
}
.img-fade-enter-from {
  opacity: 0;
  transform: scale(0.97);
}
.img-fade-leave-to {
  opacity: 0;
  transform: scale(1.03);
}
</style>
