<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import logger from '../../utils/logger'
import { api } from '../../services/api'
import VueDraggableLib from 'vuedraggable'
const VueDraggable = VueDraggableLib as any

const props = defineProps<{
 settings: any
 inventoryData?: any[]
 showNotify: (msg: string, type?: string) => void
 askConfirm: (title: string, message: string, icon: string, onConfirm: () => void) => void
}>()

const emit = defineEmits<{
 (e: 'save-settings', silent?: boolean, updatedData?: any): void

 (e: 'seo-optimize'): void
}>()

const webSubTab = ref('general')

// Catalog functions
const editingCatalogItemIndex = ref<number | null>(null)

const handleCreateCatalogProduct = () => {
 const newProduct = {
 name: '', category: 'General', subcategory: '', price: '0',
 original_price: '0', image: '', images: [], description: '', status: 'active'
 }
 props.settings.web.catalog.unshift(newProduct)
 editingCatalogItemIndex.value = 0
 props.showNotify('Nuevo producto creado en el borrador del catalogo', 'success')
}

const handleResetCatalog = () => {
 props.askConfirm(
 'Reiniciar Catalogo',
 '¿Estás seguro de que deseas restablecer el catálogo? Se eliminarán todos los productos y podrás empezar de cero.',
 '',
 () => {
 props.settings.web.catalog = []
 props.showNotify('Catálogo limpio. Crea tus productos desde cero.', 'warning')
 }
 )
}

const isDiscounted = (item: any) => {
 if (!item.price || !item.original_price) return false
 const p = parseFloat(String(item.price).replace(/[^0-9.-]+/g,""))
 const op = parseFloat(String(item.original_price).replace(/[^0-9.-]+/g,""))
 return p < op && p > 0
}

const scrollToCatalogItem = (index: number) => {
 editingCatalogItemIndex.value = index
 setTimeout(() => {
 const el = document.getElementById(`catalog-item-${index}`)
 if (el) {
 el.scrollIntoView({ behavior: 'smooth', block: 'center' })
 el.classList.add('ring-4', 'ring-primary/50', 'transition-all')
 setTimeout(() => el.classList.remove('ring-4', 'ring-primary/50'), 2000)
 }
 }, 50)
}

const platformLabels: Record<string, string> = {
 facebook: 'Facebook', instagram: 'Instagram', tiktok: 'TikTok',
 whatsapp: 'WhatsApp', youtube: 'YouTube', linkedin: 'LinkedIn',
 twitter: 'X (Twitter)', telegram: 'Telegram', discord: 'Discord',
 spotify: 'Spotify', other: 'Otra',
}

const dashboardStats = computed(() => {
 const web = props.settings?.web || {}
 return {
 products: (web.catalog || []).length,
 productsActive: (web.catalog || []).filter((p: any) => p.status === 'active').length,
 productsDiscounted: (web.catalog || []).filter((p: any) => isDiscounted(p)).length,
 images: (web.gallery || []).length,
 articles: (web.news || []).length,
 articlesPublished: (web.news || []).filter((a: any) => a.status === 'published').length,
 platforms: (web.ecosystem || []).length,
 hasSocial: !!(web.social?.instagram || web.social?.facebook || web.social?.tiktok),
 hasPrivacy: !!(web.privacy_policy || web.terms_conditions),
 }
})

const groupedCatalog = computed(() => {
 const groups: Record<string, any[]> = {}
 const catalog = props.settings?.web?.catalog || []
 catalog.forEach((item: any, index: number) => {
 if (!item) return
 const cat = item.category || 'General'
 if (!groups[cat]) groups[cat] = []
 groups[cat].push({ ...item, originalIndex: index })
 })
 return groups
})

const newGalleryUrl = ref('')

const addGalleryUrl = () => {
 const url = newGalleryUrl.value.trim()
 if (!url) return
 if (!props.settings.web.gallery) props.settings.web.gallery = []
 props.settings.web.gallery.push(url)
 newGalleryUrl.value = ''
}

const removeCatalogImage = (index: number, imgIdx: number) => {
 const item = props.settings.web.catalog[index]
 if (item && item.images) {
 item.images.splice(imgIdx, 1)
 }
}

const uploadingGallery = ref(false)
const isDragging = ref(false)

const handleCreateGalleryProject = () => {
 if (!props.settings.web.gallery) props.settings.web.gallery = []
 
 // Normalize existing string entries first
 props.settings.web.gallery = props.settings.web.gallery.map((item: any, idx: number) => 
 typeof item === 'string' ? { title: `Proyecto ${idx + 1}`, image: item, images: [], category: 'General', technology: 'SLA', tags: '', featured: false } : item
 )

 props.settings.web.gallery.unshift({
 title: '',
 category: 'General',
 technology: 'SLA',
 featured: false,
 image: '',
 images: [],
 tags: '',
 description: '',
 date: new Date().toISOString().split('T')[0]
 })
 props.showNotify('Proyecto creado. Recuerda guardar cambios.', 'success')
}

const handleGalleryImageUpload = async (e: Event, item: any, isMain: boolean = false) => {
 const input = e.target as HTMLInputElement
 if (!input.files || input.files.length === 0) return
 const file = input.files[0]
 
 try {
 const formData = new FormData()
 formData.append('file', file)
 const res = await api.post('/admin/upload-image', formData)
 if (res && res.secure_url) {
 if (isMain) {
 item.image = res.secure_url
 } else {
 if (!item.images) item.images = []
 item.images.push(res.secure_url)
 }
 props.showNotify('Imagen subida correctamente', 'success')
 }
 } catch (err) {
 logger.error('Error uploading image', err)
 let errorMsg = 'Error de conexión con el servidor'
 if (err instanceof Error) {
 if (err.message.includes('401') || err.message.includes('Sesión')) errorMsg = 'Sesión expirada. Inicia sesión de nuevo.'
 else if (err.message.includes('500')) errorMsg = 'Error interno del servidor. Revisa la consola.'
 else if (err.message.includes('Cloudinary')) errorMsg = 'Error al procesar la imagen en Cloudinary.'
 else errorMsg = err.message
 }
 props.showNotify('Error al subir imagen: ' + errorMsg, 'error')
 } finally {
 input.value = ''
 }
}

const addImageByUrl = (item: any) => {
 const url = item._newImgUrl?.trim()
 if (!url) return
 if (!item.images) item.images = []
 item.images.push(url)
 item._newImgUrl = ''
}

const handleSaveGallery = () => {
 // Limpiar propiedades temporales antes de guardar
 if (props.settings?.web?.gallery) {
 props.settings.web.gallery.forEach((item: any) => {
 delete item._newImgUrl
 })
 }
 emit('save-settings')
}

const removeGalleryImage = (item: any, imgIdx: number) => {
 if (item && item.images) {
 item.images.splice(imgIdx, 1)
 }
}

const processFiles = async (files: FileList | File[]) => {
 if (!files || files.length === 0) return

 uploadingGallery.value = true
 
 if (!props.settings.web.gallery) {
 props.settings.web.gallery = []
 }

 for (const file of files) {
 try {
 const formData = new FormData()
 formData.append('file', file)
 
 const res = await api.post('/admin/upload-image', formData)
 
 if (res && res.secure_url) {
 props.settings.web.gallery.push({
 title: 'Nuevo Proyecto',
 image: res.secure_url,
 images: [],
 category: 'General',
 technology: 'SLA',
 featured: false
 })
 }
 } catch (err) {
 logger.error('Error subiendo imagen:', err)
 let errorMsg = 'Error de red'
 if (err instanceof Error) {
 if (err.message.includes('401') || err.message.includes('Sesión')) errorMsg = 'Sesión expirada. Inicia sesión de nuevo.'
 else if (err.message.includes('500')) errorMsg = 'Error interno del servidor.'
 else errorMsg = err.message
 }
 props.showNotify?.('Error al subir imagen: ' + errorMsg, 'error')
 }
 }
 
 props.showNotify?.(`${files.length} imagen(es) subida(s) correctamente`, 'success')

 uploadingGallery.value = false
}

const uploadGalleryFiles = async (event: Event) => {
 const input = event.target as HTMLInputElement
 if (!input.files) return
 await processFiles(input.files)
 input.value = ''
}

const handleDragOver = (e: DragEvent) => {
 e.preventDefault()
 isDragging.value = true
}

const handleDragLeave = (e: DragEvent) => {
 // Only hide if we're actually leaving the drop zone (not entering a child)
 if (!e.currentTarget || !(e.currentTarget as HTMLElement).contains(e.relatedTarget as Node)) {
 isDragging.value = false
 }
}

const handleDrop = async (e: DragEvent) => {
 e.preventDefault()
 isDragging.value = false
 
 if (!e.dataTransfer?.files || e.dataTransfer.files.length === 0) return
 
 const imageFiles = Array.from(e.dataTransfer.files).filter(f => f.type.startsWith('image/'))
 
 if (imageFiles.length === 0) {
 props.showNotify?.('Solo se aceptan imágenes', 'warning')
 return
 }
 
 if (imageFiles.length < e.dataTransfer.files.length) {
 props.showNotify?.(`${imageFiles.length} imagen(es) detectada(s), archivos no-imagen ignorados`, 'warning')
 }
 
 await processFiles(imageFiles)
}




const handleCatalogImageUpload = (e: Event, item: any) => {
 const input = e.target as HTMLInputElement
 const file = input?.files?.[0]
 if (!file) return
 const reader = new window.FileReader()
 reader.onload = (ev: ProgressEvent<FileReader>) => {
 if (!item.images) item.images = []
 item.images.push(ev.target?.result)
 }
 reader.readAsDataURL(file)
 input.value = ''
}
</script>

