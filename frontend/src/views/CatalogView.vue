<script setup>
import { ref, onMounted, computed } from 'vue'
import { api } from '../services/api'
import AppNavbar from '../components/AppNavbar.vue'

const isDark = ref(localStorage.getItem('n3xt_theme') !== 'light')
const companyLogo = ref(null)
const loading = ref(true)
const webSettings = ref({
  catalog: [],
  pdf_catalog_url: '',
  pdf_catalog_desc: ''
})

const activeCategory = ref('Todos')
const activeSubcategory = ref('Todos')

// Iconografía Técnica Industrial
const categoryIcons = {
  'Todos': `<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>`,
  'Mecanico': `<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>`,
  'Joyería': `<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>`,
  'Coleccionable': `<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>`,
  'Decoración': `<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>`,
}

const getIcon = (cat) => {
    return categoryIcons[cat] || categoryIcons['Todos']
}

const categories = computed(() => {
  const cats = new Set(webSettings.value.catalog.map(i => i.category))
  return ['Todos', ...Array.from(cats)]
})

const availableSubcategories = computed(() => {
  if (activeCategory.value === 'Todos') return []
  const subs = [...new Set(
    webSettings.value.catalog
      .filter(i => i.category === activeCategory.value && i.subcategory)
      .map(i => i.subcategory)
  )]
  return subs.length > 0 ? ['Todos', ...subs] : []
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

const formatPrice = (p) => {
  if (!p) return '$ 0'
  if (typeof p === 'string' && p.toLowerCase().includes('cotizar')) return p.toUpperCase()
  const val = parseFloat(String(p).replace(/[^0-9.-]+/g,""))
  return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(val)
}

const isDiscounted = (item) => {
  if (!item.price || !item.original_price) return false
  const p = parseFloat(String(item.price).replace(/[^0-9.-]+/g,""))
  const op = parseFloat(String(item.original_price).replace(/[^0-9.-]+/g,""))
  return p < op && p > 0
}

const getOptimizedImage = (url) => {
  if (!url) return ''
  if (!webSettings.value.cloudinary_name || !url.includes('cloudinary.com')) return url
  // Si es cloudinary, forzar auto-format y auto-quality
  return url.replace('/upload/', '/upload/f_auto,q_auto,w_800/')
}

const fetchSettings = async () => {
  loading.value = true
  try {
    const data = await api.get('/settings')
    if (data.company_logo) companyLogo.value = data.company_logo
    if (data.web) {
      const newWeb = { ...data.web }
      if (!Array.isArray(newWeb.catalog)) {
          newWeb.catalog = []
      }
      webSettings.value = { ...webSettings.value, ...newWeb }
    }
  } catch (err) {
    console.error('Error:', err)
  } finally {
    // Simulamos un pequeño delay de proceso industrial para que el skeleton sea visible y fluido
    setTimeout(() => { loading.value = false }, 800)
  }
}

onMounted(() => {
  document.title = 'Catálogo de Piezas 3D | N3XT 3D Systems'
  if (isDark.value) document.documentElement.classList.add('dark')
  fetchSettings()
})
</script>

<template>
  <div class="min-h-screen bg-[#f8fafc] dark:bg-[#0a0f14] text-gray-900 dark:text-white transition-colors duration-500 overflow-x-hidden">
    <AppNavbar activeTab="catalog" subtext="Catálogo Maestro" />

    <main class="max-w-7xl mx-auto px-6 py-20">
      <!-- Catalog Header -->
      <div class="flex flex-col md:flex-row justify-between items-end gap-10 mb-20">
        <div class="max-w-2xl text-left">
          <div class="inline-flex items-center gap-3 px-4 py-2 bg-primary/10 rounded-full border border-primary/20 mb-6">
            <span class="w-2 h-2 bg-primary rounded-full animate-pulse"></span>
            <span class="text-[9px] font-black text-primary uppercase tracking-[0.4em]">Coleccion de piezas</span>
          </div>
          <h1 class="text-5xl md:text-7xl font-black tracking-tighter uppercase leading-none italic">
            Nuestra <span class="text-primary">Galería</span>
          </h1>
          <p class="text-gray-500 dark:text-gray-400 font-bold text-sm uppercase tracking-widest mt-6">Explora las piezas creadas en nuestros videos y proyectos industriales.</p>
        </div>

        <div class="flex flex-col items-start md:items-end gap-6">
          <p class="text-[10px] font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest text-left md:text-right max-w-[200px] leading-relaxed italic">
            {{ webSettings.pdf_catalog_desc }}
          </p>
          <a 
            :href="webSettings.pdf_catalog_url" 
            target="_blank"
            class="group relative px-10 py-6 bg-gray-950 dark:bg-white text-white dark:text-black rounded-3xl font-black text-[10px] uppercase tracking-[0.3em] transition-all hover:scale-105 active:scale-95 shadow-2xl flex items-center gap-4"
          >
            <span>Descargar Catálogo PDF</span>
            <svg class="w-5 h-5 transition-transform group-hover:translate-y-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" /></svg>
          </a>
        </div>
      </div>

      <div class="flex flex-wrap gap-4 mb-16 bg-white dark:bg-white/5 p-4 rounded-[2rem] border border-gray-100 dark:border-white/5 backdrop-blur-sm">
        <button 
          v-for="cat in categories" 
          :key="cat"
          @click="activeCategory = cat; activeSubcategory = 'Todos'"
          :class="[
            'group px-8 py-4 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all flex items-center gap-3',
            activeCategory === cat 
              ? 'bg-primary text-white shadow-xl shadow-primary/20 scale-105' 
              : 'bg-transparent text-gray-500 hover:text-primary'
          ]"
        >
          <div v-html="getIcon(cat)" class="opacity-80 group-hover:scale-110 transition-transform"></div>
          {{ cat }}
        </button>
      </div>

      <!-- Subcategory Filters -->
      <div v-if="availableSubcategories.length > 0" class="flex flex-wrap gap-3 mb-16 animate-in fade-in slide-in-from-top-4 duration-700">
         <button 
           v-for="sub in availableSubcategories" 
           :key="sub"
           @click="activeSubcategory = sub"
           :class="[
             'px-6 py-2 rounded-xl text-[8px] font-black uppercase tracking-[0.2em] transition-all',
             activeSubcategory === sub 
               ? 'bg-primary/10 text-primary border border-primary/30' 
               : 'bg-transparent text-gray-400 border border-gray-200 dark:border-white/10 hover:border-primary/50'
           ]"
         >
           {{ sub }}
         </button>
      </div>

      <!-- Skeleton Loading State -->
      <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-10">
        <div v-for="i in 6" :key="i" class="bg-white dark:bg-[#0a0f14]/40 rounded-[3.5rem] overflow-hidden border border-gray-100 dark:border-white/5 p-12 flex flex-col relative overflow-hidden">
           <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/5 to-transparent skeleton-scan"></div>
           <div class="aspect-square bg-gray-50 dark:bg-white/5 rounded-[2.5rem] mb-10"></div>
           <div class="h-8 bg-gray-50 dark:bg-white/5 rounded-xl w-3/4 mx-auto mb-6"></div>
           <div class="h-20 bg-gray-50 dark:bg-white/5 rounded-2xl w-full mt-auto"></div>
        </div>
      </div>

      <!-- Real Items Grid -->
      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-10 animate-in fade-in duration-700">
        <router-link 
          v-for="(item, index) in filteredItems" 
          :key="index"
          :to="'/catalog/' + encodeURIComponent(item.name)"
          class="group bg-white dark:bg-[#0a0f14]/80 backdrop-blur-xl rounded-[4rem] overflow-hidden border border-gray-100 dark:border-white/5 shadow-sm hover:shadow-4xl hover:border-emerald-500/30 transition-all duration-700 flex flex-col relative"
        >
          <!-- Badge de Categoria Neon -->
          <div class="absolute top-8 left-8 z-30 inline-flex items-center gap-2 px-4 py-1.5 bg-emerald-500/20 dark:bg-emerald-500/10 rounded-full border border-emerald-500/40 dark:border-emerald-500/30 shadow-[0_0_15px_rgba(16,185,129,0.2)] animate-pulse">
              <span class="w-1 h-1 bg-emerald-400 rounded-full"></span>
              <span class="text-[7px] font-black text-emerald-400 uppercase tracking-[0.4em] italic">{{ item.category }}</span>
          </div>

          <div class="aspect-square overflow-hidden relative p-12 flex items-center justify-center">
            <!-- Pedestal de Luz Industrial -->
            <div class="absolute inset-0 bg-radial-gradient from-emerald-500/10 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-700"></div>
            
            <img :src="getOptimizedImage(item.image)" :alt="'Pieza de catálogo 3D: ' + item.name" class="max-w-[85%] max-h-[85%] object-contain group-hover:scale-110 group-hover:-rotate-2 transition-all duration-1000 relative z-10" />
          </div>

          <div class="p-12 pt-0 text-center flex flex-col flex-1 relative z-10">
            <h3 class="text-3xl font-black text-gray-900 dark:text-white uppercase tracking-tighter mb-4 italic leading-none group-hover:text-emerald-400 transition-colors">{{ item.name }}</h3>
            
            <div class="mt-auto pt-8 border-t border-gray-100 dark:border-white/5">
               <div class="flex flex-col items-center gap-1 mb-10">
                  <p class="text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-[0.4em] mb-2 italic">Inversión Estimada</p>
                  <p class="text-5xl font-black text-emerald-500 dark:text-emerald-400 tracking-tighter leading-none italic drop-shadow-[0_0_10px_rgba(16,185,129,0.2)]">
                    ${{ Math.round(parseFloat(String(item.price).replace(/[^0-9]+/g,"")) || 0).toLocaleString() }}
                  </p>
               </div>
               
               <div class="w-full py-5 bg-gray-950 dark:bg-[#0f172a] border border-transparent dark:border-white/10 text-white dark:text-gray-400 rounded-3xl text-[9px] font-black uppercase tracking-[0.5em] group-hover:bg-emerald-500 group-hover:text-white group-hover:border-emerald-500 transition-all duration-500 shadow-xl group-hover:shadow-emerald-500/30 flex items-center justify-center gap-4">
                   <span>Analizar pieza</span>
                   <svg class="w-4 h-4 group-hover:translate-x-2 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
               </div>
            </div>
          </div>
        </router-link>
      </div>

      <!-- No items fallback -->
      <div v-if="filteredItems.length === 0" class="py-40 text-center">
          <p class="text-xl font-black text-gray-400 uppercase tracking-widest italic">No hay piezas en esta categoría todavía.</p>
      </div>
    </main>

    <!-- Footer Simple -->
    <footer class="py-20 border-t border-gray-100 dark:border-white/5 text-center">
        <p class="text-[10px] font-black text-gray-400 uppercase tracking-[0.5em]">N3XT 3D — Catalogo de piezas 2026</p>
    </footer>
  </div>
</template>

<style scoped>
.technical-grid {
  background-size: 40px 40px;
  background-image: 
    linear-gradient(to right, rgba(30, 58, 52, 0.05) 1px, transparent 1px),
    linear-gradient(to bottom, rgba(30, 58, 52, 0.05) 1px, transparent 1px);
}
</style>
