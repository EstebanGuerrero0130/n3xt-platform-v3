<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, watch, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import { sanitizeSVG } from '../utils/sanitize'
import { api } from '../services/api'
import { useRevealAnim } from '../composables/useRevealAnim'
import AppNavbar from '../components/AppNavbar.vue'
import AppFooter from '../components/AppFooter.vue'
import logger from '../utils/logger'
import { useSplitTitle } from '../composables/useSplitTitle'
import { useSplitButton } from '../composables/useSplitButton'
import { usePageMeta } from '../composables/usePageMeta'

const { applySplitTitle } = useSplitTitle()
const { applySplitBtn } = useSplitButton()

usePageMeta({
  title: 'Detalles del Producto | N3XT 3D Shop',
  description: 'Explora nuestros productos 3D de alta precision. Figuras, prototipos y piezas industriales.',
  image: '/assets/n3xt_og_product.png',
  type: 'product',
})

useRevealAnim({ delay: 200 })

const route = useRoute()
const quantity = ref(1)
const product = ref<any>(null)
const selectedImage = ref('')
const loading = ref(true)
const companySettings = ref({ company: { phone: '' } })
const customerForm = ref({ name: '' })

const catalog = ref<any[]>([])
const relatedProducts = computed(() => {
  if (!product.value || !catalog.value.length) return []
  
  // 1. Intentar por subcategoría (si existe)
  let related = catalog.value.filter(i => 
    i.id !== product.value.id && 
    i.subcategory === product.value.subcategory && 
    i.subcategory
  )
  
  // 2. Si no hay suficientes, intentar por categoría
  if (related.length < 3) {
    const byCategory = catalog.value.filter(i => 
      i.id !== product.value.id && 
      i.category === product.value.category &&
      !related.find(r => r.id === i.id)
    )
    related = [...related, ...byCategory]
  }
  
  return related.slice(0, 3)
})

const fetchProduct = async () => {
  try {
    const data = await api.get('/settings')
    companySettings.value = data
    catalog.value = data.web?.catalog || []
    const paramId = decodeURIComponent(route.params.id)
    
    // Buscar por ID numérico o por nombre (slug)
    const found = catalog.value.find(i => String(i.id) === paramId || i.name === paramId)
    
    if (found) {
      product.value = found
      selectedImage.value = found.images && found.images.length > 0 ? found.images[0] : found.image
      usePageMeta({ title: `${found.name} | N3XT 3D Shop`, description: found.description || 'Explora nuestros productos 3D de alta precision.', image: found.image || '/assets/n3xt_og_product.png', type: 'product' })
    }
  } catch (err) {
    logger.error('Error fetching product:', err)
  } finally {
    loading.value = false
    nextTick(() => {
      applySplitTitle()
      applySplitBtn()
    })
  }
}

const checkoutWhatsApp = () => {
  // Prioridad: Soporte específico del producto -> Soporte Global de la empresa
  const phone = (product.value.support_phone || companySettings.value.company?.phone || '573118796416').replace(/\+/g, '').replace(/\s/g, '')
  const message = `Hola N3XT 3D,\n\nMe interesa este producto de su catalogo:\n\n*Producto:* ${product.value.name}\n*Cantidad:* ${quantity.value}\n*Precio Unitario:* ${product.value.price}\n\nQuisiera mas informacion para concretar la compra.`
  window.open(`https://wa.me/${phone}?text=${encodeURIComponent(message)}`, '_blank')
}

const formatPrice = (p: any) => {
  if (!p) return '$ 0'
  if (typeof p === 'string' && p.toLowerCase().includes('cotizar')) return p.toUpperCase()
  const val = parseFloat(String(p).replace(/[^0-9.-]+/g,""))
  return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(val)
}

const isDiscounted = computed(() => {
  if (!product.value?.price || !product.value?.original_price) return false
  const p = parseFloat(String(product.value.price).replace(/[^0-9.-]+/g,""))
  const op = parseFloat(String(product.value.original_price).replace(/[^0-9.-]+/g,""))
  return p < op && p > 0
})

onMounted(() => {
  fetchProduct()
})

onUnmounted(() => {
})

// Observar cambios en la URL para recargar el producto (Navegación entre relacionados)
watch(() => route.params.id, () => {
  loading.value = true
  product.value = null
  fetchProduct()
  window.scrollTo({ top: 0, behavior: 'smooth' })
})
</script>