<template>
 <div class="space-y-12 pb-12">
 <!-- Header de Módulo -->
 <div class="flex flex-col md:flex-row md:items-end justify-between gap-8 mb-12 bg-[#151a22]/40 dark:bg-[#090d0a]/40 backdrop-blur-xl p-8 md:p-12 rounded-[4rem] border border-white/60 dark:border-[#21262d] relative overflow-hidden group">
 <div class="absolute -right-20 -top-20 w-64 h-64 bg-[#08872b]/5 rounded-[60px] blur-3xl group-hover:bg-[#08872b]/10 transition-all duration-1000"></div>
 <div class="relative z-10">
 <h2 class="text-3xl md:text-5xl font-black text-[#ffffff] dark:text-white tracking-tighter uppercase mb-4 italic">Gestión <span class="text-[#8dd6ff]">Landing Page</span></h2>
 <p class="text-[10px] md:text-sm text-[#c3c4c5] font-bold uppercase tracking-[0.3em] flex items-center gap-3">
 <span class="w-3 h-3 rounded-[60px] bg-[#08872b] animate-pulse"></span>
 Control de Interfaz Pública • N3XT Web Core
 </p>
 </div>

 <!-- Sub-Navegación Interna (scrollable en mobile) -->
 <div class="flex flex-wrap gap-y-2 bg-[#151a22] dark:bg-[#151a22]/5 p-1.5 md:p-2 rounded-[1.5rem] md:rounded-[2rem] border border-[#21262d] dark:border-[#21262d] relative z-10 max-w-full">
 <button 
 v-for="st in [
 {id: 'dashboard', n: 'Dashboard', i: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'},
 {id: 'general', n: 'General', i: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z'}, 
 {id: 'gallery', n: 'Galería', i: 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z'},
 {id: 'catalog', n: 'Catálogo', i: 'M4 6h16M4 10h16M4 14h16M4 18h16'},
 {id: 'news', n: 'Noticias', i: 'M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10l4 4v10a2 2 0 01-2 2zM7 8h5M7 12h8M7 16h8'}, 
 {id: 'social', n: 'Social', i: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z'},
 {id: 'ecosystem', n: 'Ecosistema', i: 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10'}
 ]" 
 :key="st.id"
 :class="[
 'whitespace-nowrap px-4 md:px-8 py-2.5 md:py-3.5 rounded-[1.2rem] md:rounded-[1.5rem] text-[9px] md:text-[10px] font-black uppercase tracking-widest transition-all flex items-center gap-1.5 md:gap-3',
 webSubTab === st.id ? 'bg-[#151a22] dark:bg-[#08872b] text-[#ffffff] dark:text-white ' : 'text-[#c3c4c5] hover:text-[#a4aea6] dark:hover:text-gray-300'
 ]"
 @click="webSubTab = st.id"
 >
 <svg class="w-3.5 h-3.5 md:w-4 md:h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="st.i"/>
 </svg>
 {{ st.n }}
 </button>
 </div>
 </div>

 <!-- ===== TAB: DASHBOARD (Resumen Ejecutivo) ===== -->
 <div v-if="webSubTab === 'dashboard'" class="space-y-8">
 <!-- Hero Stats -->
 <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6">
 <div class="bg-[#151a22] dark:bg-[#151a22]/70 backdrop-blur-xl p-6 md:p-8 rounded-[2.5rem] border border-[#21262d] dark:border-[#21262d] group hover: transition-all">
 <div class="flex items-center gap-4 mb-4">
 <div class="w-10 h-10 md:w-12 md:h-12 rounded-[24px] bg-[#08872b]/10 flex items-center justify-center">
 <svg class="w-5 h-5 md:w-6 md:h-6 text-[#8dd6ff]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
 </div>
 <div>
 <p class="text-[9px] font-black text-[#c3c4c5] uppercase tracking-widest">Productos</p>
 <div class="flex items-baseline gap-2">
 <span class="text-3xl md:text-4xl font-black text-[#ffffff] dark:text-white tracking-tighter">{{ dashboardStats.products }}</span>
 <span class="text-[8px] font-black text-[#c3c4c5]">items</span>
 </div>
 </div>
 </div>
 <div class="flex flex-wrap gap-2">
 <span class="px-3 py-1 bg-emerald-500/10 rounded-[60px] text-[8px] font-black text-emerald-500 uppercase tracking-wider">{{ dashboardStats.productsActive }} activos</span>
 <span v-if="dashboardStats.productsDiscounted > 0" class="px-3 py-1 bg-amber-500/10 rounded-[60px] text-[8px] font-black text-amber-500 uppercase tracking-wider">{{ dashboardStats.productsDiscounted }} desc.</span>
 </div>
 </div>

 <div class="bg-[#151a22] dark:bg-[#151a22]/70 backdrop-blur-xl p-6 md:p-8 rounded-[2.5rem] border border-[#21262d] dark:border-[#21262d] group hover: transition-all">
 <div class="flex items-center gap-4 mb-4">
 <div class="w-10 h-10 md:w-12 md:h-12 rounded-[24px] bg-indigo-500/10 flex items-center justify-center">
 <svg class="w-5 h-5 md:w-6 md:h-6 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
 </div>
 <div>
 <p class="text-[9px] font-black text-[#c3c4c5] uppercase tracking-widest">Galería</p>
 <div class="flex items-baseline gap-2">
 <span class="text-3xl md:text-4xl font-black text-[#ffffff] dark:text-white tracking-tighter">{{ dashboardStats.images }}</span>
 <span class="text-[8px] font-black text-[#c3c4c5]">imágenes</span>
 </div>
 </div>
 </div>
 <div class="flex flex-wrap gap-2">
 <span class="px-3 py-1 bg-indigo-500/10 rounded-[60px] text-[8px] font-black text-indigo-500 uppercase tracking-wider">{{ dashboardStats.images > 0 ? 'Con contenido' : 'Sin imágenes' }}</span>
 </div>
 </div>

 <div class="bg-[#151a22] dark:bg-[#151a22]/70 backdrop-blur-xl p-6 md:p-8 rounded-[2.5rem] border border-[#21262d] dark:border-[#21262d] group hover: transition-all">
 <div class="flex items-center gap-4 mb-4">
 <div class="w-10 h-10 md:w-12 md:h-12 rounded-[24px] bg-amber-500/10 flex items-center justify-center">
 <svg class="w-5 h-5 md:w-6 md:h-6 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10l4 4v10a2 2 0 01-2 2zM7 8h5M7 12h8M7 16h8"/></svg>
 </div>
 <div>
 <p class="text-[9px] font-black text-[#c3c4c5] uppercase tracking-widest">Noticias</p>
 <div class="flex items-baseline gap-2">
 <span class="text-3xl md:text-4xl font-black text-[#ffffff] dark:text-white tracking-tighter">{{ dashboardStats.articles }}</span>
 <span class="text-[8px] font-black text-[#c3c4c5]">artículos</span>
 </div>
 </div>
 </div>
 <div class="flex flex-wrap gap-2">
 <span class="px-3 py-1 bg-emerald-500/10 rounded-[60px] text-[8px] font-black text-emerald-500 uppercase tracking-wider">{{ dashboardStats.articlesPublished }} publicados</span>
 <span v-if="dashboardStats.articles - dashboardStats.articlesPublished > 0" class="px-3 py-1 bg-amber-500/10 rounded-[60px] text-[8px] font-black text-amber-500 uppercase tracking-wider">{{ dashboardStats.articles - dashboardStats.articlesPublished }} borradores</span>
 </div>
 </div>

 <div class="bg-[#151a22] dark:bg-[#151a22]/70 backdrop-blur-xl p-6 md:p-8 rounded-[2.5rem] border border-[#21262d] dark:border-[#21262d] group hover: transition-all">
 <div class="flex items-center gap-4 mb-4">
 <div class="w-10 h-10 md:w-12 md:h-12 rounded-[24px] bg-rose-500/10 flex items-center justify-center">
 <svg class="w-5 h-5 md:w-6 md:h-6 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
 </div>
 <div>
 <p class="text-[9px] font-black text-[#c3c4c5] uppercase tracking-widest">Social</p>
 <div class="flex items-baseline gap-2">
 <span class="text-3xl md:text-4xl font-black text-[#ffffff] dark:text-white tracking-tighter">{{ dashboardStats.platforms }}</span>
 <span class="text-[8px] font-black text-[#c3c4c5]">plataformas</span>
 </div>
 </div>
 </div>
 <div class="flex flex-wrap gap-2">
 <span :class="dashboardStats.hasSocial ? 'bg-emerald-500/10 text-emerald-500' : 'bg-[#151a22] dark:bg-[#151a22]/5 text-[#c3c4c5]'" class="px-3 py-1 rounded-[60px] text-[8px] font-black uppercase tracking-wider">{{ dashboardStats.hasSocial ? 'Redes configuradas' : 'Sin redes' }}</span>
 </div>
 </div>
 </div>

 <!-- Checklist de Configuración -->
 <div class="bg-[#151a22] dark:bg-[#151a22]/70 backdrop-blur-xl p-8 md:p-10 rounded-[3rem] border border-[#21262d] dark:border-[#21262d] ">
 <h3 class="text-lg md:text-xl font-black text-[#ffffff] dark:text-white uppercase tracking-tight mb-6 flex items-center gap-3">
 <svg class="w-5 h-5 text-[#8dd6ff]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
 Checklist de Publicación
 </h3>
 <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
 <div
v-for="(item, idx) in [
 { label: 'Logo de empresa', done: !!settings?.company_logo, tab: 'settings', hint: 'Sube el logo en Parámetros' },
 { label: 'Redes sociales configuradas', done: dashboardStats.hasSocial, tab: 'general', hint: 'Completa las redes en General' },
 { label: 'Información legal completa', done: dashboardStats.hasPrivacy, tab: 'general', hint: 'Agrega términos y privacidad' },
 { label: 'Galería con imágenes', done: dashboardStats.images > 0, tab: 'gallery', hint: 'Agrega imágenes en Galería' },
 { label: 'Productos en catálogo', done: dashboardStats.products > 0, tab: 'catalog', hint: 'Crea productos en Catálogo' },
 { label: 'Noticias publicadas', done: dashboardStats.articlesPublished > 0, tab: 'news', hint: 'Publica noticias en el tab Noticias' },
 { label: 'Plataformas vinculadas', done: dashboardStats.platforms > 0, tab: 'social', hint: 'Agrega plataformas en Social' },
 ]" :key="idx">
 <div class="flex items-center gap-4 p-4 rounded-[24px] transition-all cursor-pointer" :class="item.done ? 'bg-emerald-500/5 hover:bg-emerald-500/10' : 'bg-[#151a22] dark:bg-[#151a22]/5 hover:bg-[#151a22] dark:hover:bg-[#151a22]/10'" @click="webSubTab = item.tab">
 <div :class="item.done ? 'bg-emerald-500 text-white' : 'bg-gray-200 dark:bg-[#151a22]/10 text-[#c3c4c5]'" class="w-8 h-8 rounded-[6px] flex items-center justify-center shrink-0 transition-all">
 <svg v-if="item.done" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
 <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
 </div>
 <div class="flex-1 min-w-0">
 <p :class="item.done ? 'text-[#ffffff] dark:text-white line-through opacity-60' : 'text-gray-700 dark:text-gray-300'" class="text-sm font-black uppercase tracking-tight truncate">{{ item.label }}</p>
 <p v-if="!item.done" class="text-[9px] font-bold text-[#8dd6ff] mt-0.5">{{ item.hint }} →</p>
 </div>
 </div>
 </div>
 </div>
 </div>

 <!-- Company Snapshot -->
 <div class="bg-[#151a22] dark:bg-[#151a22]/70 backdrop-blur-xl p-8 md:p-10 rounded-[3rem] border border-[#21262d] dark:border-[#21262d] ">
 <div class="flex items-center gap-5">
 <div class="w-16 h-16 rounded-[24px] overflow-hidden border-2 border-primary/20 bg-[#151a22] dark:bg-[#283041] shrink-0">
 <img v-if="settings?.company_logo" :src="settings.company_logo.startsWith('http') ? settings.company_logo : api.storageUrl + '/' + settings.company_logo" class="w-full h-full object-contain"  alt="Imagen N3XT 3D" />
 <div v-else class="w-full h-full bg-gradient-to-br from-primary/80 via-emerald-600 to-teal-800 flex items-center justify-center">
 <span class="text-white font-black italic text-xl drop--[0_2px_4px_rgba(0,0,0,0.3)]">N</span>
 </div>
 </div>
 <div class="min-w-0 flex-1">
 <p class="text-xl font-black text-[#ffffff] dark:text-white tracking-tight truncate">{{ settings?.company?.name || 'Sin nombre' }}</p>
 <div class="flex flex-wrap gap-x-4 gap-y-1 mt-1 text-[10px] font-bold text-[#c3c4c5]">
 <span v-if="settings?.company?.email">{{ settings.company.email }}</span>
 <span v-if="settings?.company?.phone">{{ settings.company.phone }}</span>
 <span v-if="settings?.web?.workshop_status" class="uppercase tracking-wider">{{ settings.web.workshop_status }}</span>
 </div>
 </div>
 </div>
 </div>
 </div>

 <!-- ===== TAB: GENERAL ===== -->
 <div v-if="webSubTab === 'general'" class="grid grid-cols-1 lg:grid-cols-2 gap-12">

 <!-- Sección: Información de la Empresa -->
 <div class="bg-[#151a22] dark:bg-[#151a22]/70 backdrop-blur-xl p-10 rounded-[3rem] border border-[#21262d] dark:border-[#21262d] group">
 <h3 class="text-lg font-black text-[#ffffff] dark:text-white uppercase tracking-tight mb-8 flex items-center gap-3">
 <svg class="w-5 h-5 text-[#8dd6ff]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
 Información Comercial
 </h3>
 <div class="space-y-6">
 <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
 <div>
 <label class="text-[10px] font-black text-[#c3c4c5] uppercase tracking-widest mb-2 block">Nombre Legal</label>
 <input v-model="settings.company.name" class="w-full bg-[#151a22] dark:bg-[#090d0a]/50 border border-[#21262d] dark:border-[#21262d] rounded-[16px] px-6 py-4 font-bold text-sm text-[#ffffff] dark:text-white outline-none focus:ring-2 focus:ring-primary/20 transition-all" />
 </div>
 <div>
 <label class="text-[10px] font-black text-[#c3c4c5] uppercase tracking-widest mb-2 block">NIT / ID Fiscal</label>
 <input v-model="settings.company.nit" class="w-full bg-[#151a22] dark:bg-[#090d0a]/50 border border-[#21262d] dark:border-[#21262d] rounded-[16px] px-6 py-4 font-bold text-sm text-[#ffffff] dark:text-white outline-none focus:ring-2 focus:ring-primary/20 transition-all" />
 </div>
 </div>
 <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
 <div>
 <label class="text-[10px] font-black text-[#c3c4c5] uppercase tracking-widest mb-2 block">Correo</label>
 <input v-model="settings.company.email" type="email" class="w-full bg-[#151a22] dark:bg-[#090d0a]/50 border border-[#21262d] dark:border-[#21262d] rounded-[16px] px-6 py-4 font-bold text-sm text-[#ffffff] dark:text-white outline-none focus:ring-2 focus:ring-primary/20 transition-all" />
 </div>
 <div>
 <label class="text-[10px] font-black text-[#c3c4c5] uppercase tracking-widest mb-2 block">Teléfono</label>
 <input v-model="settings.company.phone" class="w-full bg-[#151a22] dark:bg-[#090d0a]/50 border border-[#21262d] dark:border-[#21262d] rounded-[16px] px-6 py-4 font-bold text-sm text-[#ffffff] dark:text-white outline-none focus:ring-2 focus:ring-primary/20 transition-all" />
 </div>
 </div>
 <div>
 <label class="text-[10px] font-black text-[#c3c4c5] uppercase tracking-widest mb-2 block">Dirección</label>
 <input v-model="settings.company.address" class="w-full bg-[#151a22] dark:bg-[#090d0a]/50 border border-[#21262d] dark:border-[#21262d] rounded-[16px] px-6 py-4 font-bold text-sm text-[#ffffff] dark:text-white outline-none focus:ring-2 focus:ring-primary/20 transition-all" />
 </div>
 <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
 <div>
 <label class="text-[10px] font-black text-[#c3c4c5] uppercase tracking-widest mb-2 block">Slogan</label>
 <input v-model="settings.company.slogan" class="w-full bg-[#151a22] dark:bg-[#090d0a]/50 border border-[#21262d] dark:border-[#21262d] rounded-[16px] px-6 py-4 font-bold text-sm text-[#ffffff] dark:text-white outline-none focus:ring-2 focus:ring-primary/20 transition-all" />
 </div>
 <div>
 <label class="text-[10px] font-black text-[#c3c4c5] uppercase tracking-widest mb-2 block">Sitio Web</label>
 <input v-model="settings.company.website" class="w-full bg-[#151a22] dark:bg-[#090d0a]/50 border border-[#21262d] dark:border-[#21262d] rounded-[16px] px-6 py-4 font-bold text-sm text-[#ffffff] dark:text-white outline-none focus:ring-2 focus:ring-primary/20 transition-all" />
 </div>
 </div>

 <div>
 <label class="text-[10px] font-black text-[#c3c4c5] uppercase tracking-widest mb-2 block">Estado del Taller</label>
 <select v-model="settings.web.workshop_status" class="w-full bg-[#151a22] dark:bg-[#090d0a]/50 border border-[#21262d] dark:border-[#21262d] rounded-[16px] px-6 py-4 font-bold text-sm text-[#ffffff] dark:text-white outline-none focus:ring-2 focus:ring-primary/20 transition-all">
 <option value="operativo 24/7">Operativo 24/7</option>
 <option value="solo por encargo">Solo por Encargo</option>
 <option value="cerrado temporalmente">Cerrado Temporalmente</option>
 <option value="mantenimiento">En Mantenimiento</option>
 </select>
 </div>
 </div>
 </div>

 <!-- Sección: Redes Sociales -->
 <div class="bg-[#151a22] dark:bg-[#151a22]/70 backdrop-blur-xl p-10 rounded-[3rem] border border-[#21262d] dark:border-[#21262d] ">
 <h3 class="text-lg font-black text-[#ffffff] dark:text-white uppercase tracking-tight mb-8 flex items-center gap-3">
 <svg class="w-5 h-5 text-[#8dd6ff]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
 Redes Sociales
 </h3>
 <div class="space-y-5">
 <div
v-for="(social, key) in [
 {k: 'tiktok', label: 'TikTok', ph: '@n3xt3d', color: 'text-pink-500'},
 {k: 'instagram', label: 'Instagram', ph: '@n3xt_3d', color: 'text-purple-500'},
 {k: 'facebook', label: 'Facebook', ph: 'N3XT3D', color: 'text-blue-500'},
 {k: 'whatsapp', label: 'WhatsApp Link', ph: 'https://wa.me/57...', color: 'text-emerald-500'},
 {k: 'youtube', label: 'YouTube', ph: 'N3XT Channel', color: 'text-red-500'}
 ]" :key="key">
 <label class="text-[10px] font-black uppercase tracking-widest mb-2 block" :class="social.color">{{ social.label }}</label>
 <input v-model="settings.web.social[social.k]" :placeholder="social.ph" class="w-full bg-[#151a22] dark:bg-[#090d0a]/50 border border-[#21262d] dark:border-[#21262d] rounded-[16px] px-6 py-4 font-bold text-sm text-[#ffffff] dark:text-white outline-none focus:ring-2 focus:ring-primary/20 transition-all" />
 </div>
 </div>
 </div>

 <!-- Sección: Privacidad & Términos -->
 <div class="col-span-1 lg:col-span-2 bg-[#151a22] dark:bg-[#151a22]/70 backdrop-blur-xl p-10 rounded-[3rem] border border-[#21262d] dark:border-[#21262d] ">
 <h3 class="text-lg font-black text-[#ffffff] dark:text-white uppercase tracking-tight mb-8 flex items-center gap-3">
 <svg class="w-5 h-5 text-[#8dd6ff]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
 Políticas Legales
 </h3>
 <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
 <div>
 <label class="text-[10px] font-black text-[#c3c4c5] uppercase tracking-widest mb-2 block">Política de Privacidad</label>
 <textarea v-model="settings.web.privacy_policy" rows="5" class="w-full bg-[#151a22] dark:bg-[#090d0a]/50 border border-[#21262d] dark:border-[#21262d] rounded-[16px] px-6 py-4 font-medium text-sm text-[#a4aea6] dark:text-gray-300 outline-none focus:ring-2 focus:ring-primary/20 transition-all resize-none" placeholder="Describe cómo manejas los datos..."></textarea>
 </div>
 <div>
 <label class="text-[10px] font-black text-[#c3c4c5] uppercase tracking-widest mb-2 block">Términos y Condiciones</label>
 <textarea v-model="settings.web.terms_conditions" rows="5" class="w-full bg-[#151a22] dark:bg-[#090d0a]/50 border border-[#21262d] dark:border-[#21262d] rounded-[16px] px-6 py-4 font-medium text-sm text-[#a4aea6] dark:text-gray-300 outline-none focus:ring-2 focus:ring-primary/20 transition-all resize-none" placeholder="Términos del servicio..."></textarea>
 </div>
 </div>
 </div>
 </div>

 <!-- ===== TAB: GALERÍA (Gestión Completa de Proyectos) ===== -->
 <div 
 v-if="webSubTab === 'gallery'" 
 class="bg-[#151a22] dark:bg-[#151a22]/70 backdrop-blur-xl p-10 rounded-[3rem] border border-[#21262d] dark:border-[#21262d] relative overflow-hidden"
 @dragover.prevent="handleDragOver"
 @dragleave="handleDragLeave"
 @drop="handleDrop"
 >
 
 <!-- Drop Zone Overlay (solo visual, eventos burbujean al contenedor padre) -->
 <div 
 v-if="isDragging"
 class="absolute inset-0 z-20 bg-[#08872b]/10 dark:bg-[#08872b]/20 backdrop-blur-sm rounded-[3rem] flex flex-col items-center justify-center gap-4 animate-in fade-in zoom-in-95 duration-200 pointer-events-none"
 >
 <div class="w-20 h-20 bg-[#08872b]/20 rounded-[2rem] flex items-center justify-center">
 <svg class="w-10 h-10 text-[#8dd6ff]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
 </div>
 <p class="text-xl font-black text-[#8dd6ff] uppercase tracking-tight">Suelta tus imágenes aquí</p>
 <p class="text-sm text-[#8dd6ff]/60 font-bold uppercase tracking-widest">Crearán proyectos automáticamente</p>
 </div>

 <div
 :class="['transition-all duration-300 rounded-[2.5rem] -m-2 p-2', isDragging ? 'ring-2 ring-primary/30 bg-[#08872b]/5' : '']"
 >
 <div class="flex items-center justify-between mb-10">
 <div>
 <h3 class="text-2xl font-black text-[#ffffff] dark:text-white uppercase tracking-tight">Galería de Proyectos</h3>
 <p class="text-xs text-[#c3c4c5] font-bold uppercase tracking-widest mt-1">{{ (settings?.web?.gallery || []).length }} proyectos</p>
 </div>
 <div class="flex gap-3">
 <button class="px-6 py-4 bg-[#08872b]/10 hover:bg-[#08872b]/20 text-[#8dd6ff] rounded-[24px] text-[10px] font-black uppercase tracking-widest transition-all" @click="handleCreateGalleryProject">+ Nuevo Proyecto</button>
 </div>
 </div>
 
 <!-- Lista de Proyectos con Drag & Drop -->
 <VueDraggable 
 v-model="settings.web.gallery" 
 tag="div"
 class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-8"
 handle=".drag-handle"
 ghost-class="opacity-30 scale-95"
 :animation="250"
 :item-key="(el: any, idx: number) => el.title + idx"
 >
 <template #item="{ element: item, index: idx }">
 <div class="relative bg-[#151a22] dark:bg-[#151a22]/5 rounded-[2rem] p-6 border border-[#21262d] dark:border-[#21262d] transition-all hover: group/item">
 <!-- Drag Handle -->
 <div class="absolute top-6 right-6 w-8 h-8 bg-[#151a22]/80 dark:bg-[#283041]/80 backdrop-blur-sm rounded-[60px] flex items-center justify-center cursor-grab active:cursor-grabbing opacity-0 group-hover/item:opacity-100 transition-all drag-handle z-10 hover:bg-[#151a22] dark:hover:bg-gray-700" title="Arrastrar para reordenar" @click.stop>
 <svg class="w-4 h-4 text-[#a4aea6]" fill="currentColor" viewBox="0 0 24 24"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg>
 </div>

 <!-- Content Row -->
 <div class="flex flex-col md:flex-row gap-6">
 <!-- Main Image Preview -->
 <div class="w-full md:w-32 aspect-square rounded-[24px] overflow-hidden border-2 bg-[#151a22] dark:bg-[#283041] relative shrink-0 transition-all duration-500" :class="item.featured ? 'border-amber-400/50 -[0_0_20px_rgba(251,191,36,0.25)]' : 'border-[#21262d] dark:border-[#21262d]'">
 <img v-if="typeof item === 'object' ? item.image : item" :src="typeof item === 'object' ? item.image : item" alt="Imagen de galería N3XT 3D" class="w-full h-full object-cover" @error="(e: any) => { const t = e.target as HTMLImageElement; if (t) t.style.display = 'none' }" />
 <div v-else class="w-full h-full flex items-center justify-center">
 <svg class="w-8 h-8 text-[#c3c4c5]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
 </div>
 
 <!-- Featured glow dot -->
 <div v-if="item.featured" class="absolute top-1.5 left-1.5 w-3 h-3 bg-amber-400 rounded-[60px] -[0_0_12px_rgba(251,191,36,0.9)] animate-pulse ring-2 ring-amber-400/30 z-10"></div>
 
 <!-- Main Image Upload overlay -->
 <label class="absolute inset-0 bg-black/50 opacity-0 hover:opacity-100 transition-opacity cursor-pointer flex flex-col items-center justify-center gap-2 z-10">
 <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
 <span class="text-[8px] font-black text-white uppercase tracking-wider">Subir</span>
 <input type="file" accept="image/*" class="hidden" @change="(e) => handleGalleryImageUpload(e, item, true)" />
 </label>
 </div>
 <!-- URL de Cloudinary para imagen principal -->
 <div class="mt-1.5">
 <input v-model="item.image" placeholder="URL de Cloudinary para imagen principal..." class="w-full bg-[#151a22] dark:bg-[#090d0a]/50 border border-[#21262d] dark:border-[#21262d] rounded-[6px] px-3 py-1.5 text-[9px] font-mono text-[#a4aea6] dark:text-[#c3c4c5] outline-none border border-[#21262d] dark:border-[#21262d] placeholder:text-gray-300 dark:placeholder:text-gray-700" @click.stop />
 </div>

 <!-- Fields -->
 <div class="flex-1 space-y-4 pr-10">
 <div class="flex items-center gap-3">
 <input v-model="item.title" placeholder="Título del Proyecto" class="flex-1 bg-transparent text-xl font-black text-[#ffffff] dark:text-white outline-none placeholder:text-gray-300 dark:placeholder:text-gray-700 autofill:bg-transparent" autocomplete="off" spellcheck="false" />
 <!-- Featured Toggle - clickable star -->
 <button type="button" class="relative w-9 h-9 rounded-[60px] flex items-center justify-center shrink-0 transition-all duration-300 hover:scale-110 active:scale-90" :class="item.featured ? 'text-amber-400 drop--[0_0_8px_rgba(251,191,36,0.6)]' : 'text-gray-300 dark:text-[#a4aea6] hover:text-[#c3c4c5]'" :title="item.featured ? 'Quitar destacado' : 'Marcar como destacado'" @click.stop="item.featured = !item.featured">
 <svg class="w-5 h-5 transition-all" :class="item.featured ? 'fill-amber-400' : 'fill-none'" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/></svg>
 <span v-if="item.featured" class="absolute -top-0.5 -right-0.5 w-2 h-2 bg-amber-400 rounded-[60px] animate-ping"></span>
 </button>
 </div>

 <div class="grid grid-cols-2 gap-3">
 <div>
 <label class="text-[8px] font-black text-[#c3c4c5] uppercase">Categoría</label>
 <input v-model="item.category" placeholder="Ej: Figuras, Industrial..." class="w-full bg-[#151a22] dark:bg-[#090d0a]/50 border border-[#21262d] dark:border-[#21262d] rounded-[6px] px-3 py-2 text-[10px] font-bold text-[#a4aea6] dark:text-gray-300 outline-none mt-1" />
 </div>
 <div>
 <label class="text-[8px] font-black text-[#c3c4c5] uppercase">Tecnología</label>
 <input v-model="item.technology" placeholder="Ej: SLA, FDM, Resina..." class="w-full bg-[#151a22] dark:bg-[#090d0a]/50 border border-[#21262d] dark:border-[#21262d] rounded-[6px] px-3 py-2 text-[10px] font-bold text-[#a4aea6] dark:text-gray-300 outline-none mt-1" />
 </div>
 </div>

 <div>
 <label class="text-[8px] font-black text-[#c3c4c5] uppercase">Etiquetas (Separadas por comas)</label>
 <input v-model="item.tags" placeholder="ej: anime, premium, pintado a mano" class="w-full bg-[#151a22] dark:bg-[#090d0a]/50 border border-[#21262d] dark:border-[#21262d] rounded-[6px] px-3 py-2 text-[10px] font-bold text-[#a4aea6] dark:text-gray-300 outline-none mt-1" />
 </div>
 
 <div class="pt-2">
 <label class="text-[8px] font-black text-[#c3c4c5] uppercase mb-2 block">Imágenes Adicionales del Proyecto</label>
 <div class="flex flex-wrap gap-2 items-center">
 <div v-for="(addImg, addIdx) in (item.images || [])" :key="addIdx" class="relative group/addimg">
 <img :src="addImg" class="w-12 h-12 rounded-[6px] object-cover border border-[#21262d] dark:border-[#21262d]"  alt="Imagen N3XT 3D" />
 <button class="absolute -top-1.5 -right-1.5 w-4 h-4 bg-red-500 text-white rounded-[60px] text-[8px] font-black flex items-center justify-center opacity-0 group-hover/addimg:opacity-100 transition-all " aria-label="Botón interactivo" @click="removeGalleryImage(item, Number(addIdx))">✕</button>
 </div>
 <!-- Upload desde computador -->
 <label class="w-12 h-12 rounded-[6px] border-2 border-dashed border-gray-300 dark:border-[#21262d] flex items-center justify-center cursor-pointer hover:border-primary transition-all bg-[#151a22] dark:bg-transparent shrink-0" title="Subir desde computador">
 <svg class="w-4 h-4 text-[#c3c4c5]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
 <input type="file" accept="image/*" class="hidden" @change="(e) => handleGalleryImageUpload(e, item, false)" />
 </label>
 <!-- URL desde Cloudinary -->
 <div class="flex items-center gap-1 h-12">
 <input v-model="item._newImgUrl" placeholder="URL Cloudinary..." class="w-24 bg-[#151a22] dark:bg-[#090d0a]/50 border border-[#21262d] dark:border-[#21262d] rounded-[6px] px-2.5 py-2 text-[8px] font-mono text-[#a4aea6] dark:text-[#c3c4c5] outline-none border border-[#21262d] dark:border-[#21262d] placeholder:text-gray-300 dark:placeholder:text-gray-700 h-full" @keyup.enter="addImageByUrl(item)" @click.stop />
 <button class="w-8 h-full rounded-[6px] bg-[#08872b]/10 hover:bg-[#08872b]/20 flex items-center justify-center text-[#8dd6ff] transition-all shrink-0" title="Añadir por URL" @click.stop="addImageByUrl(item)">
 <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
 </button>
 </div>
 </div>
 </div>
 </div>
 </div>

 <!-- Delete button -->
 <button class="absolute bottom-6 right-6 p-2 hover:bg-gray-200 dark:hover:bg-[#151a22]/10 rounded-[6px] transition-all opacity-0 group-hover/item:opacity-100" title="Eliminar" @click="settings.web.gallery.splice(idx, 1); props.showNotify('Proyecto eliminado', 'warning')">
 <svg class="w-4 h-4 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
 </button>
 </div>
 </template>
 </VueDraggable>
 
 <!-- URL input bulk add disabled or repurposed to just uploading -->
 <!-- Upload placeholder (Bulk images to individual projects) -->
 <div class="flex justify-center mb-8">
 <label :class="['w-full max-w-sm py-8 rounded-[2rem] border-2 border-dashed flex flex-col items-center justify-center transition-all bg-[#151a22] dark:bg-transparent', uploadingGallery ? 'border-primary/30 bg-[#08872b]/5 pointer-events-none opacity-60' : 'border-gray-300 dark:border-[#21262d] cursor-pointer hover:border-primary/50 hover:bg-[#08872b]/5 group/upload']">
 <div v-if="uploadingGallery" class="flex flex-col items-center gap-2">
 <svg class="w-8 h-8 text-[#8dd6ff] animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
 <span class="text-[10px] font-black text-[#8dd6ff] uppercase tracking-widest">Creando Proyectos...</span>
 </div>
 <template v-else>
 <svg class="w-8 h-8 text-gray-300 dark:text-[#a4aea6]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
 <span class="text-[10px] font-black text-[#c3c4c5] mt-2 uppercase tracking-widest">Añadir Imágenes (Crea un proyecto por imagen)</span>
 </template>
 <input type="file" accept="image/*" multiple class="hidden" :disabled="uploadingGallery" @change="uploadGalleryFiles" />
 </label>
 </div>
 </div>
 </div>

 <!-- ===== TAB: CATÁLOGO MASTER ===== -->
 <div v-if="webSubTab === 'catalog'" class="bg-[#151a22] dark:bg-[#151a22]/70 backdrop-blur-xl p-10 rounded-[3rem] border border-[#21262d] dark:border-[#21262d] ">
 <div class="flex items-center justify-between mb-10">
 <div>
 <h3 class="text-2xl font-black text-[#ffffff] dark:text-white uppercase tracking-tight">Catálogo de Productos</h3>
 <p class="text-xs text-[#c3c4c5] font-bold uppercase tracking-widest mt-1">{{ settings?.web?.catalog?.length || 0 }} productos registrados</p>
 </div>
 <div class="flex gap-3">
 <button class="px-6 py-4 bg-[#151a22] dark:bg-[#151a22]/5 hover:bg-gray-200 dark:hover:bg-[#151a22]/10 rounded-[24px] text-[10px] font-black uppercase tracking-widest text-[#a4aea6] dark:text-[#c3c4c5] transition-all" @click="handleResetCatalog">Reset</button>
 <button class="px-6 py-4 bg-[#08872b]/10 hover:bg-[#08872b]/20 text-[#8dd6ff] rounded-[24px] text-[10px] font-black uppercase tracking-widest transition-all" @click="handleCreateCatalogProduct">+ Nuevo Producto</button>
 </div>
 </div>

 <div v-for="(group, category) in groupedCatalog" :key="category" class="mb-12 last:mb-0">
 <div class="flex items-center gap-4 mb-6">
 <h4 class="text-sm font-black text-[#8dd6ff] uppercase tracking-widest">{{ category }}</h4>
 <div class="flex-1 h-px bg-[#151a22] dark:bg-[#151a22]/5"></div>
 <span class="text-[10px] font-black text-[#c3c4c5]">{{ group.length }} items</span>
 </div>

 <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
 <div v-for="(item, idx) in group" :id="`catalog-item-${item.originalIndex}`" :key="item.originalIndex" class="bg-[#151a22] dark:bg-[#151a22]/5 rounded-[2rem] p-6 border border-[#21262d] dark:border-[#21262d] transition-all hover: group/item">
 <div class="flex items-start justify-between mb-4">
 <div class="flex-1">
 <input v-model="item.name" placeholder="Nombre del Producto" class="w-full bg-transparent text-lg font-black text-[#ffffff] dark:text-white outline-none placeholder:text-gray-300 dark:placeholder:text-gray-700" />
 <div class="flex gap-3 mt-2">
 <div class="flex-1">
 <label class="text-[8px] font-black text-[#c3c4c5] uppercase">Categoría</label>
 <input v-model="item.category" class="block w-full bg-[#151a22] dark:bg-[#090d0a]/50 border border-[#21262d] dark:border-[#21262d] rounded-[6px] px-3 py-1.5 text-[10px] font-bold text-[#a4aea6] dark:text-gray-300 outline-none mt-1" />
 </div>
 <div class="flex-1">
 <label class="text-[8px] font-black text-[#c3c4c5] uppercase">Subcategoría</label>
 <input v-model="item.subcategory" class="block w-full bg-[#151a22] dark:bg-[#090d0a]/50 border border-[#21262d] dark:border-[#21262d] rounded-[6px] px-3 py-1.5 text-[10px] font-bold text-[#a4aea6] dark:text-gray-300 outline-none mt-1" />
 </div>
 </div>
 </div>
 <div class="flex items-center gap-2">
 <button class="p-2 hover:bg-gray-200 dark:hover:bg-[#151a22]/10 rounded-[6px] transition-all opacity-0 group-hover/item:opacity-100" title="Eliminar" @click="settings.web.catalog.splice(item.originalIndex, 1); props.showNotify('Producto eliminado', 'warning')">
 <svg class="w-4 h-4 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
 </button>
 </div>
 </div>

 <div class="grid grid-cols-3 gap-3 mb-4">
 <div>
 <label class="text-[8px] font-black text-[#c3c4c5] uppercase">Precio</label>
 <div class="relative mt-1">
 <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[#c3c4c5] font-bold text-xs">$</span>
 <input v-model="item.price" class="w-full bg-[#151a22] dark:bg-[#090d0a]/50 border border-[#21262d] dark:border-[#21262d] rounded-[6px] pl-7 pr-3 py-2 text-xs font-bold text-[#ffffff] dark:text-white outline-none" />
 </div>
 </div>
 <div>
 <label class="text-[8px] font-black text-[#c3c4c5] uppercase">Precio Original</label>
 <div class="relative mt-1">
 <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[#c3c4c5] font-bold text-xs">$</span>
 <input v-model="item.original_price" class="w-full bg-[#151a22] dark:bg-[#090d0a]/50 border border-[#21262d] dark:border-[#21262d] rounded-[6px] pl-7 pr-3 py-2 text-xs font-bold text-[#ffffff] dark:text-white outline-none" />
 </div>
 <span v-if="isDiscounted(item)" class="text-[8px] font-black text-emerald-500 mt-1 block">{{ Math.round((1 - parseFloat(String(item.price).replace(/[^0-9.-]+/g,'')) / parseFloat(String(item.original_price).replace(/[^0-9.-]+/g,''))) * 100) }}% OFF</span>
 </div>
 <div>
 <label class="text-[8px] font-black text-[#c3c4c5] uppercase">Estado</label>
 <select v-model="item.status" class="w-full bg-[#151a22] dark:bg-[#090d0a]/50 border border-[#21262d] dark:border-[#21262d] rounded-[6px] px-3 py-2 text-xs font-bold text-[#ffffff] dark:text-white outline-none mt-1">
 <option value="active">Activo</option>
 <option value="inactive">Inactivo</option>
 </select>
 </div>
 </div>

 <div class="mb-4">
 <label class="text-[8px] font-black text-[#c3c4c5] uppercase">Descripción</label>
 <textarea v-model="item.description" rows="2" class="w-full bg-[#151a22] dark:bg-[#090d0a]/50 border border-[#21262d] dark:border-[#21262d] rounded-[6px] px-4 py-3 text-xs font-medium text-[#a4aea6] dark:text-gray-300 outline-none mt-1 resize-none" placeholder="Descripción del producto..."></textarea>
 </div>

  <div class="pt-2 border-t border-[#21262d] dark:border-[#21262d] mt-4">
  <label class="text-[8px] font-black text-[#c3c4c5] uppercase mb-2 block">Imagen Principal</label>
  <div class="flex gap-4 items-center mb-4">
  <div class="w-16 h-16 rounded-[6px] object-cover border border-[#21262d] relative group/mainimg shrink-0 bg-[#151a22] overflow-hidden">
  <img v-if="item.image" :src="item.image" alt="Imagen de catálogo N3XT 3D" class="w-full h-full object-cover" @error="(e: any) => e.target.style.display = 'none'" />
  <div v-else class="w-full h-full flex items-center justify-center">
  <svg class="w-6 h-6 text-[#c3c4c5]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
  </div>
  <label class="absolute inset-0 bg-black/50 opacity-0 group-hover/mainimg:opacity-100 transition-opacity cursor-pointer flex items-center justify-center">
  <span class="text-[8px] font-black text-white uppercase">Subir</span>
  <input type="file" accept="image/*" class="hidden" @change="(e) => handleGalleryImageUpload(e, item, true)" />
  </label>
  </div>
  <div class="flex-1">
  <input v-model="item.image" placeholder="URL de imagen principal (Cloudinary recomendado)" class="w-full bg-[#151a22] dark:bg-[#090d0a]/50 border border-[#21262d] dark:border-[#21262d] rounded-[6px] px-4 py-2.5 text-[10px] font-medium text-[#a4aea6] dark:text-[#c3c4c5] outline-none" />
  </div>
  </div>

  <label class="text-[8px] font-black text-[#c3c4c5] uppercase mb-2 block">Imágenes Adicionales</label>
  <div class="flex flex-wrap gap-2 items-center">
  <div v-for="(addImg, addIdx) in (item.images || [])" :key="addIdx" class="relative group/addimg">
  <img :src="addImg" class="w-12 h-12 rounded-[6px] object-cover border border-[#21262d] dark:border-[#21262d]"  alt="Imagen N3XT 3D" />
  <button class="absolute -top-1.5 -right-1.5 w-4 h-4 bg-red-500 text-white rounded-[60px] text-[8px] font-black flex items-center justify-center opacity-0 group-hover/addimg:opacity-100 transition-all " aria-label="Botón interactivo" @click="removeCatalogImage(Number(item.originalIndex), Number(addIdx))">✕</button>
  </div>
  <label class="w-12 h-12 rounded-[6px] border-2 border-dashed border-gray-300 dark:border-[#21262d] flex items-center justify-center cursor-pointer hover:border-primary transition-all bg-[#151a22] dark:bg-transparent shrink-0" title="Subir desde computador">
  <svg class="w-4 h-4 text-[#c3c4c5]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
  <input type="file" accept="image/*" class="hidden" @change="(e) => handleGalleryImageUpload(e, item, false)" />
  </label>
  <div class="flex items-center gap-1 h-12">
  <input v-model="item._newImgUrl" placeholder="URL Cloudinary..." class="w-24 bg-[#151a22] dark:bg-[#090d0a]/50 border border-[#21262d] dark:border-[#21262d] rounded-[6px] px-2.5 py-2 text-[8px] font-mono text-[#a4aea6] dark:text-[#c3c4c5] outline-none h-full" @keyup.enter="addImageByUrl(item)" @click.stop />
  <button class="w-8 h-full rounded-[6px] bg-[#08872b]/10 hover:bg-[#08872b]/20 flex items-center justify-center text-[#8dd6ff] transition-all shrink-0" title="Añadir por URL" @click.stop="addImageByUrl(item)">
  <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
  </button>
  </div>
  </div>
  </div>
 </div>
 </div>
 </div>

 <div v-if="!settings?.web?.catalog?.length" class="text-center py-20">
 <div class="w-20 h-20 bg-[#151a22] dark:bg-[#151a22]/5 rounded-[2rem] flex items-center justify-center mx-auto mb-6">
 <svg class="w-10 h-10 text-[#c3c4c5]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
 </div>
 <p class="text-lg font-black text-[#c3c4c5] uppercase tracking-tight">Catálogo Vacío</p>
 <p class="text-sm text-[#a4aea6] mt-2">Crea tu primer producto para mostrar en la landing page</p>
 <button class="mt-6 px-8 py-4 bg-[#08872b] text-white rounded-[24px] font-black text-xs uppercase tracking-widest hover:-translate-y-0.5 transition-all" @click="handleCreateCatalogProduct">+ Crear Producto</button>
 </div>
 </div>

 <!-- ===== TAB: NOTICIAS (Completo con cuerpo, fecha, estado, categoría, slug) ===== -->
 <div v-if="webSubTab === 'news'" class="bg-[#151a22] dark:bg-[#151a22]/70 backdrop-blur-xl p-10 rounded-[3rem] border border-[#21262d] dark:border-[#21262d] ">
 <div class="flex items-center justify-between mb-10">
 <div>
 <h3 class="text-2xl font-black text-[#ffffff] dark:text-white uppercase tracking-tight">Noticias y Novedades</h3>
 <p class="text-xs text-[#c3c4c5] font-bold uppercase tracking-widest mt-1">{{ settings?.web?.news?.length || 0 }} artículos</p>
 </div>
 </div>

 <div v-for="(article, idx) in settings?.web?.news || []" :key="idx" class="p-6 bg-[#151a22] dark:bg-[#151a22]/5 rounded-[2rem] mb-5 border border-[#21262d] dark:border-[#21262d] hover: transition-all">
 <div class="flex items-start justify-between mb-5">
 <div class="flex items-center gap-4 flex-1 min-w-0">
 <div class="w-14 h-14 rounded-[24px] overflow-hidden bg-gray-200 dark:bg-[#283041] shrink-0 border border-[#21262d] dark:border-[#21262d]">
 <img v-if="article.image" :src="article.image" alt="Imagen de artículo N3XT 3D" class="w-full h-full object-cover" @error="(e: any) => { const t = e.target as HTMLImageElement; if (t) t.style.display = 'none' }" />
 <div v-else class="w-full h-full flex items-center justify-center">
 <svg class="w-5 h-5 text-[#c3c4c5]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
 </div>
 </div>
 <div class="min-w-0 flex-1">
 <input v-model="article.t" placeholder="Título del artículo" class="w-full bg-transparent text-sm font-black text-[#ffffff] dark:text-white outline-none placeholder:text-gray-300 dark:placeholder:text-gray-700" />
 <div class="flex items-center gap-3 mt-1">
 <span v-if="article.date" class="text-[9px] font-bold text-[#c3c4c5] uppercase tracking-widest">{{ new Date(article.date).toLocaleDateString('es-CO', { year: 'numeric', month: 'long', day: 'numeric' }) }}</span>
 <span :class="article.status === 'published' ? 'bg-emerald-500/10 text-emerald-500' : 'bg-amber-500/10 text-amber-500'" class="text-[8px] font-black px-2 py-0.5 rounded-[60px] uppercase tracking-wider">{{ article.status === 'published' ? 'Publicado' : 'Borrador' }}</span>
 </div>
 </div>
 </div>
 <button class="p-2 hover:bg-red-50 dark:hover:bg-red-500/10 rounded-[6px] transition-all shrink-0 ml-3" title="Eliminar noticia" @click="settings.web.news.splice(idx, 1)">
 <svg class="w-4 h-4 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
 </button>
 </div>

 <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 mb-6">
 <div class="col-span-1 xl:col-span-4">
 <label class="text-[8px] font-black text-[#c3c4c5] uppercase tracking-widest block mb-1.5">URL de Imagen</label>
 <input v-model="article.image" placeholder="https://..." class="w-full bg-[#151a22] dark:bg-[#283041] border border-[#21262d] dark:border-[#21262d] rounded-[6px] px-4 py-2.5 text-xs text-[#a4aea6] dark:text-gray-300 outline-none focus:ring-2 focus:ring-primary/20 transition-all" />
 </div>
 <div class="col-span-1 xl:col-span-2">
 <label class="text-[8px] font-black text-[#c3c4c5] uppercase tracking-widest block mb-1.5">Fecha de Publicación</label>
 <input v-model="article.date" type="date" class="w-full bg-[#151a22] dark:bg-[#283041] border border-[#21262d] dark:border-[#21262d] rounded-[6px] px-4 py-2.5 text-xs text-[#a4aea6] dark:text-gray-300 outline-none focus:ring-2 focus:ring-primary/20 transition-all" />
 </div>
 <div class="col-span-1 xl:col-span-3">
 <label class="text-[8px] font-black text-[#c3c4c5] uppercase tracking-widest block mb-1.5">Estado</label>
 <select v-model="article.status" class="w-full bg-[#151a22] dark:bg-[#283041] border border-[#21262d] dark:border-[#21262d] rounded-[6px] px-4 py-2.5 text-xs font-bold text-[#ffffff] dark:text-white outline-none focus:ring-2 focus:ring-primary/20 transition-all">
 <option value="draft">Borrador</option>
 <option value="published">Publicado</option>
 </select>
 </div>
 <div class="col-span-1 xl:col-span-3">
 <label class="text-[8px] font-black text-[#c3c4c5] uppercase tracking-widest block mb-1.5">Categoría</label>
 <input v-model="article.category" placeholder="Ej: Tecnología, Lanzamiento..." class="w-full bg-[#151a22] dark:bg-[#283041] border border-[#21262d] dark:border-[#21262d] rounded-[6px] px-4 py-2.5 text-xs text-[#a4aea6] dark:text-gray-300 outline-none focus:ring-2 focus:ring-primary/20 transition-all" />
 </div>
 </div>

 <div class="mb-4">
 <label class="text-[8px] font-black text-[#c3c4c5] uppercase tracking-widest block mb-1.5">Subtítulo</label>
 <input v-model="article.st" placeholder="Subtítulo o resumen breve del artículo" class="w-full bg-[#151a22] dark:bg-[#283041] border border-[#21262d] dark:border-[#21262d] rounded-[6px] px-4 py-2.5 text-xs text-[#a4aea6] dark:text-gray-300 outline-none focus:ring-2 focus:ring-primary/20 transition-all" />
 </div>

 <div class="mb-4">
 <label class="text-[8px] font-black text-[#c3c4c5] uppercase tracking-widest block mb-1.5">Cuerpo del Artículo</label>
 <textarea v-model="article.body" rows="5" placeholder="Escribe el contenido completo de la noticia aquí..." class="w-full bg-[#151a22] dark:bg-[#283041] border border-[#21262d] dark:border-[#21262d] rounded-[24px] px-5 py-4 text-sm font-medium text-[#a4aea6] dark:text-gray-300 outline-none focus:ring-2 focus:ring-primary/20 transition-all resize-y leading-relaxed"></textarea>
 </div>

  <div class="mb-4">
  <label class="text-[8px] font-black text-[#c3c4c5] uppercase tracking-widest block mb-1.5">URL del Articulo (boton Leer)</label>
  <input v-model="article.url" placeholder="https://ejemplo.com/noticia" class="w-full bg-[#151a22] dark:bg-[#283041] border border-[#21262d] dark:border-[#21262d] rounded-[6px] px-4 py-2.5 text-xs text-[#a4aea6] dark:text-gray-300 outline-none focus:ring-2 focus:ring-primary/20 transition-all" />
  <p class="text-[8px] text-[#a4aea6] mt-1">Enlace externo (abre en pestaña nueva). Si lo dejas vacio usa el slug de la noticia.</p>
  </div>

 <div class="grid grid-cols-12 gap-4">
 <div class="col-span-8">
 <label class="text-[8px] font-black text-[#c3c4c5] uppercase tracking-widest block mb-1.5">URL Amigable (Slug)</label>
 <div class="flex gap-2">
 <input v-model="article.slug" placeholder="auto-generado-del-titulo" class="flex-1 bg-[#151a22] dark:bg-[#283041] border border-[#21262d] dark:border-[#21262d] rounded-[6px] px-4 py-2.5 text-xs font-mono text-[#a4aea6] dark:text-[#c3c4c5] outline-none focus:ring-2 focus:ring-primary/20 transition-all" />
 <button class="px-4 py-2.5 bg-[#151a22] dark:bg-[#151a22]/5 hover:bg-gray-200 dark:hover:bg-[#151a22]/10 rounded-[6px] text-[9px] font-black text-[#a4aea6] uppercase tracking-wider transition-all shrink-0" title="Generar slug automático" @click="article.slug = article.t?.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z0-9]/g,'-').replace(/-+/g,'-').replace(/^-|-$/g,'') || ''">
 <svg class="w-3.5 h-3.5 inline-block" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
 </button>
 </div>
 </div>
 <div class="col-span-4 flex items-end justify-end gap-2">
 <button v-if="Number(idx) > 0" class="px-4 py-2.5 bg-[#151a22] dark:bg-[#151a22]/5 border border-[#21262d] dark:border-[#21262d] rounded-[6px] text-[9px] font-black text-[#a4aea6] uppercase tracking-wider hover:bg-[#151a22] transition-all" @click="const news = settings.web.news; const i = Number(idx); [news[i-1], news[i]] = [news[i], news[i-1]]">
 <svg class="w-3.5 h-3.5 inline-block" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
 Subir
 </button>
 <button v-if="Number(idx) < (settings?.web?.news?.length || 1) - 1" class="px-4 py-2.5 bg-[#151a22] dark:bg-[#151a22]/5 border border-[#21262d] dark:border-[#21262d] rounded-[6px] text-[9px] font-black text-[#a4aea6] uppercase tracking-wider hover:bg-[#151a22] transition-all" @click="const news = settings.web.news; const i = Number(idx); [news[i], news[i+1]] = [news[i+1], news[i]]">
 <svg class="w-3.5 h-3.5 inline-block" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
 Bajar
 </button>
 </div>
 </div>
 </div>

 <div v-if="!settings?.web?.news?.length" class="text-center py-16">
 <div class="w-16 h-16 bg-[#151a22] dark:bg-[#151a22]/5 rounded-[2rem] flex items-center justify-center mx-auto mb-4">
 <svg class="w-8 h-8 text-[#c3c4c5]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10l4 4v10a2 2 0 01-2 2zM7 8h5M7 12h8M7 16h8"/></svg>
 </div>
 <p class="text-sm font-black text-[#c3c4c5] uppercase tracking-tight">Sin noticias aún</p>
 <p class="text-xs text-[#a4aea6] mt-1">Agrega artículos para mostrar en la landing page</p>
 </div>

 <button class="px-6 py-4 bg-[#08872b]/10 hover:bg-[#08872b]/20 text-[#8dd6ff] rounded-[24px] text-[10px] font-black uppercase tracking-widest transition-all flex items-center gap-2" @click="settings.web.news.push({ t: '', st: '', image: '', body: '', url: '', date: new Date().toISOString().split('T')[0], status: 'draft', category: '', slug: '' })">
 <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
 Nueva Noticia
 </button>
 </div>

 <!-- ===== TAB: SOCIAL HUB (Completo con plataformas, descripción, orden) ===== -->
 <div v-if="webSubTab === 'social'" class="bg-[#151a22] dark:bg-[#151a22]/70 backdrop-blur-xl p-10 rounded-[3rem] border border-[#21262d] dark:border-[#21262d] ">
 <div class="flex items-center justify-between mb-8">
 <div>
 <h3 class="text-2xl font-black text-[#ffffff] dark:text-white uppercase tracking-tight">Plataformas Sociales</h3>
 <p class="text-xs text-[#c3c4c5] font-bold uppercase tracking-widest mt-1">{{ settings?.web?.social_links?.length || 0 }} plataformas vinculadas</p>
 </div>
 </div>

 <div v-for="(item, idx) in settings?.web?.social_links || []" :key="idx" class="p-5 bg-[#151a22] dark:bg-[#151a22]/5 rounded-[2rem] mb-4 border border-[#21262d] dark:border-[#21262d] hover: transition-all group/social">
 <div class="flex items-start justify-between mb-4">
 <div class="flex items-center gap-4 flex-1 min-w-0">
 <!-- Drag Handle -->
 <div class="cursor-grab active:cursor-grabbing opacity-20 group-hover/social:opacity-60 transition-opacity select-none" title="Arrastrar para reordenar">
 <svg class="w-5 h-5 text-[#a4aea6]" fill="currentColor" viewBox="0 0 24 24"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg>
 </div>
 <!-- Platform Icon Preview -->
 <div
class="w-12 h-12 rounded-[24px] flex items-center justify-center shrink-0 border-2" 
 :class="{
 'bg-blue-500/10 border-blue-500/30 text-blue-500': item.platform === 'facebook',
 'bg-pink-500/10 border-pink-500/30 text-pink-500': item.platform === 'instagram',
 'bg-black/10 border-black/20 text-black dark:bg-[#151a22]/10 dark:border-[#21262d] dark:text-white': item.platform === 'tiktok',
 'bg-emerald-500/10 border-emerald-500/30 text-emerald-500': item.platform === 'whatsapp',
 'bg-red-500/10 border-red-500/30 text-red-500': item.platform === 'youtube',
 'bg-purple-500/10 border-purple-500/30 text-purple-500': item.platform === 'linkedin',
 'bg-sky-500/10 border-sky-500/30 text-sky-500': item.platform === 'twitter' || item.platform === 'x',
 'bg-[#151a22] dark:bg-[#151a22]/5 border-[#21262d] dark:border-[#21262d] text-[#a4aea6]': !item.platform
 }"
 >
 <svg v-if="item.platform === 'facebook'" class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
 <svg v-else-if="item.platform === 'instagram'" class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
 <svg v-else-if="item.platform === 'tiktok'" class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg>
 <svg v-else-if="item.platform === 'whatsapp'" class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
 <svg v-else-if="item.platform === 'youtube'" class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 00-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 00.502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 002.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 002.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
 <svg v-else-if="item.platform === 'linkedin'" class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
 <svg v-else-if="item.platform === 'twitter' || item.platform === 'x'" class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
 <svg v-else class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
 </div>
 <div class="min-w-0 flex-1">
 <input v-model="item.name" placeholder="Nombre de la plataforma" class="w-full bg-transparent text-sm font-black text-[#ffffff] dark:text-white outline-none placeholder:text-gray-300 dark:placeholder:text-gray-700" />
 <div class="flex items-center gap-2 mt-0.5">
 <span v-if="item.platform" class="text-[9px] font-bold text-[#c3c4c5] uppercase tracking-wider">{{ platformLabels[item.platform] || item.platform }}</span>
 <span v-if="item.url" class="text-[8px] text-gray-300 dark:text-[#a4aea6] font-mono truncate">{{ item.url }}</span>
 </div>
 </div>
 </div>
 <button class="p-2 hover:bg-red-50 dark:hover:bg-red-500/10 rounded-[6px] transition-all shrink-0 ml-3 opacity-0 group-hover/social:opacity-100" title="Eliminar" @click="settings.web.social_links.splice(idx, 1)">
 <svg class="w-4 h-4 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
 </button>
 </div>

 <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 xl:pl-11">
 <div class="col-span-1 xl:col-span-4">
 <label class="text-[8px] font-black text-[#c3c4c5] uppercase tracking-widest block mb-1.5">Plataforma</label>
 <select v-model="item.platform" class="w-full bg-[#151a22] dark:bg-[#283041] border border-[#21262d] dark:border-[#21262d] rounded-[6px] px-4 py-2.5 text-xs font-bold text-[#ffffff] dark:text-white outline-none focus:ring-2 focus:ring-primary/20 transition-all">
 <option value="">Seleccionar...</option>
 <option value="facebook">Facebook</option>
 <option value="instagram">Instagram</option>
 <option value="tiktok">TikTok</option>
 <option value="whatsapp">WhatsApp</option>
 <option value="youtube">YouTube</option>
 <option value="linkedin">LinkedIn</option>
 <option value="twitter">X (Twitter)</option>
 <option value="telegram">Telegram</option>
 <option value="discord">Discord</option>
 <option value="spotify">Spotify</option>
 <option value="other">Otra</option>
 </select>
 </div>
 <div class="col-span-1 xl:col-span-5">
 <label class="text-[8px] font-black text-[#c3c4c5] uppercase tracking-widest block mb-1.5">URL / Enlace</label>
 <input v-model="item.url" placeholder="https://..." class="w-full bg-[#151a22] dark:bg-[#283041] border border-[#21262d] dark:border-[#21262d] rounded-[6px] px-4 py-2.5 text-xs text-[#a4aea6] dark:text-gray-300 outline-none focus:ring-2 focus:ring-primary/20 transition-all" />
 </div>
 <div class="col-span-1 xl:col-span-3 flex items-end gap-2">
 <button v-if="Number(idx) > 0" class="flex-1 px-3 py-2.5 bg-[#151a22] dark:bg-[#151a22]/5 border border-[#21262d] dark:border-[#21262d] rounded-[6px] text-[9px] font-black text-[#a4aea6] hover:bg-[#151a22] transition-all" title="Mover arriba" @click="const eco = settings.web.social_links; const i = Number(idx); [eco[i-1], eco[i]] = [eco[i], eco[i-1]]">
 <svg class="w-3.5 h-3.5 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/></svg>
 </button>
 <button v-if="Number(idx) < (settings?.web?.social_links?.length || 1) - 1" class="flex-1 px-3 py-2.5 bg-[#151a22] dark:bg-[#151a22]/5 border border-[#21262d] dark:border-[#21262d] rounded-[6px] text-[9px] font-black text-[#a4aea6] hover:bg-[#151a22] transition-all" title="Mover abajo" @click="const eco = settings.web.social_links; const i = Number(idx); [eco[i], eco[i+1]] = [eco[i+1], eco[i]]">
 <svg class="w-3.5 h-3.5 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
 </button>
 </div>
 </div>

 <div class="mt-2 pl-11">
 <label class="text-[8px] font-black text-[#c3c4c5] uppercase tracking-widest block mb-1.5">Descripción Corta</label>
 <input v-model="item.description" placeholder="Breve descripción de esta plataforma..." class="w-full bg-[#151a22] dark:bg-[#283041] border border-[#21262d] dark:border-[#21262d] rounded-[6px] px-4 py-2.5 text-xs text-[#a4aea6] dark:text-[#c3c4c5] outline-none focus:ring-2 focus:ring-primary/20 transition-all" />
 </div>
 </div>

 <div v-if="!settings?.web?.social_links?.length" class="text-center py-16">
 <div class="w-16 h-16 bg-[#151a22] dark:bg-[#151a22]/5 rounded-[2rem] flex items-center justify-center mx-auto mb-4">
 <svg class="w-8 h-8 text-[#c3c4c5]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
 </div>
 <p class="text-sm font-black text-[#c3c4c5] uppercase tracking-tight">Sin plataformas vinculadas</p>
 <p class="text-xs text-[#a4aea6] mt-1">Agrega enlaces a tus plataformas sociales</p>
 </div>

 <button class="px-6 py-4 bg-[#08872b]/10 hover:bg-[#08872b]/20 text-[#8dd6ff] rounded-[24px] text-[10px] font-black uppercase tracking-widest transition-all flex items-center gap-2" @click="if(!settings.web.social_links) settings.web.social_links = []; settings.web.social_links.push({ name: '', url: '', platform: '', description: '' })">
 <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
 Añadir Plataforma
 </button>
 </div>

  <!-- ===== SUBSECCION: NOVEDADES EN TIEMPO REAL ===== -->
  <div class="mt-10 p-8 bg-[#0a0f14] rounded-[2.5rem] border border-[#21262d]">
  <div class="flex items-center justify-between mb-6">
  <div>
  <h4 class="text-lg font-black text-[#ffffff] uppercase tracking-tight">Novedades en Tiempo Real</h4>
  <p class="text-xs text-[#c3c4c5] font-bold uppercase tracking-widest mt-0.5">{{ (settings?.web?.posts || []).length }} publicaciones en el feed</p>
  </div>
  </div>

  <div v-for="(post, idx) in settings?.web?.posts || []" :key="idx" class="p-6 bg-[#151a22] rounded-[2rem] mb-5 border border-[#21262d] hover:border-emerald-500/30 transition-all">
  <div class="flex items-start justify-between mb-5">
  <div class="flex items-center gap-3 flex-1 min-w-0">
  <div class="w-14 h-14 rounded-[16px] overflow-hidden bg-[#283041] shrink-0 border border-[#21262d]">
  <img v-if="post.i" :src="post.i" alt="Preview" class="w-full h-full object-cover" @error="(e) => { const t = e.target; if(t) t.style.display = 'none' }" />
  <div v-else class="w-full h-full flex items-center justify-center">
  <svg class="w-5 h-5 text-[#c3c4c5]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
  </div>
  </div>
  <div class="min-w-0 flex-1">
  <input v-model="post.t" placeholder="Titulo de la novedad" class="w-full bg-transparent text-sm font-black text-[#ffffff] outline-none placeholder:text-gray-600" />
  <span v-if="post.tag" class="inline-block mt-1 px-2 py-0.5 bg-emerald-500/10 text-emerald-400 text-[8px] font-black uppercase tracking-wider rounded-[60px]">{{ post.tag }}</span>
  </div>
  </div>
  <button class="p-2 hover:bg-red-500/10 rounded-[6px] transition-all shrink-0 ml-3" @click="settings.web.posts.splice(idx, 1)">
  <svg class="w-4 h-4 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
  </button>
  </div>

  <div class="grid grid-cols-1 xl:grid-cols-12 gap-4 mb-4">
  <div class="col-span-1 xl:col-span-8">
  <label class="text-[8px] font-black text-[#c3c4c5] uppercase tracking-widest block mb-1.5">URL de Imagen</label>
  <input v-model="post.i" placeholder="https://res.cloudinary.com/... o URL directa" class="w-full bg-[#283041] border border-[#21262d] rounded-[6px] px-4 py-2.5 text-xs text-[#a4aea6] outline-none focus:ring-2 focus:ring-emerald-500/20 transition-all" />
  </div>
  <div class="col-span-1 xl:col-span-4">
  <label class="text-[8px] font-black text-[#c3c4c5] uppercase tracking-widest block mb-1.5">Tag / Etiqueta</label>
  <input v-model="post.tag" placeholder="RESINA, FDM, MAKER..." class="w-full bg-[#283041] border border-[#21262d] rounded-[6px] px-4 py-2.5 text-xs text-[#a4aea6] outline-none focus:ring-2 focus:ring-emerald-500/20 transition-all" />
  </div>
  </div>

  <div class="mb-4">
  <label class="text-[8px] font-black text-[#c3c4c5] uppercase tracking-widest block mb-1.5">Descripcion del post</label>
  <input v-model="post.d" placeholder="Descripcion breve..." class="w-full bg-[#283041] border border-[#21262d] rounded-[6px] px-4 py-2.5 text-xs text-[#a4aea6] outline-none focus:ring-2 focus:ring-emerald-500/20 transition-all" />
  </div>

  <div class="mb-4">
  <label class="text-[8px] font-black text-[#c3c4c5] uppercase tracking-widest block mb-1.5">URL del post (enlace Ver Post)</label>
  <input v-model="post.url" placeholder="https://www.instagram.com/p/..." class="w-full bg-[#283041] border border-[#21262d] rounded-[6px] px-4 py-2.5 text-xs text-[#a4aea6] outline-none focus:ring-2 focus:ring-emerald-500/20 transition-all" />
  </div>

  <div class="grid grid-cols-3 gap-3">
  <div>
  <label class="text-[8px] font-black text-[#c3c4c5] uppercase tracking-widest block mb-1.5">Me Gusta</label>
  <input v-model="post.l" placeholder="1.2K" class="w-full bg-[#283041] border border-[#21262d] rounded-[6px] px-4 py-2.5 text-xs font-black text-rose-400 outline-none focus:ring-2 focus:ring-rose-500/20 transition-all" />
  </div>
  <div>
  <label class="text-[8px] font-black text-[#c3c4c5] uppercase tracking-widest block mb-1.5">Comentarios</label>
  <input v-model="post.c" placeholder="24" class="w-full bg-[#283041] border border-[#21262d] rounded-[6px] px-4 py-2.5 text-xs font-black text-blue-400 outline-none focus:ring-2 focus:ring-blue-500/20 transition-all" />
  </div>
  <div>
  <label class="text-[8px] font-black text-[#c3c4c5] uppercase tracking-widest block mb-1.5">Compartidos</label>
  <input v-model="post.s" placeholder="156" class="w-full bg-[#283041] border border-[#21262d] rounded-[6px] px-4 py-2.5 text-xs font-black text-purple-400 outline-none focus:ring-2 focus:ring-purple-500/20 transition-all" />
  </div>
  </div>
  </div>

  <div v-if="!(settings?.web?.posts?.length)" class="text-center py-10 border border-dashed border-[#21262d] rounded-[2rem] mt-4">
  <p class="text-sm font-black text-[#c3c4c5] uppercase tracking-tight">Sin publicaciones aun</p>
  <p class="text-xs text-[#a4aea6] mt-1">Agrega novedades para mostrar en el Social Hub</p>
  </div>

  <button class="mt-4 px-6 py-4 bg-[#08872b]/10 hover:bg-[#08872b]/20 text-[#8dd6ff] rounded-[24px] text-[10px] font-black uppercase tracking-widest transition-all flex items-center gap-2" @click="if(!settings.web.posts) settings.web.posts = []; settings.web.posts.push({ t: '', d: '', l: '', c: '', s: '', i: '', tag: '', url: '' })">
  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
  Nueva Novedad
  </button>
  </div>


 <!-- ===== TAB: ECOSISTEMA N3XT 3D ===== -->
 <div v-if="webSubTab === 'ecosystem'" class="bg-[#151a22] dark:bg-[#151a22]/70 backdrop-blur-xl p-10 rounded-[3rem] border border-[#21262d] dark:border-[#21262d] ">
 <div class="flex items-center justify-between mb-8">
 <div>
 <h3 class="text-2xl font-black text-[#ffffff] dark:text-white uppercase tracking-tight">Ecosistema N3XT 3D</h3>
 <p class="text-xs text-[#c3c4c5] font-bold uppercase tracking-widest mt-1">{{ settings?.web?.ecosystem?.length || 0 }} tarjetas configuradas</p>
 </div>
 </div>

 <div v-for="(item, idx) in settings?.web?.ecosystem || []" :key="idx" class="p-5 bg-[#151a22] dark:bg-[#151a22]/5 rounded-[2rem] mb-6 border border-[#21262d] dark:border-[#21262d] hover:border-[#08872b]/40 transition-all group/eco">
 <div class="flex items-start justify-between mb-4">
 <div class="flex items-center gap-4 flex-1 min-w-0">
 <div class="cursor-grab active:cursor-grabbing opacity-20 group-hover/eco:opacity-60 transition-opacity select-none" title="Arrastrar para reordenar">
 <svg class="w-5 h-5 text-[#a4aea6]" fill="currentColor" viewBox="0 0 24 24"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg>
 </div>
 <div class="w-16 h-16 rounded-[16px] flex items-center justify-center shrink-0 border border-[#21262d] dark:border-[#21262d] bg-[#151a22] dark:bg-[#283041] overflow-hidden">
 <img v-if="item.type === 'image' && item.i" :src="item.i.split(',')[0]" class="w-full h-full object-cover" />
 <span v-else-if="item.type === 'icon' && item.i" class="text-emerald-500 w-8 h-8 flex items-center justify-center" v-html="item.i"></span>
 <svg v-else class="w-6 h-6 text-[#c3c4c5]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
 </div>
 <div class="min-w-0 flex-1">
 <div class="flex gap-2">
 <input v-model="item.t1" placeholder="N3XT" class="w-1/2 bg-transparent text-lg font-black text-[#ffffff] dark:text-white outline-none placeholder:text-gray-300 dark:placeholder:text-gray-700" />
 <input v-model="item.t2" placeholder="LAB" class="w-1/2 bg-transparent text-lg font-black text-emerald-500 outline-none placeholder:text-emerald-500/50" />
 </div>
 <div class="mt-2">
 <select v-model="item.type" class="bg-[#151a22] dark:bg-[#090d0a]/50 border border-[#21262d] dark:border-[#21262d] rounded-[6px] px-2 py-1 text-[9px] font-bold text-[#a4aea6] dark:text-white outline-none">
 <option value="image">Imagen</option>
 <option value="icon">Icono (SVG)</option>
 </select>
 </div>
 </div>
 </div>
 <button class="p-2 hover:bg-red-50 dark:hover:bg-red-500/10 rounded-[6px] transition-all shrink-0 ml-3 opacity-0 group-hover/eco:opacity-100" title="Eliminar Tarjeta" @click="settings.web.ecosystem.splice(idx, 1)">
 <svg class="w-4 h-4 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
 </button>
 </div>

 <div class="mb-4">
 <label class="text-[8px] font-black text-[#c3c4c5] uppercase tracking-widest block mb-1.5">Fuente de Medios (URL Imagen o Código SVG)</label>
 <textarea v-model="item.i" rows="2" placeholder="URL(s) de imagen separadas por comas, o código <svg>..." class="w-full bg-[#151a22] dark:bg-[#283041] border border-[#21262d] dark:border-[#21262d] rounded-[12px] px-4 py-2.5 text-xs text-[#a4aea6] dark:text-gray-300 outline-none focus:ring-2 focus:ring-primary/20 transition-all font-mono resize-y"></textarea>
 </div>

 <div class="mb-4">
 <label class="text-[8px] font-black text-[#c3c4c5] uppercase tracking-widest block mb-1.5">Descripción</label>
 <textarea v-model="item.d" rows="2" placeholder="Descripción de esta sección del ecosistema..." class="w-full bg-[#151a22] dark:bg-[#283041] border border-[#21262d] dark:border-[#21262d] rounded-[12px] px-4 py-2.5 text-xs text-[#a4aea6] dark:text-[#c3c4c5] outline-none focus:ring-2 focus:ring-primary/20 transition-all resize-y"></textarea>
 </div>

 <div class="bg-[#151a22] dark:bg-[#090d0a]/30 rounded-[1rem] p-4 border border-[#21262d] dark:border-[#21262d]">
 <label class="text-[8px] font-black text-[#c3c4c5] uppercase tracking-widest block mb-3">Estadísticas (Stats)</label>
 <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
 <div v-for="(stat, sidx) in item.stats || []" :key="sidx" class="flex gap-2">
 <div class="flex-1">
 <input v-model="stat.val" placeholder="Valor (ej: +500)" class="w-full bg-[#151a22] dark:bg-[#283041] border border-[#21262d] dark:border-[#21262d] rounded-[6px] px-3 py-1.5 text-[10px] font-black text-white outline-none mb-1" />
 <input v-model="stat.label" placeholder="Etiqueta (ej: Productos)" class="w-full bg-[#151a22] dark:bg-[#283041] border border-[#21262d] dark:border-[#21262d] rounded-[6px] px-3 py-1.5 text-[8px] font-bold text-[#a4aea6] uppercase outline-none" />
 </div>
 <button class="text-red-400 hover:text-red-300 px-1 shrink-0" @click="item.stats.splice(sidx, 1)">✕</button>
 </div>
 </div>
 <button v-if="(item.stats || []).length < 3" class="mt-3 text-[9px] font-black text-[#8dd6ff] uppercase tracking-widest hover:text-[#08872b] transition-colors" @click="if(!item.stats) item.stats = []; item.stats.push({val: '', label: ''})">+ Añadir Stat</button>
 </div>

 </div>

 <button class="px-6 py-4 bg-[#08872b]/10 hover:bg-[#08872b]/20 text-[#8dd6ff] rounded-[24px] text-[10px] font-black uppercase tracking-widest transition-all flex items-center gap-2" @click="if(!settings.web.ecosystem) settings.web.ecosystem = []; settings.web.ecosystem.push({ type: 'image', t1: 'NUEVA', t2: 'TARJETA', d: '', i: '', stats: [] })">
 <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
 Añadir Tarjeta de Ecosistema
 </button>
 </div>

 <!-- Save Button -->
 <div class="pt-8 border-t border-[#21262d] dark:border-[#21262d] flex justify-end">
 <button class="px-12 py-5 bg-[#08872b] text-white rounded-[2rem] font-black text-xs uppercase tracking-widest -primary/20 hover:-translate-y-0.5 hover: transition-all" @click="handleSaveGallery">
 Publicar Cambios en Landing Page
 </button>
 </div>
 </div>
</template>
