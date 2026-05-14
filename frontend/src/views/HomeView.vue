<script setup>
import { ref, reactive, onMounted, onUnmounted, watch, nextTick } from 'vue'
import { api } from '../services/api'
import AppNavbar from '../components/AppNavbar.vue'
import AppFooter from '../components/AppFooter.vue'

// --- PERFORMANCE & VISIBILITY PROTOCOL ---
const visibleSections = reactive({})
const vIntersection = {
    mounted(el, binding) {
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
const currentImgIdx = ref({})

const companyLogo = ref(null)
const showMobileMenu = ref(false)
// Por defecto DARK a menos que esté guardado explícitamente como light
const isDark = ref(localStorage.getItem('n3xt_theme') !== 'light') 

const toggleDarkMode = () => {
  isDark.value = !isDark.value
  const theme = isDark.value ? 'dark' : 'light'
  localStorage.setItem('n3xt_theme', theme)
  updateTheme()
}

const updateTheme = () => {
  if (isDark.value) {
    document.documentElement.classList.add('dark')
  } else {
    document.documentElement.classList.remove('dark')
  }
}

const webSettings = ref({
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
      tag: 'BEHIND THE SCENES',
      url: '#'
    }
  ],
  catalog: [],
  pdf_catalog_url: '',
  pdf_catalog_desc: '',
  company_name: 'N3XT 3D',
  ecosystem: [
    {
      type: 'image',
      t1: 'N3XT',
      t2: 'LAB',
      d: 'Nuestro núcleo de innovación. Donde transformamos conceptos complejos en prototipos de alta precisión usando tecnología Bambu Lab.',
      i: '/assets/n3xt_lab_render_hq_1778542148509.png'
    },
    {
      type: 'icon',
      t1: 'N3XT',
      t2: 'SHOP',
      d: 'Accede a nuestro catálogo curado de filamentos técnicos, accesorios y piezas de colección listas para entrega.',
      i: '<svg class=\"w-8 h-8\" fill=\"none\" viewBox=\"0 0 24 24\" stroke=\"currentColor\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"1.5\" d=\"M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z\"/></svg>',
      items: ['Filamentos Premium', 'Coleccionables', 'Hardware 3D']
    },
    {
      type: 'icon',
      t1: 'MAKER',
      t2: 'SPACE',
      d: 'Únete a la red de creadores más grande de Colombia. Talleres, soporte técnico y proyectos colaborativos.',
      i: '<svg class=\"w-8 h-8\" fill=\"none\" viewBox=\"0 0 24 24\" stroke=\"currentColor\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"1.5\" d=\"M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z\"/></svg>'
    }
  ]
})

const companySettings = ref({
    phone: '',
    email: '',
    address: ''
})

const isReady = ref(false)

const legalModal = ref({
    show: false,
    title: '',
    content: ''
})

const openLegal = (type) => {
    legalModal.value.show = true
    if (type === 'privacy') {
        legalModal.value.title = 'Política de Privacidad'
        legalModal.value.content = webSettings.value.privacy_policy || 'Contenido en redacción...'
    } else {
        legalModal.value.title = 'Términos y Condiciones'
        legalModal.value.content = webSettings.value.terms_conditions || 'Contenido en redacción...'
    }
}

// Lógica de Carrusel Automático para Tarjetas de Ecosistema
const activeIndices = ref([0, 0, 0])
const getImages = (iString) => iString.split(',').map(s => s.trim())

