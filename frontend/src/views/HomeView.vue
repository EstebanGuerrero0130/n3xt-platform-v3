<script setup lang="ts">
import { ref, reactive, onMounted, onUnmounted, nextTick } from 'vue'
import gsap from 'gsap'
import { api } from '../services/api'
import { sanitizeSVG } from '../utils/sanitize'
import AppNavbar from '../components/AppNavbar.vue'
import BrandTicker from '../components/BrandTicker.vue'
import WaveDivider from '../components/WaveDivider.vue'
import AppFooter from '../components/AppFooter.vue'
import logger from '../utils/logger'
import { useSplitTitle } from '../composables/useSplitTitle'
import { usePageMeta } from '../composables/usePageMeta'
import { prefersReducedMotion } from '../composables/useMotion'

const { applySplitTitle } = useSplitTitle()

usePageMeta({
  title: 'N3XT 3D | Impresión 3D en resina y filamento en Colombia',
  description: 'Estudio de impresión 3D en Colombia. Figuras coleccionables, prototipos y piezas a medida en resina y filamento, con acabado a mano.',
  image: '/assets/n3xt_og_home.png',
})

// ─── Entrada firma del hero: "capa por capa" ───
// El wordmark se construye por franjas horizontales de abajo hacia arriba, como
// una impresora depositando capas; luego entran titular, texto, CTAs e imagen.
// Solo transform/opacity/clip-path. Con reduced-motion no se arma nada.
const heroRef = ref<HTMLElement | null>(null)
const nozzleRef = ref<HTMLElement | null>(null)
const WORDMARK_LAYERS = 9
const layerClip = (i: number) => {
  const step = 100 / WORDMARK_LAYERS
  return `inset(${((i - 1) * step).toFixed(3)}% 0 ${(100 - i * step).toFixed(3)}% 0)`
}
const heroMotion = !prefersReducedMotion()
// Mientras se construye, el wordmark real queda transparente (sigue en el árbol
// de accesibilidad) y se ven las capas superpuestas (aria-hidden).
const buildingWordmark = ref(heroMotion)
let heroTl: gsap.core.Timeline | null = null
let heroSafety: ReturnType<typeof setTimeout> | null = null

const finishHero = () => {
  buildingWordmark.value = false
  if (heroSafety) clearTimeout(heroSafety)
  heroSafety = null
}

onMounted(() => {
  const root = heroRef.value
  if (!root || !heroMotion) return finishHero()

  const layers = Array.from(root.querySelectorAll<HTMLElement>('[data-wm-layer]')).reverse() // de abajo hacia arriba
  const items = root.querySelectorAll<HTMLElement>('[data-hero-item]')
  const media = root.querySelector<HTMLElement>('[data-hero-media]')
  const mediaImg = media?.querySelector('img')
  const nozzle = nozzleRef.value
  const layerStep = 0.075
  const buildStart = 0.15
  const buildEnd = buildStart + layerStep * WORDMARK_LAYERS

  heroTl = gsap.timeline({ defaults: { ease: 'power3.out' }, onComplete: finishHero })
  heroTl
    .from(root.querySelectorAll('[data-hero-intro]'), { opacity: 0, y: 10, duration: 0.6 }, 0)
    .fromTo(layers, { opacity: 0, y: -12 }, { opacity: 1, y: 0, duration: 0.55, stagger: layerStep }, buildStart)
  if (nozzle) {
    heroTl
      .fromTo(nozzle, { opacity: 0, y: 0 }, { opacity: 1, duration: 0.15, ease: 'none' }, buildStart - 0.05)
      .to(nozzle, { y: () => -(nozzle.parentElement?.offsetHeight ?? 0), duration: buildEnd - buildStart, ease: 'none' }, buildStart)
      .to(nozzle, { opacity: 0, duration: 0.35, ease: 'power2.out' }, buildEnd - 0.05)
  }
  heroTl.from(items, { opacity: 0, y: 20, duration: 0.8, stagger: 0.09 }, buildEnd - 0.2)
  if (media) {
    heroTl.fromTo(media,
      { clipPath: 'inset(100% 0% 0% 0% round 22px)' },
      { clipPath: 'inset(0% 0% 0% 0% round 22px)', duration: 1.1, ease: 'power4.out', clearProps: 'clipPath' },
      buildEnd - 0.35)
    if (mediaImg) heroTl.fromTo(mediaImg, { scale: 1.08 }, { scale: 1, duration: 1.5, ease: 'power3.out', clearProps: 'transform' }, '<')
  }
  // Red de seguridad: pase lo que pase, el wordmark real queda visible
  heroSafety = setTimeout(() => { heroTl?.progress(1); finishHero() }, 4000)
})

