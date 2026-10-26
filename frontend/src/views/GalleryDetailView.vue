<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, nextTick, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../services/api'
import AppNavbar from '../components/AppNavbar.vue'
import AppFooter from '../components/AppFooter.vue'
import logger from '../utils/logger'
import { useSplitTitle } from '../composables/useSplitTitle'
import { usePageMeta, absoluteUrl } from '../composables/usePageMeta'
import { useFocusTrap } from '../composables/useFocusTrap'

const { applySplitTitle } = useSplitTitle()


const route = useRoute()
const router = useRouter()
const loading = ref(true)
const loadError = ref(false)
const lightboxRef = ref<HTMLElement | null>(null)
const relatedGridRef = ref<HTMLElement | null>(null)

interface GalleryDetailItem {
  image: string
  title: string
  category?: string
  technology?: string
  material?: string
  images?: string[] | string
  tags?: string[] | string
  subtitle?: string
  description?: string
  client?: string
  date?: string
  dimensions?: string
  stats?: { val: string; label: string }[]
}

// ─── Scroll reveal for related projects ───
const relatedVisible = ref(false)
let relatedObserver: IntersectionObserver | null = null
let _timerLoad: ReturnType<typeof setTimeout> | null = null

const normalizeTags = (tags: unknown): string[] => Array.isArray(tags) ? tags : (typeof tags === 'string' ? tags.split(',').map(t => t.trim()).filter(Boolean) : [])
const normalizeImages = (images: unknown): string[] => Array.isArray(images) ? images : (typeof images === 'string' ? images.split(',').map(s => s.trim()).filter(Boolean) : [])
const item = ref<GalleryDetailItem | null>(null)
usePageMeta(() => ({
  title: item.value?.title ? `${item.value.title} | Galería N3XT 3D` : 'Proyecto | Galería N3XT 3D',
  description: item.value?.description || 'Un proyecto impreso en 3D en nuestro taller: proceso, materiales y acabado.',
  image: item.value?.image || undefined,
  // schema.org/CreativeWork: la obra impresa, con sus fotos y material
  jsonLd: item.value ? {
    '@type': 'CreativeWork',
    name: item.value.title,
    description: item.value.description || item.value.subtitle || undefined,
    image: [item.value.image, ...normalizeImages(item.value.images)].filter(Boolean).map(i => absoluteUrl(i)),
    genre: item.value.category || undefined,
    material: item.value.material || undefined,
    keywords: normalizeTags(item.value.tags).join(', ') || undefined,
    dateCreated: item.value.date || undefined,
    creator: { '@id': 'https://n3xt3d.com/#business' },
  } : null,
}))
const webSettings = ref<{ gallery: GalleryDetailItem[]; cloudinary_name?: string }>({ gallery: [] })
const lightboxOpen = ref(false)
const lightboxIdx = ref(0)
const selectedIdx = ref(0)
const mainImageFailed = ref(false)
const brokenRelated = ref<Record<string, boolean>>({})

const allImages = computed(() => {
  if (!item.value) return []
  const imgs = [item.value.image]
  const extraImgs = normalizeImages(item.value.images)
  extraImgs.forEach(img => {
    if (img && !imgs.includes(img)) imgs.push(img)
  })
  return imgs.filter(Boolean)
})

// Si falla la imagen principal se muestra un placeholder; se reintenta al cambiar de miniatura
watch(selectedIdx, () => { mainImageFailed.value = false })

const hasMeta = computed(() => !!item.value && !!(
  item.value.category || item.value.technology || item.value.material ||
  item.value.client || item.value.date || item.value.dimensions ||
  (item.value.stats && item.value.stats.length)
))

const relatedItems = computed(() =>
  item.value ? webSettings.value.gallery.filter(g => g.title !== item.value!.title).slice(0, 3) : []
)

const openLightbox = (idx: number) => {
  if (!allImages.value.length) return
  lightboxIdx.value = idx
  lightboxOpen.value = true
}

const closeLightbox = () => {
  lightboxOpen.value = false
}

const nextImage = () => {
  if (lightboxIdx.value < allImages.value.length - 1) lightboxIdx.value++
}

const prevImage = () => {
  if (lightboxIdx.value > 0) lightboxIdx.value--
}