onMounted(() => {
    setInterval(() => {
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

const fetchSettings = async () => {
  try {
    const data = await api.get('/settings')
    if (data.company_logo) companyLogo.value = data.company_logo
    
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
    console.error('Error fetching settings:', err)
  } finally {
    isReady.value = true
  }
}

onMounted(() => {
  updateTheme()
  document.title = 'N3XT 3D | Manufactura Digital y Coleccionables de Alta Precisión'
  fetchSettings()
  if (!localStorage.getItem('n3xt_theme')) {
    localStorage.setItem('n3xt_theme', 'dark')
  }

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
  <div :class="{'dark': isDark}" class="min-h-screen bg-[#f8fafc] dark:bg-[#0a0f14] text-gray-900 dark:text-white overflow-x-hidden selection:bg-primary/20 transition-colors duration-500">
    <AppNavbar activeTab="home" subtext="Centro de Precisión Industrial" />

    <!-- Hero Section -->
    <section class="relative pt-20 pb-32 px-6 overflow-hidden">
        <div class="absolute inset-0 technical-grid opacity-20 dark:opacity-10 pointer-events-none"></div>
        <div class="absolute -top-24 -left-24 w-96 h-96 bg-primary/10 rounded-full blur-[120px] pointer-events-none"></div>
        <div class="absolute top-1/2 -right-24 w-96 h-96 bg-primary/5 rounded-full blur-[120px] pointer-events-none"></div>

        <div class="max-w-7xl mx-auto flex flex-col items-center text-center relative z-10">
            <div class="inline-flex items-center gap-3 px-4 py-2 bg-primary/10 rounded-full border border-primary/20 mb-10 animate-in fade-in slide-in-from-top-4 duration-700">
                <span class="w-2 h-2 bg-emerald-400 rounded-full animate-pulse shadow-[0_0_10px_#10b981]"></span>
                <span class="text-[9px] font-black text-emerald-400 dark:text-emerald-400 uppercase tracking-[0.4em]">Estudio de manufactura digital</span>
            </div>

            <h1 class="text-6xl md:text-[13rem] font-black text-slate-900 dark:text-white tracking-tighter uppercase leading-[0.75] mb-16">
                N3XT<br/>
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-600 via-emerald-400 to-emerald-600 dark:from-primary dark:via-emerald-500 dark:to-primary bg-[length:200%_auto] animate-gradient-shift italic">3D</span>
            </h1>

            <div class="max-w-5xl mx-auto mb-24 relative group px-6">
                <!-- Elementos Decorativos Técnicos -->
                <div class="absolute -top-8 -left-8 w-12 h-12 border-t-2 border-l-2 border-primary/20 rounded-tl-2xl opacity-0 group-hover:opacity-100 transition-all duration-1000 pointer-events-none"></div>
                <div class="absolute -bottom-8 -right-8 w-12 h-12 border-b-2 border-r-2 border-primary/20 rounded-br-2xl opacity-0 group-hover:opacity-100 transition-all duration-1000 pointer-events-none"></div>
                
                <!-- Aura de luz sutil -->
                <div class="absolute inset-0 bg-primary/5 blur-[120px] rounded-full opacity-40 pointer-events-none"></div>

                <p class="relative font-black text-sm md:text-3xl uppercase tracking-tighter leading-[1.05] text-center animate-in fade-in slide-in-from-bottom-12 duration-1000 delay-300">
                    <span class="text-slate-400 dark:text-gray-500">Especialistas en </span>
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-600 to-emerald-400 dark:from-primary dark:to-emerald-400 drop-shadow-[0_0_15px_rgba(16,185,129,0.2)]">fabricación digital</span>.
                    <span class="text-slate-900 dark:text-white"> Miniaturas, figuras y piezas de colección creadas con precisión industrial.</span>
                    <br class="hidden md:block" />
                    <span class="text-slate-400 dark:text-gray-500"> Inspirados en universos icónicos y el </span>
                    <span class="text-emerald-500 dark:text-emerald-400 italic">espíritu de Colombia.</span>
                </p>
                
                <!-- Footer técnico del bloque -->
                <div class="flex justify-center items-center gap-4 mt-10 opacity-20 group-hover:opacity-50 transition-opacity">
                    <div class="h-[1px] w-8 bg-primary"></div>
                    <span class="text-[8px] font-black tracking-[0.6em] text-primary uppercase">N3XT System Core</span>
                    <div class="h-[1px] w-8 bg-primary"></div>
                </div>

                <div class="flex flex-col md:flex-row items-center justify-center gap-8 animate-in fade-in zoom-in duration-1000 mt-16">
                    <!-- Botón: Iniciar Proyecto (Elite Luxury) -->
                    <router-link to="/project/init" class="group relative px-20 py-8 bg-gray-900 dark:bg-primary text-white rounded-2xl font-black text-xs uppercase tracking-[0.5em] overflow-hidden transition-all hover:-translate-y-1 hover:scale-105 active:scale-95 shadow-[0_20px_50px_-15px_rgba(16,185,129,0.4)]">
                        <span class="relative z-10 flex items-center gap-4">
                            INICIAR PROYECTO 3D
                            <span class="relative flex h-2 w-2">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-white opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-white"></span>
                            </span>
                        </span>
                        <!-- Efecto de Brillo (Shine) -->
                        <div class="absolute inset-0 bg-gradient-to-tr from-transparent via-white/20 to-transparent -translate-x-[200%] group-hover:translate-x-[200%] transition-transform duration-1000 ease-in-out"></div>
                        <div class="absolute inset-0 bg-gradient-to-r from-emerald-500 to-primary opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    </router-link>

                    <!-- Botón: Ver Catálogo (Elite Ghost) -->
                    <router-link to="/catalog" class="group relative px-20 py-8 bg-black/[0.03] dark:bg-white/5 backdrop-blur-2xl text-gray-800 dark:text-white/50 rounded-2xl font-black text-xs uppercase tracking-[0.5em] overflow-hidden transition-all hover:-translate-y-1 hover:text-gray-900 dark:hover:text-white border border-black/10 dark:border-white/5 hover:border-black/30 dark:hover:border-white/20">
                        <span class="relative z-10 flex items-center gap-4">
                            VER CATÁLOGO
                            <svg class="w-5 h-5 opacity-0 -translate-x-4 group-hover:opacity-100 group-hover:translate-x-0 transition-all duration-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                            </svg>
                        </span>
                        <div class="absolute inset-0 bg-black/5 dark:bg-white/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    </router-link>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section (Estilo Industrial Refinado) -->
    <section class="py-24 bg-gray-50 dark:bg-[#05080b] border-y border-gray-100 dark:border-white/5 relative overflow-hidden">
        <div class="absolute inset-0 technical-grid opacity-5"></div>
        <div class="max-w-7xl mx-auto grid grid-cols-2 lg:grid-cols-4 gap-12 px-6 relative z-10">
            <div v-for="stat in [{val: '+10K', label: 'Piezas Fabricadas'}, {val: '24H', label: 'Prototipado Rapido'}, {val: '8K', label: 'Resolucion Resina'}, {val: '100%', label: 'Acabado Manual'}]" :key="stat.label" class="group relative">
                <p class="text-5xl md:text-7xl font-black text-slate-900 dark:text-white tracking-tighter mb-2 group-hover:text-emerald-500 transition-all duration-500 transform group-hover:scale-110 origin-left italic">{{ stat.val }}</p>
                <div class="flex items-center gap-3">
                    <p class="text-[10px] font-black text-slate-400 dark:text-gray-600 uppercase tracking-[0.3em]">{{ stat.label }}</p>
                </div>
                <div class="absolute -bottom-4 left-0 w-12 h-1 bg-emerald-500/20 group-hover:w-full transition-all duration-700"></div>
            </div>
        </div>
    </section>

    <!-- Sección: Fabricación Digital -->
    <section class="py-32 bg-white dark:bg-[#0a0f14] px-6 relative overflow-hidden">
        <div class="absolute top-1/2 left-0 w-full h-px bg-gradient-to-r from-transparent via-primary/20 to-transparent"></div>
        <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-24 items-center">
            <div class="relative group">
                <div class="aspect-square bg-gray-900 rounded-[4.5rem] overflow-hidden shadow-[0_50px_100px_-20px_rgba(30,58,52,0.3)] relative border border-white/10">
                    <img src="/assets/n3xt_industrial_maker_figures_1778542131405.png" alt="Figuras coleccionables de anime y videojuegos fabricadas por N3XT 3D" class="w-full h-full object-cover group-hover:scale-110 transition-all duration-[2s]" />
                    <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent opacity-60"></div>
                    <div class="absolute bottom-12 left-12 text-white">
                        <p class="text-[10px] font-black uppercase tracking-[0.4em] mb-3 text-primary">FABRICA TU PEDIDO</p>
                        <h3 class="text-4xl font-black uppercase tracking-tighter italic">N3XT <span class="text-primary">3D</span></h3>
                    </div>
                </div>
                <div class="absolute -top-6 -right-6 w-32 h-32 border-t-2 border-r-2 border-primary/40 rounded-tr-[3rem]"></div>
                <div class="absolute -bottom-6 -left-6 w-32 h-32 border-b-2 border-l-2 border-primary/40 rounded-bl-[3rem]"></div>
            </div>

            <div class="space-y-10 text-left">
                <div class="inline-block px-5 py-2 bg-emerald-500/10 rounded-full border border-emerald-500/30 shadow-[0_0_15px_rgba(16,185,129,0.1)]">
                    <span class="text-[9px] font-black text-emerald-400 uppercase tracking-[0.4em]">¿QUÉ HACEMOS?</span>
                </div>
                <h2 class="text-5xl md:text-7xl font-black text-gray-900 dark:text-white tracking-tighter uppercase leading-[0.9]">
                    EL SIGUIENTE NIVEL DE LA<br/>
                    <span class="text-gray-400 dark:text-gray-500 italic">FABRICACIÓN DIGITAL.</span>
                </h2>
                <p class="text-gray-500 dark:text-gray-400 font-bold text-lg leading-relaxed uppercase tracking-tight">
                    En N3XT no solo fabricamos piezas; damos vida a universos, personajes y proyectos personalizados con acabados premium y detalle profesional.
                </p>
                <div class="grid grid-cols-1 gap-10 pt-12">
                    <!-- Categoría 1: Figuras -->
                    <div class="group p-6 bg-white dark:bg-white/10 backdrop-blur-2xl rounded-[2.5rem] border border-slate-200 dark:border-white/10 hover:border-emerald-500/50 transition-all duration-700 relative overflow-hidden hover:-translate-y-2 shadow-sm hover:shadow-xl">
                        <div class="absolute -right-12 -top-12 w-32 h-32 bg-emerald-500/10 rounded-full blur-3xl group-hover:bg-emerald-500/20 transition-all duration-700"></div>
                        <div class="flex items-center gap-6 relative z-10">
                            <div class="flex-shrink-0 w-16 h-16 bg-emerald-500/10 rounded-xl flex items-center justify-center border border-emerald-500/20 shadow-[0_0_20px_rgba(16,185,129,0.1)] group-hover:bg-emerald-500 group-hover:text-white transition-all duration-500">
                                <svg class="w-8 h-8 text-emerald-400 group-hover:text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
                            </div>
                            <div class="text-left">
                                <h4 class="text-2xl font-black text-slate-900 dark:text-white uppercase tracking-tighter mb-2 italic">Figuras <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-500 to-emerald-700 dark:from-emerald-400 dark:to-emerald-600">Premium</span></h4>
                                <p class="text-xs text-slate-400 dark:text-gray-400 font-bold uppercase leading-relaxed tracking-tight mb-4 max-w-md">Piezas artísticas en alta resolución con acabados de museo.</p>
                                <div class="flex flex-wrap gap-2">
                                    <span v-for="tag in ['Resina SLA', 'Aerografia', '8K Detail']" :key="tag" class="text-[9px] font-black px-3 py-1 bg-emerald-500/5 dark:bg-black/40 rounded-full border border-emerald-500/20 uppercase tracking-[0.2em] text-emerald-600 shadow-inner">{{ tag }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Categoría 2: Impresión 3D -->
                    <div class="group p-6 bg-white dark:bg-white/10 backdrop-blur-2xl rounded-[2.5rem] border border-slate-200 dark:border-white/10 hover:border-emerald-500/50 transition-all duration-700 relative overflow-hidden hover:-translate-y-2 shadow-sm hover:shadow-xl">
                        <div class="absolute -right-12 -top-12 w-32 h-32 bg-emerald-500/10 rounded-full blur-3xl group-hover:bg-emerald-500/20 transition-all duration-700"></div>
                        <div class="flex items-center gap-6 relative z-10">
                            <div class="flex-shrink-0 w-16 h-16 bg-emerald-500/10 rounded-xl flex items-center justify-center border border-emerald-500/20 shadow-[0_0_20px_rgba(16,185,129,0.1)] group-hover:bg-emerald-500 group-hover:text-white transition-all duration-500">
                                <svg class="w-8 h-8 text-emerald-400 group-hover:text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </div>
                            <div class="text-left">
                                <h4 class="text-2xl font-black text-slate-900 dark:text-white uppercase tracking-tighter mb-2 italic">Fabricación <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-500 to-emerald-700 dark:from-emerald-400 dark:to-emerald-600">Multimaterial</span></h4>
                                <p class="text-xs text-slate-400 dark:text-gray-400 font-bold uppercase leading-relaxed tracking-tight mb-4 max-w-md">Flota de alta gama para resultados industriales.</p>
                                <div class="flex flex-wrap gap-2">
                                    <span v-for="tag in ['Bambulab', 'Elegoo', 'Creality', 'Anycubic']" :key="tag" class="text-[9px] font-black px-3 py-1 bg-emerald-500/5 dark:bg-black/40 rounded-full border border-emerald-500/20 uppercase tracking-[0.2em] text-emerald-600 shadow-inner">{{ tag }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Categoría 3: Prototipos -->
                    <div class="group p-6 bg-white dark:bg-white/10 backdrop-blur-2xl rounded-[2.5rem] border border-slate-200 dark:border-white/10 hover:border-emerald-400/50 transition-all duration-700 relative overflow-hidden hover:-translate-y-2 shadow-sm hover:shadow-xl">
                        <div class="absolute -right-12 -top-12 w-32 h-32 bg-emerald-400/10 rounded-full blur-3xl group-hover:bg-emerald-400/20 transition-all duration-700"></div>
                        <div class="flex items-center gap-6 relative z-10">
                            <div class="flex-shrink-0 w-16 h-16 bg-emerald-400/10 rounded-xl flex items-center justify-center border border-emerald-400/20 shadow-[0_0_20px_rgba(52,211,153,0.1)] group-hover:bg-emerald-400 group-hover:text-white transition-all duration-500">
                                <svg class="w-8 h-8 text-emerald-400 group-hover:text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z"/></svg>
                            </div>
                            <div class="text-left">
                                <h4 class="text-2xl font-black text-slate-900 dark:text-white uppercase tracking-tighter mb-2 italic">Prototipos <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-500 to-primary">Funcionales</span></h4>
                                <p class="text-xs text-slate-400 dark:text-gray-400 font-bold uppercase leading-relaxed tracking-tight mb-4 max-w-md">Ingeniería aplicada con precisión mecánica industrial.</p>
                                <div class="flex flex-wrap gap-2">
                                    <span v-for="tag in ['Ingenieria', 'Nylon / PETG', 'Stress Test']" :key="tag" class="text-[9px] font-black px-3 py-1 bg-emerald-400/5 dark:bg-black/40 rounded-full border border-emerald-400/20 uppercase tracking-[0.2em] text-emerald-600 shadow-inner">{{ tag }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Sección: Proceso de Fabricación N3XT (Numbered Steps) -->
    <section class="py-40 bg-gray-50 dark:bg-[#05080b] px-6 relative overflow-hidden">
        <div class="absolute inset-0 technical-grid opacity-10 pointer-events-none"></div>
        <div class="absolute top-0 left-0 w-full h-px bg-gradient-to-r from-transparent via-primary to-transparent opacity-30"></div>
        
        <div class="max-w-7xl mx-auto mb-32 text-center relative z-10">
            <h2 class="text-7xl md:text-9xl font-black text-slate-400 dark:text-white/30 tracking-tighter uppercase italic leading-[0.8] mb-8 transition-colors duration-500">
                NUESTRO <span class="text-emerald-600 dark:text-emerald-400">PROCESO</span><br/>
                <span class="text-slate-500 dark:text-white/40 transition-all duration-700">DE FABRICACIÓN.</span>
            </h2>
            <div class="flex items-center justify-center gap-4">
                <div class="h-px w-12 bg-gradient-to-r from-transparent to-emerald-500/50"></div>
                <p class="text-[10px] md:text-xs text-emerald-400/80 font-black uppercase tracking-[0.6em]">N3XT • DISEÑO, PRECISIÓN Y DETALLE</p>
                <div class="h-px w-12 bg-gradient-to-l from-transparent to-emerald-500/50"></div>
            </div>
        </div>

        <div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 relative z-10">
            <!-- Paso 01 -->
            <div class="group relative p-10 bg-white dark:bg-white/5 backdrop-blur-xl rounded-[2.5rem] border border-slate-200 dark:border-white/10 hover:border-emerald-500/50 transition-all duration-500 overflow-hidden shadow-sm hover:shadow-xl">
                <div class="absolute -right-4 -top-4 text-8xl font-black text-slate-200 dark:text-white/10 group-hover:text-emerald-500/20 transition-all duration-500 tracking-tighter italic">01</div>
                <div class="mb-8 w-14 h-14 bg-emerald-500/10 rounded-2xl flex items-center justify-center border border-emerald-500/20 group-hover:bg-emerald-500 transition-all duration-500 shadow-lg">
                    <svg class="w-7 h-7 text-emerald-400 group-hover:text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.989-2.386l-.548-.547z"/></svg>
                </div>
                <h4 class="text-xl font-black text-slate-900 dark:text-white uppercase tracking-tighter mb-4 italic">Envíanos tu <span class="text-emerald-500 dark:text-emerald-400">Idea</span></h4>
                <p class="text-xs text-slate-400 dark:text-gray-400 leading-relaxed font-bold uppercase tracking-tight">
                    Compártenos tu idea, imagen o archivo 3D. Si aún no tienes el diseño listo, <span class="text-slate-900 dark:text-white/60">te ayudamos a desarrollarlo.</span>
                </p>
                <div class="mt-8 h-1 w-0 bg-emerald-500 group-hover:w-full transition-all duration-1000 rounded-full shadow-[0_0_10px_rgba(16,185,129,0.5)]"></div>
            </div>

            <!-- Paso 02 -->
            <div class="group relative p-10 bg-white dark:bg-white/5 backdrop-blur-xl rounded-[2.5rem] border border-slate-200 dark:border-white/10 hover:border-emerald-500/50 transition-all duration-500 overflow-hidden shadow-sm hover:shadow-xl">
                <div class="absolute -right-4 -top-4 text-8xl font-black text-slate-200 dark:text-white/10 group-hover:text-emerald-500/20 transition-all duration-500 tracking-tighter italic">02</div>
                <div class="mb-8 w-14 h-14 bg-emerald-500/10 rounded-2xl flex items-center justify-center border border-emerald-500/20 group-hover:bg-emerald-500 transition-all duration-500 shadow-lg">
                    <svg class="w-7 h-7 text-emerald-400 group-hover:text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </div>
                <h4 class="text-xl font-black text-slate-900 dark:text-white uppercase tracking-tighter mb-4 italic">Asesoría <span class="text-emerald-500 dark:text-emerald-400">Personalizada</span></h4>
                <p class="text-xs text-slate-400 dark:text-gray-400 leading-relaxed font-bold uppercase tracking-tight">
                    Analizamos tu proyecto y te recomendamos el <span class="text-slate-900 dark:text-white/60">material, tamaño y tipo de fabricación</span> ideal para tu pieza.
                </p>
                <div class="mt-8 h-1 w-0 bg-emerald-500 group-hover:w-full transition-all duration-1000 rounded-full shadow-[0_0_10px_rgba(16,185,129,0.5)]"></div>
            </div>

            <!-- Paso 03 -->
            <div class="group relative p-10 bg-white dark:bg-white/5 backdrop-blur-xl rounded-[2.5rem] border border-slate-200 dark:border-white/10 hover:border-emerald-500/50 transition-all duration-500 overflow-hidden shadow-sm hover:shadow-xl">
                <div class="absolute -right-4 -top-4 text-8xl font-black text-slate-200 dark:text-white/10 group-hover:text-emerald-500/20 transition-all duration-500 tracking-tighter italic">03</div>
                <div class="mb-8 w-14 h-14 bg-emerald-500/10 rounded-2xl flex items-center justify-center border border-emerald-500/20 group-hover:bg-emerald-500 transition-all duration-500 shadow-lg">
                    <svg class="w-7 h-7 text-emerald-400 group-hover:text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <h4 class="text-xl font-black text-slate-900 dark:text-white uppercase tracking-tighter mb-4 italic">Recibe tu <span class="text-emerald-500 dark:text-emerald-400">Cotización</span></h4>
                <p class="text-xs text-slate-400 dark:text-gray-400 leading-relaxed font-bold uppercase tracking-tight">
                    Te enviamos una <span class="text-slate-900 dark:text-white/60">cotización clara</span> según complejidad, materiales, acabados y tiempo de fabricación.
                </p>
                <div class="mt-8 h-1 w-0 bg-emerald-500 group-hover:w-full transition-all duration-1000 rounded-full shadow-[0_0_10px_rgba(16,185,129,0.5)]"></div>
            </div>

            <!-- Paso 04 -->
            <div class="group relative p-10 bg-white dark:bg-white/5 backdrop-blur-xl rounded-[2.5rem] border border-slate-200 dark:border-white/10 hover:border-emerald-500/50 transition-all duration-500 overflow-hidden shadow-sm hover:shadow-xl">
                <div class="absolute -right-4 -top-4 text-8xl font-black text-slate-200 dark:text-white/10 group-hover:text-emerald-500/20 transition-all duration-500 tracking-tighter italic">04</div>
                <div class="mb-8 w-14 h-14 bg-emerald-500/10 rounded-2xl flex items-center justify-center border border-emerald-500/20 group-hover:bg-emerald-500 transition-all duration-500 shadow-lg">
                    <svg class="w-7 h-7 text-emerald-400 group-hover:text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 13l4 4L19 7"/></svg>
                </div>
                <h4 class="text-xl font-black text-slate-900 dark:text-white uppercase tracking-tighter mb-4 italic">Fabricación y <span class="text-emerald-500 dark:text-emerald-400">Entrega</span></h4>
                <p class="text-xs text-slate-400 dark:text-gray-400 leading-relaxed font-bold uppercase tracking-tight">
                    Fabricamos tu pieza con equipos profesionales y la entregamos con <span class="text-slate-900 dark:text-white/60">acabados premium y detalle profesional.</span>
                </p>
                <div class="mt-8 h-1 w-0 bg-emerald-500 group-hover:w-full transition-all duration-1000 rounded-full shadow-[0_0_10px_rgba(16,185,129,0.5)]"></div>
            </div>
        </div>

        <div class="max-w-7xl mx-auto mt-32 text-center relative z-10">
            <router-link to="/project/init" class="inline-flex items-center gap-6 px-16 py-7 bg-slate-900 dark:bg-white text-white dark:text-gray-950 rounded-full font-black text-xs uppercase tracking-[0.4em] hover:bg-emerald-500 dark:hover:bg-emerald-500 hover:text-white transition-all duration-500 shadow-[0_20px_50px_-10px_rgba(16,185,129,0.3)] hover:scale-105">
                INICIAR PROYECTO 3D <span class="text-xl">→</span>
            </router-link>
        </div>
    </section>

    <!-- Sección: Proceso Artesanal -->
    <section class="py-40 bg-slate-50 dark:bg-gray-950 relative overflow-hidden transition-colors duration-500">
        <div class="absolute inset-0 opacity-10 dark:opacity-10 technical-grid pointer-events-none"></div>
        <div class="absolute -right-40 top-0 w-96 h-96 bg-emerald-500/10 rounded-full blur-[120px]"></div>
        
        <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-24 items-center px-6">
            <div class="order-2 lg:order-1 space-y-12">
                <div class="inline-flex items-center gap-4 px-6 py-2 bg-emerald-500/10 rounded-full border border-emerald-500/20">
                    <span class="text-[10px] font-black text-emerald-600 dark:text-emerald-400 uppercase tracking-[0.4em]">Acabado artesanal</span>
                </div>
                <h2 class="text-6xl md:text-[5.5rem] font-black text-slate-900 dark:text-white tracking-tighter uppercase leading-[0.8]">
                    EL ARTE DE LA<br/>
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-600 to-emerald-400 italic">POST-PRODUCCIÓN.</span>
                </h2>
                <p class="text-slate-500 dark:text-white/50 font-bold text-xl uppercase tracking-tight leading-relaxed max-w-xl">
                    Cada pieza pasa por un proceso de <span class="text-slate-900 dark:text-white">curado, lijado y acabado manual</span> para lograr superficies limpias, detalles definidos y una <span class="text-emerald-600 dark:text-emerald-400">apariencia premium.</span>
                </p>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 max-w-2xl">
                    <div v-for="step in [
                        {n: 'Curado UV Pro', code: 'UV', icon: '<svg class=\'w-7 h-7\' fill=\'none\' viewBox=\'0 0 24 24\' stroke=\'currentColor\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'1.5\' d=\'M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m12.728 0l-.707-.707M6.343 6.343l-.707-.707M12 8a4 4 0 100 8 4 4 0 000-8z\'/></svg>'}, 
                        {n: 'Lijado fino', code: 'FINISH', icon: '<svg class=\'w-7 h-7\' fill=\'none\' viewBox=\'0 0 24 24\' stroke=\'currentColor\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'1.5\' d=\'M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z\'/></svg>'}, 
                        {n: 'Imprimacion', code: 'BASE', icon: '<svg class=\'w-7 h-7\' fill=\'none\' viewBox=\'0 0 24 24\' stroke=\'currentColor\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'1.5\' d=\'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10\'/></svg>'}, 
                        {n: 'Aerografia', code: 'PREM', icon: '<svg class=\'w-7 h-7\' fill=\'none\' viewBox=\'0 0 24 24\' stroke=\'currentColor\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'1.5\' d=\'M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z\'/></svg>'}
                    ]" :key="step.n" class="group flex flex-col items-center justify-center text-center gap-4 bg-white dark:bg-white/5 backdrop-blur-xl border border-slate-200 dark:border-white/10 p-6 rounded-[2rem] hover:border-emerald-500/50 transition-all duration-500 shadow-sm hover:shadow-2xl relative overflow-hidden aspect-square">
                        <div class="w-14 h-14 bg-slate-100 dark:bg-gray-900 rounded-2xl flex items-center justify-center group-hover:bg-emerald-500 transition-all duration-500 border border-slate-200 dark:border-white/5 shadow-inner">
                            <span v-html="step.icon" class="text-emerald-500 group-hover:text-white transition-all duration-500 group-hover:scale-110"></span>
                        </div>
                        <span class="text-slate-900 dark:text-white font-black text-[10px] uppercase tracking-[0.2em] leading-tight max-w-[80px]">{{ step.n }}</span>
                        <div class="absolute inset-x-0 bottom-0 h-1 w-0 bg-emerald-500 group-hover:w-full transition-all duration-700"></div>
                    </div>
                </div>
            </div>
            <div class="order-1 lg:order-2">
                <div class="aspect-[4/3] bg-white/5 rounded-[4rem] overflow-hidden border border-white/10 shadow-2xl relative group transform hover:rotate-2 transition-all duration-700">
                    <img src="/assets/n3xt_airbrush_process_hq_1778542148509.png" alt="Proceso de aerografía y pintura artesanal en figuras 3D" class="w-full h-full object-cover group-hover:scale-125 transition-transform duration-[2.5s]" />
                    <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent opacity-60"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Sección: Ecosistema N3XT 3D (Rediseño Premium Industrial) -->
    <section v-intersection="'ecosystem'" class="py-40 bg-white dark:bg-[#0a0f14] px-6 relative overflow-hidden min-h-[600px]">
        <transition name="smooth-reveal">
            <div v-if="visibleSections['ecosystem']" class="w-full h-full">
                <div class="absolute inset-0 technical-grid opacity-5 pointer-events-none"></div>
                <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[800px] h-[400px] bg-primary/10 blur-[150px] rounded-full pointer-events-none"></div>
                
                <div class="max-w-7xl mx-auto mb-32 text-center relative z-10">
                    <h2 class="text-6xl md:text-[8rem] font-black text-slate-400 dark:text-white/30 tracking-tighter uppercase italic leading-[0.8] mb-8 transition-all duration-500">
                        EL <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-600 to-emerald-400">ECOSISTEMA</span><br/>
                        <span class="text-slate-500 dark:text-white/40">N3XT 3D.</span>
                    </h2>
                    <p class="text-[10px] md:text-sm text-gray-400 font-bold uppercase tracking-[0.5em]">Manufactura Avanzada • Cultura Maker • Innovación Digital</p>
                </div>

        <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-12 relative z-10">
            <!-- Card Iterativa Dinámica -->
            <div v-for="(card, cidx) in webSettings.ecosystem" :key="cidx" 
                 :class="[
                    'group relative rounded-[4.5rem] p-10 md:p-14 border transition-all duration-700 flex flex-col shadow-2xl hover:-translate-y-6 overflow-hidden',
                    cidx === 1 ? 'bg-gray-950 border-white/5 hover:border-primary/50 justify-between' : 'bg-gray-50 dark:bg-white/5 border-gray-100 dark:border-white/10 hover:border-primary/40'
                 ]">
                <div class="absolute inset-0 bg-scanline opacity-0 group-hover:opacity-10 transition-opacity pointer-events-none"></div>
                
                <div v-if="cidx === 0" class="absolute -top-6 -right-6 px-8 py-3 bg-emerald-500 text-white text-[10px] font-black rounded-3xl uppercase tracking-[0.4em] shadow-2xl shadow-emerald-500/40 transform -rotate-3 group-hover:rotate-0 transition-all z-20">NOVEDADES</div>
                
                <!-- Contenido Tipo Imagen (Soporta Carrusel si hay comas) -->
                <div v-if="card.type === 'image'" class="w-full aspect-[4/5] bg-gray-900 rounded-[3.5rem] mb-12 overflow-hidden border border-white/5 relative group-hover:shadow-[0_40px_80px_-20px_rgba(30,58,52,0.4)] transition-all">
                    <div class="relative w-full h-full">
                        <transition-group name="fade">
                            <img v-for="(img, imgIdx) in getImages(card.i)" 
                                 :key="img" 
                                 v-show="activeIndices[cidx] === imgIdx"
                                 :src="img" 
                                 :alt="'Proyecto de ecosistema N3XT 3D - ' + card.t1"
                                 class="absolute inset-0 w-full h-full object-cover group-hover:scale-125 transition-transform duration-[2s]" />
                        </transition-group>
                    </div>
                    
                    <div class="absolute inset-0 bg-gradient-to-t from-black via-black/20 to-transparent"></div>
                    
                    <!-- Indicadores de Carrusel (Solo si hay varias fotos) -->
                    <div v-if="getImages(card.i).length > 1" class="absolute top-6 left-1/2 -translate-x-1/2 flex gap-1.5 z-30">
                        <div v-for="(_, dotIdx) in getImages(card.i)" :key="dotIdx" 
                             :class="['h-1 rounded-full transition-all duration-500', activeIndices[cidx] === dotIdx ? 'w-6 bg-primary' : 'w-2 bg-white/20']"></div>
                    </div>

                    <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-700 pointer-events-none">
                        <div class="w-20 h-20 border-2 border-primary/50 rounded-full animate-ping"></div>
                    </div>
                    <div class="absolute bottom-10 left-0 right-0 px-8">
                        <div class="bg-white/10 backdrop-blur-2xl p-4 rounded-3xl border border-white/20 flex items-center justify-between">
                            <span class="text-[9px] font-black text-white uppercase tracking-widest">Tienda N3XT 3D</span>
                            <div class="flex gap-1">
                                <div v-for="i in 3" :key="i" class="w-1 h-1 bg-primary rounded-full animate-pulse" :style="{animationDelay: i*0.2+'s'}"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contenido Tipo Icono -->
                <div v-else-if="card.type === 'icon'" class="w-20 h-20 bg-emerald-500/10 rounded-[2rem] flex items-center justify-center mb-12 border border-emerald-500/20 shadow-inner group-hover:bg-emerald-500 group-hover:text-white transition-all duration-500 relative z-10">
                    <span v-html="card.i" class="text-emerald-500 group-hover:text-white transition-colors"></span>
                </div>

                <div class="relative z-10 flex-1">
                    <h3 :class="['text-3xl md:text-4xl font-black uppercase tracking-tighter mb-6 italic leading-none', cidx === 1 ? 'text-white' : 'text-gray-900 dark:text-white']">
                        {{ card.t1 }}<br/>
                        <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 to-emerald-600">{{ card.t2 }}</span>
                    </h3>
                    <p class="text-[12px] md:text-sm text-gray-400 font-bold uppercase leading-relaxed tracking-wide mb-8">
                        {{ card.d }}
                    </p>
                </div>

                <!-- Lista de items para la tarjeta 2 -->
                <div v-if="cidx === 1 && card.items" class="relative z-10 space-y-4">
                    <div v-for="item in card.items" :key="item" class="flex items-center gap-4 text-[10px] font-black text-white/40 uppercase tracking-[0.3em] group-hover:text-white/80 transition-colors">
                        <span class="w-10 h-px bg-primary/30 group-hover:w-16 transition-all duration-500"></span> {{ item }}
                    </div>
                </div>

                <!-- Efecto visual extra para Card 3 -->
                <div v-if="cidx === 2" class="relative z-10 pt-12 border-t border-gray-200 dark:border-white/10">
                    <div class="flex items-center gap-4 mb-4">
                        <span class="text-[10px] font-black text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 px-5 py-2 rounded-full uppercase tracking-[0.3em] animate-pulse shadow-[0_0_25px_rgba(16,185,129,0.15)]">FIRMA N3XT</span>
                    </div>
                    <p class="text-xs font-black text-gray-900 dark:text-white uppercase italic tracking-wider">El siguiente nivel de la manufactura</p>
                </div>

            </div>
        </div>
        </div>
        </transition>
    </section>

    <!-- Sección: Social Media Hub (Tus últimas publicaciones) -->
    <!-- SECCIÓN 1: NOVEDADES 3D COLOMBIA (Editorial) -->
    <section v-if="webSettings.news.length > 0" id="news" v-intersection="'news'" class="py-32 px-6 md:px-12 bg-white dark:bg-[#0a0f14] relative overflow-hidden transition-colors duration-500 min-h-[600px]">
        <transition name="smooth-reveal">
            <div v-if="visibleSections['news']" class="w-full h-full">
                <div class="absolute top-0 left-1/4 w-96 h-96 bg-primary/5 rounded-full blur-[120px] pointer-events-none"></div>
                
                <div class="max-w-7xl mx-auto mb-24 flex flex-col md:flex-row md:items-end justify-between gap-8">
                    <div class="relative z-10">
                        <div class="inline-flex items-center gap-3 px-5 py-2 bg-emerald-500/10 rounded-full border border-emerald-500/30 mb-6 shadow-[0_0_20px_rgba(16,185,129,0.1)]">
                            <span class="w-2.5 h-2.5 bg-emerald-400 rounded-full animate-pulse shadow-[0_0_12px_#10b981]"></span>
                            <span class="text-[10px] font-black text-emerald-400 uppercase tracking-[0.5em]">NOTICIAS LOCALES</span>
                        </div>
                        <h2 class="text-4xl md:text-[6rem] font-black text-slate-400 dark:text-white/30 tracking-tighter uppercase italic mb-4 leading-[0.8] transition-all duration-500">
                            NOVEDADES <br class="md:hidden"/> <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-600 to-emerald-400">3D COLOMBIA</span>
                        </h2>
                        <p class="text-[10px] md:text-sm text-slate-500 dark:text-gray-500 font-bold uppercase tracking-[0.3em] flex items-center gap-3">
                            <span class="w-8 h-[2px] bg-emerald-500"></span>
                            El pulso de la manufactura nacional
                        </p>
                    </div>
                    <a href="https://www.3dnatives.com/es/" target="_blank" class="group flex items-center gap-4 text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest hover:text-primary transition-all">
                        Explorar Más Noticias
                        <span class="w-12 h-12 rounded-full border border-gray-200 dark:border-white/10 flex items-center justify-center group-hover:bg-primary group-hover:text-white transition-all text-xl">→</span>
                    </a>
                </div>

                <!-- Skeleton Loader during fetch -->
                <div v-if="!isReady" class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                    <div v-for="i in 4" :key="i" class="bg-gray-100 dark:bg-white/5 rounded-[3.5rem] h-[500px] animate-pulse overflow-hidden">
                        <div class="h-56 bg-gray-200 dark:bg-white/10"></div>
                        <div class="p-10 space-y-4">
                            <div class="h-6 w-3/4 bg-gray-200 dark:bg-white/10 rounded-full"></div>
                            <div class="h-4 w-full bg-gray-200 dark:bg-white/10 rounded-full"></div>
                            <div class="h-4 w-1/2 bg-gray-200 dark:bg-white/10 rounded-full"></div>
                        </div>
                    </div>
                </div>

                <div v-else class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                    <div v-for="(item, idx) in webSettings.news" :key="item.t" 
                        class="group bg-white dark:bg-white/5 backdrop-blur-xl rounded-[3.5rem] overflow-hidden border border-slate-100 dark:border-white/10 hover:border-emerald-500/50 transition-all duration-700 flex flex-col shadow-2xl hover:-translate-y-4"
                        :style="{ animationDelay: (idx * 150) + 'ms' }">
                        <div class="h-64 overflow-hidden relative bg-slate-100 dark:bg-white/5">
                            <img :src="item.i" 
                                 :alt="'Noticia: ' + item.t"
                                 class="w-full h-full object-cover group-hover:scale-125 transition-transform duration-[2s]" 
                                 @error="(e) => e.target.src = 'https://images.unsplash.com/photo-1631033031102-f855d4872494?auto=format&fit=crop&q=80&w=800'"
                            />
                            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent"></div>
                            <div class="absolute top-8 left-8">
                                <span class="px-5 py-2 bg-emerald-500 text-white text-[9px] font-black uppercase tracking-[0.2em] rounded-full shadow-2xl shadow-emerald-500/20">{{ item.tag }}</span>
                            </div>
                        </div>
                        <div class="p-10 flex-1 flex flex-col justify-between">
                            <div>
                                <h4 class="text-2xl font-black text-slate-900 dark:text-white uppercase tracking-tighter mb-4 italic group-hover:text-emerald-500 transition-colors leading-tight">{{ item.t }}</h4>
                                <p class="text-[11px] text-slate-500 dark:text-gray-400 font-bold leading-relaxed mb-10 uppercase tracking-tight">{{ item.d }}</p>
                            </div>
                            <a :href="item.url || '#'" target="_blank" class="relative group/btn w-full py-5 bg-slate-900 dark:bg-white/10 border border-slate-800 dark:border-white/10 rounded-2xl overflow-hidden transition-all shadow-xl text-center">
                                <span class="relative z-10 text-[9px] font-black text-white uppercase tracking-[0.3em]">Leer Artículo Completo</span>
                                <div class="absolute inset-0 bg-emerald-500 translate-x-[-100%] group-hover/btn:translate-x-0 transition-transform duration-500"></div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </section>

    <!-- SECCIÓN 2: SOCIAL HUB N3XT (TikTok/Instagram Feed) -->
    <section v-if="webSettings.posts.length > 0" class="py-32 bg-white dark:bg-[#0a0f14] px-6 overflow-hidden relative transition-colors duration-500">
        <div class="absolute inset-0 technical-grid opacity-5 pointer-events-none"></div>
        
        <div class="max-w-7xl mx-auto text-center mb-24 relative z-10">
            <div class="inline-flex items-center gap-3 px-5 py-2 bg-emerald-500/10 rounded-full border border-emerald-500/30 mb-8 shadow-[0_0_20px_rgba(16,185,129,0.1)]">
                <span class="w-2.5 h-2.5 bg-emerald-400 rounded-full animate-pulse shadow-[0_0_12px_#10b981]"></span>
                <span class="text-[10px] font-black text-emerald-400 uppercase tracking-[0.5em]">REDES SOCIALES</span>
            </div>
            <h2 class="text-4xl md:text-[6.5rem] font-black text-gray-900 dark:text-white tracking-tighter uppercase italic leading-[0.85] mb-12">NOVEDADES<br/><span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 to-emerald-600">EN TIEMPO REAL.</span></h2>
            
            <div class="flex flex-wrap justify-center gap-6 md:gap-8 items-center mb-20">
                <a :href="webSettings.social.tiktok" target="_blank" class="group flex items-center gap-3 bg-white dark:bg-white/5 px-8 py-4 rounded-2xl border border-gray-200 dark:border-white/10 hover:border-primary/50 transition-all">
                    <svg class="w-5 h-5 text-black dark:text-white group-hover:scale-125 transition-transform" fill="currentColor" viewBox="0 0 24 24"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-1.13-.32-2.43-.23-3.41.45-.83.53-1.33 1.47-1.38 2.44-.01.52.16 1.03.45 1.46.39.58 1.02.95 1.7 1.02 1.3.16 2.61-.41 3.17-1.58.34-.7.4-1.5.39-2.28-.02-5.91-.01-11.83-.01-17.74z"/></svg>
                    <span class="text-[10px] font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest">TikTok</span>
                </a>
                <a :href="webSettings.social.instagram" target="_blank" class="group flex items-center gap-3 bg-white dark:bg-white/5 px-8 py-4 rounded-2xl border border-gray-200 dark:border-white/10 hover:border-primary/50 transition-all">
                    <svg class="w-5 h-5 text-rose-500 group-hover:scale-125 transition-transform" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.058-1.69-.072-4.949-.072zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c.796 0 1.441.645 1.441 1.44s-.645 1.44-1.441 1.44c-.795 0-1.439-.645-1.439-1.44s.644-1.44 1.439-1.44z"/></svg>
                    <span class="text-[10px] font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest">Instagram</span>
                </a>
                <a :href="webSettings.social.youtube" target="_blank" class="group flex items-center gap-3 bg-white dark:bg-white/5 px-8 py-4 rounded-2xl border border-gray-200 dark:border-white/10 hover:border-rose-500/50 transition-all">
                    <svg class="w-5 h-5 text-red-600 group-hover:scale-125 transition-transform" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                    <span class="text-[10px] font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest">YouTube</span>
                </a>
            </div>
        </div>
        
        <!-- Skeleton Loader Social -->
        <div v-if="!isReady" class="max-w-7xl mx-auto grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 relative z-10">
            <div v-for="i in 4" :key="i" class="bg-white dark:bg-gray-900 rounded-[3.5rem] h-[600px] animate-pulse overflow-hidden">
                <div class="p-6 h-20 bg-gray-100 dark:bg-white/5 border-b border-gray-200 dark:border-white/10"></div>
                <div class="aspect-[4/5] bg-gray-200 dark:bg-white/10"></div>
            </div>
        </div>

        <div v-else class="max-w-7xl mx-auto grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 relative z-10">
            <div v-for="(post, idx) in webSettings.posts" :key="post.t" 
                 class="group bg-white dark:bg-gray-900 rounded-[3.5rem] overflow-hidden border border-gray-100 dark:border-white/5 hover:border-primary/50 transition-all flex flex-col shadow-2xl hover:-translate-y-4 duration-500 animate-in fade-in slide-in-from-bottom-10"
                 :style="{ animationDelay: (idx * 200) + 'ms' }">
                <!-- Post Header -->
                <div class="p-6 flex items-center justify-between border-b border-gray-100 dark:border-white/5">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-gray-100 dark:bg-gray-800 rounded-2xl flex items-center justify-center border border-gray-200 dark:border-white/10 shadow-lg overflow-hidden p-1.5 group-hover:border-primary/50 transition-all">
                            <img 
                                v-if="companyLogo" 
                                :src="companyLogo.startsWith('http') ? companyLogo : (companyLogo.startsWith('/') ? companyLogo : '/storage/' + companyLogo)" 
                                class="w-full h-full object-contain"
                                @error="(e) => { e.target.style.display = 'none'; e.target.nextSibling.style.display = 'flex'; }"
                            />
                            <div class="hidden w-full h-full items-center justify-center text-emerald-500 font-black italic text-base">N</div>
                            <span v-if="!companyLogo" class="text-emerald-500 font-black italic text-base">N</span>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-[10px] font-black text-gray-900 dark:text-white uppercase tracking-widest">{{ webSettings.company_name }}</span>
                            <div class="flex items-center gap-1.5">
                                <div class="w-3 h-3 flex items-center justify-center">
                                    <svg v-if="post.c === 'Instagram'" class="w-2.5 h-2.5 text-rose-500" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.058-1.69-.072-4.949-.072zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c.796 0 1.441.645 1.441 1.44s-.645 1.44-1.441 1.44c-.795 0-1.439-.645-1.439-1.44s.644-1.44 1.439-1.44z"/></svg>
                                    <svg v-else-if="post.c === 'TikTok'" class="w-2.5 h-2.5 text-black dark:text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-1.13-.32-2.43-.23-3.41.45-.83.53-1.33 1.47-1.38 2.44-.01.52.16 1.03.45 1.46.39.58 1.02.95 1.7 1.02 1.3.16 2.61-.41 3.17-1.58.34-.7.4-1.5.39-2.28-.02-5.91-.01-11.83-.01-17.74z"/></svg>
                                    <svg v-else-if="post.c === 'YouTube'" class="w-2.5 h-2.5 text-red-600" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                                </div>
                                <span class="text-[8px] font-bold text-primary uppercase tracking-widest">{{ post.c }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="text-primary/40 text-lg">•••</div>
                </div>
                
                <!-- Post Visual -->
                <div class="aspect-[4/5] overflow-hidden relative">
                    <img :src="post.i" :alt="'Publicación social de ' + post.t" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-1000" />
                    <div class="absolute inset-0 bg-black/20 group-hover:bg-transparent transition-colors"></div>
                    <div class="absolute bottom-6 right-6 bg-black/60 backdrop-blur-xl px-6 py-2.5 rounded-full border border-white/20 flex items-center gap-3 shadow-2xl">
                        <span class="text-rose-500 text-sm">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                        </span>
                        <span class="text-[11px] font-black text-white uppercase tracking-widest">{{ post.l }}</span>
                    </div>
                </div>

                <!-- Post Content (Text Below) -->
                <div class="p-10 flex-1 flex flex-col justify-between bg-white dark:bg-gray-900">
                    <div>
                        <h4 class="text-base font-black text-gray-900 dark:text-white uppercase tracking-tighter mb-4 leading-tight group-hover:text-primary transition-colors">{{ post.t }}</h4>
                        <p class="text-[10px] text-gray-400 font-bold uppercase leading-relaxed mb-8">{{ post.d }}</p>
                    </div>
                    <div class="pt-8 border-t border-gray-100 dark:border-white/10 flex justify-between items-center">
                        <span class="text-[9px] font-black text-gray-300 uppercase tracking-widest italic">Contenido reciente</span>
                        <a :href="post.url || '#'" target="_blank" class="text-[10px] font-black text-emerald-500 uppercase tracking-[0.4em] hover:tracking-[0.6em] transition-all flex items-center gap-2 group/link">
                            VER PUBLICACIÓN <span class="text-lg group-hover/link:translate-x-2 transition-transform">→</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer: Industrial Terminal -->
    <footer class="mt-32 py-24 bg-black border-t border-emerald-500/20 px-6 relative overflow-hidden transition-colors duration-500 shadow-[0_-20px_50px_-20px_rgba(16,185,129,0.1)]">
        <div class="absolute inset-0 technical-grid opacity-5 pointer-events-none"></div>
        
        <div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-4 gap-16 relative z-10">

            <!-- Branding Column -->
            <div class="md:col-span-1 space-y-8">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 bg-emerald-500 rounded-[1.2rem] flex items-center justify-center text-white font-black italic text-2xl shadow-xl shadow-emerald-500/20">N</div>
                    <div>
                        <h4 class="text-2xl font-black text-slate-900 dark:text-white uppercase tracking-tighter italic leading-none">N3XT 3D</h4>
                        <p class="text-[8px] font-black text-emerald-500 uppercase tracking-[0.4em]">Manufactura de precision</p>
                    </div>
                </div>
                <p class="text-[11px] text-slate-500 dark:text-white/40 font-bold uppercase leading-relaxed tracking-tight max-w-xs">
                    El siguiente nivel de la fabricación digital en Colombia. Coleccionables de alto nivel y soluciones industriales.
                </p>
            </div>

            <!-- Links: Navegación -->
            <div>
                <h5 class="text-[10px] font-black text-slate-900 dark:text-white uppercase tracking-[0.5em] mb-10 border-l-4 border-emerald-500 pl-4">NAVEGACIÓN</h5>
                <ul class="space-y-5">
                    <li v-for="link in [{n:'Inicio', p:'/'}, {n:'Catálogo', p:'/catalog'}, {n:'Cotizador', p:'/quote'}, {n:'Rastrear', p:'/track'}, {n:'Taller', p:'/admin/login'}]" :key="link.n">
                        <router-link :to="link.p" class="text-[10px] font-black text-slate-400 dark:text-gray-500 hover:text-emerald-500 dark:hover:text-white uppercase tracking-[0.2em] transition-all flex items-center gap-3 group">
                            <span class="w-4 h-px bg-slate-200 dark:bg-white/10 group-hover:w-8 group-hover:bg-emerald-500 transition-all"></span>
                            {{ link.n }}
                        </router-link>
                    </li>
                    <li>
                        <router-link to="/project/init" class="text-[10px] font-black text-emerald-500 hover:text-white uppercase tracking-[0.2em] transition-all flex items-center gap-3 group">
                            <span class="w-4 h-px bg-emerald-500/20 group-hover:w-8 group-hover:bg-emerald-500 transition-all"></span>
                            Contacto
                        </router-link>
                    </li>
                </ul>
            </div>

            <!-- Links: Social -->
            <div>
                <h5 class="text-[10px] font-black text-slate-900 dark:text-white uppercase tracking-[0.5em] mb-10 border-l-4 border-emerald-500 pl-4">CONECTA</h5>
                <div class="flex flex-col gap-4">
                    <a :href="webSettings.social.tiktok" target="_blank" class="group flex items-center gap-4 p-4 bg-slate-50 dark:bg-white/5 rounded-2xl border border-slate-100 dark:border-white/10 hover:border-emerald-500/30 transition-all">
                        <div class="w-8 h-8 flex items-center justify-center bg-white dark:bg-gray-900 rounded-xl group-hover:bg-black group-hover:text-white transition-all shadow-sm">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-1.13-.32-2.43-.23-3.41.45-.83.53-1.33 1.47-1.38 2.44-.01.52.16 1.03.45 1.46.39.58 1.02.95 1.7 1.02 1.3.16 2.61-.41 3.17-1.58.34-.7.4-1.5.39-2.28-.02-5.91-.01-11.83-.01-17.74z"/></svg>
                        </div>
                        <span class="text-[9px] font-black text-slate-500 dark:text-gray-400 uppercase tracking-widest">TikTok @N3XT3D</span>
                    </a>
                    <a :href="'https://wa.me/' + (companySettings.phone || '573118796416').replace(/\+/g, '').replace(/\s/g, '')" target="_blank" class="group flex items-center gap-4 p-4 bg-slate-50 dark:bg-white/5 rounded-2xl border border-slate-100 dark:border-white/10 hover:border-emerald-500/30 transition-all">
                        <div class="w-8 h-8 flex items-center justify-center bg-white dark:bg-gray-900 rounded-xl group-hover:bg-emerald-500 group-hover:text-white transition-all shadow-sm">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.246 2.248 3.484 5.232 3.483 8.413-.003 6.557-5.338 11.892-11.893 11.892-1.997-.001-3.951-.5-5.688-1.448l-6.308 1.654zm6.215-3.628c1.546.918 3.078 1.401 4.671 1.402 5.462 0 9.903-4.44 9.905-9.901.002-2.651-1.03-5.144-2.907-7.022-1.876-1.877-4.368-2.907-7.02-2.908-5.463 0-9.904 4.441-9.906 9.903-.001 1.67.463 3.305 1.342 4.729l-1.008 3.682 3.773-.989zm10.741-7.147c-.287-.143-1.697-.838-1.959-.933-.262-.095-.452-.143-.642.143-.19.285-.737.933-.903 1.124-.167.19-.332.214-.618.071-.286-.143-1.208-.445-2.3-1.419-.85-.758-1.422-1.694-1.589-1.98-.166-.285-.018-.439.125-.581.128-.128.286-.333.429-.499.143-.167.19-.285.286-.476.095-.19.048-.357-.024-.5-.071-.143-.642-1.548-.88-2.119-.232-.553-.467-.478-.642-.486-.167-.007-.357-.009-.547-.009s-.5.071-.762.357c-.262.285-1 1.024-1 2.5s1.071 2.905 1.214 3.095c.143.19 2.109 3.22 5.11 4.512.714.307 1.272.49 1.706.629.716.227 1.368.195 1.884.118.575-.085 1.697-.693 1.936-1.359.238-.667.238-1.238.167-1.359-.071-.121-.262-.19-.548-.333z"/></svg>
                        </div>
                        <span class="text-[9px] font-black text-slate-500 dark:text-gray-400 uppercase tracking-widest">WhatsApp Directo</span>
                    </a>
                </div>
            </div>

            <!-- Support / Status -->
            <div class="space-y-8">
                <div class="space-y-4 bg-slate-900 dark:bg-white/5 p-8 rounded-[2.5rem] border border-white/5 shadow-2xl">
                    <h5 class="text-[12px] font-black text-white uppercase tracking-[0.4em] mb-4">SOPORTE ELITE</h5>
                    <div class="space-y-3">
                        <p class="text-[10px] font-black text-emerald-400 uppercase tracking-widest italic flex items-center gap-3">
                            <span class="w-2 h-2 bg-emerald-400 rounded-full animate-pulse shadow-[0_0_10px_#10b981]"></span>
                            {{ companySettings.email || 'servicion3xt@gmail.com' }}
                        </p>
                        <p class="text-[10px] font-black text-white/60 uppercase tracking-widest flex items-center gap-3">
                            <span class="w-2 h-2 bg-white/20 rounded-full"></span>
                            {{ companySettings.phone || '+57 311 879 6416' }}
                        </p>
                    </div>
                </div>
                <div class="p-6 bg-emerald-500/5 dark:bg-white/5 rounded-[2rem] border border-emerald-500/10 dark:border-white/5 flex items-center justify-between">
                    <div>
                        <p class="text-[8px] font-black text-slate-400 uppercase tracking-widest mb-1 italic">ESTADO DEL TALLER</p>
                        <span class="text-[11px] font-black text-emerald-500 uppercase tracking-[0.2em]">{{ webSettings.workshop_status }}</span>
                    </div>
                    <span class="w-3 h-3 bg-emerald-500 rounded-full animate-pulse"></span>
                </div>
            </div>
        </div>

        <!-- Final Bar -->
        <div class="max-w-7xl mx-auto mt-24 pt-12 border-t border-emerald-500/10 flex flex-col md:flex-row justify-between items-center gap-8">
            <p class="text-[10px] font-black text-slate-400 dark:text-gray-800 uppercase tracking-[0.5em]">&copy; 2026 N3XT 3D. FABRICACIÓN AVANZADA.</p>
            <div class="flex gap-10">
                <button @click="openLegal('privacy')" class="text-[9px] font-black text-slate-500 hover:text-emerald-500 uppercase tracking-widest transition-all">Privacidad</button>
                <button @click="openLegal('terms')" class="text-[9px] font-black text-slate-500 hover:text-emerald-500 uppercase tracking-widest transition-all">Términos</button>
            </div>
        </div>
    </footer>

    <!-- Modal Legal Premium -->
    <transition name="fade">
        <div v-if="legalModal.show" class="fixed inset-0 z-[100] flex items-center justify-center p-6 md:p-12">
            <div class="absolute inset-0 bg-black/90 backdrop-blur-xl" @click="legalModal.show = false"></div>
            <div class="relative w-full max-w-4xl bg-[#0a0f14] border border-white/10 rounded-[3rem] p-10 md:p-16 shadow-2xl overflow-hidden max-h-[80vh] flex flex-col">
                <div class="absolute top-0 right-0 p-8">
                    <button @click="legalModal.show = false" class="w-12 h-12 rounded-2xl bg-white/5 text-white flex items-center justify-center hover:bg-primary transition-colors text-2xl">✕</button>
                </div>
                
                <div class="mb-12">
                    <h2 class="text-3xl md:text-5xl font-black text-white uppercase tracking-tighter italic border-l-8 border-primary pl-8">{{ legalModal.title }}</h2>
                    <p class="text-[10px] font-black text-gray-500 uppercase tracking-widest mt-4 ml-10">Documentación Legal Oficial N3XT 3D</p>
                </div>

                <div class="flex-1 overflow-y-auto pr-6 custom-scrollbar text-sm md:text-lg text-gray-400 font-medium leading-relaxed whitespace-pre-wrap">
                    {{ legalModal.content }}
                </div>

                <div class="mt-12 pt-8 border-t border-white/5 flex justify-end">
                    <button @click="legalModal.show = false" class="btn-primary px-10 py-4">Entendido</button>
                </div>
            </div>
        </div>
    </transition>
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
</style>
