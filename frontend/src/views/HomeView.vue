<script setup lang="ts">
import { ref, reactive, onMounted, onUnmounted, watch, nextTick } from 'vue'
import gsap from 'gsap'
import { api } from '../services/api'
import { sanitizeSVG } from '../utils/sanitize'
import AppNavbar from '../components/AppNavbar.vue'
import BrandTicker from '../components/BrandTicker.vue'
import WaveDivider from '../components/WaveDivider.vue'
import LegalModal from '../components/LegalModal.vue'
import logger from '../utils/logger'
import { useSplitTitle } from '../composables/useSplitTitle'
import { useSplitButton } from '../composables/useSplitButton'
import { usePageMeta } from '../composables/usePageMeta'

useSplitTitle()
useSplitButton()

usePageMeta({
 title: 'N3XT 3D | Manufactura Digital y Coleccionables de Alta Precisión',
 description: 'Especialistas en fabricación digital en Colombia. Miniaturas, figuras y piezas de colección creadas con precisión industrial e inspiradas en universos icónicos.',
 image: '/assets/n3xt_og_home.png',
})


// Hero title animation (no SplitText — preserves gradient)
const heroTitleRef = ref<HTMLElement | null>(null)

onMounted(() => {
 if (heroTitleRef.value) {
 gsap.from(heroTitleRef.value, {
 opacity: 0,
 y: 50,
 duration: 0.8,
 ease: 'power3.out',
 })
 }
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
 news: { t: string; d?: string; st?: string; tag?: string; category?: string; i?: string; image?: string; url?: string; slug?: string }[]
 gallery: GalleryItem[]
 posts: { t: string; d: string; l: string; c?: string; s?: string; i: string; tag: string; url: string }[]
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
const vIntersection = {
 mounted(el: HTMLElement, binding: { value: string }) {
 const observer = new IntersectionObserver((entries) => {
 entries.forEach(entry => {
 if (entry.isIntersecting) {
 visibleSections[binding.value] = true
 // Una vez visible, dejamos de observar este disparador
 observer.unobserve(el)
 }
 })
 }, { threshold: 0.1 })
 observer.observe(el)
 }
}


const webSettings = ref<WebSettings>({
 social: { tiktok: '#', instagram: '#', facebook: '#', whatsapp: '#', youtube: '#' },
 workshop_status: 'OPERATIVO 24/7',
 news: [
 {
 t: 'PRECISIÓN 8K: EL FUTURO DE LA RESINA',
 d: 'Exploramos cómo la tecnología de alta resolución está redefiniendo los estándares de detalle en miniaturas y joyería técnica.',
 tag: 'TECNOLOGÍA',
 i: 'https://images.unsplash.com/photo-1631033031102-f855d4872494?auto=format&fit=crop&q=80&w=800',
 url: '#'
 },
 {
 t: 'REVOLUCIÓN FDM CON BAMBU LAB',
 d: 'Nuestra flota de alta velocidad nos permite entregar piezas funcionales en tiempo récord sin comprometer la resistencia estructural.',
 tag: 'INDUSTRIAL',
 i: 'https://images.unsplash.com/photo-1638202993928-7267aad84c31?auto=format&fit=crop&q=80&w=800',
 url: '#'
 },
 {
 t: 'EL ARTE DEL ACABADO MANUAL',
 d: 'Cada pieza en N3XT pasa por un riguroso proceso de curado y lijado artesanal para garantizar superficies listas para pintura.',
 tag: 'MAKER',
 i: 'https://images.unsplash.com/photo-1581092160607-ee22621dd758?auto=format&fit=crop&q=80&w=800',
 url: '#'
 },
 {
 t: 'N3XT 3D: MANUFACTURA NACIONAL',
 d: 'Estamos construyendo el centro de impresión 3D más avanzado de Colombia para apoyar a creativos y empresas locales.',
 tag: 'EVENTO',
 i: 'https://images.unsplash.com/photo-1531297484001-80022131f5a1?auto=format&fit=crop&q=80&w=800',
 url: '#'
 }
 ],
 gallery: [],
 posts: [
 {
 t: 'DRAGONES EN RESINA 8K',
 d: 'Nivel de detalle extremo para los coleccionistas más exigentes. Cada escama cuenta.',
 l: '1.2K',
 i: 'https://images.unsplash.com/photo-1560972550-aba3456b5564?auto=format&fit=crop&q=80&w=600',
 tag: 'RESINA',
 url: '#'
 },
 {
 t: 'PROTOTIPADO INDUSTRIAL',
 d: 'Carcasas funcionales impresas en PETG para pruebas de ingeniería de alto impacto.',
 l: '850',
 i: 'https://images.unsplash.com/photo-1581092162384-8987c1d64718?auto=format&fit=crop&q=80&w=600',
 tag: 'FDM',
 url: '#'
 },
 {
 t: 'ARTE DIGITAL COLECTIVO',
 d: 'Colaboración con artistas locales para traer sus diseños al mundo físico.',
 l: '2.1K',
 i: 'https://images.unsplash.com/photo-1614613535308-eb5fbd3d2c17?auto=format&fit=crop&q=80&w=600',
 tag: 'MAKER',
 url: '#'
 },
 {
 t: 'TALLER N3XT EN ACCIÓN',
 d: 'Descubre el proceso de post-procesado que hace que nuestras piezas sean únicas.',
 l: '3.4K',
 i: 'https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?auto=format&fit=crop&q=80&w=600',
 tag: 'DETRÁS DE CÁMARAS',
 url: '#'
 }
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
 t2: 'LAB',
 d: 'Nuestro núcleo de innovación. Donde transformamos conceptos complejos en prototipos de alta precisión usando tecnología Bambu Lab.',
 i: '/assets/n3xt_lab_render_hq_1778542148509.png',
 stats: [
 { val: '8K', label: 'Resolución' },
 { val: 'SLA', label: 'Tecnología' },
 { val: '.025mm', label: 'Precisión' }
 ]
 },
 {
 type: 'icon',
 t1: 'N3XT',
 t2: 'SHOP',
 d: 'Accede a nuestro catálogo curado de filamentos técnicos, accesorios y piezas de colección listas para entrega.',
 i: '<svg class=\"w-8 h-8\" fill=\"none\" viewBox=\"0 0 24 24\" stroke=\"currentColor\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"1.5\" d=\"M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z\"/></svg>',
 stats: [
 { val: '+500', label: 'Productos' },
 { val: '+200 SKU', label: 'Disponibles' },
 { val: 'Envío 24H', label: 'Express' }
 ]
 },
 {
 type: 'icon',
 t1: 'MAKER',
 t2: 'SPACE',
 d: 'Únete a la red de creadores más grande de Colombia. Talleres, soporte técnico y proyectos colaborativos.',
 i: '<svg class=\"w-8 h-8\" fill=\"none\" viewBox=\"0 0 24 24\" stroke=\"currentColor\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"1.5\" d=\"M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z\"/></svg>',
 stats: [
 { val: '+200', label: 'Miembros' },
 { val: '24/7', label: 'Soporte' },
 { val: 'N3XT', label: 'Comunidad' }
 ]
 }
 ]
})

const companySettings = ref({
 phone: '',
 email: '',
 address: ''
})

const isReady = ref(false)

const normalizeTags = (tags: any): string[] => Array.isArray(tags) ? tags : (typeof tags === 'string' ? tags.split(',').map((t: string) => t.trim()).filter(Boolean) : [])
const brokenGalleryImgs = ref<Record<number, boolean>>({})

const getOptimizedImage = (url: string, width = 600): string => {
 if (!url) return ''
 if (!webSettings.value.cloudinary_name || !url.includes('cloudinary.com')) return url
 return url.replace('/upload/', `/upload/f_auto,q_auto,w_${width}/`)
}

const legalModalRef = ref<any>(null)

const openLegal = (type: any) => {
 const title = type === 'privacy' ? 'Política de Privacidad' : 'Términos y Condiciones'
 const content = type === 'privacy' 
 ? (webSettings.value.privacy_policy || 'Contenido en redacción...')
 : (webSettings.value.terms_conditions || 'Contenido en redacción...')
 if (legalModalRef.value) {
 legalModalRef.value.setContent(title, content)
 }
}

// Lógica de Carrusel Automático para Tarjetas de Ecosistema
const activeIndices = ref([0, 0, 0])
const getImages = (iString?: string): string[] => {
  if (!iString) return []
  return iString.split(',').map((s: string) => s.trim()).filter(Boolean)
}

let carouselInterval: ReturnType<typeof setInterval> | null = null

onMounted(() => {
 carouselInterval = setInterval(() => {
 webSettings.value.ecosystem.forEach((card, idx) => {
 if (card.type === 'image') {
 const imgs = getImages(card.i)
 if (imgs.length > 1) {
 activeIndices.value[idx] = (activeIndices.value[idx] + 1) % imgs.length
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
 // Si la DB está vacía (como en instalaciones nuevas), conservamos los assets generados.
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

 if (data.company) {
 companySettings.value = {
 ...companySettings.value,
 ...data.company
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

 // Sistema de Revelación por Scroll (AOS Native) Bidireccional Optimizado
 const observer = new IntersectionObserver((entries) => {
 entries.forEach(entry => {
 if (entry.isIntersecting) {
 // Añadimos clase visible y forzamos un pequeño retraso si es necesario
 entry.target.classList.add('visible')
 } else {
 // Desvanecimiento de salida (Out-fading)
 entry.target.classList.remove('visible')
 }
 })
 }, { 
 threshold: 0.05, // Se activa con el 5% de visibilidad
 rootMargin: '0px 0px -20px 0px' // Margen mínimo para que aparezca casi al tocar el borde inferior
 })

 // Observamos SOLO cuando los datos reales estén en el DOM
 watch(isReady, (ready) => {
 if (ready) {
 nextTick(() => {
 document.querySelectorAll('.reveal').forEach(el => observer.observe(el))
 })
 }
 })
})
</script>

<template>
 <div class="min-h-screen bg-[#f8fafc] dark:bg-[#0a0f14] text-[#ffffff] dark:text-white overflow-x-hidden selection:bg-[#08872b]/20 transition-colors duration-500">
 <AppNavbar active-tab="home" subtext="Centro de Precisión Industrial" />

<main id="main-content">

 <!-- Partículas ambientales -->
 <!-- Hero Section -->
 <section class="relative pt-20 pb-32 px-6 overflow-hidden">
  <div class="absolute inset-0 technical-grid opacity-20 dark:opacity-10 pointer-events-none"></div>
 <div class="absolute -top-24 -left-24 w-96 h-96 bg-[#08872b]/10 rounded-[60px] blur-[120px] pointer-events-none"></div>
 <div class="absolute top-1/2 -right-24 w-96 h-96 bg-[#08872b]/5 rounded-[60px] blur-[120px] pointer-events-none"></div>

 <div class="max-w-7xl mx-auto flex flex-col items-center text-center relative z-10">
 <div class="inline-flex items-center gap-3 px-4 py-2 bg-[#08872b]/10 rounded-[60px] border border-primary/20 mb-10 animate-in fade-in slide-in-from-top-4 duration-700">
 <span class="w-2 h-2 bg-emerald-400 rounded-[60px] animate-pulse -[0_0_10px_#10b981]"></span>
 <span class="text-[10px] font-black text-emerald-400 dark:text-emerald-400 uppercase tracking-[0.4em]">Estudio de manufactura digital</span>
 </div>

 <h1 ref="heroTitleRef" class="text-7xl md:text-9xl lg:text-[11rem] font-black text-slate-900 dark:text-white tracking-tighter uppercase leading-[0.85] mb-6 animate-fade-in">
 N3XT <br class="md:hidden" />
 <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-500 via-primary to-emerald-300 dark:from-emerald-400 dark:via-primary dark:to-emerald-300 drop-shadow-[0_0_15px_rgba(16,185,129,0.3)] italic">3D</span>
 </h1>

 <div class="max-w-5xl mx-auto mb-24 relative group px-6">
 <!-- Elementos Decorativos Técnicos -->
 <div class="absolute -top-8 -left-8 w-12 h-12 border-t-2 border-l-2 border-primary/20 rounded-tl-2xl opacity-0 group-hover:opacity-100 transition-all duration-1000 pointer-events-none"></div>
 <div class="absolute -bottom-8 -right-8 w-12 h-12 border-b-2 border-r-2 border-primary/20 rounded-br-2xl opacity-0 group-hover:opacity-100 transition-all duration-1000 pointer-events-none"></div>
 
 <!-- Aura de luz sutil -->
 <div class="absolute inset-0 bg-[#08872b]/5 blur-[120px] rounded-[60px] opacity-40 pointer-events-none"></div>

 <p class="relative font-black text-sm md:text-3xl uppercase tracking-tighter leading-[1.05] text-center animate-in fade-in slide-in-from-bottom-12 duration-1000 delay-300">
 <span class="text-slate-400 dark:text-[#a4aea6]">Especialistas en </span>
 <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-600 to-emerald-400 dark:from-primary dark:to-emerald-400 drop--[0_0_15px_rgba(16,185,129,0.2)]">fabricación digital</span>.
 <span class="text-slate-900 dark:text-white"> Miniaturas, figuras y piezas de colección creadas con precisión industrial.</span>
 <br class="hidden md:block" />
 <span class="text-slate-400 dark:text-[#a4aea6]"> Inspirados en universos icónicos y el </span>
 <span class="text-emerald-500 dark:text-emerald-400 italic">espíritu de Colombia.</span>
 </p>
 
 <!-- Footer técnico del bloque -->
 <div class="flex justify-center items-center gap-4 mt-10 opacity-20 group-hover:opacity-50 transition-opacity">
 <div class="h-[1px] w-8 bg-[#08872b]"></div>
 <span class="text-[8px] font-black tracking-[0.6em] text-[#8dd6ff] uppercase">N3XT System Core</span>
 <div class="h-[1px] w-8 bg-[#08872b]"></div>
 </div>

 <div class="flex flex-col md:flex-row items-center justify-center gap-8 animate-in fade-in zoom-in duration-1000 mt-16">
 <!-- Botón: Iniciar Proyecto (Elite Luxury) -->
 <router-link to="/project/init" class="group relative px-20 py-8 bg-[#151a22] dark:bg-[#08872b] text-white rounded-[24px] font-black text-xs uppercase tracking-[0.5em] overflow-hidden transition-all hover:-translate-y-1 hover:scale-105 active:scale-95 -[0_20px_50px_-15px_rgba(16,185,129,0.4)]"> <span class="relative z-10 flex items-center gap-4 btn-fade-in">
 INICIAR PROYECTO 3D
 <span class="relative flex h-2 w-2">
 <span class="animate-ping absolute inline-flex h-full w-full rounded-[60px] bg-[#151a22] opacity-75"></span>
 <span class="relative inline-flex rounded-[60px] h-2 w-2 bg-[#151a22]"></span>
 </span>
 </span>
 <!-- Efecto de Brillo (Shine) -->
 <div class="absolute inset-0 bg-gradient-to-tr from-transparent via-white/20 to-transparent -translate-x-[200%] group-hover:translate-x-[200%] transition-transform duration-1000 ease-in-out"></div>
 <div class="absolute inset-0 bg-gradient-to-r from-emerald-500 to-primary opacity-0 group-hover:opacity-100 transition-opacity"></div>
 </router-link>

 <!-- Botón: Ver Catálogo (Elite Ghost) -->
 <router-link to="/catalog" class="group relative px-20 py-8 bg-black/[0.03] dark:bg-[#151a22]/5 backdrop-blur-2xl text-[#f0f6fc] dark:text-white/50 rounded-[24px] font-black text-xs uppercase tracking-[0.5em] overflow-hidden transition-all hover:-translate-y-1 hover:text-[#ffffff] dark:hover:text-white border border-black/10 dark:border-[#21262d] hover:border-black/30 dark:hover:border-white/20"> <span class="relative z-10 flex items-center gap-4 btn-fade-in">
 VER CATÁLOGO
 <svg class="w-5 h-5 opacity-0 -translate-x-4 group-hover:opacity-100 group-hover:translate-x-0 transition-all duration-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
 </svg>
 </span>
 <div class="absolute inset-0 bg-black/5 dark:bg-[#151a22]/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
 </router-link>
 </div>
 </div>
 </div>
 </section>

 <!-- Wave Divider -->
 <WaveDivider color="#10b981" opacity="0.04" height="60px" />

 <!-- Stats Section (Estilo Industrial Refinado) -->
 <section class="py-28 bg-[#151a22] dark:bg-[#05080b] relative overflow-hidden">
 <div class="absolute inset-0 technical-grid opacity-5"></div>
 <div class="max-w-7xl mx-auto grid grid-cols-2 lg:grid-cols-4 gap-12 px-6 relative z-10">
 <div v-for="stat in [{val: '+10K', label: 'Piezas Fabricadas'}, {val: '24H', label: 'Prototipado Rapido'}, {val: '8K', label: 'Resolucion Resina'}, {val: '100%', label: 'Acabado Manual'}]" :key="stat.label" class="group relative">
 <p class="text-5xl md:text-7xl font-black text-slate-900 dark:text-white tracking-tighter mb-2 group-hover:text-emerald-500 transition-all duration-500 transform group-hover:scale-110 origin-left italic">{{ stat.val }}</p>
 <div class="flex items-center gap-3">
 <p class="text-[10px] font-black text-slate-400 dark:text-[#a4aea6] uppercase tracking-[0.3em]">{{ stat.label }}</p>
 </div>
 <div class="absolute -bottom-4 left-0 w-12 h-1 bg-emerald-500/20 group-hover:w-full transition-all duration-700"></div>
 </div>
 </div>
 </section>

 <!-- Wave Divider -->
 <WaveDivider color="#10b981" opacity="0.03" height="60px" />

 <!-- Sección: Fabricación Digital -->
 <section class="py-28 bg-[#151a22] dark:bg-[#0a0f14] px-6 relative overflow-hidden">
 <div class="absolute top-1/2 left-0 w-full h-px bg-gradient-to-r from-transparent via-primary/20 to-transparent"></div>
 <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-24 items-center">
 <div class="relative group">
 <div class="aspect-square bg-[#151a22] rounded-[4.5rem] overflow-hidden -[0_50px_100px_-20px_rgba(30,58,52,0.3)] relative border border-white/10">
 <picture>
 <source srcset="/assets/n3xt_industrial_maker_figures_1778542131405.webp" type="image/webp" />
 <img src="/assets/n3xt_industrial_maker_figures_1778542131405.webp" alt="Figuras coleccionables de anime y videojuegos fabricadas por N3XT 3D" width="1200" height="1200" class="w-full h-full object-cover group-hover:scale-110 transition-all duration-[2s]" loading="eager" fetchpriority="high" />
 </picture>
 <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent opacity-60"></div>
 <div class="absolute bottom-12 left-12 text-white">
 <p class="text-[10px] font-black uppercase tracking-[0.4em] mb-3 text-[#8dd6ff]">FABRICA TU PEDIDO</p>
 <h2 class="text-4xl font-black uppercase tracking-tighter italic">N3XT <span class="text-[#8dd6ff]">3D</span></h2>
 </div>
 </div>
 <div class="absolute -top-6 -right-6 w-32 h-32 border-t-2 border-r-2 border-primary/40 rounded-tr-[3rem]"></div>
 <div class="absolute -bottom-6 -left-6 w-32 h-32 border-b-2 border-l-2 border-primary/40 rounded-bl-[3rem]"></div>
 </div>

 <div class="space-y-10 text-left">
 <div class="inline-block px-5 py-2 bg-emerald-500/10 rounded-[60px] border border-emerald-500/30 -[0_0_15px_rgba(16,185,129,0.1)]">
 <span class="text-[10px] font-black text-emerald-400 uppercase tracking-[0.4em]">¿QUÉ HACEMOS?</span>
 </div>
 <h2 class="text-5xl md:text-7xl font-black text-slate-900 dark:text-white tracking-tighter uppercase leading-[0.9] animate-fade-in">
 EL SIGUIENTE NIVEL DE LA<br/>
 <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-600 to-emerald-400 dark:from-emerald-400 dark:to-emerald-200 italic">FABRICACIÓN DIGITAL.</span>
 </h2>
 <p class="text-[#a4aea6] dark:text-[#c3c4c5] text-xs md:text-sm font-bold uppercase leading-relaxed tracking-[0.3em]">
 En N3XT no solo fabricamos piezas; damos vida a universos, personajes y proyectos personalizados con acabados premium y detalle profesional.
 </p>
 <div class="grid grid-cols-1 gap-10 pt-12">
 <!-- Categoría 1: Figuras -->
 <div class="group p-6 bg-[#151a22] dark:bg-[#151a22]/10 backdrop-blur-2xl rounded-[2.5rem] border border-slate-200 dark:border-[#21262d] hover:border-emerald-500/50 transition-all duration-700 relative overflow-hidden hover:-translate-y-2 hover:">
 <div class="absolute -right-12 -top-12 w-32 h-32 bg-emerald-500/10 rounded-[60px] blur-3xl group-hover:bg-emerald-500/20 transition-all duration-700"></div>
 <div class="flex items-center gap-6 relative z-10">
 <div class="flex-shrink-0 w-16 h-16 bg-emerald-500/10 rounded-[6px] flex items-center justify-center border border-emerald-500/20 -[0_0_20px_rgba(16,185,129,0.1)] group-hover:bg-emerald-500 group-hover:text-white transition-all duration-500">
 <svg class="w-8 h-8 text-emerald-400 group-hover:text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
 </div>
 <div class="text-left">
 <h2 class="text-xl md:text-2xl font-black text-slate-900 dark:text-white uppercase tracking-tighter mb-2 italic">Figuras <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-500 to-emerald-700 dark:from-emerald-400 dark:to-emerald-600">Premium</span></h2>
 <p class="text-xs md:text-sm text-slate-400 dark:text-[#c3c4c5] font-bold uppercase leading-relaxed tracking-tight mb-4 max-w-md">Piezas artísticas en alta resolución con acabados de museo.</p>
 <div class="flex flex-wrap gap-2">
 <span v-for="tag in ['Resina SLA', 'Aerografia', 'Detalle 8K']" :key="tag" class="text-[9px] font-black px-3 py-1 bg-emerald-500/5 dark:bg-black/40 rounded-[60px] border border-emerald-500/20 uppercase tracking-[0.2em] text-emerald-600 ">{{ tag }}</span>
 </div>
 </div>
 </div>
 </div>

 <!-- Categoría 2: Impresión 3D -->
 <div class="group p-6 bg-[#151a22] dark:bg-[#151a22]/10 backdrop-blur-2xl rounded-[2.5rem] border border-slate-200 dark:border-[#21262d] hover:border-emerald-500/50 transition-all duration-700 relative overflow-hidden hover:-translate-y-2 hover:">
 <div class="absolute -right-12 -top-12 w-32 h-32 bg-emerald-500/10 rounded-[60px] blur-3xl group-hover:bg-emerald-500/20 transition-all duration-700"></div>
 <div class="flex items-center gap-6 relative z-10">
 <div class="flex-shrink-0 w-16 h-16 bg-emerald-500/10 rounded-[6px] flex items-center justify-center border border-emerald-500/20 -[0_0_20px_rgba(16,185,129,0.1)] group-hover:bg-emerald-500 group-hover:text-white transition-all duration-500">
 <svg class="w-8 h-8 text-emerald-400 group-hover:text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
 </div>
 <div class="text-left">
 <h2 class="text-xl md:text-2xl font-black text-slate-900 dark:text-white uppercase tracking-tighter mb-2 italic">Fabricación <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-500 to-emerald-700 dark:from-emerald-400 dark:to-emerald-600">Multimaterial</span></h2>
 <p class="text-xs md:text-sm text-slate-400 dark:text-[#c3c4c5] font-bold uppercase leading-relaxed tracking-tight mb-4 max-w-md">Flota de alta gama para resultados industriales.</p>
 <div class="flex flex-wrap gap-2">
 <span v-for="tag in ['Bambulab', 'Elegoo', 'Creality', 'Anycubic']" :key="tag" class="text-[9px] font-black px-3 py-1 bg-emerald-500/5 dark:bg-black/40 rounded-[60px] border border-emerald-500/20 uppercase tracking-[0.2em] text-emerald-600 ">{{ tag }}</span>
 </div>
 </div>
 </div>
 </div>

 <!-- Categoría 3: Prototipos -->
 <div class="group p-6 bg-[#151a22] dark:bg-[#151a22]/10 backdrop-blur-2xl rounded-[2.5rem] border border-slate-200 dark:border-[#21262d] hover:border-emerald-400/50 transition-all duration-700 relative overflow-hidden hover:-translate-y-2 hover:">
 <div class="absolute -right-12 -top-12 w-32 h-32 bg-emerald-400/10 rounded-[60px] blur-3xl group-hover:bg-emerald-400/20 transition-all duration-700"></div>
 <div class="flex items-center gap-6 relative z-10">
 <div class="flex-shrink-0 w-16 h-16 bg-emerald-400/10 rounded-[6px] flex items-center justify-center border border-emerald-400/20 -[0_0_20px_rgba(52,211,153,0.1)] group-hover:bg-emerald-400 group-hover:text-white transition-all duration-500">
 <svg class="w-8 h-8 text-emerald-400 group-hover:text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z"/></svg>
 </div>
 <div class="text-left">
 <h2 class="text-xl md:text-2xl font-black text-slate-900 dark:text-white uppercase tracking-tighter mb-2 italic">Prototipos <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-500 to-primary">Funcionales</span></h2>
 <p class="text-xs md:text-sm text-slate-400 dark:text-[#c3c4c5] font-bold uppercase leading-relaxed tracking-tight mb-4 max-w-md">Ingeniería aplicada con precisión mecánica industrial.</p>
 <div class="flex flex-wrap gap-2">
 <span v-for="tag in ['Ingenieria', 'Nylon / PETG', 'Prueba de Estrés']" :key="tag" class="text-[9px] font-black px-3 py-1 bg-emerald-400/5 dark:bg-black/40 rounded-[60px] border border-emerald-400/20 uppercase tracking-[0.2em] text-emerald-600 ">{{ tag }}</span>
 </div>
 </div>
 </div>
 </div>
 </div>
 </div>
 </div>
 </section>

 <!-- Wave Divider -->
 <WaveDivider color="#10b981" opacity="0.04" height="60px" flip />

 <!-- Sección: Proceso de Fabricación N3XT (Numbered Steps) -->
 <section class="py-28 bg-[#151a22] dark:bg-[#05080b] px-6 relative overflow-hidden">
 <div class="absolute inset-0 technical-grid opacity-10 pointer-events-none"></div>
 
 <div class="max-w-7xl mx-auto mb-32 text-center relative z-10">
 <h2 class="split-title text-5xl md:text-7xl font-black text-slate-400 dark:text-white/30 tracking-tighter uppercase leading-[0.9] mb-8 transition-colors duration-500">
 NUESTRO <span class="text-emerald-600 dark:text-emerald-400">PROCESO</span><br/>
 <span class="text-slate-500 dark:text-white/40 transition-all duration-700">DE FABRICACIÓN.</span>
 </h2>
 <div class="flex items-center justify-center gap-4">
 <div class="h-px w-12 bg-gradient-to-r from-transparent to-emerald-500/50"></div>
 <p class="text-[10px] md:text-xs text-emerald-400/80 font-black uppercase tracking-[0.4em]">N3XT • DISEÑO, PRECISIÓN Y DETALLE</p>
 <div class="h-px w-12 bg-gradient-to-l from-transparent to-emerald-500/50"></div>
 </div>
 </div>

 <div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 relative z-10">
 <!-- Paso 01 -->
 <div class="group relative p-10 bg-[#151a22] dark:bg-[#151a22]/5 backdrop-blur-xl rounded-[2.5rem] border border-slate-200 dark:border-[#21262d] hover:border-emerald-500/50 transition-all duration-500 overflow-hidden hover:">
 <div class="absolute -right-4 -top-4 text-8xl font-black text-slate-200 dark:text-white/10 group-hover:text-emerald-500/20 transition-all duration-500 tracking-tighter italic">01</div>
 <div class="mb-8 w-14 h-14 bg-emerald-500/10 rounded-[24px] flex items-center justify-center border border-emerald-500/20 group-hover:bg-emerald-500 transition-all duration-500 ">
 <svg class="w-7 h-7 text-emerald-400 group-hover:text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.989-2.386l-.548-.547z"/></svg>
 </div>
 <h2 class="text-xl font-black text-slate-900 dark:text-white uppercase tracking-tighter mb-4 italic">Envíanos tu <span class="text-emerald-500 dark:text-emerald-400">Idea</span></h2>
 <p class="text-xs text-slate-400 dark:text-[#c3c4c5] leading-relaxed font-bold uppercase tracking-tight">
 Compártenos tu idea, imagen o archivo 3D. Si aún no tienes el diseño listo, <span class="text-slate-900 dark:text-white/60">te ayudamos a desarrollarlo.</span>
 </p>
 <div class="mt-8 h-1 w-0 bg-emerald-500 group-hover:w-full transition-all duration-1000 rounded-[60px] -[0_0_10px_rgba(16,185,129,0.5)]"></div>
 </div>

 <!-- Paso 02 -->
 <div class="group relative p-10 bg-[#151a22] dark:bg-[#151a22]/5 backdrop-blur-xl rounded-[2.5rem] border border-slate-200 dark:border-[#21262d] hover:border-emerald-500/50 transition-all duration-500 overflow-hidden hover:">
 <div class="absolute -right-4 -top-4 text-8xl font-black text-slate-200 dark:text-white/10 group-hover:text-emerald-500/20 transition-all duration-500 tracking-tighter italic">02</div>
 <div class="mb-8 w-14 h-14 bg-emerald-500/10 rounded-[24px] flex items-center justify-center border border-emerald-500/20 group-hover:bg-emerald-500 transition-all duration-500 ">
 <svg class="w-7 h-7 text-emerald-400 group-hover:text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
 </div>
 <h2 class="text-xl font-black text-slate-900 dark:text-white uppercase tracking-tighter mb-4 italic">Asesoría <span class="text-emerald-500 dark:text-emerald-400">Personalizada</span></h2>
 <p class="text-xs text-slate-400 dark:text-[#c3c4c5] leading-relaxed font-bold uppercase tracking-tight">
 Analizamos tu proyecto y te recomendamos el <span class="text-slate-900 dark:text-white/60">material, tamaño y tipo de fabricación</span> ideal para tu pieza.
 </p>
 <div class="mt-8 h-1 w-0 bg-emerald-500 group-hover:w-full transition-all duration-1000 rounded-[60px] -[0_0_10px_rgba(16,185,129,0.5)]"></div>
 </div>

 <!-- Paso 03 -->
 <div class="group relative p-10 bg-[#151a22] dark:bg-[#151a22]/5 backdrop-blur-xl rounded-[2.5rem] border border-slate-200 dark:border-[#21262d] hover:border-emerald-500/50 transition-all duration-500 overflow-hidden hover:">
 <div class="absolute -right-4 -top-4 text-8xl font-black text-slate-200 dark:text-white/10 group-hover:text-emerald-500/20 transition-all duration-500 tracking-tighter italic">03</div>
 <div class="mb-8 w-14 h-14 bg-emerald-500/10 rounded-[24px] flex items-center justify-center border border-emerald-500/20 group-hover:bg-emerald-500 transition-all duration-500 ">
 <svg class="w-7 h-7 text-emerald-400 group-hover:text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
 </div>
 <h2 class="text-xl font-black text-slate-900 dark:text-white uppercase tracking-tighter mb-4 italic">Recibe tu <span class="text-emerald-500 dark:text-emerald-400">Cotización</span></h2>
 <p class="text-xs text-slate-400 dark:text-[#c3c4c5] leading-relaxed font-bold uppercase tracking-tight">
 Te enviamos una <span class="text-slate-900 dark:text-white/60">cotización clara</span> según complejidad, materiales, acabados y tiempo de fabricación.
 </p>
 <div class="mt-8 h-1 w-0 bg-emerald-500 group-hover:w-full transition-all duration-1000 rounded-[60px] -[0_0_10px_rgba(16,185,129,0.5)]"></div>
 </div>

 <!-- Paso 04 -->
 <div class="group relative p-10 bg-[#151a22] dark:bg-[#151a22]/5 backdrop-blur-xl rounded-[2.5rem] border border-slate-200 dark:border-[#21262d] hover:border-emerald-500/50 transition-all duration-500 overflow-hidden hover:">
 <div class="absolute -right-4 -top-4 text-8xl font-black text-slate-200 dark:text-white/10 group-hover:text-emerald-500/20 transition-all duration-500 tracking-tighter italic">04</div>
 <div class="mb-8 w-14 h-14 bg-emerald-500/10 rounded-[24px] flex items-center justify-center border border-emerald-500/20 group-hover:bg-emerald-500 transition-all duration-500 ">
 <svg class="w-7 h-7 text-emerald-400 group-hover:text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 13l4 4L19 7"/></svg>
 </div>
 <h2 class="text-xl font-black text-slate-900 dark:text-white uppercase tracking-tighter mb-4 italic">Fabricación y <span class="text-emerald-500 dark:text-emerald-400">Entrega</span></h2>
 <p class="text-xs text-slate-400 dark:text-[#c3c4c5] leading-relaxed font-bold uppercase tracking-tight">
 Fabricamos tu pieza con equipos profesionales y la entregamos con <span class="text-slate-900 dark:text-white/60">acabados premium y detalle profesional.</span>
 </p>
 <div class="mt-8 h-1 w-0 bg-emerald-500 group-hover:w-full transition-all duration-1000 rounded-[60px] -[0_0_10px_rgba(16,185,129,0.5)]"></div>
 </div>
 </div>

 <div class="max-w-7xl mx-auto mt-32 text-center relative z-10">
 <router-link to="/project/init" class="inline-flex items-center gap-6 px-16 py-7 bg-gray-100 dark:bg-white text-gray-900 dark:text-black border border-gray-200 dark:border-white/20 shadow-md hover:shadow-lg rounded-[60px] font-black text-xs uppercase tracking-[0.4em] hover:bg-emerald-500 dark:hover:bg-emerald-500 hover:text-white transition-all duration-500 -[0_20px_50px_-10px_rgba(16,185,129,0.3)] hover:scale-105">
 <span class="btn-fade-in">INICIAR PROYECTO 3D <span class="text-xl">→</span></span>
 </router-link>
 </div>
 </section>

 <!-- Wave Divider -->
 <WaveDivider color="#10b981" opacity="0.03" height="50px" />

 <!-- Sección: Marcas que Nos Respaldan (Brand Ticker) -->
 <section v-intersection="'brandticker'" class="py-20 bg-[#151a22] dark:bg-[#05080b] relative overflow-hidden transition-colors duration-500 min-h-[200px]">
 <transition name="smooth-reveal">
 <BrandTicker v-if="visibleSections['brandticker']" :visible="visibleSections['brandticker']" />
 </transition>
 </section>

 <!-- Wave Divider -->
 <WaveDivider color="#10b981" opacity="0.04" height="60px" flip />

 <!-- Sección: Proceso Artesanal -->
 <section class="py-28 bg-slate-50 dark:bg-[#090d0a] relative overflow-hidden transition-colors duration-500">
 <div class="absolute inset-0 opacity-10 dark:opacity-10 technical-grid pointer-events-none"></div>
 <div class="absolute -right-40 top-0 w-96 h-96 bg-emerald-500/10 rounded-[60px] blur-[120px]"></div>
 
 <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-24 items-center px-6">
 <div class="order-2 lg:order-1 space-y-12">
 <div class="inline-flex items-center gap-4 px-6 py-2 bg-emerald-500/10 rounded-[60px] border border-emerald-500/20">
 <span class="text-[10px] font-black text-emerald-600 dark:text-emerald-400 uppercase tracking-[0.4em]">Acabado artesanal</span>
 </div>
 <h2 class="text-5xl md:text-7xl font-black text-slate-900 dark:text-white tracking-tighter uppercase leading-[0.9] fade-title">
 EL ARTE DE LA<br/>
 <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-600 to-emerald-400 italic">POST-PRODUCCIÓN.</span>
 </h2>
 <p class="text-slate-500 dark:text-white/50 text-xs md:text-sm font-bold uppercase tracking-[0.3em] leading-relaxed max-w-xl">
 Cada pieza pasa por un proceso de <span class="text-slate-900 dark:text-white">curado, lijado y acabado manual</span> para lograr superficies limpias, detalles definidos y una <span class="text-emerald-600 dark:text-emerald-400">apariencia premium.</span>
 </p>
 <div class="grid grid-cols-2 md:grid-cols-4 gap-4 max-w-2xl">
 <div
v-for="step in [
 {n: 'Curado UV Pro', code: 'UV', icon: '<svg class=\'w-7 h-7\' fill=\'none\' viewBox=\'0 0 24 24\' stroke=\'currentColor\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'1.5\' d=\'M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m12.728 0l-.707-.707M6.343 6.343l-.707-.707M12 8a4 4 0 100 8 4 4 0 000-8z\'/></svg>'}, 
 {n: 'Lijado fino', code: 'FINISH', icon: '<svg class=\'w-7 h-7\' fill=\'none\' viewBox=\'0 0 24 24\' stroke=\'currentColor\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'1.5\' d=\'M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z\'/></svg>'}, 
 {n: 'Imprimacion', code: 'BASE', icon: '<svg class=\'w-7 h-7\' fill=\'none\' viewBox=\'0 0 24 24\' stroke=\'currentColor\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'1.5\' d=\'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10\'/></svg>'}, 
 {n: 'Aerografia', code: 'PREM', icon: '<svg class=\'w-7 h-7\' fill=\'none\' viewBox=\'0 0 24 24\' stroke=\'currentColor\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'1.5\' d=\'M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z\'/></svg>'}
 ]" :key="step.n" class="group flex flex-col items-center justify-center text-center gap-4 bg-[#151a22] dark:bg-[#151a22]/5 backdrop-blur-xl border border-slate-200 dark:border-[#21262d] p-6 rounded-[2rem] hover:border-emerald-500/50 transition-all duration-500 hover: relative overflow-hidden aspect-square">
 <div class="w-14 h-14 bg-slate-100 dark:bg-[#151a22] rounded-[24px] flex items-center justify-center group-hover:bg-emerald-500 transition-all duration-500 border border-slate-200 dark:border-[#21262d] ">
 <span :innerHTML="sanitizeSVG(step.icon)" class="text-emerald-500 group-hover:text-white transition-all duration-500 group-hover:scale-110"></span>
 </div>
 <span class="text-slate-900 dark:text-white font-black text-[10px] uppercase tracking-[0.2em] leading-tight max-w-[80px]">{{ step.n }}</span>
 <div class="absolute inset-x-0 bottom-0 h-1 w-0 bg-emerald-500 group-hover:w-full transition-all duration-700"></div>
 </div>
 </div>

 <!-- Botón: Ir a Galería debajo de Post-Producción -->
 <div class="flex justify-center mt-16"><router-link
to="/galeria"
 class="group inline-flex items-center gap-6 px-16 py-7 bg-gray-100 dark:bg-white text-gray-900 dark:text-black border border-gray-200 dark:border-white/20 shadow-md hover:shadow-lg rounded-[60px] font-black text-xs uppercase tracking-[0.4em] hover:bg-emerald-500 dark:hover:bg-emerald-500 hover:text-white transition-all duration-500 -[0_20px_50px_-10px_rgba(16,185,129,0.3)] hover:scale-105">
 <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
 </svg>
 <span class="relative z-10 flex items-center gap-4 btn-fade-in">
 VER GALERÍA
 <svg class="w-5 h-5 group-hover:translate-x-1.5 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
 </svg>
 </span>
 </router-link>
 </div>
 </div>
 <div class="order-1 lg:order-2">
 <div class="aspect-[4/3] bg-[#151a22]/5 rounded-[4rem] overflow-hidden border border-white/10 relative group transform hover:rotate-2 transition-all duration-700">
 <picture>
 <source srcset="/assets/n3xt_airbrush_process_hq_1778542148509.webp" type="image/webp" />
 <img src="/assets/n3xt_airbrush_process_hq_1778542148509.webp" alt="Proceso de aerografía y pintura artesanal en figuras 3D" width="1200" height="900" class="w-full h-full object-cover group-hover:scale-125 transition-transform duration-[2.5s]" loading="lazy" />
 </picture>
 <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent opacity-60"></div>
 </div>
 </div>
 </div>
 </section>

 <!-- Wave Divider -->
 <WaveDivider color="#10b981" opacity="0.03" height="60px" />

 <!-- Sección: Ecosistema N3XT 3D (Rediseño Premium Industrial) -->
 <section v-intersection="'ecosystem'" class="py-28 bg-[#151a22] dark:bg-[#0a0f14] px-6 relative overflow-hidden min-h-[600px]">
 <transition name="smooth-reveal">
 <div v-if="visibleSections['ecosystem']" class="w-full h-full">
 <div class="absolute inset-0 technical-grid opacity-5 pointer-events-none"></div>
 <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[800px] h-[400px] bg-[#08872b]/10 blur-[150px] rounded-[60px] pointer-events-none"></div>
 
 <div class="max-w-7xl mx-auto mb-32 text-center relative z-10">
 <h2 class="text-5xl md:text-7xl font-black text-slate-400 dark:text-white/30 tracking-tighter uppercase leading-[0.9] mb-8 transition-all duration-500 fade-title">
 EL <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-600 to-emerald-400">ECOSISTEMA</span><br/>
 <span class="text-slate-500 dark:text-white/40">N3XT 3D.</span>
 </h2>
 <p class="text-xs md:text-sm text-[#c3c4c5] font-bold uppercase tracking-[0.3em]">Manufactura Avanzada • Cultura Maker • Innovación Digital</p>
 </div>

 <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-8 relative z-10">
 <!-- Card Ecosistema — Estilo originui adaptado -->
 <div
v-for="(card, cidx) in webSettings.ecosystem" :key="cidx" 
 class="group bg-[#151a22] dark:bg-[#151a22]/5 rounded-[2.5rem] border border-[#21262d] dark:border-[#21262d] hover:border-emerald-500/40 transition-all duration-500 flex flex-col hover: overflow-hidden">
 
 <!-- Imagen (clean, aspect-square, rounded-[24px] como la referencia) -->
 <div v-if="card.type === 'image'" class="relative p-4 pb-0">
 <div class="aspect-square w-full rounded-[24px] overflow-hidden bg-[#151a22] relative">
 <img
:src="getImages(card.i)[0]" 
 :alt="card.t1"
 class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700"
 loading="lazy"
 @error="(e: any) => e.target.src = 'https://images.unsplash.com/photo-1638202993928-7267aad84c31?auto=format&fit=crop&q=80&w=800'" />
 <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent"></div>
 
 <!-- Indicador carrusel -->
 <div v-if="getImages(card.i).length > 1" class="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-1.5">
 <div
v-for="(_, dotIdx) in getImages(card.i)" :key="dotIdx" 
 :class="['h-1.5 rounded-[60px] transition-all duration-500', activeIndices[cidx] === dotIdx ? 'w-6 bg-emerald-400' : 'w-1.5 bg-[#151a22]/30']"></div>
 </div>
 </div>
 </div>

 <!-- Icono (clean icon container) -->
 <div v-else-if="card.type === 'icon'" class="p-6 pb-0 flex justify-center">
 <div class="w-16 h-16 bg-emerald-500/10 rounded-[24px] flex items-center justify-center border border-emerald-500/20 group-hover:bg-emerald-500 group-hover:border-emerald-500 transition-all duration-500">
 <span class="text-emerald-400 group-hover:text-white transition-colors duration-500" v-html="sanitizeSVG(card.i)"></span>
 </div>
 </div>

 <!-- Content -->
 <div class="p-6 flex-1 flex flex-col gap-4">
 <h2 class="text-2xl md:text-3xl font-black text-[#ffffff] dark:text-white uppercase tracking-tighter italic leading-tight">
 {{ card.t1 }}<br/>
 <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-500 to-emerald-400">{{ card.t2 }}</span>
 </h2>
 <p class="text-[11px] text-[#c3c4c5] font-bold uppercase leading-relaxed tracking-wide">
 {{ card.d }}
 </p>
 </div>

 <!-- Stats row (dinámico desde card.stats, con separadores verticales) -->
 <div v-if="card.stats && card.stats.length" class="px-6 pb-4">
 <div class="flex items-center justify-around py-3.5 bg-[#151a22] dark:bg-[#151a22]/[0.03] rounded-[24px] border border-[#21262d] dark:border-[#21262d]">
 <template v-for="(stat, si) in card.stats" :key="si">
 <!-- Separador vertical entre stats -->
 <div v-if="si !== 0" class="w-px h-8 bg-gray-200 dark:bg-[#151a22]/10"></div>
 <div class="flex flex-col items-center gap-1">
 <p class="text-base md:text-lg font-black text-[#ffffff] dark:text-white tracking-tight">{{ stat.val }}</p>
 <span class="text-[8px] md:text-[9px] font-bold text-[#c3c4c5] dark:text-[#a4aea6] uppercase tracking-[0.15em]">{{ stat.label }}</span>
 </div>
 </template>
 </div>
 </div>

 <!-- CTA Button (rounded-[60px] como referencia) -->
 <div class="px-6 pb-6">
 <router-link
to="/project/init" 
 class="group/btn flex items-center justify-center gap-3 w-full py-4 bg-[#151a22] dark:bg-[#151a22]/10 hover:bg-emerald-500 text-white rounded-[60px] text-[9px] font-black uppercase tracking-[0.35em] transition-all duration-500 -black/10">
 <span>Explorar</span>
 <svg class="w-4 h-4 group-hover/btn:translate-x-1 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
 </router-link>
 </div>
 </div>
 </div>
 </div>
 </transition>
 </section>

 <!-- Wave Divider -->
 <WaveDivider color="#10b981" opacity="0.04" height="50px" />

 <!-- Sección: Trabajos Destacados de la Galería (N3XT Portfolio) -->
 <section v-if="webSettings.gallery && webSettings.gallery.length > 0" class="py-28 bg-[#151a22] dark:bg-[#0a0f14] px-6 relative overflow-hidden transition-colors duration-500">
 <div class="max-w-7xl mx-auto">
 <div class="flex flex-col md:flex-row md:items-end justify-between gap-8 mb-16">
 <div>
 <div class="inline-flex items-center gap-3 px-4 py-2 bg-[#08872b]/10 rounded-[60px] border border-primary/20 mb-6">
 <span class="w-2 h-2 bg-[#08872b] rounded-[60px] animate-pulse"></span>
 <span class="text-[10px] font-black text-[#8dd6ff] uppercase tracking-[0.4em]">Portafolio</span>
 </div>
 <h2 class="split-title text-5xl md:text-7xl font-black text-[#ffffff] dark:text-white tracking-tighter uppercase leading-[0.9]">
 Trabajos <span class="text-emerald-500">Destacados</span>
 </h2>
 </div>
 <router-link to="/galeria" class="group flex items-center gap-4 text-[10px] font-black text-[#c3c4c5] uppercase tracking-widest hover:text-emerald-500 transition-all">
 Ver Galería Completa
 <svg class="w-5 h-5 group-hover:translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
 </router-link>
 </div>

 <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
 <router-link
 v-for="(work, idx) in (webSettings.gallery.filter(g => g.featured).length > 0 ? webSettings.gallery.filter(g => g.featured) : webSettings.gallery).slice(0, 6)" :key="idx"
 :to="'/galeria/' + encodeURIComponent(work.title || idx)"
 class="group bg-[#151a22] dark:bg-[#151a22]/5 rounded-[2.5rem] overflow-hidden border border-[#21262d] dark:border-[#21262d] hover:border-emerald-500/40 transition-all duration-500 hover: hover:-translate-y-1">
 <!-- Image -->
 <div class="p-4 pb-0">
 <div class="aspect-square rounded-[24px] overflow-hidden relative bg-[#151a22] dark:bg-[#283041]">
 <!-- Imagen Secundaria (aparece en hover si existe) -->
 <img
 v-if="work.images && work.images.length > 0"
 :src="getOptimizedImage(work.images[0])"
 class="absolute inset-0 w-full h-full object-cover scale-110 opacity-0 group-hover:opacity-100 group-hover:scale-100 transition-all duration-700 z-10"
 loading="lazy"
 />
 <!-- Imagen Principal -->
 <img
 v-if="!brokenGalleryImgs[idx]"
 :src="getOptimizedImage(work.image)" :alt="work.title"
 class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700 relative z-0"
 loading="lazy"
 @error="brokenGalleryImgs[idx] = true"
 />
 <div v-if="!work.image || brokenGalleryImgs[idx]" class="absolute inset-0 flex items-center justify-center">
 <svg class="w-12 h-12 text-gray-300 dark:text-[#a4aea6]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
 </div>
 <div class="absolute top-3 left-3 px-3 py-1 bg-emerald-500/90 text-white text-[7px] font-black rounded-[60px] uppercase tracking-[0.25em] ">{{ work.category }}</div>
 </div>
 </div>
 <!-- Info -->
 <div class="p-6 text-center">
 <h2 class="text-lg font-black text-[#ffffff] dark:text-white uppercase tracking-tighter leading-tight group-hover:text-emerald-500 transition-colors">{{ work.title }}</h2>
 <p v-if="work.subtitle" class="text-[8px] font-black text-[#c3c4c5] uppercase tracking-[0.2em] mt-1.5">{{ work.subtitle }}</p>
 <!-- Tags -->
 <div v-if="normalizeTags(work.tags).length > 0" class="flex flex-wrap justify-center gap-1.5 mt-3">
 <span v-for="tag in normalizeTags(work.tags).slice(0, 3)" :key="tag" class="text-[6px] font-black px-2 py-0.5 bg-[#151a22] dark:bg-[#151a22]/5 rounded-[60px] uppercase tracking-[0.15em] text-[#a4aea6]">{{ tag }}</span>
 </div>
 <div class="mt-4 w-full py-3.5 bg-[#090d0a] dark:bg-[#151a22]/10 text-white rounded-[60px] text-[8px] font-black uppercase tracking-[0.3em] group-hover:bg-emerald-500 transition-all duration-500 ">
 Ver Proyecto
 </div>
 </div>
 </router-link>
 </div>
 </div>
 </section>

 <!-- Sección: Social Media Hub (Tus últimas publicaciones) -->
 <!-- Wave Divider -->
 <WaveDivider color="#10b981" opacity="0.03" height="50px" />

 <!-- SECCIÓN 1: NOVEDADES 3D COLOMBIA (Editorial) -->
 <section v-if="webSettings.news.length > 0" id="news" v-intersection="'news'" class="py-28 px-6 md:px-12 bg-[#151a22] dark:bg-[#0a0f14] relative overflow-hidden transition-colors duration-500 min-h-[600px]">
 <transition name="smooth-reveal">
 <div v-if="visibleSections['news']" class="w-full h-full">
 <div class="absolute top-0 left-1/4 w-96 h-96 bg-[#08872b]/5 rounded-[60px] blur-[120px] pointer-events-none"></div>
 
 <div class="max-w-7xl mx-auto mb-24 flex flex-col md:flex-row md:items-end justify-between gap-8">
 <div class="relative z-10">
 <div class="inline-flex items-center gap-3 px-5 py-2 bg-emerald-500/10 rounded-[60px] border border-emerald-500/30 mb-6 -[0_0_20px_rgba(16,185,129,0.1)]">
 <span class="w-2.5 h-2.5 bg-emerald-400 rounded-[60px] animate-pulse -[0_0_12px_#10b981]"></span>
 <span class="text-[10px] font-black text-emerald-400 uppercase tracking-[0.4em]">NOTICIAS LOCALES</span>
 </div>
 <h2 class="text-5xl md:text-7xl font-black text-slate-400 dark:text-white/30 tracking-tighter uppercase leading-[0.9] mb-4 transition-all duration-500 fade-title">
 NOVEDADES <br class="md:hidden"/> <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-600 to-emerald-400">3D COLOMBIA</span>
 </h2>
 <p class="text-xs md:text-sm text-slate-500 dark:text-[#a4aea6] font-bold uppercase tracking-[0.3em] flex items-center gap-3">
 <span class="w-8 h-[2px] bg-emerald-500"></span>
 El pulso de la manufactura nacional
 </p>
 </div>
 <a href="https://www.3dnatives.com/es/" target="_blank" class="group flex items-center gap-4 text-[10px] font-black text-[#c3c4c5] dark:text-[#a4aea6] uppercase tracking-widest hover:text-[#8dd6ff] transition-all">
 Explorar Más Noticias
 <span class="w-12 h-12 rounded-[60px] border border-[#21262d] dark:border-[#21262d] flex items-center justify-center group-hover:bg-[#08872b] group-hover:text-white transition-all text-xl">→</span>
 </a>
 </div>

 <!-- Skeleton Loader during fetch -->
 <div v-if="!isReady" class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
 <div v-for="i in 4" :key="i" class="bg-[#151a22] dark:bg-[#151a22]/5 rounded-[2.5rem] animate-pulse overflow-hidden">
 <div class="p-4">
 <div class="aspect-square w-full rounded-[24px] bg-gray-200 dark:bg-[#151a22]/10"></div>
 </div>
 <div class="p-5 space-y-3">
 <div class="h-4 w-3/4 bg-gray-200 dark:bg-[#151a22]/10 rounded-[60px]"></div>
 <div class="h-3 w-full bg-gray-200 dark:bg-[#151a22]/10 rounded-[60px]"></div>
 <div class="h-3 w-1/2 bg-gray-200 dark:bg-[#151a22]/10 rounded-[60px]"></div>
 </div>
 </div>
 </div>

 <div v-else class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
 <div
v-for="(item, idx) in webSettings.news" :key="item.t" 
 class="group bg-[#151a22] dark:bg-[#151a22]/5 rounded-[2.5rem] overflow-hidden border border-[#21262d] dark:border-[#21262d] hover:border-emerald-500/40 transition-all duration-500 flex flex-col hover:"
 :style="{ animationDelay: (idx * 150) + 'ms' }">
 <!-- Imagen clean aspect-square rounded-[24px] como referencia -->
 <div class="p-4 pb-0">
 <div class="aspect-square w-full rounded-[24px] overflow-hidden relative bg-[#151a22] dark:bg-[#283041]">
 <img
:src="item.image || item.i || 'https://images.unsplash.com/photo-1631033031102-f855d4872494?auto=format&fit=crop&q=80&w=800'" 
 :alt="'Noticia: ' + (item.t || 'N3XT')"
 class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700"
 loading="lazy"
 @error="(e: any) => e.target.src = 'https://images.unsplash.com/photo-1631033031102-f855d4872494?auto=format&fit=crop&q=80&w=800'"
 />
 <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent"></div>
 <div class="absolute top-3 left-3">
 <span class="px-3.5 py-1.5 bg-emerald-500 text-white text-[8px] font-black uppercase tracking-[0.25em] rounded-[60px] -emerald-500/20">{{ item.category || item.tag || 'NOTICIA' }}</span>
 </div>
 </div>
 </div>
 <!-- Content -->
 <div class="p-5 flex-1 flex flex-col gap-3">
 <h2 class="text-base font-black text-[#ffffff] dark:text-white uppercase tracking-tight leading-tight group-hover:text-emerald-500 transition-colors">{{ item.t || 'Nueva Noticia' }}</h2>
 <p class="text-[10px] text-[#c3c4c5] font-bold leading-relaxed uppercase tracking-tight flex-1">{{ item.st || item.d }}</p>
 <!-- CTA rounded-[60px] como referencia -->
 <a
:href="item.url || (item.slug ? '/noticias/' + item.slug : '#')" :target="(item.url && item.url !== '#') ? '_blank' : '_self'" 
 class="mt-2 flex items-center justify-center gap-2 w-full py-3.5 bg-[#151a22] dark:bg-[#151a22]/10 hover:bg-emerald-500 text-white rounded-[60px] text-[8px] font-black uppercase tracking-[0.35em] transition-all duration-500 ">
 <span>Leer</span>
 <svg class="w-3.5 h-3.5 group-hover:translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
 </a>
 </div>
 </div>
 </div>
 </div>
 </transition>
 </section>

 <!-- Wave Divider -->
 <WaveDivider color="#10b981" opacity="0.03" height="50px" flip />

 <!-- SECCIÓN 2: SOCIAL HUB N3XT (TikTok/Instagram Feed) -->
 <section v-if="webSettings.posts.length > 0" class="py-28 bg-[#151a22] dark:bg-[#0a0f14] px-6 overflow-hidden relative transition-colors duration-500">
 <div class="absolute inset-0 technical-grid opacity-5 pointer-events-none"></div>
 
 <div class="max-w-7xl mx-auto text-center mb-24 relative z-10">
 <div class="inline-flex items-center gap-3 px-5 py-2 bg-emerald-500/10 rounded-[60px] border border-emerald-500/30 mb-8 -[0_0_20px_rgba(16,185,129,0.1)]">
 <span class="w-2.5 h-2.5 bg-emerald-400 rounded-[60px] animate-pulse -[0_0_12px_#10b981]"></span>
 <span class="text-[10px] font-black text-emerald-400 uppercase tracking-[0.4em]">REDES SOCIALES</span>
 </div>
 <h2 class="text-5xl md:text-7xl font-black text-[#ffffff] dark:text-white tracking-tighter uppercase leading-[0.9] mb-12 fade-title">NOVEDADES<br/><span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 to-emerald-600">EN TIEMPO REAL.</span></h2>
 
 <div class="flex flex-wrap justify-center gap-6 md:gap-8 items-center mb-20">
 <a :href="webSettings.social.tiktok" target="_blank" class="group flex items-center gap-3 bg-[#151a22] dark:bg-[#151a22]/5 px-8 py-4 rounded-[24px] border border-[#21262d] dark:border-[#21262d] hover:border-primary/50 transition-all">
 <svg class="w-5 h-5 text-black dark:text-white group-hover:scale-125 transition-transform" fill="currentColor" viewBox="0 0 24 24"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-1.13-.32-2.43-.23-3.41.45-.83.53-1.33 1.47-1.38 2.44-.01.52.16 1.03.45 1.46.39.58 1.02.95 1.7 1.02 1.3.16 2.61-.41 3.17-1.58.34-.7.4-1.5.39-2.28-.02-5.91-.01-11.83-.01-17.74z"/></svg>
 <span class="text-[10px] font-black text-[#a4aea6] dark:text-[#c3c4c5] uppercase tracking-widest">TikTok</span>
 </a>
 <a :href="webSettings.social.instagram" target="_blank" class="group flex items-center gap-3 bg-[#151a22] dark:bg-[#151a22]/5 px-8 py-4 rounded-[24px] border border-[#21262d] dark:border-[#21262d] hover:border-primary/50 transition-all">
 <svg class="w-5 h-5 text-rose-500 group-hover:scale-125 transition-transform" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.058-1.69-.072-4.949-.072zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c.796 0 1.441.645 1.441 1.44s-.645 1.44-1.441 1.44c-.795 0-1.439-.645-1.439-1.44s.644-1.44 1.439-1.44z"/></svg>
 <span class="text-[10px] font-black text-[#a4aea6] dark:text-[#c3c4c5] uppercase tracking-widest">Instagram</span>
 </a>
 <a :href="webSettings.social.youtube" target="_blank" class="group flex items-center gap-3 bg-[#151a22] dark:bg-[#151a22]/5 px-8 py-4 rounded-[24px] border border-[#21262d] dark:border-[#21262d] hover:border-rose-500/50 transition-all">
 <svg class="w-5 h-5 text-red-600 group-hover:scale-125 transition-transform" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
 <span class="text-[10px] font-black text-[#a4aea6] dark:text-[#c3c4c5] uppercase tracking-widest">YouTube</span>
 </a>
 </div>
 </div>
 
 <!-- Skeleton Loader Social -->
 <div v-if="!isReady" class="max-w-7xl mx-auto grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 relative z-10">
 <div v-for="i in 4" :key="i" class="bg-[#151a22] dark:bg-[#151a22] rounded-[2.5rem] animate-pulse overflow-hidden">
 <div class="p-4">
 <div class="aspect-square w-full rounded-[24px] bg-gray-200 dark:bg-[#151a22]/10"></div>
 </div>
 <div class="p-5 space-y-3">
 <div class="h-4 w-3/4 bg-gray-200 dark:bg-[#151a22]/10 rounded-[60px]"></div>
 <div class="h-3 w-full bg-gray-200 dark:bg-[#151a22]/10 rounded-[60px]"></div>
 </div>
 </div>
 </div>

 <div v-else class="max-w-7xl mx-auto grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 relative z-10">
 <div
v-for="(post, idx) in webSettings.posts" :key="post.t" 
 class="group bg-[#151a22] dark:bg-[#151a22] rounded-[2.5rem] overflow-hidden border border-[#21262d] dark:border-[#21262d] hover:border-emerald-500/40 transition-all duration-500 flex flex-col hover:"
 :style="{ animationDelay: (idx * 200) + 'ms' }">
 
 <!-- Imagen clean aspect-square rounded-[24px] como referencia -->
 <div class="p-4 pb-0">
 <div class="aspect-square w-full rounded-[24px] overflow-hidden relative bg-[#151a22] dark:bg-[#283041]">
 <img :src="post.i || 'https://images.unsplash.com/photo-1638202993928-7267aad84c31?auto=format&fit=crop&q=80&w=800'" :alt="'Publicación social de ' + post.t" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700" loading="lazy" @error="(e: any) => e.target.src = 'https://images.unsplash.com/photo-1638202993928-7267aad84c31?auto=format&fit=crop&q=80&w=800'" />
 <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent"></div>
 <!-- Likes badge -->
 <div class="absolute bottom-3 right-3 bg-black/50 backdrop-blur-md px-3.5 py-1.5 rounded-[60px] border border-white/10 flex items-center gap-2">
 <svg class="w-3 h-3 text-rose-400" fill="currentColor" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
 <span class="text-[9px] font-black text-white uppercase">{{ post.l }}</span>
 </div>
 <!-- Tag badge -->
 <div v-if="post.tag" class="absolute top-3 left-3 px-3 py-1.5 bg-emerald-500/90 text-white text-[7px] font-black rounded-[60px] uppercase tracking-[0.25em]">{{ post.tag }}</div>
 </div>
 </div>

 <!-- Content -->
 <div class="p-5 flex-1 flex flex-col gap-2">
 <!-- Header con avatar y nombre (más compacto) -->
 <div class="flex items-center gap-2.5 mb-1">
 <div class="flex items-center gap-1.5">
 <span class="text-[9px] font-black text-[#ffffff] dark:text-white uppercase tracking-wider">{{ webSettings.company_name }}</span>
 <svg class="w-3 h-3 text-emerald-500" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2zm-1 15l-5-5 1.41-1.41L11 14.17l7.59-7.59L20 8l-9 9z"/></svg>
 </div>
 </div>

 <h2 class="text-sm font-black text-[#ffffff] dark:text-white uppercase tracking-tight leading-tight group-hover:text-emerald-500 transition-colors">{{ post.t }}</h2>
 <p class="text-[9px] text-[#c3c4c5] font-bold uppercase leading-relaxed flex-1">{{ post.d }}</p>

 <!-- Stats row con separadores verticales (como referencia) -->
 <div class="flex items-center justify-around py-2.5 mt-2 bg-[#151a22] dark:bg-[#151a22]/[0.03] rounded-[6px] border border-[#21262d] dark:border-[#21262d]">
 <div class="flex flex-col items-center gap-0.5">
 <span class="text-[10px] font-black text-[#ffffff] dark:text-white">{{ post.l }}</span>
 <span class="text-[7px] font-bold text-[#c3c4c5] uppercase tracking-wider">Me Gusta</span>
 </div>
 <div class="w-px h-6 bg-gray-200 dark:bg-[#151a22]/10"></div>
 <div class="flex flex-col items-center gap-0.5">
 <span class="text-[10px] font-black text-[#ffffff] dark:text-white">{{ post.c || '24' }}</span>
 <span class="text-[7px] font-bold text-[#c3c4c5] uppercase tracking-wider">Comentarios</span>
 </div>
 <div class="w-px h-6 bg-gray-200 dark:bg-[#151a22]/10"></div>
 <div class="flex flex-col items-center gap-0.5">
 <span class="text-[10px] font-black text-[#ffffff] dark:text-white">{{ post.s || '156' }}</span>
 <span class="text-[7px] font-bold text-[#c3c4c5] uppercase tracking-wider">Compartidos</span>
 </div>
 </div>

 <!-- CTA rounded-[60px] como referencia -->
 <a
:href="post.url || '#'" target="_blank"
 class="mt-1 flex items-center justify-center gap-2 w-full py-3 bg-[#151a22] dark:bg-[#151a22]/10 hover:bg-emerald-500 text-white rounded-[60px] text-[8px] font-black uppercase tracking-[0.35em] transition-all duration-500 ">
 <span>Ver Post</span>
 <svg class="w-3 h-3 group-hover:translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
 </a>
 </div>
 </div>
 </div>
 </section>

 <!-- Wave Divider -->
 <WaveDivider color="#10b981" opacity="0.04" height="80px" />

 </main>

 <!-- Footer: Premium Boxed Design -->
 <footer class="px-6 pb-20 relative overflow-hidden">
 <div class="max-w-7xl mx-auto relative">
 <!-- Main Footer Box -->
 <div class="relative bg-gradient-to-br from-[#05080b] via-[#0a1520] to-[#0f1a12] rounded-[4rem] border border-emerald-500/10 -[0_40px_100px_-20px_rgba(16,185,129,0.2)] overflow-hidden">
 <!-- Premium noise/grid overlay -->
 <div class="absolute inset-0 opacity-[0.03]" style="background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.3) 1px, transparent 1px); background-size: 24px 24px; pointer-events: none;"></div>
 
 <!-- Glow accents more dramatic -->
 <div class="absolute -top-48 -right-48 w-[500px] h-[500px] bg-emerald-500/6 rounded-[60px] blur-[180px] pointer-events-none"></div>
 <div class="absolute -bottom-48 -left-48 w-[500px] h-[500px] bg-emerald-500/6 rounded-[60px] blur-[180px] pointer-events-none"></div>
 <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[300px] bg-emerald-500/3 rounded-[60px] blur-[200px] pointer-events-none"></div>

 <!-- Top decorative line -->
 <div class="relative h-[2px] w-3/4 mx-auto bg-gradient-to-r from-transparent via-emerald-500/30 to-transparent"></div>

 <div class="relative z-10 px-6 pt-12 pb-8 md:px-12 lg:px-20 md:pt-16 md:pb-12">
 <!-- Top Row: Brand + Tagline -->
 <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 mb-14 pb-10 border-b border-emerald-500/10">
 <div class="flex items-center gap-5">
 <div class="w-16 h-16 md:w-20 md:h-20 bg-gradient-to-br from-emerald-500 to-emerald-700 rounded-[1.5rem] md:rounded-[2rem] flex items-center justify-center text-white font-black italic text-3xl md:text-4xl -emerald-500/20 ring-2 ring-emerald-400/20 ring-offset-2 ring-offset-[#0a0f14]">
 N
 </div>
 <div>
 <h2 class="text-3xl md:text-5xl font-black text-white uppercase tracking-tighter leading-none">N3XT <span class="text-emerald-400">3D</span></h2>
 <p class="text-[9px] md:text-[10px] font-black text-emerald-500/60 uppercase tracking-[0.4em] md:tracking-[0.5em] mt-2 md:mt-3">Manufactura de Precisión</p>
 </div>
 </div>
 <p class="text-[10px] md:text-[12px] text-[#a4aea6] font-bold uppercase leading-relaxed tracking-wider max-w-md text-left md:text-right">
 El siguiente nivel de la fabricación digital en Colombia. Coleccionables de alto nivel y soluciones industriales.
 </p>
 </div>

 <!-- Content Grid -->
 <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-10 md:gap-12 lg:gap-16">
 <!-- COL 1: Navegación -->
 <div class="space-y-6 md:space-y-8">
 <div class="flex items-center gap-3">
 <div class="w-6 h-[2px] bg-emerald-500/60 rounded-[60px]"></div>
 <h2 class="text-[9px] md:text-[10px] font-black text-white/80 uppercase tracking-[0.4em] md:tracking-[0.45em]">Navegación</h2>
 </div>
 <ul class="space-y-4 md:space-y-5">
 <li v-for="link in [{n:'Inicio', p:'/'}, {n:'Catálogo', p:'/catalog'}, {n:'Cotizador', p:'/quote'}, {n:'Rastrear', p:'/track'}, {n:'Taller', p:'/admin/login'}]" :key="link.n">
 <router-link :to="link.p" class="group flex items-center gap-4 text-[11px] font-black text-[#c3c4c5] hover:text-emerald-400 uppercase tracking-[0.25em] transition-all duration-300">
 <span class="w-5 h-px bg-gray-700 group-hover:w-10 group-hover:bg-emerald-500 transition-all duration-500"></span>
 <span class="group-hover:tracking-[0.35em] transition-all duration-300">{{ link.n }}</span>
 </router-link>
 </li>
 <li>
 <router-link to="/project/init" class="group flex items-center gap-4 text-[11px] font-black text-emerald-500/70 hover:text-emerald-400 uppercase tracking-[0.25em] transition-all duration-300">
 <span class="w-5 h-px bg-emerald-500/30 group-hover:w-10 group-hover:bg-emerald-500 transition-all duration-500"></span>
 <span class="group-hover:tracking-[0.35em] transition-all duration-300">Contacto</span>
 </router-link>
 </li>
 </ul>
 </div>

 <!-- COL 2: Conecta -->
 <div class="space-y-6 md:space-y-8">
 <div class="flex items-center gap-3">
 <div class="w-6 h-[2px] bg-emerald-500/60 rounded-[60px]"></div>
 <h2 class="text-[9px] md:text-[10px] font-black text-white/80 uppercase tracking-[0.4em] md:tracking-[0.45em]">Conecta</h2>
 </div>
 <div class="space-y-3 md:space-y-4">
 <a :href="webSettings.social.tiktok" target="_blank" class="group flex items-center gap-5 p-5 bg-[#151a22]/[0.02] hover:bg-[#151a22]/[0.05] border border-white/[0.05] hover:border-emerald-500/30 rounded-[24px] transition-all duration-500">
 <div class="w-10 h-10 flex items-center justify-center bg-[#151a22]/5 rounded-[6px] group-hover:bg-black group-hover:text-white transition-all duration-500">
 <svg class="w-4 h-4 text-[#c3c4c5] group-hover:text-white" fill="currentColor" viewBox="0 0 384 382"><path d="M137.17 156.98v-15.56c-5.34-.73-10.76-1.18-16.29-1.18C54.23 140.24 0 194.47 0 261.13c0 40.9 20.43 77.09 51.61 98.97-20.12-21.6-32.46-50.53-32.46-82.31 0-65.7 52.69-119.28 118.03-120.81Z"/><path d="M140.02 333c29.74 0 54-23.66 55.1-53.13l.11-263.2h48.08c-1-5.41-1.55-10.97-1.55-16.67h-65.67l-.11 263.2c-1.1 29.47-25.36 53.13-55.1 53.13-9.24 0-17.95-2.31-25.61-6.34C105.3 323.9 121.6 333 140.02 333ZM333.13 106V91.37c-18.34 0-35.43-5.45-49.76-14.8 12.76 14.65 30.09 25.22 49.76 29.43Z"/><path d="M283.38 76.57c-13.98-16.05-22.47-37-22.47-59.91h-17.59c4.63 25.02 19.48 46.49 40.06 59.91ZM120.88 205.92c-30.44 0-55.21 24.77-55.21 55.21 0 21.2 12.03 39.62 29.6 48.86-6.55-9.08-10.45-20.18-10.45-32.2 0-30.44 24.77-55.21 55.21-55.21 5.68 0 11.13.94 16.29 2.55v-67.05c-5.34-.73-10.76-1.18-16.29-1.18-.96 0-1.9.05-2.85.07v51.49c-5.16-1.61-10.61-2.55-16.29-2.55Z"/><path d="M333.13 106v51.04c-34.05 0-65.61-10.89-91.37-29.38v133.47c0 66.66-54.23 120.88-120.88 120.88-25.76 0-49.64-8.12-69.28-21.91 22.08 23.71 53.54 38.57 88.42 38.57 66.66 0 120.88-54.23 120.88-120.88V144.33c25.76 18.49 57.32 29.38 91.37 29.38v-65.68c-6.57 0-12.97-.71-19.14-2.03Z"/></svg>
 </div>
 <div>
 <p class="text-[10px] font-black text-[#c3c4c5] group-hover:text-white uppercase tracking-widest transition-colors duration-300">TikTok</p>
 <p class="text-[7px] font-bold text-[#a4aea6] uppercase tracking-[0.25em]">@N3XT3D</p>
 </div>
 </a>
 <a :href="'https://wa.me/' + (companySettings.phone || '573118796416').replace(/\+/g, '').replace(/\s/g, '')" target="_blank" class="group flex items-center gap-5 p-5 bg-[#151a22]/[0.02] hover:bg-[#151a22]/[0.05] border border-white/[0.05] hover:border-emerald-500/30 rounded-[24px] transition-all duration-500">
 <div class="w-10 h-10 flex items-center justify-center bg-[#151a22]/5 rounded-[6px] group-hover:bg-emerald-500 group-hover:text-white transition-all duration-500">
 <svg class="w-4 h-4 text-[#c3c4c5] group-hover:text-white" fill="currentColor" viewBox="0 0 362 362"><path d="M307.546 52.566C273.709 18.684 228.706.017 180.756 0 81.951 0 1.538 80.404 1.504 179.235c-.017 31.594 8.242 62.432 23.928 89.609L0 361.736l95.024-24.925c26.179 14.285 55.659 21.805 85.655 21.814h.077c98.788 0 179.21-80.413 179.244-179.244.017-47.898-18.608-92.926-52.454-126.807v-.008Zm-126.79 275.788h-.06c-26.73-.008-52.952-7.194-75.831-20.765l-5.44-3.231-56.391 14.791 15.05-54.981-3.542-5.638c-14.912-23.721-22.793-51.139-22.776-79.286.035-82.14 66.867-148.973 149.051-148.973 39.793.017 77.198 15.53 105.328 43.695 28.131 28.157 43.61 65.596 43.593 105.398-.035 82.149-66.867 148.982-148.982 148.982v.008Zm81.719-111.577c-4.478-2.243-26.497-13.073-30.606-14.568-4.108-1.496-7.09-2.243-10.073 2.243-2.982 4.487-11.568 14.577-14.181 17.559-2.613 2.991-5.226 3.361-9.704 1.117-4.477-2.243-18.908-6.97-36.02-22.226-13.313-11.878-22.304-26.54-24.916-31.027-2.613-4.486-.275-6.91 1.959-9.136 2.011-2.011 4.478-5.234 6.721-7.847 2.244-2.613 2.983-4.486 4.478-7.469 1.496-2.991.748-5.603-.369-7.847-1.118-2.243-10.073-24.289-13.812-33.253-3.636-8.732-7.331-7.546-10.073-7.692-2.613-.13-5.595-.155-8.586-.155-2.991 0-7.839 1.118-11.947 5.604-4.108 4.486-15.677 15.324-15.677 37.361s16.047 43.344 18.29 46.335c2.243 2.991 31.585 48.225 76.51 67.632 10.684 4.615 19.029 7.374 25.535 9.437 10.727 3.412 20.49 2.931 28.208 1.779 8.604-1.289 26.498-10.838 30.228-21.298 3.73-10.46 3.73-19.433 2.613-21.298-1.117-1.865-4.108-2.991-8.586-5.234l.008-.017Z"/></svg>
 </div>
 <div>
 <p class="text-[10px] font-black text-[#c3c4c5] group-hover:text-white uppercase tracking-widest transition-colors duration-300">WhatsApp</p>
 <p class="text-[7px] font-bold text-[#a4aea6] uppercase tracking-[0.25em]">Directo</p>
 </div>
 </a>
 </div>
 </div>

 <!-- COL 3: Soporte -->
 <div class="space-y-6 md:space-y-8">
 <div class="flex items-center gap-3">
 <div class="w-6 h-[2px] bg-emerald-500/60 rounded-[60px]"></div>
 <h2 class="text-[9px] md:text-[10px] font-black text-white/80 uppercase tracking-[0.4em] md:tracking-[0.45em]">Soporte</h2>
 </div>
 <div class="bg-[#151a22]/[0.02] border border-white/[0.05] rounded-[2.5rem] p-6 space-y-5">
 <div class="flex items-center gap-5">
 <div class="w-10 h-10 bg-emerald-500/10 rounded-[6px] flex items-center justify-center">
 <svg class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
 </div>
 <div>
 <p class="text-[10px] font-black text-emerald-400 uppercase tracking-[0.3em]">Email</p>
 <p class="text-[9px] font-bold text-[#a4aea6] uppercase tracking-[0.15em]">{{ companySettings.email || 'servicion3xt@gmail.com' }}</p>
 </div>
 </div>
 <div class="flex items-center gap-5">
 <div class="w-10 h-10 bg-[#151a22]/5 rounded-[6px] flex items-center justify-center">
 <svg class="w-4 h-4 text-[#c3c4c5]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
 </div>
 <div>
 <p class="text-[10px] font-black text-[#c3c4c5] uppercase tracking-[0.3em]">Teléfono</p>
 <p class="text-[9px] font-bold text-[#a4aea6] uppercase tracking-[0.15em]">{{ companySettings.phone || '+57 311 879 6416' }}</p>
 </div>
 </div>
 </div>
 </div>

 <!-- COL 4: Estado -->
 <div class="space-y-6 md:space-y-8">
 <div class="flex items-center gap-3">
 <div class="w-6 h-[2px] bg-emerald-500/60 rounded-[60px]"></div>
 <h2 class="text-[9px] md:text-[10px] font-black text-white/80 uppercase tracking-[0.4em] md:tracking-[0.45em]">Estado</h2>
 </div>
 <div class="bg-emerald-500/[0.02] border border-emerald-500/10 rounded-[2.5rem] p-6 space-y-6">
 <div class="flex items-center justify-between">
 <div>
 <p class="text-[8px] font-black text-[#a4aea6] uppercase tracking-[0.3em] mb-3">Taller</p>
 <p class="text-[13px] font-black text-emerald-400 uppercase tracking-[0.25em]">{{ webSettings.workshop_status }}</p>
 </div>
 <span class="relative flex h-4 w-4">
 <span class="animate-ping absolute inline-flex h-full w-full rounded-[60px] bg-emerald-400 opacity-75"></span>
 <span class="relative inline-flex rounded-[60px] h-4 w-4 bg-emerald-500"></span>
 </span>
 </div>
 <div class="pt-6 border-t border-emerald-500/10">
 <router-link to="/project/init" class="group flex items-center justify-between w-full py-2">
 <span class="text-[10px] font-black text-[#c3c4c5] group-hover:text-emerald-400 uppercase tracking-[0.35em] transition-all duration-300">Iniciar Proyecto</span>
 <svg class="w-5 h-5 text-emerald-500 group-hover:translate-x-1.5 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
 </router-link>
 </div>
 </div>
 </div>
 </div>
 </div>

 <!-- Bottom Bar -->
 <div class="relative border-t border-emerald-500/10 px-6 md:px-12 lg:px-20 py-6 flex flex-col md:flex-row justify-between items-center gap-5">
 <p class="text-[8px] md:text-[9px] font-black text-[#a4aea6] uppercase tracking-[0.4em] md:tracking-[0.45em]">&copy; 2026 N3XT 3D. Fabricación Avanzada.</p>
 <div class="flex gap-8">
 <button class="text-[8px] md:text-[9px] font-black text-[#a4aea6] hover:text-emerald-400 uppercase tracking-[0.3em] md:tracking-[0.35em] transition-all duration-300 hover:tracking-[0.4em] md:hover:tracking-[0.45em]" @click="openLegal('privacy')">Privacidad</button>
 <button class="text-[8px] md:text-[9px] font-black text-[#a4aea6] hover:text-emerald-400 uppercase tracking-[0.3em] md:tracking-[0.35em] transition-all duration-300 hover:tracking-[0.4em] md:hover:tracking-[0.45em]" @click="openLegal('terms')">Términos</button>
 </div>
 </div>
 </div>
 </div>
 </footer>

 <LegalModal ref="legalModalRef" />
 </div>
</template>

<style scoped>
.technical-grid {
 background-size: 50px 50px;
 background-image: 
 linear-gradient(to right, rgba(30, 58, 52, 0.08) 1px, transparent 1px),
 linear-gradient(to bottom, rgba(30, 58, 52, 0.08) 1px, transparent 1px);
}

.dark .technical-grid {
 background-image: 
 linear-gradient(to right, rgba(30, 58, 52, 0.15) 1px, transparent 1px),
 linear-gradient(to bottom, rgba(30, 58, 52, 0.15) 1px, transparent 1px);
}

@keyframes gradient-shift {
 0% { background-position: 0% 50%; }
 50% { background-position: 100% 50%; }
 100% { background-position: 0% 50%; }
}

.animate-gradient-shift {
 animation: gradient-shift 5s ease infinite;
}
/* Transiciones Globales Ultra-Fluidas */
.smooth-reveal-enter-active {
 transition: all 1.2s cubic-bezier(0.19, 1, 0.22, 1);
}
.smooth-reveal-enter-from {
 opacity: 0;
 transform: translateY(100px) scale(0.95);
}

.fade-carousel-enter-active, .fade-carousel-leave-active {
 transition: opacity 1.5s ease-in-out;
}
.fade-carousel-enter-from, .fade-carousel-leave-to {
 opacity: 0;
}

.fade-enter-active, .fade-leave-active {
 transition: opacity 1s ease;
}
.fade-enter-from, .fade-leave-to {
 opacity: 0;
}

/* Grid overlay consistente con AppFooter */
footer::before {
 content: '';
 position: absolute;
 inset: 0;
 background-image: linear-gradient(to right, rgba(16, 185, 129, 0.03) 1px, transparent 1px),
 linear-gradient(to bottom, rgba(16, 185, 129, 0.03) 1px, transparent 1px);
 background-size: 40px 40px;
 pointer-events: none;
 z-index: 1;
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

/* Botones sin SplitText — fade-in limpio */
.btn-fade-in {
 animation: btnFadeIn 0.6s cubic-bezier(0.22, 1, 0.36, 1) forwards;
}

@keyframes btnFadeIn {
 0% {
 opacity: 0;
 transform: translateY(10px);
 }
 100% {
 opacity: 1;
 transform: translateY(0);
 }
}
</style>