onUnmounted(() => {
  heroTl?.kill()
  heroTl = null
  if (heroSafety) clearTimeout(heroSafety)
})

// --- TypeScript interfaces ---
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
  client?: string
  date?: string
  dimensions?: string
  stats?: { val: string; label: string }[]
}

interface WebSettings {
  social: { tiktok: string; instagram: string; facebook: string; whatsapp: string; youtube: string }
  workshop_status: string
  news: { t: string; d: string; tag: string; i: string; url: string }[]
  gallery: GalleryItem[]
  posts: { t: string; d: string; l: string; i: string; tag: string; url: string }[]
  catalog: any[]
  pdf_catalog_url: string
  pdf_catalog_desc: string
  company_name: string
  ecosystem: any[]
  privacy_policy?: string
  terms_conditions?: string
  cloudinary_name?: string
}

// --- PERFORMANCE & VISIBILITY PROTOCOL ---
const visibleSections = reactive<Record<string, boolean>>({})
const sectionObservers = new WeakMap<HTMLElement, IntersectionObserver>()
const vIntersection = {
    mounted(el: HTMLElement, binding: { value: string }) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    visibleSections[binding.value] = true
                    // Una vez visible, dejamos de observar este disparador
                    observer.disconnect()
                    sectionObservers.delete(el)
                }
            })
        }, { threshold: 0.1 })
        observer.observe(el)
        sectionObservers.set(el, observer)
    },
    unmounted(el: HTMLElement) {
        sectionObservers.get(el)?.disconnect()
        sectionObservers.delete(el)
    }
}

// Contenido estático de las secciones informativas
const highlights = [
  { val: '8K', label: 'Resolución en resina' },
  { val: '24 h', label: 'Prototipado rápido' },
  { val: '100 %', label: 'Acabado a mano' },
]

const processSteps = [
  { t: 'Envíanos tu idea', d: 'Una imagen, un boceto o un archivo 3D. Si aún no tienes el diseño, te ayudamos a desarrollarlo.' },
  { t: 'Te asesoramos', d: 'Revisamos el proyecto y te recomendamos material, tamaño y tecnología para tu pieza.' },
  { t: 'Recibe tu cotización', d: 'Un precio claro según complejidad, material, acabado y tiempo de fabricación.' },
  { t: 'Fabricamos y entregamos', d: 'Imprimimos con equipos profesionales, damos el acabado y te enviamos la pieza.' },
]

const finishingSteps = [
  { n: 'Curado UV', icon: '<svg class=\'w-5 h-5\' fill=\'none\' viewBox=\'0 0 24 24\' stroke=\'currentColor\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'1.5\' d=\'M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m12.728 0l-.707-.707M6.343 6.343l-.707-.707M12 8a4 4 0 100 8 4 4 0 000-8z\'/></svg>' },
  { n: 'Lijado fino', icon: '<svg class=\'w-5 h-5\' fill=\'none\' viewBox=\'0 0 24 24\' stroke=\'currentColor\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'1.5\' d=\'M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z\'/></svg>' },
  { n: 'Imprimación', icon: '<svg class=\'w-5 h-5\' fill=\'none\' viewBox=\'0 0 24 24\' stroke=\'currentColor\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'1.5\' d=\'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10\'/></svg>' },
  { n: 'Aerografía', icon: '<svg class=\'w-5 h-5\' fill=\'none\' viewBox=\'0 0 24 24\' stroke=\'currentColor\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'1.5\' d=\'M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z\'/></svg>' },
]

const webSettings = ref<WebSettings>({
  social: { tiktok: '#', instagram: '#', facebook: '#', whatsapp: '#', youtube: '#' },
  workshop_status: 'Abierto de lunes a sábado',
  // Noticias y publicaciones solo se muestran si vienen configuradas desde el panel
  news: [],
  gallery: [],
  posts: [],
  catalog: [],
  pdf_catalog_url: '',
  pdf_catalog_desc: '',
  company_name: 'N3XT 3D',
  privacy_policy: '',
  terms_conditions: '',
  cloudinary_name: '',
  ecosystem: [
    {
      type: 'image',
      t1: 'N3XT',
      t2: 'Lab',
      d: 'Nuestro taller. Aquí convertimos ideas en prototipos y piezas terminadas con impresoras de resina y filamento.',
      i: '/assets/n3xt_lab_render_hq_1778542148509.png',
      stats: []
    },
    {
      type: 'icon',
      t1: 'N3XT',
      t2: 'Shop',
      d: 'Catálogo de figuras, piezas de colección y accesorios listos para entrega.',
      i: '<svg class=\"w-7 h-7\" fill=\"none\" viewBox=\"0 0 24 24\" stroke=\"currentColor\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"1.5\" d=\"M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z\"/></svg>',
      stats: []
    },
    {
      type: 'icon',
      t1: 'Maker',
      t2: 'Space',
      d: 'Talleres, asesoría técnica y proyectos en colaboración con creadores locales.',
      i: '<svg class=\"w-7 h-7\" fill=\"none\" viewBox=\"0 0 24 24\" stroke=\"currentColor\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"1.5\" d=\"M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z\"/></svg>',
      stats: []
    }
  ]
})