<template>
  <div class="min-h-screen bg-[#f8fafc] dark:bg-[#0a0f14] text-gray-900 dark:text-white transition-colors duration-500 overflow-x-hidden">
    <AppNavbar active-tab="catalog" subtext="Detalle de producto" />

    <main class="max-w-7xl mx-auto px-6 py-12 md:py-20">
      <div v-if="loading" class="grid grid-cols-1 lg:grid-cols-2 gap-24 py-12 md:py-20 animate-in fade-in duration-500">
          <!-- Skeleton Galeria -->
          <div class="space-y-6 relative overflow-hidden rounded-[3rem]">
             <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/5 to-transparent skeleton-scan z-10"></div>
             <div class="aspect-square bg-white dark:bg-white/5 border border-gray-100 dark:border-white/5 rounded-[3.5rem]"></div>
             <div class="grid grid-cols-4 gap-4">
                <div v-for="i in 4" :key="i" class="aspect-square bg-white dark:bg-white/5 rounded-2xl border border-gray-100 dark:border-white/5"></div>
             </div>
          </div>
          <!-- Skeleton Info -->
          <div class="space-y-10 relative overflow-hidden">
             <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/5 to-transparent skeleton-scan z-10"></div>
             <div class="w-32 h-8 bg-white dark:bg-white/5 rounded-full"></div>
             <div class="w-full h-24 bg-white dark:bg-white/5 rounded-[2rem]"></div>
             <div class="w-2/3 h-12 bg-white dark:bg-white/5 rounded-xl"></div>
             <div class="grid grid-cols-2 gap-8">
                <div v-for="i in 4" :key="i" class="h-24 bg-white dark:bg-white/5 rounded-[1.5rem]"></div>
             </div>
          </div>
      </div>

      <template v-else-if="product">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 md:gap-24 items-start">
        <!-- Columna Galería -->
        <div class="space-y-6 animate-in fade-in slide-in-from-left-4 duration-1000 lg:sticky lg:top-24">
          <div class="aspect-square bg-gray-50 dark:bg-gray-900 rounded-[3rem] overflow-hidden border border-gray-100 dark:border-white/5 shadow-2xl relative group p-10 flex items-center justify-center">
            <img :src="selectedImage || product.image" :alt="'Detalle de producto 3D: ' + product.name + (product.category ? ' (' + product.category + ')' : '') + ' — N3XT 3D Shop'" class="max-w-full max-h-full object-contain group-hover:scale-105 transition-transform duration-[2s]" fetchpriority="high" decoding="async" @error="(e: any) => e.target.style.display='none'" />
            <!-- Cloudinary Optimization Note Tag (Invisible to user) -->
            <div class="absolute top-8 right-8 px-4 py-2 bg-black/40 backdrop-blur-md rounded-xl border border-white/10">
                <span class="text-[8px] font-black text-emerald-400 uppercase tracking-widest">Alta definicion</span>
            </div>
          </div>
          <!-- Miniaturas -->
          <div v-if="product.images && product.images.length > 1" class="grid grid-cols-4 gap-4">
             <div v-for="(img, i) in product.images" :key="i" :class="[selectedImage === img ? 'border-primary opacity-100 ring-2 ring-primary/30' : 'border-gray-200 dark:border-white/10 opacity-50']" class="aspect-square bg-gray-100 dark:bg-white/5 rounded-2xl border overflow-hidden cursor-pointer hover:opacity-100 hover:border-primary transition-all" @click="selectedImage = img">
                <img :src="img" :alt="'Vista previa ' + (i + 1) + ' de ' + product.name + ' — N3XT 3D Shop'" class="w-full h-full object-cover" loading="lazy" decoding="async" @error="(e: any) => e.target.style.display='none'" />
             </div>
          </div>
        </div>

        <!-- Columna Información -->
        <div class="space-y-10 text-left animate-in fade-in slide-in-from-right-4 duration-1000">
          <div>
            <div class="inline-flex items-center gap-3 px-5 py-2 bg-emerald-500/20 dark:bg-emerald-500/10 rounded-full border border-emerald-500/40 dark:border-emerald-500/30 mb-8 shadow-[0_0_25px_rgba(16,185,129,0.3)] animate-pulse hover:animate-none transition-all cursor-default">
                <span class="w-1.5 h-1.5 bg-emerald-400 rounded-full shadow-[0_0_12px_#34d399]"></span>
                <span class="text-[10px] font-black text-emerald-400 dark:text-emerald-300 uppercase tracking-[0.4em] drop-shadow-[0_0_8px_rgba(52,211,153,0.5)]">{{ product.category }}</span>
            </div>
            <h1 class="split-title text-5xl md:text-7xl font-black text-gray-900 dark:text-white uppercase tracking-tighter leading-[0.9] mb-4">
              {{ product.name }}
            </h1>
            <div class="flex items-center gap-6">
            <div class="flex items-end gap-6 mb-2">
               <div class="relative group/price">
                  <p class="text-xs font-black text-gray-400 dark:text-gray-500 uppercase tracking-[0.4em] mb-1 ml-1 italic">Precio</p>
                  <p class="text-5xl md:text-7xl font-black text-emerald-500 dark:text-emerald-400 tracking-tighter leading-none italic drop-shadow-[0_0_15px_rgba(16,185,129,0.3)]">
                    {{ formatPrice(product.price) }}
                  </p>
               </div>
               <div v-if="isDiscounted" class="flex flex-col mb-1 animate-bounce">
                  <p class="text-lg font-black text-gray-400 line-through opacity-40 leading-none mb-1 italic">{{ formatPrice(product.original_price) }}</p>
                  <span class="text-[9px] font-black px-3 py-1 bg-rose-600 text-white rounded-lg uppercase tracking-widest shadow-lg shadow-rose-600/20">Ahorro Activo</span>
               </div>
            </div>
            </div>
          </div>

          <p class="text-gray-500 dark:text-gray-400 text-sm md:text-base font-bold leading-relaxed">
            {{ product.description }}
          </p>

          <!-- Specifications List -->
          <div class="grid grid-cols-2 gap-6 pt-8 border-t border-gray-100 dark:border-white/5">
             <div