// Foco atrapado, Escape, bloqueo de scroll y foco de vuelta a la miniatura (useFocusTrap)
useFocusTrap(lightboxRef, lightboxOpen, { onEscape: closeLightbox })

const onLightboxKey = (e: KeyboardEvent) => {
  if (!lightboxOpen.value) return
  if (e.key === 'ArrowRight') nextImage()
  else if (e.key === 'ArrowLeft') prevImage()
}

watch(lightboxOpen, (open) => {
  if (open) window.addEventListener('keydown', onLightboxKey)
  else window.removeEventListener('keydown', onLightboxKey)
})

const hideImg = (e: Event) => { (e.target as HTMLImageElement).style.display = 'none' }

const formatDate = (d: string) => {
  try {
    const dt = new Date(d)
    if (isNaN(dt.getTime())) return d
    return dt.toLocaleDateString('es-CO', { year: 'numeric', month: 'long' })
  } catch {
    return d || ''
  }
}

const getOptimizedImage = (url: string | undefined): string => {
  if (!url) return ''
  if (!webSettings.value.cloudinary_name || !url.includes('cloudinary.com')) return url
  return url.replace('/upload/', '/upload/f_auto,q_auto,w_1200/')
}

const fetchData = async () => {
  loading.value = true
  loadError.value = false
  lightboxOpen.value = false
  lightboxIdx.value = 0
  selectedIdx.value = 0
  mainImageFailed.value = false
  brokenRelated.value = {}
  relatedVisible.value = false
  if (relatedObserver) relatedObserver.disconnect()
  if (_timerLoad !== null) clearTimeout(_timerLoad)
  try {
    const data = await api.get('/settings')
    if (data?.web?.gallery) {
      webSettings.value.gallery = data.web.gallery.map((g: GalleryDetailItem | string, idx: number) =>
        typeof g === 'string' ? { image: g, title: `Imagen ${idx + 1}` } : g
      )
      if (data.web.cloudinary_name) webSettings.value.cloudinary_name = data.web.cloudinary_name
      // vue-router ya entrega el parámetro decodificado
      const rawParam = route.params.id
      const slug = Array.isArray(rawParam) ? rawParam.join('/') : (rawParam || '')
      item.value = webSettings.value.gallery.find(g =>
        g.title === slug || encodeURIComponent(g.title || '') === slug
      ) || null
    } else {
      item.value = null
    }
  } catch (err) {
    logger.error('Error:', err)
    item.value = null
    loadError.value = true
  } finally {
    _timerLoad = setTimeout(() => {
      loading.value = false
      setupRelatedReveal()
      nextTick(() => {
        applySplitTitle()
      })
    }, 300)
  }
}