const isReady = ref(false)

const brokenGalleryImgs = ref<Record<number, boolean>>({})

const getOptimizedImage = (url: string, width = 600): string => {
  if (!url) return ''
  if (!webSettings.value.cloudinary_name || !url.includes('cloudinary.com')) return url
  return url.replace('/upload/', `/upload/f_auto,q_auto,w_${width}/`)
}

// Un enlace '#' o vacío no es un enlace real: no se renderiza como externo
const isRealUrl = (url?: string): boolean => !!url && url.trim() !== '' && url.trim() !== '#'

// Destino del CTA de cada tarjeta del ecosistema
const ecosystemLink = (card: any): string => {
    if (card?.url && String(card.url).startsWith('/')) return card.url
    return String(card?.t2 || '').toUpperCase() === 'SHOP' ? '/catalog' : '/project/init'
}

// Lógica de Carrusel Automático para Tarjetas de Ecosistema
const activeIndices = ref([0, 0, 0])
const getImages = (iString: string): string[] => String(iString || '').split(',').map((s: string) => s.trim()).filter(Boolean)

let carouselInterval: ReturnType<typeof setInterval> | null = null

onMounted(() => {
    carouselInterval = setInterval(() => {
        webSettings.value.ecosystem.forEach((card, idx) => {
            if (card.type === 'image') {
                const imgs = getImages(card.i)
                if (imgs.length > 1) {
                    activeIndices.value[idx] = ((activeIndices.value[idx] ?? 0) + 1) % imgs.length
                }
            }
        })
    }, 5000) // Cambia cada 5 segundos
})

onUnmounted(() => {
    if (carouselInterval) clearInterval(carouselInterval)
})

const fetchSettings = async () => {
  try {
    const data = await api.get('/settings')
    if (data.web) {
      const dbWeb = data.web

      // Merge inteligente: Priorizamos datos de la DB si existen.
      // Si la DB está vacía (como en instalaciones nuevas), conservamos los valores por defecto.
      webSettings.value = {
        ...webSettings.value,
        social: dbWeb.social || webSettings.value.social,
        workshop_status: dbWeb.workshop_status || webSettings.value.workshop_status,
        news: (dbWeb.news && dbWeb.news.length > 0) ? dbWeb.news : webSettings.value.news,
        posts: (dbWeb.posts && dbWeb.posts.length > 0) ? dbWeb.posts : webSettings.value.posts,
        catalog: dbWeb.catalog || webSettings.value.catalog,
        pdf_catalog_url: dbWeb.pdf_catalog_url || webSettings.value.pdf_catalog_url,
        privacy_policy: dbWeb.privacy_policy || '',
        terms_conditions: dbWeb.terms_conditions || '',
        ecosystem: (dbWeb.ecosystem && dbWeb.ecosystem.length > 0) ? dbWeb.ecosystem : webSettings.value.ecosystem,
        gallery: (dbWeb.gallery && dbWeb.gallery.length > 0)
          ? dbWeb.gallery
              .map((item: any, idx: number) =>
                typeof item === 'string'
                  ? { image: item, title: `Imagen ${idx + 1}`, category: 'General', technology: '', images: [], featured: false, tags: '' }
                  : { images: [], tags: '', featured: false, ...item }
              )
              .filter((item: any) => item.image || item.title)
          : webSettings.value.gallery,
        company_name: data.company?.name || webSettings.value.company_name
      }
    }
  } catch (err) {
    logger.error('Error fetching settings:', err)
  } finally {
    isReady.value = true
    // Títulos que aparecen tras la carga (bajo v-if)
    nextTick(() => applySplitTitle())
  }
}

onMounted(() => {
  fetchSettings()
})
</script>