v-for="spec in [
               {l: 'Tecnologia', v: 'Resina 8K / FDM Pro', c: 'text-primary', i: `<svg class='w-5 h-5' fill='none' viewBox='0 0 24 24' stroke='currentColor'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2.5' d='M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z'/></svg>`},
               {l: 'Material', v: 'Grado industrial', c: 'text-emerald-500', i: `<svg class='w-5 h-5' fill='none' viewBox='0 0 24 24' stroke='currentColor'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2.5' d='M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10'/></svg>`},
               {l: 'Envio', v: 'Nacional (Colombia)', c: 'text-blue-500', i: `<svg class='w-5 h-5' fill='none' viewBox='0 0 24 24' stroke='currentColor'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2.5' d='M12 19l9 2-9-18-9 18 9-2zm0 0v-8'/></svg>`},
               {l: 'Garantia', v: '6 Meses N3XT', c: 'text-orange-500', i: `<svg class='w-5 h-5' fill='none' viewBox='0 0 24 24' stroke='currentColor'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2.5' d='M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'/></svg>`}
             ]" :key="spec.l" class="group/spec flex items-center gap-5 p-5 bg-gray-50 dark:bg-[#0f172a] rounded-[2rem] border border-transparent hover:border-primary/20 transition-all duration-500">
                <div class="w-12 h-12 bg-white dark:bg-black/40 rounded-2xl flex items-center justify-center shadow-lg group-hover/spec:scale-110 group-hover/spec:bg-primary/10 transition-all" :innerHTML="sanitizeSVG(spec.i)">
                </div>
                <div>
                   <p class="text-[8px] font-black text-gray-400 uppercase tracking-[0.3em] mb-1">{{ spec.l }}</p>
                   <p :class="['text-[10px] font-black uppercase tracking-widest italic', spec.c]">{{ spec.v }}</p>
                </div>
             </div>
          </div>

          <!-- Checkout Controls -->
          <div class="pt-10 space-y-6">
            <div class="flex items-center gap-6">
              <div class="flex items-center bg-gray-100 dark:bg-[#0f172a] rounded-[1.5rem] p-1.5 border border-gray-200 dark:border-white/5">
                <button class="w-12 h-12 flex items-center justify-center text-xl font-black hover:text-primary transition-colors hover:bg-white/5 rounded-xl" @click="quantity > 1 ? quantity-- : null">-</button>
                <span class="w-14 text-center font-black text-xl italic tracking-tighter">{{ quantity }}</span>
                <button class="w-12 h-12 flex items-center justify-center text-xl font-black hover:text-primary transition-colors hover:bg-white/5 rounded-xl" @click="quantity++">+</button>
              </div>
              <p class="text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest max-w-[120px] leading-tight italic">Disponibilidad inmediata bajo pedido industrial</p>
            </div>

            <!-- IDENTIFICACION (NOMBRE PARA WHATSAPP) -->
            <div class="space-y-3 p-6 bg-gray-100 dark:bg-[#0f172a] rounded-[2rem] border border-transparent dark:border-white/5">
              <label class="text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest block ml-2">Tu Nombre (Para identificarte en WhatsApp)</label>
              <input v-model="customerForm.name" type="text" placeholder="Ej. Juan Perez..." class="w-full bg-white dark:bg-black/40 border border-transparent dark:border-white/5 rounded-2xl px-6 py-4 text-sm font-black text-gray-900 dark:text-white uppercase outline-none focus:ring-2 focus:ring-primary/20 transition-all">
            </div>

            <div class="flex flex-col gap-5">
              <button class="split-btn w-full px-10 py-6 bg-emerald-500 text-white rounded-[2rem] font-black text-[11px] uppercase tracking-[0.3em] shadow-3xl shadow-emerald-500/20 hover:scale-105 active:scale-95 transition-all flex items-center justify-center gap-4 group" @click="checkoutWhatsApp">
                <svg class="w-6 h-6 group-hover:rotate-12 transition-transform" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.67-1.618-.918-2.213-.242-.588-.487-.51-.67-.51-.172-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                <span>Adquirir por WhatsApp</span>
              </button>
            </div>

            <div class="p-6 bg-emerald-500/5 rounded-3xl border border-emerald-500/10 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 bg-emerald-500 rounded-xl flex items-center justify-center text-white shadow-lg">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                    </div>
                    <div>
                        <p class="text-[8px] font-black text-gray-400 uppercase tracking-widest">Soporte Técnico Especializado</p>
                        <p class="text-[10px] font-black text-emerald-600 uppercase tracking-widest">WhatsApp: {{ product.support_phone || companySettings.company?.phone || '+57 3118796416' }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-1.5 px-3 py-1 bg-emerald-500/20 rounded-full">
                    <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></span>
                    <span class="text-[7px] font-black text-emerald-500 uppercase">En Línea</span>
                </div>
            </div>
          </div>
        </div>
      </div>
      <!-- Related Products Section -->
      <div v-if="product && relatedProducts.length > 0" class="mt-40 pt-20 border-t border-gray-100 dark:border-white/5 reveal">
         <div class="flex flex-col md:flex-row justify-between items-end gap-6 mb-16">
            <div>
               <h4 class="text-3xl font-black text-gray-900 dark:text-white uppercase tracking-tighter italic mb-2">Colecciones Similares</h4>
               <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest italic">Piezas de la misma línea técnica o temática</p>
            </div>
            <router-link to="/catalog" class="text-[10px] font-black text-primary uppercase tracking-[0.4em] hover:tracking-[0.6em] transition-all flex items-center gap-3">
               Ver Todo el Catálogo <span class="text-xl">→</span>
            </router-link>
         </div>

         <div class="grid grid-cols-1 md:grid-cols-3 gap-10">
            <router-link 
               v-for="rel in relatedProducts" 
               :key="rel.id"
               :to="'/catalog/' + encodeURIComponent(rel.name)"
               class="group block space-y-6"
            >
               <div class="aspect-square bg-gray-50 dark:bg-gray-900/50 rounded-3xl overflow-hidden border border-gray-100 dark:border-white/5 p-8 flex items-center justify-center group-hover:scale-[1.02] transition-all duration-500">
                  <img :src="rel.image" :alt="'Producto relacionado: ' + rel.name + ' | N3XT 3D Shop'" class="max-w-full max-h-full object-contain" loading="lazy" decoding="async" @error="(e: any) => e.target.style.display='none'" />
               </div>
               <div class="px-4">
                  <p class="text-[8px] font-black text-primary uppercase tracking-widest mb-1">{{ rel.category }}</p>
                  <h5 class="text-lg font-black text-gray-900 dark:text-white uppercase tracking-tighter italic leading-tight group-hover:text-primary transition-colors">{{ rel.name }}</h5>
                  <p class="text-sm font-bold text-gray-400 mt-2">{{ rel.price }}</p>
               </div>
            </router-link>
          </div>
       </div>
    </template>

      <!-- Fallback Error -->
      <div v-else class="py-40 text-center space-y-8">
          <h3 class="text-4xl font-black uppercase italic">Producto no encontrado</h3>
          <router-link to="/catalog" class="btn-primary inline-flex px-12 py-5">Volver al Catálogo</router-link>
      </div>
    </main>

    <AppFooter />
  </div>
</template>

<style scoped>
/* Estilos específicos del detalle si son necesarios */


/* --- Scroll Reveal --- */
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
</style>