// ─── Scroll reveal for related projects ───
const setupRelatedReveal = () => {
  nextTick(() => {
    if (!relatedGridRef.value) return
    if (relatedObserver) relatedObserver.disconnect()
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

watch(() => route.params.id, (newId, oldId) => {
  if (!newId || newId === oldId) return
  window.scrollTo({ top: 0, behavior: 'smooth' })
  fetchData()
})

onMounted(() => {
  fetchData()
})

onUnmounted(() => {
  if (_timerLoad !== null) clearTimeout(_timerLoad)
  if (relatedObserver) relatedObserver.disconnect()
  window.removeEventListener('keydown', onLightboxKey)
})
</script>

<template>
  <div class="min-h-screen bg-canvas text-ink overflow-x-clip">
    <AppNavbar active-tab="gallery" subtext="Galería de trabajos" />

    <main id="main" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 md:py-14">
      <!-- Cargando -->
      <div v-if="loading" class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-16" aria-hidden="true">
        <div class="space-y-4">
          <div class="aspect-[4/3] bg-surface-2 animate-pulse rounded-[var(--radius-card)]"></div>
          <div class="flex gap-3">
            <div v-for="i in 4" :key="i" class="w-20 h-20 bg-surface-2 animate-pulse rounded-[var(--radius-control)]"></div>
          </div>
        </div>
        <div class="space-y-4">
          <div class="h-12 bg-surface-2 animate-pulse rounded w-3/4"></div>
          <div class="h-4 bg-surface-2 animate-pulse rounded w-full"></div>
          <div class="h-4 bg-surface-2 animate-pulse rounded w-5/6"></div>
          <div class="h-32 bg-surface-2 animate-pulse rounded-[var(--radius-card)] mt-8"></div>
        </div>
      </div>

      <!-- Error -->
      <div v-else-if="loadError" class="py-24 text-center max-w-md mx-auto" role="alert">
        <h1 class="text-2xl font-extrabold tracking-tight text-ink">No pudimos cargar el proyecto</h1>
        <p class="mt-3 text-muted">Revisa tu conexión e inténtalo de nuevo.</p>
        <button type="button" class="btn-primary mt-6" @click="fetchData">Reintentar</button>
      </div>

      <!-- No encontrado -->
      <div v-else-if="!item" class="py-24 text-center max-w-md mx-auto">
        <p class="text-6xl font-extrabold tracking-tight text-muted/40">404</p>
        <h1 class="mt-4 text-2xl font-extrabold tracking-tight text-ink">Proyecto no encontrado</h1>
        <p class="mt-3 text-muted">Puede que lo hayamos retirado o que el enlace esté incompleto.</p>
        <router-link to="/galeria" class="btn-primary mt-6">Volver a la galería</router-link>
      </div>

      <!-- Contenido -->
      <template v-else>
        <button type="button" class="group inline-flex items-center gap-2 text-sm font-medium text-muted hover:text-brand transition-colors mb-8" @click="router.push('/galeria')">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
          Volver a la galería
        </button>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-16 items-start">
          <!-- Imágenes -->
          <div class="space-y-4 min-w-0">
            <button
              type="button"
              class="relative block w-full aspect-[4/3] rounded-[var(--radius-card)] overflow-hidden bg-surface-2 cursor-zoom-in"
              :aria-label="'Ampliar imagen ' + (selectedIdx + 1) + ' de ' + (allImages.length || 1)"
              @click="openLightbox(selectedIdx)">
              <img
                v-if="(allImages[selectedIdx] || item.image) && !mainImageFailed"
                :src="getOptimizedImage(allImages[selectedIdx] || item.image)"
                :alt="'Proyecto de impresión 3D: ' + item.title + (item.category ? ' (' + item.category + ')' : '') + ' — N3XT 3D Galería'"
                class="w-full h-full object-cover"
                fetchpriority="high"
                decoding="async"
                @error="mainImageFailed = true" />
              <span v-else class="absolute inset-0 flex items-center justify-center text-muted/60">
                <svg class="w-16 h-16" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.25" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
              </span>
              <span v-if="allImages.length" class="absolute bottom-3 right-3 inline-flex items-center gap-1.5 h-8 px-3 rounded-[var(--radius-control)] bg-black/60 text-white text-sm font-medium" aria-hidden="true">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/></svg>
                Ampliar
              </span>
            </button>

            <!-- Miniaturas -->
            <div v-if="allImages.length > 1" class="flex gap-3 overflow-x-auto pb-1" role="group" aria-label="Imágenes del proyecto">
              <button
                v-for="(img, idx) in allImages" :key="idx"
                type="button"
                class="w-20 h-20 shrink-0 rounded-[var(--radius-control)] overflow-hidden border-2 bg-surface-2 transition-colors"
                :class="idx === selectedIdx ? 'border-primary' : 'border-line hover:border-muted'"
                :aria-pressed="idx === selectedIdx"
                :aria-label="'Ver imagen ' + (idx + 1)"
                @click="selectedIdx = idx">
                <img :src="getOptimizedImage(img)" :alt="'Vista previa del proyecto: ' + item.title + ' | N3XT 3D'" class="w-full h-full object-cover" loading="lazy" decoding="async" @error="hideImg" />
              </button>
            </div>
          </div>

          <!-- Información -->
          <div class="min-w-0">
            <h1 class="split-title text-4xl sm:text-5xl lg:text-6xl font-black uppercase leading-[0.95] tracking-tight [font-stretch:115%] text-ink break-words [overflow-wrap:anywhere]">{{ item.title }}</h1>
            <p v-if="item.subtitle" class="mt-3 text-lg md:text-xl text-muted">{{ item.subtitle }}</p>

            <p v-if="item.description" class="mt-6 max-w-prose text-base leading-relaxed text-ink whitespace-pre-line">{{ item.description }}</p>

            <dl v-if="hasMeta" class="mt-8 grid grid-cols-2 gap-x-6 gap-y-5 pt-2">
              <div v-if="item.category">
                <dt class="text-sm text-muted">Categoría</dt>
                <dd class="mt-1 font-medium text-ink">{{ item.category }}</dd>
              </div>
              <div v-if="item.technology">
                <dt class="text-sm text-muted">Tecnología</dt>
                <dd class="mt-1 font-medium text-ink">{{ item.technology }}</dd>
              </div>
              <div v-if="item.material">
                <dt class="text-sm text-muted">Material</dt>
                <dd class="mt-1 font-medium text-ink">{{ item.material }}</dd>
              </div>
              <div v-if="item.client">
                <dt class="text-sm text-muted">Cliente</dt>
                <dd class="mt-1 font-medium text-ink">{{ item.client }}</dd>
              </div>
              <div v-if="item.date">
                <dt class="text-sm text-muted">Fecha</dt>
                <dd class="mt-1 font-medium text-ink">{{ formatDate(item.date) }}</dd>
              </div>
              <div v-if="item.dimensions">
                <dt class="text-sm text-muted">Dimensiones</dt>
                <dd class="mt-1 font-medium text-ink tabular-nums">{{ item.dimensions }}</dd>
              </div>
              <div v-for="stat in (item.stats || [])" :key="stat.label">
                <dt class="text-sm text-muted">{{ stat.label }}</dt>
                <dd class="mt-1 font-medium text-ink tabular-nums">{{ stat.val }}</dd>
              </div>
            </dl>

            <div v-if="normalizeTags(item.tags).length > 0" class="mt-6 flex flex-wrap gap-2">
              <span v-for="tag in normalizeTags(item.tags)" :key="tag" class="inline-flex items-center h-6 px-2 rounded-md text-xs font-medium bg-surface-2 text-ink">{{ tag }}</span>
            </div>

            <div class="mt-10 flex flex-wrap gap-3">
              <router-link to="/quote" class="split-btn btn-primary btn-lg">Cotizar una pieza similar</router-link>
              <router-link to="/catalog" class="btn-secondary btn-lg">Ver el catálogo</router-link>
            </div>
          </div>
        </div>

        <!-- Relacionados -->
        <section v-if="relatedItems.length > 0" ref="relatedGridRef" class="mt-20 md:mt-28" aria-labelledby="related-heading">
          <h2 id="related-heading" class="section-title mb-12">Otros <span class="section-title-accent">trabajos</span></h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <router-link
              v-for="(related, idx) in relatedItems" :key="related.title || idx"
              :to="'/galeria/' + encodeURIComponent(related.title || String(idx))"
              :class="['group flex flex-col bg-surface rounded-[var(--radius-card)] overflow-hidden transition-colors hover:bg-surface-2 hover:border-primary', relatedVisible ? 'related-revealed' : 'related-hidden']"
              :style="{ '--rel-delay': (idx * 80) + 'ms' }">
              <div class="relative aspect-[4/3] bg-surface-2 overflow-hidden">
                <img
                  v-if="related.image && !brokenRelated[related.title]"
                  :src="getOptimizedImage(related.image)"
                  :alt="'Proyecto relacionado: ' + related.title + ' — N3XT 3D Galería'"
                  class="absolute inset-0 w-full h-full object-cover" loading="lazy" decoding="async"
                  @error="brokenRelated[related.title] = true" />
                <span v-else class="absolute inset-0 flex items-center justify-center text-muted/60">
                  <svg class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.25" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </span>
              </div>
              <div class="flex flex-col flex-1 p-5">
                <h3 class="text-lg md:text-xl font-bold tracking-tight text-ink group-hover:text-brand transition-colors line-clamp-2">{{ related.title }}</h3>
                <p v-if="related.category || related.technology || related.material" class="mt-1.5 flex flex-wrap gap-x-3 text-sm text-muted">
                  <span v-if="related.category">{{ related.category }}</span>
                  <span v-if="related.technology || related.material">{{ related.technology || related.material }}</span>
                </p>
                <div v-if="normalizeTags(related.tags).length > 0" class="mt-4 flex flex-wrap gap-2">
                  <span v-for="tag in normalizeTags(related.tags).slice(0, 2)" :key="tag" class="inline-flex items-center h-6 px-2 rounded-md text-xs font-medium bg-surface-2 text-ink">{{ tag }}</span>
                </div>
              </div>
            </router-link>
          </div>
        </section>
      </template>
    </main>

    <!-- Lightbox -->
    <transition name="fade">
      <div
        v-if="lightboxOpen && allImages.length" ref="lightboxRef"
        class="fixed inset-0 z-[1100] flex flex-col"
        role="dialog" aria-modal="true" :aria-label="'Galería de imágenes' + (item ? ': ' + item.title : '')" tabindex="-1">
        <div class="absolute inset-0 bg-black/90" @click="closeLightbox"></div>

        <!-- Barra superior -->
        <div class="relative z-10 flex items-center justify-between px-4 sm:px-6 h-16 text-white">
          <p class="text-sm font-medium tabular-nums" aria-live="polite">{{ lightboxIdx + 1 }} de {{ allImages.length }}</p>
          <button
            type="button"
            class="w-11 h-11 -mr-2 flex items-center justify-center rounded-[var(--radius-control)] text-white hover:bg-white/15 transition-colors"
            aria-label="Cerrar galería" @click="closeLightbox">
            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>

        <!-- Imagen + flechas -->
        <div class="relative flex-1 min-h-0 flex items-center justify-center px-4 sm:px-20 pointer-events-none">
          <img
            :key="lightboxIdx"
            :src="getOptimizedImage(allImages[lightboxIdx])"
            :alt="'Imagen ' + (lightboxIdx + 1) + ' de ' + allImages.length + ' — ' + (item ? item.title : 'Proyecto N3XT 3D')"
            class="max-w-full max-h-full object-contain rounded-[var(--radius-control)] pointer-events-auto"
            decoding="async" />

          <button
            v-if="lightboxIdx > 0"
            type="button"
            class="pointer-events-auto absolute left-2 sm:left-6 top-1/2 -translate-y-1/2 w-12 h-12 flex items-center justify-center rounded-full bg-white/15 hover:bg-white/25 text-white transition-colors"
            aria-label="Imagen anterior" @click="prevImage">
            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
          </button>
          <button
            v-if="lightboxIdx < allImages.length - 1"
            type="button"
            class="pointer-events-auto absolute right-2 sm:right-6 top-1/2 -translate-y-1/2 w-12 h-12 flex items-center justify-center rounded-full bg-white/15 hover:bg-white/25 text-white transition-colors"
            aria-label="Imagen siguiente" @click="nextImage">
            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
          </button>
        </div>

        <!-- Miniaturas -->
        <div v-if="allImages.length > 1" class="relative z-10 flex justify-center gap-2 px-4 py-4 overflow-x-auto">
          <button
            v-for="(img, idx) in allImages" :key="idx"
            type="button"
            class="w-14 h-14 shrink-0 rounded-[var(--radius-control)] overflow-hidden border-2 transition-opacity"
            :class="idx === lightboxIdx ? 'border-white opacity-100' : 'border-transparent opacity-60 hover:opacity-100'"
            :aria-pressed="idx === lightboxIdx"
            :aria-label="'Ver imagen ' + (idx + 1)" @click="lightboxIdx = idx">
            <img :src="getOptimizedImage(img)" :alt="'Miniatura ' + (idx + 1) + ' — ' + (item ? item.title : 'Proyecto') + ' | N3XT 3D'" class="w-full h-full object-cover" loading="lazy" decoding="async" @error="hideImg" />
          </button>
        </div>
      </div>
    </transition>

    <AppFooter />
  </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.related-hidden {
  opacity: 0;
  transform: translateY(12px);
}
.related-revealed {
  opacity: 1;
  transform: translateY(0);
  transition: opacity 0.5s cubic-bezier(0.22, 1, 0.36, 1) var(--rel-delay, 0ms),
              transform 0.5s cubic-bezier(0.22, 1, 0.36, 1) var(--rel-delay, 0ms),
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