<template>
  <div class="min-h-screen bg-canvas text-ink overflow-x-hidden">
    <AppNavbar active-tab="home" subtext="Impresión 3D en Colombia" />

    <!-- Hero -->
    <section class="hero relative isolate overflow-hidden border-b border-line">
      <div class="hero-grid technical-grid absolute inset-0 -z-10 pointer-events-none" aria-hidden="true"></div>
      <div class="hero-glow absolute inset-0 -z-10 pointer-events-none" aria-hidden="true"></div>

      <div ref="heroRef" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-10 pb-16 md:pt-14 md:pb-20 lg:pt-16 lg:pb-24">
        <p data-hero-intro class="flex items-center gap-2.5 text-sm font-medium text-muted">
          <span class="hero-layers" aria-hidden="true"><span></span><span></span><span></span></span>
          Estudio de impresión 3D en Colombia
        </p>

        <h1 class="mt-5 md:mt-6">
          <span class="relative block">
            <span class="wordmark block" :class="{ 'opacity-0': buildingWordmark }">N3XT<span class="wordmark-accent"> 3D</span></span>
            <span v-if="buildingWordmark" class="absolute inset-0" aria-hidden="true">
              <span
                v-for="i in WORDMARK_LAYERS"
                :key="i"
                data-wm-layer
                class="wordmark absolute inset-0 block"
                :style="{ clipPath: layerClip(i) }"
              >N3XT<span class="wordmark-accent"> 3D</span></span>
              <span ref="nozzleRef" class="wm-nozzle"></span>
            </span>
          </span>
          <span data-hero-item class="mt-4 md:mt-6 block max-w-4xl text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight leading-[1.05] text-ink">
            Tu idea, impresa capa por capa.
          </span>
        </h1>

        <div class="mt-10 md:mt-12 grid gap-10 lg:grid-cols-12 lg:gap-12 lg:items-end">
          <div class="lg:col-span-5">
            <p data-hero-item class="max-w-xl text-lg md:text-xl leading-relaxed text-muted">
              Imprimimos en resina y filamento figuras coleccionables, prototipos y piezas a medida, con acabado a mano.
            </p>
            <div data-hero-item class="mt-8 flex flex-col sm:flex-row gap-3">
              <router-link to="/quote" class="btn-primary btn-lg">
                Cotizar mi pieza
                <svg class="btn-arrow w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14m-6-6 6 6-6 6"/></svg>
              </router-link>
              <router-link to="/galeria" class="btn-secondary btn-lg">Ver trabajos</router-link>
            </div>

            <!-- Cifras -->
            <dl data-hero-item class="mt-10 pt-6 border-t border-line grid grid-cols-3 gap-4 sm:gap-6">
              <div v-for="stat in highlights" :key="stat.label" class="flex flex-col-reverse">
                <dt class="mt-1 text-sm text-muted">{{ stat.label }}</dt>
                <dd class="text-2xl sm:text-3xl font-extrabold tracking-tight text-ink tabular-nums [font-stretch:112%]">{{ stat.val }}</dd>
              </div>
            </dl>
          </div>

          <div class="lg:col-span-7">
            <div data-hero-media class="hero-media relative aspect-[4/3] sm:aspect-[16/10] overflow-hidden rounded-[var(--radius-panel)] border border-line bg-surface-2">
              <img
                src="/assets/n3xt_industrial_maker_figures_1778542131405.webp"
                alt="Figuras coleccionables de anime y videojuegos fabricadas por N3XT 3D"
                width="1200"
                height="1200"
                fetchpriority="high"
                class="w-full h-full object-cover"
              />
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Qué hacemos -->
    <section class="py-16 md:py-24">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl">
          <h2 class="text-3xl md:text-4xl font-extrabold tracking-tight text-ink">Qué hacemos</h2>
          <p class="mt-4 text-lg text-muted">
            Piezas únicas o pequeñas series. Tú traes la idea; nosotros elegimos el material y la tecnología que mejor le sirven.
          </p>
        </div>

        <div class="mt-12 grid gap-6 md:grid-cols-3">
          <article class="flex flex-col p-6 bg-surface border border-line rounded-[var(--radius-card)]">
            <div class="flex h-11 w-11 items-center justify-center rounded-[var(--radius-control)] bg-surface-2 text-brand" aria-hidden="true">
              <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
            </div>
            <h3 class="mt-5 text-lg md:text-xl font-extrabold tracking-tight text-ink">Figuras coleccionables</h3>
            <p class="mt-2 text-[0.9375rem] leading-relaxed text-muted flex-1">Personajes y miniaturas en resina de alta resolución, listos para exhibir o pintados a mano.</p>
            <ul class="mt-5 flex flex-wrap gap-2">
              <li v-for="tag in ['Resina SLA', 'Aerografía', 'Detalle 8K']" :key="tag" class="inline-flex items-center h-6 px-2 rounded-md text-xs font-medium bg-surface-2 text-ink">{{ tag }}</li>
            </ul>
          </article>

          <article class="flex flex-col p-6 bg-surface border border-line rounded-[var(--radius-card)]">
            <div class="flex h-11 w-11 items-center justify-center rounded-[var(--radius-control)] bg-surface-2 text-brand" aria-hidden="true">
              <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
            <h3 class="mt-5 text-lg md:text-xl font-extrabold tracking-tight text-ink">Impresión multimaterial</h3>
            <p class="mt-2 text-[0.9375rem] leading-relaxed text-muted flex-1">Piezas en resina y filamento con una flota de impresoras profesionales para cada tipo de trabajo.</p>
            <ul class="mt-5 flex flex-wrap gap-2">
              <li v-for="tag in ['Bambu Lab', 'Elegoo', 'Creality', 'Anycubic']" :key="tag" class="inline-flex items-center h-6 px-2 rounded-md text-xs font-medium bg-surface-2 text-ink">{{ tag }}</li>
            </ul>
          </article>

          <article class="flex flex-col p-6 bg-surface border border-line rounded-[var(--radius-card)]">
            <div class="flex h-11 w-11 items-center justify-center rounded-[var(--radius-control)] bg-surface-2 text-brand" aria-hidden="true">
              <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z"/></svg>
            </div>
            <h3 class="mt-5 text-lg md:text-xl font-extrabold tracking-tight text-ink">Prototipos funcionales</h3>
            <p class="mt-2 text-[0.9375rem] leading-relaxed text-muted flex-1">Carcasas, soportes y repuestos para probar un diseño antes de producirlo.</p>
            <ul class="mt-5 flex flex-wrap gap-2">
              <li v-for="tag in ['Ingeniería', 'Nylon / PETG', 'Pruebas de resistencia']" :key="tag" class="inline-flex items-center h-6 px-2 rounded-md text-xs font-medium bg-surface-2 text-ink">{{ tag }}</li>
            </ul>
          </article>
        </div>
      </div>
    </section>

    <!-- Proceso -->
    <section class="py-16 md:py-24 bg-surface border-y border-line">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl">
          <h2 class="text-3xl md:text-4xl font-extrabold tracking-tight text-ink">Cómo trabajamos</h2>
          <p class="mt-4 text-lg text-muted">De la idea a la pieza en tus manos, en cuatro pasos.</p>
        </div>

        <ol class="mt-12 grid gap-10 lg:grid-cols-4 lg:gap-8">
          <li v-for="(step, i) in processSteps" :key="step.t" class="relative pl-14 lg:pl-0 lg:pt-14">
            <span class="absolute left-0 top-0 flex h-10 w-10 items-center justify-center rounded-full border border-line bg-canvas text-base font-semibold text-ink tabular-nums" aria-hidden="true">{{ i + 1 }}</span>
            <span
              v-if="i < processSteps.length - 1"
              class="absolute left-5 top-12 -bottom-8 w-px bg-line lg:left-12 lg:top-5 lg:bottom-auto lg:-right-6 lg:h-px lg:w-auto"
              aria-hidden="true"
            ></span>
            <h3 class="text-lg md:text-xl font-extrabold tracking-tight text-ink">{{ step.t }}</h3>
            <p class="mt-2 text-[0.9375rem] leading-relaxed text-muted">{{ step.d }}</p>
          </li>
        </ol>

        <div class="mt-12">
          <router-link to="/project/init" class="btn-primary btn-lg">Iniciar un proyecto</router-link>
        </div>
      </div>
    </section>

    <!-- Marcas -->
    <section v-intersection="'brandticker'" class="py-16 min-h-[200px]">
      <transition name="smooth-reveal">
        <BrandTicker v-if="visibleSections['brandticker']" :visible="visibleSections['brandticker']" />
      </transition>
    </section>

    <WaveDivider />

    <!-- Acabado artesanal -->
    <section class="py-16 md:py-24">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid gap-12 lg:grid-cols-2 lg:items-center">
        <div>
          <h2 class="text-3xl md:text-4xl font-extrabold tracking-tight text-ink">Acabado a mano</h2>
          <p class="mt-4 max-w-prose text-lg text-muted">
            Cada pieza pasa por curado, lijado y acabado manual para llegar con superficies limpias y detalles definidos.
          </p>
          <ul class="mt-8 grid grid-cols-2 gap-4 max-w-md">
            <li v-for="step in finishingSteps" :key="step.n" class="flex items-center gap-3 p-4 bg-surface border border-line rounded-[var(--radius-card)]">
              <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[var(--radius-control)] bg-surface-2 text-brand" aria-hidden="true" :innerHTML="sanitizeSVG(step.icon)"></span>
              <span class="text-[0.9375rem] font-medium text-ink">{{ step.n }}</span>
            </li>
          </ul>
          <div class="mt-10">
            <router-link to="/galeria" class="btn-secondary">Ver galería</router-link>
          </div>
        </div>
        <div class="aspect-[4/3] overflow-hidden rounded-[var(--radius-panel)] border border-line bg-surface-2">
          <img
            src="/assets/n3xt_airbrush_process_hq_1778542148509.webp"
            alt="Proceso de aerografía y pintura artesanal en figuras 3D"
            width="1200"
            height="900"
            class="w-full h-full object-cover"
            loading="lazy"
          />
        </div>
      </div>
    </section>

    <!-- Ecosistema -->
    <section v-intersection="'ecosystem'" class="py-16 md:py-24 bg-surface border-y border-line min-h-[600px]">
      <transition name="smooth-reveal">
        <div v-if="visibleSections['ecosystem']" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="max-w-2xl">
            <h2 class="text-3xl md:text-4xl font-extrabold tracking-tight text-ink">El ecosistema N3XT</h2>
            <p class="mt-4 text-lg text-muted">Taller, tienda y comunidad: tres formas de trabajar con nosotros.</p>
          </div>

          <div class="mt-12 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            <article
              v-for="(card, cidx) in webSettings.ecosystem" :key="cidx"
              class="flex flex-col overflow-hidden bg-canvas border border-line rounded-[var(--radius-card)] transition-colors hover:border-brand/40"
            >
              <div class="relative aspect-[4/3] overflow-hidden bg-surface-2">
                <template v-if="card.type === 'image'">
                  <img
                    :src="getImages(card.i)[activeIndices[cidx] ?? 0] || getImages(card.i)[0]"
                    :alt="card.t1 + ' ' + card.t2"
                    class="w-full h-full object-cover"
                    loading="lazy"
                    @error="(e: any) => e.target.style.display='none'"
                  />
                  <span v-if="card.badge" class="absolute top-3 left-3 inline-flex items-center h-6 px-2 rounded-md text-xs font-medium bg-surface text-ink">{{ card.badge }}</span>
                  <div v-if="getImages(card.i).length > 1" class="absolute bottom-3 left-1/2 -translate-x-1/2 flex gap-1.5" aria-hidden="true">
                    <span
                      v-for="(_, dotIdx) in getImages(card.i)" :key="dotIdx"
                      :class="['h-1.5 rounded-full transition-all duration-300', activeIndices[cidx] === dotIdx ? 'w-5 bg-white' : 'w-1.5 bg-white/50']"
                    ></span>
                  </div>
                </template>
                <div v-else-if="card.type === 'icon'" class="absolute inset-0 flex items-center justify-center text-brand technical-grid">
                  <span class="flex h-14 w-14 items-center justify-center rounded-[var(--radius-card)] bg-surface border border-line" :innerHTML="sanitizeSVG(card.i)"></span>
                </div>
              </div>

              <div class="p-5 flex flex-1 flex-col">
                <h3 class="text-lg md:text-xl font-extrabold tracking-tight text-ink">{{ card.t1 }} {{ card.t2 }}</h3>
                <p class="mt-2 text-[0.9375rem] leading-relaxed text-muted line-clamp-2 flex-1">{{ card.d }}</p>
                <router-link :to="ecosystemLink(card)" class="mt-4 self-start text-[0.9375rem] font-medium text-brand hover:underline underline-offset-4">
                  Conocer más
                </router-link>
              </div>
            </article>
          </div>
        </div>
      </transition>
    </section>

    <!-- Trabajos destacados -->
    <section v-if="webSettings.gallery && webSettings.gallery.length > 0" class="py-16 md:py-24">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
          <div class="max-w-2xl">
            <h2 class="text-3xl md:text-4xl font-extrabold tracking-tight text-ink">Trabajos destacados</h2>
            <p class="mt-4 text-lg text-muted">Algunas piezas que han salido del taller.</p>
          </div>
          <router-link to="/galeria" class="text-[0.9375rem] font-medium text-brand hover:underline underline-offset-4">Ver toda la galería</router-link>
        </div>

        <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          <component
            :is="work.title ? 'router-link' : 'div'"
            v-for="(work, idx) in (webSettings.gallery.filter(g => g.featured).length > 0 ? webSettings.gallery.filter(g => g.featured) : webSettings.gallery).slice(0, 6)" :key="idx"
            :to="work.title ? '/galeria/' + encodeURIComponent(work.title) : undefined"
            class="group flex flex-col overflow-hidden bg-surface border border-line rounded-[var(--radius-card)] transition-colors hover:border-brand/40"
          >
            <div class="relative aspect-[4/3] overflow-hidden bg-surface-2">
              <!-- Imagen secundaria (aparece en hover si existe) -->
              <img
                v-if="work.images && work.images.length > 0"
                :src="getOptimizedImage(work.images[0])"
                alt=""
                class="absolute inset-0 z-10 w-full h-full object-cover opacity-0 transition-opacity duration-300 group-hover:opacity-100"
                loading="lazy"
              />
              <img
                v-if="!brokenGalleryImgs[idx]"
                :src="getOptimizedImage(work.image)" :alt="work.title"
                class="w-full h-full object-cover"
                loading="lazy"
                @error="brokenGalleryImgs[idx] = true"
              />
              <div v-if="!work.image || brokenGalleryImgs[idx]" class="absolute inset-0 flex items-center justify-center text-muted" aria-hidden="true">
                <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
              </div>
            </div>
            <div class="p-5 flex flex-1 flex-col">
              <p v-if="work.category" class="text-xs text-muted">{{ work.category }}</p>
              <h3 class="mt-1 text-lg md:text-xl font-extrabold tracking-tight text-ink">{{ work.title }}</h3>
              <p v-if="work.subtitle" class="mt-2 text-[0.9375rem] text-muted line-clamp-2 flex-1">{{ work.subtitle }}</p>
              <span v-if="work.title" class="mt-4 text-[0.9375rem] font-medium text-brand group-hover:underline underline-offset-4">Ver proyecto</span>
            </div>
          </component>
        </div>
      </div>
    </section>

    <!-- Novedades -->
    <WaveDivider v-if="webSettings.news.length > 0 && webSettings.gallery.length > 0" />
    <section v-if="webSettings.news.length > 0" id="news" v-intersection="'news'" class="py-16 md:py-24 min-h-[600px]">
      <transition name="smooth-reveal">
        <div v-if="visibleSections['news']" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
            <div class="max-w-2xl">
              <h2 class="text-3xl md:text-4xl font-extrabold tracking-tight text-ink">Novedades de la impresión 3D en Colombia</h2>
              <p class="mt-4 text-lg text-muted">Lo que está pasando en la manufactura digital del país.</p>
            </div>
            <a href="https://www.3dnatives.com/es/" target="_blank" rel="noopener noreferrer" class="text-[0.9375rem] font-medium text-brand hover:underline underline-offset-4">Más noticias en 3Dnatives</a>
          </div>

          <!-- Esqueleto mientras carga -->
          <div v-if="!isReady" class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <div v-for="i in 4" :key="i" class="overflow-hidden bg-surface border border-line rounded-[var(--radius-card)]">
              <div class="aspect-[4/3] bg-surface-2 animate-pulse"></div>
              <div class="p-5 space-y-3">
                <div class="h-5 w-3/4 rounded bg-surface-2 animate-pulse"></div>
                <div class="h-4 w-full rounded bg-surface-2 animate-pulse"></div>
              </div>
            </div>
          </div>

          <div v-else class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <article
              v-for="item in webSettings.news" :key="item.t"
              class="flex flex-col overflow-hidden bg-surface border border-line rounded-[var(--radius-card)] transition-colors hover:border-brand/40"
            >
              <div class="aspect-[4/3] overflow-hidden bg-surface-2">
                <img
                  :src="item.i"
                  :alt="'Noticia: ' + item.t"
                  class="w-full h-full object-cover"
                  loading="lazy"
                  @error="(e: any) => e.target.style.display='none'"
                />
              </div>
              <div class="p-5 flex flex-1 flex-col">
                <p v-if="item.tag" class="text-xs text-muted">{{ item.tag }}</p>
                <h3 class="mt-1 text-lg md:text-xl font-extrabold tracking-tight text-ink">{{ item.t }}</h3>
                <p class="mt-2 text-[0.9375rem] leading-relaxed text-muted line-clamp-2 flex-1">{{ item.d }}</p>
                <a
                  v-if="isRealUrl(item.url)"
                  :href="item.url" target="_blank" rel="noopener noreferrer"
                  class="mt-4 self-start text-[0.9375rem] font-medium text-brand hover:underline underline-offset-4"
                >Leer nota</a>
              </div>
            </article>
          </div>
        </div>
      </transition>
    </section>

    <!-- Redes sociales -->
    <WaveDivider v-if="webSettings.posts.length > 0 && (webSettings.news.length > 0 || webSettings.gallery.length > 0)" />
    <section v-if="webSettings.posts.length > 0" class="py-16 md:py-24">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
          <div class="max-w-2xl">
            <h2 class="text-3xl md:text-4xl font-extrabold tracking-tight text-ink">En redes</h2>
            <p class="mt-4 text-lg text-muted">Lo último que hemos publicado desde el taller.</p>
          </div>
          <div class="flex flex-wrap gap-3">
            <a v-if="isRealUrl(webSettings.social.tiktok)" :href="webSettings.social.tiktok" target="_blank" rel="noopener noreferrer" class="btn-outlined btn-sm">
              <svg class="w-4 h-4" aria-hidden="true" fill="currentColor" viewBox="0 0 24 24"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-1.13-.32-2.43-.23-3.41.45-.83.53-1.33 1.47-1.38 2.44-.01.52.16 1.03.45 1.46.39.58 1.02.95 1.7 1.02 1.3.16 2.61-.41 3.17-1.58.34-.7.4-1.5.39-2.28-.02-5.91-.01-11.83-.01-17.74z"/></svg>
              TikTok
            </a>
            <a v-if="isRealUrl(webSettings.social.instagram)" :href="webSettings.social.instagram" target="_blank" rel="noopener noreferrer" class="btn-outlined btn-sm">
              <svg class="w-4 h-4" aria-hidden="true" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.058-1.69-.072-4.949-.072zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c.796 0 1.441.645 1.441 1.44s-.645 1.44-1.441 1.44c-.795 0-1.439-.645-1.439-1.44s.644-1.44 1.439-1.44z"/></svg>
              Instagram
            </a>
            <a v-if="isRealUrl(webSettings.social.youtube)" :href="webSettings.social.youtube" target="_blank" rel="noopener noreferrer" class="btn-outlined btn-sm">
              <svg class="w-4 h-4" aria-hidden="true" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
              YouTube
            </a>
          </div>
        </div>

        <!-- Esqueleto mientras carga -->
        <div v-if="!isReady" class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
          <div v-for="i in 4" :key="i" class="overflow-hidden bg-surface border border-line rounded-[var(--radius-card)]">
            <div class="aspect-[4/3] bg-surface-2 animate-pulse"></div>
            <div class="p-5 space-y-3">
              <div class="h-5 w-3/4 rounded bg-surface-2 animate-pulse"></div>
              <div class="h-4 w-full rounded bg-surface-2 animate-pulse"></div>
            </div>
          </div>
        </div>

        <div v-else class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
          <article
            v-for="post in webSettings.posts" :key="post.t"
            class="flex flex-col overflow-hidden bg-surface border border-line rounded-[var(--radius-card)] transition-colors hover:border-brand/40"
          >
            <div class="aspect-[4/3] overflow-hidden bg-surface-2">
              <img :src="post.i" :alt="'Publicación de ' + webSettings.company_name + ': ' + post.t" class="w-full h-full object-cover" loading="lazy" @error="(e: any) => e.target.style.display='none'" />
            </div>
            <div class="p-5 flex flex-1 flex-col">
              <p class="text-xs text-muted">{{ post.tag || webSettings.company_name }}</p>
              <h3 class="mt-1 text-lg md:text-xl font-extrabold tracking-tight text-ink">{{ post.t }}</h3>
              <p class="mt-2 text-[0.9375rem] leading-relaxed text-muted line-clamp-2 flex-1">{{ post.d }}</p>
              <a
                v-if="isRealUrl(post.url)"
                :href="post.url" target="_blank" rel="noopener noreferrer"
                class="mt-4 self-start text-[0.9375rem] font-medium text-brand hover:underline underline-offset-4"
              >Ver publicación</a>
            </div>
          </article>
        </div>
      </div>
    </section>

    <AppFooter />
  </div>
</template>

<style scoped>
/* Revelado de secciones diferidas: fundido simple, sin desplazamiento */
.smooth-reveal-enter-active {
  transition: opacity 0.4s ease;
}
.smooth-reveal-enter-from {
  opacity: 0;
}
</style>
