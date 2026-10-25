<script setup lang="ts">
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue'
import { useHead } from '@unhead/vue'
import gsap from 'gsap'
import { api } from '../services/api'
import AppNavbar from '../components/AppNavbar.vue'
import BrandTicker from '../components/BrandTicker.vue'
import AppFooter from '../components/AppFooter.vue'
import logger from '../utils/logger'
import { usePageMeta } from '../composables/usePageMeta'
import { prefersReducedMotion } from '../composables/useMotion'

// LCP: la imagen del hero se precarga solo en Home (antes se precargaba en todas las rutas)
useHead({ link: [{ rel: 'preload', as: 'image', type: 'image/webp', href: '/assets/hero/n3xt_hero_voronoi.webp', media: '(min-width: 768px)' }, { rel: 'preload', as: 'image', type: 'image/webp', href: '/assets/hero/n3xt_hero_voronoi_1200.webp', media: '(max-width: 767px)' }] })

usePageMeta({
  title: 'N3XT 3D | Impresión 3D en resina y filamento en Colombia',
  description: 'Estudio de impresión 3D en Colombia. Figuras coleccionables, prototipos y piezas a medida en resina y filamento, con acabado a mano.',
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
  // 1px de solape entre franjas para que no se vean líneas finas entre capas
  return `inset(calc(${((i - 1) * step).toFixed(3)}% - 1px) -10% calc(${(100 - i * step).toFixed(3)}% - 1px) -10%)`
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
  const nozzle = nozzleRef.value
  const layerStep = 0.045
  const buildStart = 0.1
  const buildEnd = buildStart + layerStep * WORDMARK_LAYERS

  heroTl = gsap.timeline({ defaults: { ease: 'power3.out' }, onComplete: finishHero })
  heroTl
    .from(root.querySelectorAll('[data-hero-intro]'), { opacity: 0, y: 10, duration: 0.6 }, 0)
    .fromTo(layers, { opacity: 0, y: -10 }, { opacity: 1, y: 0, duration: 0.35, stagger: layerStep }, buildStart)
  if (nozzle) {
    heroTl
      .fromTo(nozzle, { opacity: 0, y: 0 }, { opacity: 1, duration: 0.15, ease: 'none' }, buildStart - 0.05)
      .to(nozzle, { y: () => -(nozzle.parentElement?.offsetHeight ?? 0), duration: buildEnd - buildStart, ease: 'none' }, buildStart)
      .to(nozzle, { opacity: 0, duration: 0.35, ease: 'power2.out' }, buildEnd - 0.05)
  }
  heroTl.from(items, { opacity: 0, y: 20, duration: 0.7, stagger: 0.08 }, buildEnd - 0.15)
  // La esfera aparece desde la oscuridad mientras se construye el wordmark
  if (media) heroTl.fromTo(media, { opacity: 0, scale: 1.06 }, { opacity: 1, scale: 1, duration: 1.8, ease: 'power2.out', clearProps: 'transform,opacity' }, 0)
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

const printLines = [
  { t: 'Figuras coleccionables', d: 'Personajes y miniaturas en resina de alta resolución, listos para exhibir o pintados a mano.', tags: ['Resina SLA', 'Aerografía', 'Detalle 8K'], i: '/assets/img/hero_maker.webp', alt: 'Figura recién impresa en una impresora de resina' },
  { t: 'Impresión multimaterial', d: 'Una granja de impresoras de resina y filamento: elegimos la máquina y el material según lo que tu pieza necesita.', tags: ['Bambu Lab', 'Elegoo', 'Creality', 'Anycubic'], i: '/assets/n3xt_social_bambu_fleet_1778543135730.webp', alt: 'Granja de impresoras 3D de filamento trabajando en paralelo' },
  { t: 'Prototipos funcionales', d: 'Carcasas, engranajes, soportes y repuestos para probar un diseño antes de producirlo en serie.', tags: ['Ingeniería', 'Nylon / PETG', 'Pruebas de resistencia'], i: '/assets/n3xt_social_eco_material_1778543149399.webp', alt: 'Mecanismo de engranajes impreso en filamento verde' },
]

const finishingSteps = [
  { n: 'Curado UV', d: 'La resina termina de endurecer bajo luz ultravioleta: la pieza gana resistencia y pierde el tacto pegajoso.' },
  { n: 'Lijado fino', d: 'Retiramos soportes y marcas de capa a mano, de grano grueso a fino, hasta dejar la superficie lisa.' },
  { n: 'Imprimación', d: 'Una base uniforme que revela los últimos defectos y deja la pieza lista para pintar.' },
  { n: 'Aerografía', d: 'Color, sombras y luces a mano, capa por capa, como en la referencia que nos envíes.' },
]


const webSettings = ref<WebSettings>({
  social: { tiktok: '#', instagram: '#', facebook: '#', whatsapp: '#', youtube: '#' },
  workshop_status: 'Abierto de lunes a sábado',
  // Contenido de ejemplo con imágenes del proyecto: se reemplaza por lo que se
  // configure en el panel (Sitio web). Sin esto, las secciones desaparecían.
  news: [
    { t: 'Filamento hecho con residuos de café', d: 'Materiales compuestos con fibra vegetal y residuos de café: menos plástico virgen y un acabado mate con textura propia.', tag: 'Materiales', i: '/assets/n3xt_news_coffee_filament_1778543014372.webp', url: '' },
    { t: 'La manufactura aditiva llega a las ferias de Bogotá', d: 'Stands de impresión industrial, escáneres y materiales técnicos: la industria nacional apuesta por producir en el país.', tag: 'Eventos', i: '/assets/n3xt_news_colombia_expo_1778543002087.webp', url: '' },
    { t: 'Hackatones maker en Medellín', d: 'Equipos que diseñan, imprimen y prueban prototipos en un fin de semana, con impresoras abiertas a todos.', tag: 'Comunidad', i: '/assets/n3xt_news_maker_hackathon_1778543041136.webp', url: '' },
    { t: 'Modelos anatómicos para planear cirugías', d: 'Laboratorios médicos imprimen réplicas a partir de tomografías para estudiar cada caso antes de operar.', tag: 'Salud', i: '/assets/n3xt_news_medical_3d_1778543028268.webp', url: '' },
  ],
  gallery: [],
  posts: [
    { t: 'Sung Jinwoo, de Solo Leveling', d: 'Impresa en resina 8K y pintada a mano con aerógrafo, de la base al filo de la daga.', l: '', i: '/assets/img/tiktok1.webp', tag: 'TikTok', url: '' },
    { t: 'Blaster de Overwatch a escala real', d: 'Prop para cosplay en piezas encajables, con canal interno para luces LED.', l: '', i: '/assets/img/tiktok2.webp', tag: 'TikTok', url: '' },
    { t: 'La granja a toda máquina', d: 'Turno de noche: series cortas de piezas funcionales saliendo en paralelo.', l: '', i: '/assets/n3xt_social_bambu_fleet_1778543135730.webp', tag: 'Taller', url: '' },
    { t: 'Engranajes en filamento reciclado', d: 'Prueba de resistencia de un mecanismo impreso en material de origen reciclado.', l: '', i: '/assets/n3xt_social_eco_material_1778543149399.webp', tag: 'Prototipo', url: '' },
  ],
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
      i: '/assets/n3xt_social_feed_preview_1778542169336.webp',
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

// Trabajos de ejemplo (imágenes del proyecto) mientras el panel no tenga galería.
// `sample` evita enlazar a /galeria/:titulo, que solo existe para trabajos reales.
const sampleGallery: (GalleryItem & { sample?: boolean })[] = [
  { image: '/assets/n3xt_social_solo_leveling_1778543107506.webp', title: 'Sung Jinwoo pintado a mano', category: 'Figura coleccionable', technology: 'Resina 8K', sample: true },
  { image: '/assets/img/gallery2.webp', title: 'Maqueta arquitectónica', category: 'Arquitectura', technology: 'Resina', sample: true },
  { image: '/assets/img/gallery3.webp', title: 'Celosía flexible', category: 'Prototipo', technology: 'TPU', sample: true },
  { image: '/assets/n3xt_social_overwatch_prop_1778543121526.webp', title: 'Prop de cosplay', category: 'Cosplay', technology: 'PLA + aerografía', sample: true },
  { image: '/assets/img/gallery1.webp', title: 'Engranaje de repuesto', category: 'Repuesto', technology: 'PETG', sample: true },
]
const featuredWorks = computed(() => {
  const g = webSettings.value.gallery.length ? webSettings.value.gallery : sampleGallery
  const featured = g.filter(w => w.featured)
  return (featured.length ? featured : g).slice(0, 5) as (GalleryItem & { sample?: boolean })[]
})
const workLink = (w: GalleryItem & { sample?: boolean }) => (w.title && !w.sample ? '/galeria/' + encodeURIComponent(w.title) : '/galeria')

// Imagen de respaldo para tarjetas del ecosistema configuradas con un ícono
const ecosystemFallback: Record<string, string> = {
  SHOP: '/assets/n3xt_industrial_maker_figures_1778542131405.webp',
  SPACE: '/assets/n3xt_news_maker_hackathon_1778543041136.webp',
}
const ecosystemImage = (card: any, idx: number): string => {
  if (card.type === 'image') return getImages(card.i)[activeIndices.value[idx] ?? 0] || getImages(card.i)[0] || ''
  return ecosystemFallback[String(card.t2 || '').toUpperCase()] || '/assets/img/hero_maker.webp'
}

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
    // Sin autoavance con reduced-motion; y solo con la pestaña visible
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return
    carouselInterval = setInterval(() => {
        if (document.hidden) return
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
          : sampleGallery,
        company_name: data.company?.name || webSettings.value.company_name
      }
    }
  } catch (err) {
    logger.error('Error fetching settings:', err)
  } finally {
    isReady.value = true
  }
}

onMounted(() => {
  fetchSettings()
})
</script>

<template>
  <div class="min-h-screen bg-canvas text-ink overflow-x-clip">
    <AppNavbar active-tab="home" subtext="Impresión 3D en Colombia" />

    <main id="main">
    <!-- Hero: wordmark gigante sobre una pieza impresa (esfera voronoi) -->
    <section ref="heroRef" class="hero relative isolate overflow-hidden">
      <picture data-hero-media class="hero-media absolute inset-0 -z-10" aria-hidden="true">
        <source media="(max-width: 767px)" srcset="/assets/hero/n3xt_hero_voronoi_1200.webp" />
        <img src="/assets/hero/n3xt_hero_voronoi.webp" alt="" width="2400" height="1500" fetchpriority="high" decoding="async" class="h-full w-full object-cover" />
      </picture>
      <div class="hero-scrim absolute inset-0 -z-10" aria-hidden="true"></div>

      <div class="mx-auto flex min-h-[calc(100svh-4rem)] max-w-7xl flex-col items-center justify-center px-4 py-16 text-center sm:px-6 lg:px-8">
        <p data-hero-intro class="hero-pill">
          <span class="hero-pill-dot" aria-hidden="true"></span>
          Estudio de manufactura digital
        </p>

        <h1 class="hero-title mt-8 md:mt-10">
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
          <span class="sr-only">: estudio de impresión 3D en Colombia</span>
        </h1>

        <p data-hero-item class="hero-sub mt-8 md:mt-10">
          Especialistas en <span class="text-brand font-semibold">fabricación digital</span>: miniaturas, figuras y piezas de colección
          con precisión industrial y el <span class="text-brand font-semibold italic">espíritu de Colombia</span>.
        </p>

        <div data-hero-item class="mt-10 flex w-full flex-col justify-center gap-3 sm:w-auto sm:flex-row">
          <router-link to="/quote" class="btn-primary btn-lg">
            Cotizar mi pieza
            <svg class="btn-arrow w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14m-6-6 6 6-6 6"/></svg>
          </router-link>
          <router-link to="/catalog" class="btn-secondary btn-lg">Ver catálogo</router-link>
        </div>

        <dl data-hero-item class="mt-14 grid w-full max-w-xl grid-cols-3 gap-4 sm:gap-8">
          <div v-for="stat in highlights" :key="stat.label" class="flex flex-col-reverse items-center">
            <dt class="mt-1 text-sm text-[#a9b8ae]">{{ stat.label }}</dt>
            <dd class="text-3xl sm:text-4xl font-black tracking-tight text-white tabular-nums [font-stretch:118%]">{{ stat.val }}</dd>
          </div>
        </dl>
      </div>
    </section>

    <!-- Lo que imprimimos: tres líneas de trabajo, cada una con su foto -->
    <section class="py-24 md:py-36">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div v-reveal class="grid gap-6 lg:grid-cols-12 lg:items-end">
          <h2 class="section-title lg:col-span-7">Lo que <span class="section-title-accent">imprimimos</span></h2>
          <p class="section-lead lg:col-span-5">Piezas únicas o pequeñas series. Tú traes la idea; nosotros elegimos el material y la tecnología que mejor le sirven.</p>
        </div>

        <div class="mt-16 space-y-20 md:mt-24 md:space-y-28">
          <article
            v-for="(line, idx) in printLines" :key="line.t"
            v-reveal.stagger
            class="grid gap-8 md:grid-cols-12 md:items-center md:gap-12"
          >
            <div class="overflow-hidden rounded-[var(--radius-panel)] bg-surface-2 md:col-span-7" :class="idx % 2 ? 'md:order-2' : ''">
              <img :src="line.i" :alt="line.alt" width="1024" height="1024" loading="lazy" decoding="async" class="aspect-[4/3] w-full object-cover transition-transform duration-700 hover:scale-[1.03]" />
            </div>
            <div class="md:col-span-5">
              <h3 class="text-3xl md:text-5xl font-black uppercase tracking-tight leading-[0.95] [font-stretch:115%]">{{ line.t }}</h3>
              <p class="mt-5 text-lg leading-relaxed text-ink/80">{{ line.d }}</p>
              <p class="mt-6 text-base font-semibold text-brand">{{ line.tags.join(' · ') }}</p>
            </div>
          </article>
        </div>
      </div>
    </section>

    <!-- Cómo trabajamos: cada paso suma una capa -->
    <section class="bg-surface py-24 md:py-36">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div v-reveal class="grid gap-6 lg:grid-cols-12 lg:items-end">
          <h2 class="section-title lg:col-span-7">Cómo <span class="section-title-accent">trabajamos</span></h2>
          <p class="section-lead lg:col-span-5">De la idea a la pieza en tus manos. Como en la impresora, cada paso suma una capa.</p>
        </div>

        <ol v-reveal.stagger class="mt-16 grid gap-12 sm:grid-cols-2 lg:mt-24 lg:grid-cols-4 lg:gap-8">
          <li v-for="(step, i) in processSteps" :key="step.t" class="flex flex-col">
            <span class="layer-stack" aria-hidden="true">
              <span v-for="n in i + 1" :key="n" :style="{ width: (100 - (n - 1) * 14) + '%' }"></span>
            </span>
            <h3 class="mt-8 text-2xl md:text-3xl font-black uppercase tracking-tight leading-none [font-stretch:115%]">{{ step.t }}</h3>
            <p class="mt-4 text-base leading-relaxed text-ink/80">{{ step.d }}</p>
          </li>
        </ol>

        <div class="mt-16">
          <router-link to="/project/init" class="btn-primary btn-lg">Iniciar un proyecto</router-link>
        </div>
      </div>
    </section>

    <!-- Marcas -->
    <section v-intersection="'brandticker'" class="py-16 min-h-[200px]" aria-label="Marcas y universos que imprimimos">
      <transition name="smooth-reveal">
        <BrandTicker v-if="visibleSections['brandticker']" :visible="visibleSections['brandticker']" />
      </transition>
    </section>

    <!-- Acabado a mano -->
    <section class="py-24 md:py-36">
      <div class="mx-auto grid max-w-7xl gap-12 px-4 sm:px-6 lg:grid-cols-12 lg:items-center lg:gap-16 lg:px-8">
        <div class="overflow-hidden rounded-[var(--radius-panel)] bg-surface-2 lg:col-span-6">
          <img
            src="/assets/n3xt_airbrush_process_hq_1778542148509.webp"
            alt="Pintor dando acabado con aerógrafo a una figura impresa en 3D"
            width="1024" height="1024" loading="lazy" decoding="async"
            class="aspect-square w-full object-cover"
          />
        </div>
        <div class="lg:col-span-6">
          <h2 v-reveal class="section-title">Acabado <span class="section-title-accent">a mano</span></h2>
          <p class="section-lead mt-6">Cada pieza pasa por cuatro manos de taller antes de salir.</p>
          <dl v-reveal.stagger class="mt-10 space-y-8">
            <div v-for="step in finishingSteps" :key="step.n">
              <dt class="text-xl md:text-2xl font-black uppercase tracking-tight [font-stretch:115%]">{{ step.n }}</dt>
              <dd class="mt-2 text-base leading-relaxed text-ink/80">{{ step.d }}</dd>
            </div>
          </dl>
          <div class="mt-12">
            <router-link to="/galeria" class="btn-secondary btn-lg">Ver galería</router-link>
          </div>
        </div>
      </div>
    </section>

    <!-- Ecosistema -->
    <section v-intersection="'ecosystem'" class="bg-surface py-24 md:py-36 min-h-[600px]">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div v-reveal class="grid gap-6 lg:grid-cols-12 lg:items-end">
          <h2 class="section-title lg:col-span-7">Ecosistema <span class="section-title-accent">N3XT</span></h2>
          <p class="section-lead lg:col-span-5">Taller, tienda y comunidad: tres formas de trabajar con nosotros.</p>
        </div>

        <div v-if="visibleSections['ecosystem']" v-reveal.stagger class="mt-16 grid gap-6 md:grid-cols-2 lg:grid-cols-3 lg:grid-rows-2">
          <router-link
            v-for="(card, cidx) in webSettings.ecosystem" :key="cidx"
            :to="ecosystemLink(card)"
            class="eco-card group relative flex flex-col justify-end overflow-hidden rounded-[var(--radius-panel)] bg-canvas p-6 md:p-8"
            :class="cidx === 0 ? 'min-h-[26rem] md:col-span-2 lg:col-span-2 lg:row-span-2 lg:min-h-[40rem]' : 'min-h-[20rem]'"
          >
            <img
              :src="ecosystemImage(card, cidx)" alt=""
              width="1024" height="1024" loading="lazy" decoding="async"
              class="absolute inset-0 -z-0 h-full w-full object-cover transition-transform duration-700 group-hover:scale-[1.04]"
              @error="(e: any) => e.target.style.display='none'"
            />
            <span class="eco-scrim absolute inset-0" aria-hidden="true"></span>
            <span class="relative">
              <span class="block text-4xl md:text-5xl font-black uppercase tracking-tight leading-none text-white [font-stretch:118%]">
                {{ card.t1 }} <span class="text-[#3fcf72]">{{ card.t2 }}</span>
              </span>
              <span class="mt-4 block max-w-md text-base leading-relaxed text-white/85">{{ card.d }}</span>
              <span class="mt-6 inline-flex items-center gap-2 text-base font-semibold text-white">
                Conocer {{ card.t1 }} {{ card.t2 }}
                <svg class="btn-arrow w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14m-6-6 6 6-6 6"/></svg>
              </span>
            </span>
          </router-link>
        </div>
      </div>
    </section>

    <!-- Trabajos destacados: mosaico con una pieza protagonista -->
    <section class="py-24 md:py-36">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div v-reveal class="flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
          <h2 class="section-title">Trabajos <span class="section-title-accent">destacados</span></h2>
          <router-link to="/galeria" class="btn-secondary self-start md:self-auto">Ver toda la galería</router-link>
        </div>

        <div v-reveal.stagger class="mt-16 grid auto-rows-[15rem] gap-4 sm:grid-cols-2 md:auto-rows-[17rem] lg:grid-cols-4">
          <router-link
            v-for="(work, idx) in featuredWorks" :key="work.title + idx"
            :to="workLink(work)"
            class="work-tile group relative overflow-hidden rounded-[var(--radius-card)] bg-surface-2"
            :class="idx === 0 ? 'sm:col-span-2 sm:row-span-2' : ''"
          >
            <img
              v-if="!brokenGalleryImgs[idx]"
              :src="getOptimizedImage(work.image, idx === 0 ? 1200 : 600)" :alt="work.title"
              loading="lazy" decoding="async"
              class="absolute inset-0 h-full w-full object-cover transition-transform duration-700 group-hover:scale-[1.04]"
              @error="brokenGalleryImgs[idx] = true"
            />
            <span class="eco-scrim absolute inset-0" aria-hidden="true"></span>
            <span class="absolute inset-x-0 bottom-0 p-5">
              <span class="block text-sm font-semibold text-[#3fcf72]">{{ [work.category, work.technology].filter(Boolean).join(' · ') }}</span>
              <span class="mt-1 block font-black uppercase tracking-tight leading-none text-white [font-stretch:115%]" :class="idx === 0 ? 'text-3xl md:text-4xl' : 'text-xl'">{{ work.title }}</span>
            </span>
          </router-link>
        </div>
      </div>
    </section>

    <!-- Novedades 3D Colombia: una nota grande y tres en lista -->
    <section id="news" class="bg-surface py-24 md:py-36">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div v-reveal class="flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
          <h2 class="section-title">Novedades <span class="section-title-accent">3D Colombia</span></h2>
          <a href="https://www.3dnatives.com/es/" target="_blank" rel="noopener noreferrer" class="btn-secondary self-start md:self-auto">Más noticias en 3Dnatives</a>
        </div>

        <div v-if="webSettings.news.length" class="mt-16 grid gap-10 lg:grid-cols-12 lg:gap-12">
          <article v-reveal class="lg:col-span-7">
            <div class="overflow-hidden rounded-[var(--radius-panel)] bg-surface-2">
              <img :src="webSettings.news[0].i" :alt="webSettings.news[0].t" width="1024" height="1024" loading="lazy" decoding="async" class="aspect-[16/10] w-full object-cover" @error="(e: any) => e.target.style.display='none'" />
            </div>
            <p v-if="webSettings.news[0].tag" class="mt-6 text-base font-semibold text-brand">{{ webSettings.news[0].tag }}</p>
            <h3 class="mt-2 text-3xl md:text-4xl font-black tracking-tight leading-tight">{{ webSettings.news[0].t }}</h3>
            <p class="mt-4 max-w-2xl text-lg leading-relaxed text-ink/80">{{ webSettings.news[0].d }}</p>
            <a v-if="isRealUrl(webSettings.news[0].url)" :href="webSettings.news[0].url" target="_blank" rel="noopener noreferrer" class="mt-6 inline-flex font-semibold text-brand hover:underline underline-offset-4">Leer nota</a>
          </article>

          <ul v-reveal.stagger class="space-y-8 lg:col-span-5">
            <li v-for="item in webSettings.news.slice(1, 4)" :key="item.t" class="grid grid-cols-[7rem_1fr] gap-5 sm:grid-cols-[9rem_1fr]">
              <div class="overflow-hidden rounded-[var(--radius-card)] bg-surface-2">
                <img :src="item.i" alt="" width="1024" height="1024" loading="lazy" decoding="async" class="aspect-square w-full object-cover" @error="(e: any) => e.target.style.display='none'" />
              </div>
              <div>
                <p v-if="item.tag" class="text-sm font-semibold text-brand">{{ item.tag }}</p>
                <h3 class="mt-1 text-xl font-black tracking-tight leading-snug">{{ item.t }}</h3>
                <p class="mt-2 text-base leading-relaxed text-ink/80 line-clamp-3">{{ item.d }}</p>
                <a v-if="isRealUrl(item.url)" :href="item.url" target="_blank" rel="noopener noreferrer" class="mt-2 inline-flex font-semibold text-brand hover:underline underline-offset-4">Leer nota</a>
              </div>
            </li>
          </ul>
        </div>
      </div>
    </section>

    <!-- Novedades en tiempo real (redes) -->
    <section class="py-24 md:py-36">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div v-reveal class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
          <h2 class="section-title">Novedades <span class="section-title-accent">en tiempo real</span></h2>
          <div class="flex flex-wrap gap-3">
            <a v-if="isRealUrl(webSettings.social.tiktok)" :href="webSettings.social.tiktok" target="_blank" rel="noopener noreferrer" class="btn-secondary btn-sm">
              <svg class="w-4 h-4" aria-hidden="true" fill="currentColor" viewBox="0 0 24 24"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-1.13-.32-2.43-.23-3.41.45-.83.53-1.33 1.47-1.38 2.44-.01.52.16 1.03.45 1.46.39.58 1.02.95 1.7 1.02 1.3.16 2.61-.41 3.17-1.58.34-.7.4-1.5.39-2.28-.02-5.91-.01-11.83-.01-17.74z"/></svg>
              TikTok
            </a>
            <a v-if="isRealUrl(webSettings.social.instagram)" :href="webSettings.social.instagram" target="_blank" rel="noopener noreferrer" class="btn-secondary btn-sm">
              <svg class="w-4 h-4" aria-hidden="true" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.058-1.69-.072-4.949-.072zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c.796 0 1.441.645 1.441 1.44s-.645 1.44-1.441 1.44c-.795 0-1.439-.645-1.439-1.44s.644-1.44 1.439-1.44z"/></svg>
              Instagram
            </a>
            <a v-if="isRealUrl(webSettings.social.youtube)" :href="webSettings.social.youtube" target="_blank" rel="noopener noreferrer" class="btn-secondary btn-sm">
              <svg class="w-4 h-4" aria-hidden="true" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
              YouTube
            </a>
          </div>
        </div>

        <div v-if="webSettings.posts.length" v-reveal.stagger class="mt-16 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
          <article v-for="post in webSettings.posts.slice(0, 4)" :key="post.t" class="group flex flex-col">
            <div class="relative overflow-hidden rounded-[var(--radius-card)] bg-surface-2">
              <img :src="post.i" :alt="post.t" width="1024" height="1024" loading="lazy" decoding="async" class="aspect-[4/5] w-full object-cover transition-transform duration-700 group-hover:scale-[1.04]" @error="(e: any) => e.target.style.display='none'" />
              <span class="absolute left-3 top-3 rounded-full bg-canvas px-3 py-1 text-sm font-semibold text-ink">{{ post.tag || webSettings.company_name }}</span>
            </div>
            <h3 class="mt-5 text-xl font-black tracking-tight leading-snug">{{ post.t }}</h3>
            <p class="mt-2 text-base leading-relaxed text-ink/80 line-clamp-3">{{ post.d }}</p>
            <a v-if="isRealUrl(post.url)" :href="post.url" target="_blank" rel="noopener noreferrer" class="mt-3 inline-flex font-semibold text-brand hover:underline underline-offset-4">Ver publicación</a>
          </article>
        </div>
      </div>
    </section>

    </main>

    <AppFooter />
  </div>
</template>

<style scoped>
/* ─── Hero ─── */
.hero-media img {
  object-position: 50% 55%;
}
/* Scrim neutro para que el texto se lea sobre la esfera (único degradado permitido) */
.hero-scrim {
  background:
    radial-gradient(ellipse 60% 50% at 50% 48%, rgba(14, 21, 18, 0.55), rgba(14, 21, 18, 0) 70%),
    linear-gradient(to bottom, rgba(14, 21, 18, 0.35), rgba(14, 21, 18, 0) 30%, rgba(14, 21, 18, 0) 60%, var(--bg-main));
}
:global(:root:not(.dark)) .hero-scrim {
  background:
    radial-gradient(ellipse 60% 50% at 50% 48%, rgba(14, 21, 18, 0.55), rgba(14, 21, 18, 0) 70%),
    linear-gradient(to bottom, rgba(14, 21, 18, 0.35), rgba(14, 21, 18, 0) 30%, rgba(14, 21, 18, 0) 60%, #0e1512);
}

.hero-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.625rem;
  padding: 0.5rem 1.125rem;
  border-radius: 999px;
  background: rgba(8, 135, 43, 0.16);
  color: #8ee8ad;
  font-size: 0.875rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}
.hero-pill-dot {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: 999px;
  background: #3fcf72;
  animation: pill-pulse 2.4s var(--ease-in-out-soft) infinite;
}
@keyframes pill-pulse {
  0%, 100% { opacity: 1; transform: scale(1); }
  50% { opacity: 0.45; transform: scale(0.8); }
}

/* Wordmark: N3XT en blanco, 3D en verde itálico con brillo */
.hero-title,
.wordmark {
  font-size: clamp(4.25rem, 16vw, 15rem);
}
.wordmark {
  font-family: var(--font-display);
  font-weight: 900;
  font-stretch: 125%;
  line-height: 0.85;
  letter-spacing: -0.045em;
  text-transform: uppercase;
  color: #ffffff;
  white-space: nowrap;
}
.wordmark-accent {
  font-style: italic;
  color: #3fcf72;
  background: linear-gradient(100deg, #5ee59a 0%, #3fcf72 35%, #08872b 70%, #3fcf72 100%);
  -webkit-background-clip: text;
  background-clip: text;
  -webkit-text-fill-color: transparent;
  padding-right: 0.06em; /* la itálica no se corta */
}
/* Brillo solo en el wordmark terminado: en las capas recortadas dibujaba un rectángulo */
.wordmark:not([data-wm-layer]) .wordmark-accent {
  filter: drop-shadow(0 0 28px rgba(63, 207, 114, 0.45));
}
/* Boquilla: línea de luz que sube mientras se construyen las capas */
.wm-nozzle {
  position: absolute;
  left: -2%;
  right: -2%;
  bottom: 0;
  height: 3px;
  border-radius: 3px;
  background: #3fcf72;
  box-shadow: 0 0 18px 2px rgba(63, 207, 114, 0.7);
  opacity: 0;
}

.hero-sub {
  max-width: 44ch;
  font-size: var(--text-lead);
  line-height: 1.5;
  color: #d5ded8;
  text-wrap: balance;
}

/* ─── Cómo trabajamos: capas que crecen ─── */
.layer-stack {
  display: flex;
  flex-direction: column-reverse;
  gap: 5px;
  height: 4.75rem;
  justify-content: flex-start;
}
.layer-stack > span {
  display: block;
  height: 14px;
  border-radius: 3px;
  background: var(--color-primary);
}
.layer-stack > span:last-child {
  background: #3fcf72;
}

/* ─── Tarjetas con foto ─── */
.eco-scrim {
  background: linear-gradient(to top, rgba(10, 15, 13, 0.92), rgba(10, 15, 13, 0.35) 55%, rgba(10, 15, 13, 0) 80%);
}

/* Revelado de secciones diferidas: fundido simple, sin desplazamiento */
.smooth-reveal-enter-active {
  transition: opacity 0.4s ease;
}
.smooth-reveal-enter-from {
  opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
  .hero-pill-dot { animation: none; }
}
</style>
