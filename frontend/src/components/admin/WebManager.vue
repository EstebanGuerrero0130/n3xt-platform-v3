<script setup lang="ts">
/* eslint-disable vue/no-mutating-props -- `settings` es el objeto compartido del panel:
   aquí se edita en sitio y AdminDashboard lo publica entero con save-settings. */
import { localDateISO } from '../../utils/format'
import { HERO_PILL, DEFAULT_HIGHLIGHTS, DEFAULT_PROCESS_STEPS, DEFAULT_PRINT_LINES, DEFAULT_FINISHING_STEPS } from '../../data/homeDefaults'
import { ref, computed } from 'vue'
import logger from '../../utils/logger'
import { api } from '../../services/api'
import VueDraggableLib from 'vuedraggable'
import ReviewsManager from './ReviewsManager.vue'
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

// Ítems por unidad que puede descontar una venta del catálogo
const stockItems = computed(() => (props.inventoryData || []).filter((m: any) => m.type && m.type !== 'material'))

// Catalog functions
const editingCatalogItemIndex = ref<number | null>(null)

const handleCreateCatalogProduct = () => {
  const newProduct = {
    id: Date.now(), name: '', category: 'General', subcategory: '', price: '0',
    original_price: '0', cost: '0', stock_item: null, image: '', images: [], description: '', status: 'active'
  }
  props.settings.web.catalog.unshift(newProduct)
  editingCatalogItemIndex.value = 0
  props.showNotify('Nuevo producto creado en el borrador del catálogo', 'success')
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

// Agrupa por categoría guardando la REFERENCIA al producto: antes se copiaba
// ({ ...item }) y lo que se editaba en el formulario nunca llegaba al catálogo
const groupedCatalog = computed(() => {
  const groups: Record<string, { item: any; index: number }[]> = {}
  const catalog = props.settings?.web?.catalog || []
  catalog.forEach((item: any, index: number) => {
    if (!item) return
    const cat = item.category || 'General'
    if (!groups[cat]) groups[cat] = []
    groups[cat].push({ item, index })
  })
  return groups
})

const removeCatalogItem = (index: number) => {
  const item = props.settings.web.catalog[index]
  if (!confirm(`¿Quitar «${item?.name || 'este producto'}» del catálogo? Se aplica al publicar.`)) return
  props.settings.web.catalog.splice(index, 1)
}
const removeFromList = (list: any[], idx: number, what: string) => {
  if (!confirm(`¿Quitar ${what}? Se aplica al publicar.`)) return
  list.splice(idx, 1)
}
const moveInList = (list: any[], from: number, to: number) => {
  if (!list || to < 0 || to >= list.length) return
  ;[list[from], list[to]] = [list[to], list[from]]
}

// Redes: la Home solo enlaza URLs completas; un "@usuario" se convierte al perder el foco
const SOCIAL_BASE: Record<string, string> = {
  tiktok: 'https://www.tiktok.com/@', instagram: 'https://www.instagram.com/', facebook: 'https://www.facebook.com/', youtube: 'https://www.youtube.com/@',
}
const SOCIAL_FIELDS = [
  { k: 'tiktok', label: 'TikTok', ph: 'https://www.tiktok.com/@n3xt3d' },
  { k: 'instagram', label: 'Instagram', ph: 'https://www.instagram.com/n3xt_3d' },
  { k: 'facebook', label: 'Facebook', ph: 'https://www.facebook.com/N3XT3D' },
  { k: 'youtube', label: 'YouTube', ph: 'https://www.youtube.com/@n3xt3d' },
  { k: 'whatsapp', label: 'WhatsApp', ph: 'https://wa.me/573118796416' },
]
const normalizeSocial = (key: string) => {
  const social = props.settings.web.social
  const v = String(social[key] || '').trim()
  if (!v || /^https?:\/\//i.test(v)) return
  const handle = v.replace(/^@/, '')
  if (SOCIAL_BASE[key]) social[key] = SOCIAL_BASE[key] + handle
  else if (key === 'whatsapp' && /^\+?\d[\d\s]+$/.test(v)) social[key] = 'https://wa.me/' + v.replace(/\D/g, '')
}
const isUrl = (v: any) => /^https?:\/\//i.test(String(v || '').trim())

// ─── Textos de la Home (web.home). Se crean desde los textos actuales al abrir la pestaña ───
const defaultHome = () => ({
  hero_pill: HERO_PILL,
  hero_sub: '',
  highlights: DEFAULT_HIGHLIGHTS.map(h => ({ ...h })),
  print_lines: DEFAULT_PRINT_LINES.map(l => ({ t: l.t, d: l.d, tags: l.tags.join(', '), i: l.i })),
  process_steps: DEFAULT_PROCESS_STEPS.map(x => ({ ...x })),
  finishing_steps: DEFAULT_FINISHING_STEPS.map(x => ({ ...x })),
})
const home = computed(() => props.settings.web.home)
const ensureHome = () => {
  if (!props.settings.web.home || typeof props.settings.web.home !== 'object') props.settings.web.home = defaultHome()
}
const resetHome = () => {
  if (!confirm('¿Volver a los textos originales de la Home? Se aplica al publicar.')) return
  props.settings.web.home = defaultHome()
}

const SUBTABS = [
  { id: 'dashboard', n: 'Resumen' }, { id: 'home', n: 'Inicio' }, { id: 'general', n: 'General' }, { id: 'catalog', n: 'Catálogo' },
  { id: 'gallery', n: 'Galería' }, { id: 'news', n: 'Noticias' }, { id: 'posts', n: 'En redes' }, { id: 'reviews', n: 'Reseñas' }, { id: 'social', n: 'Plataformas' },
]
const money = (v: any) => {
  const n = parseFloat(String(v ?? '').replace(/[^0-9.-]+/g, ''))
  return isNaN(n) ? '—' : '$' + Math.round(n).toLocaleString('es-CO')
}
const discountPct = (item: any) => {
  const p = parseFloat(String(item.price).replace(/[^0-9.-]+/g, ''))
  const op = parseFloat(String(item.original_price).replace(/[^0-9.-]+/g, ''))
  return Math.round((1 - p / op) * 100)
}

// ─── Posts de redes ("Novedades en tiempo real" en la Home) ───
const addPost = () => {
  if (!props.settings.web.posts) props.settings.web.posts = []
  props.settings.web.posts.push({ t: '', d: '', i: '', tag: '', url: '', status: 'published' })
}
const movePost = (from: number, to: number) => {
  const list = props.settings.web.posts
  if (!list || to < 0 || to >= list.length) return
  ;[list[from], list[to]] = [list[to], list[from]]
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
    date: localDateISO()
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
    props.showNotify('Error al subir imagen', 'error')
  } finally {
    input.value = ''
  }
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

  let uploaded = 0
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
        uploaded++
      }
    } catch (err) {
      logger.error('Error subiendo imagen:', err)
      props.showNotify?.('Error al subir imagen: ' + ((err as Error)?.message || 'Error de red'), 'error')
    }
  }
  
  if (uploaded) props.showNotify?.(`${uploaded} de ${files.length} imagen(es) subida(s)`, uploaded === files.length ? 'success' : 'warning')

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




// Antes se guardaba la foto en base64 dentro de los ajustes (MB en cada carga
// del sitio). Ahora se sube como la galería y se guarda solo la URL.
const handleCatalogImageUpload = (e: Event, item: any, isMain = false) => handleGalleryImageUpload(e, item, isMain)
</script>

<template>
  <div class="space-y-8 pb-24 md:pb-0">
    <header class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-6">
      <div>
        <h1 class="admin-title">Sitio <span class="text-brand">web</span></h1>
        <p class="admin-lead">Catálogo, galería, noticias y redes que ve el público. Los cambios se ven en el sitio al publicar.</p>
      </div>
      <button type="button" class="btn-primary btn-lg" @click="emit('save-settings')">Publicar cambios</button>
    </header>

    <nav class="flex gap-2 overflow-x-auto no-scrollbar -mx-1 px-1" aria-label="Secciones del sitio">
      <button v-for="st in SUBTABS" :key="st.id" type="button" class="chip shrink-0" :aria-pressed="webSubTab === st.id" @click="st.id === 'home' && ensureHome(); webSubTab = st.id">{{ st.n }}</button>
    </nav>

    <!-- ===== Resumen ===== -->
    <div v-if="webSubTab === 'dashboard'" class="space-y-6">
      <dl class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4">
        <div class="admin-card"><dt class="admin-label">Productos</dt><dd class="mt-2 admin-kpi">{{ dashboardStats.products }}</dd><dd class="mt-1 text-sm text-muted">{{ dashboardStats.productsActive }} activos<template v-if="dashboardStats.productsDiscounted"> · {{ dashboardStats.productsDiscounted }} con descuento</template></dd></div>
        <div class="admin-card"><dt class="admin-label">Proyectos en galería</dt><dd class="mt-2 admin-kpi">{{ dashboardStats.images }}</dd></div>
        <div class="admin-card"><dt class="admin-label">Noticias</dt><dd class="mt-2 admin-kpi">{{ dashboardStats.articles }}</dd><dd class="mt-1 text-sm text-muted">{{ dashboardStats.articlesPublished }} publicadas</dd></div>
        <div class="admin-card"><dt class="admin-label">Plataformas</dt><dd class="mt-2 admin-kpi">{{ dashboardStats.platforms }}</dd></div>
      </dl>
      <section class="admin-card" aria-labelledby="web-checklist">
        <h2 id="web-checklist" class="admin-h2">Lista para publicar</h2>
        <ul class="mt-4 divide-y divide-line">
          <li
v-for="(item, idx) in [
            { label: 'Logo de la empresa', done: !!settings?.company_logo, tab: '', hint: 'Súbelo en Parámetros' },
            { label: 'Redes sociales con enlace completo', done: ['tiktok', 'instagram', 'facebook', 'youtube'].some(k => isUrl(settings?.web?.social?.[k])), tab: 'general', hint: 'Complétalas en General' },
            { label: 'Productos en el catálogo', done: dashboardStats.products > 0, tab: 'catalog', hint: 'Crea productos en Catálogo' },
            { label: 'Proyectos en la galería', done: dashboardStats.images > 0, tab: 'gallery', hint: 'Agrega fotos en Galería' },
            { label: 'Noticias publicadas', done: dashboardStats.articlesPublished > 0, tab: 'news', hint: 'Publica en Noticias' },
          ]" :key="idx" class="py-3 flex items-center gap-3">
            <span :class="['w-6 h-6 shrink-0 rounded-full inline-flex items-center justify-center', item.done ? 'bg-success/15 text-success' : 'bg-surface-2 text-muted']" aria-hidden="true">
              <svg v-if="item.done" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
              <span v-else class="w-1.5 h-1.5 rounded-full bg-current"></span>
            </span>
            <span class="flex-1">{{ item.label }}<span class="sr-only">{{ item.done ? ' (listo)' : ' (pendiente)' }}</span></span>
            <button v-if="!item.done && item.tab" type="button" class="text-sm font-semibold text-brand hover:underline" @click="webSubTab = item.tab">{{ item.hint }}</button>
            <span v-else-if="!item.done" class="text-sm text-muted">{{ item.hint }}</span>
          </li>
        </ul>
      </section>
    </div>

    <!-- ===== Inicio (textos de la Home) ===== -->
    <div v-if="webSubTab === 'home' && home" class="space-y-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-muted">Los títulos grandes de cada sección se mantienen; aquí cambias los textos y las fotos.</p>
        <button type="button" class="btn-outlined btn-sm" @click="resetHome">Volver a los textos originales</button>
      </div>

      <section class="admin-card" aria-labelledby="home-hero">
        <h2 id="home-hero" class="admin-h2">Portada</h2>
        <div class="mt-4 grid gap-4">
          <div><label for="home-pill" class="admin-label mb-2">Etiqueta sobre el logo</label><input id="home-pill" v-model="home.hero_pill" maxlength="60" class="field"></div>
          <div>
            <label for="home-sub" class="admin-label mb-2">Frase bajo el logo</label>
            <textarea id="home-sub" v-model="home.hero_sub" rows="2" maxlength="240" placeholder="Especialistas en fabricación digital: miniaturas, figuras y piezas de colección con precisión industrial y el espíritu de Colombia." class="field !h-auto py-2.5"></textarea>
            <p class="mt-1.5 text-sm text-muted">Vacío deja la frase original, con las palabras resaltadas en verde.</p>
          </div>
          <fieldset>
            <legend class="admin-label mb-2">Cifras</legend>
            <div class="grid gap-3 sm:grid-cols-3">
              <div v-for="(h, i) in home.highlights" :key="i" class="grid grid-cols-[5rem_1fr] gap-2">
                <label :for="`home-hl-v-${i}`" class="sr-only">Cifra {{ Number(i) + 1 }}</label>
                <input :id="`home-hl-v-${i}`" v-model="h.val" maxlength="12" class="field font-bold tabular-nums" placeholder="8K">
                <label :for="`home-hl-l-${i}`" class="sr-only">Texto de la cifra {{ Number(i) + 1 }}</label>
                <input :id="`home-hl-l-${i}`" v-model="h.label" maxlength="40" class="field" placeholder="Resolución en resina">
              </div>
            </div>
          </fieldset>
        </div>
      </section>

      <section aria-labelledby="home-prints">
        <h2 id="home-prints" class="admin-h2 mb-3">Lo que imprimimos</h2>
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
          <article v-for="(l, i) in home.print_lines" :key="i" class="admin-card space-y-3">
            <div class="aspect-[4/3] rounded-[var(--radius-control)] bg-surface-2 overflow-hidden relative">
              <img v-if="l.i" :src="l.i" alt="" class="w-full h-full object-cover">
              <label class="absolute inset-x-0 bottom-0 py-1.5 text-center text-sm font-semibold bg-black/60 text-white cursor-pointer hover:bg-black/75">
                Cambiar foto
                <input type="file" accept="image/*" class="sr-only" @change="(e: any) => handleGalleryImageUpload(e, { get image() { return l.i }, set image(v: string) { l.i = v } }, true)">
              </label>
            </div>
            <div><label :for="`home-pl-t-${i}`" class="admin-label mb-1">Título</label><input :id="`home-pl-t-${i}`" v-model="l.t" maxlength="60" class="field h-10 font-semibold"></div>
            <div><label :for="`home-pl-d-${i}`" class="admin-label mb-1">Texto</label><textarea :id="`home-pl-d-${i}`" v-model="l.d" rows="3" maxlength="240" class="field !h-auto py-2.5"></textarea></div>
            <div><label :for="`home-pl-tags-${i}`" class="admin-label mb-1">Etiquetas <span class="opacity-70">(separadas por comas)</span></label><input :id="`home-pl-tags-${i}`" v-model="l.tags" class="field h-10"></div>
          </article>
        </div>
      </section>

      <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <section class="admin-card" aria-labelledby="home-steps">
          <h2 id="home-steps" class="admin-h2">Cómo trabajamos</h2>
          <ol class="mt-4 space-y-4">
            <li v-for="(st, i) in home.process_steps" :key="i" class="grid gap-2">
              <label :for="`home-ps-t-${i}`" class="admin-label">Paso {{ Number(i) + 1 }}</label>
              <input :id="`home-ps-t-${i}`" v-model="st.t" maxlength="60" class="field h-10 font-semibold">
              <label :for="`home-ps-d-${i}`" class="sr-only">Descripción del paso {{ Number(i) + 1 }}</label>
              <textarea :id="`home-ps-d-${i}`" v-model="st.d" rows="2" maxlength="240" class="field !h-auto py-2.5"></textarea>
            </li>
          </ol>
        </section>
        <section class="admin-card" aria-labelledby="home-finish">
          <h2 id="home-finish" class="admin-h2">Acabado a mano</h2>
          <ol class="mt-4 space-y-4">
            <li v-for="(st, i) in home.finishing_steps" :key="i" class="grid gap-2">
              <label :for="`home-fs-n-${i}`" class="admin-label">Etapa {{ Number(i) + 1 }}</label>
              <input :id="`home-fs-n-${i}`" v-model="st.n" maxlength="40" class="field h-10 font-semibold">
              <label :for="`home-fs-d-${i}`" class="sr-only">Descripción de la etapa {{ Number(i) + 1 }}</label>
              <textarea :id="`home-fs-d-${i}`" v-model="st.d" rows="2" maxlength="240" class="field !h-auto py-2.5"></textarea>
            </li>
          </ol>
        </section>
      </div>
    </div>

    <!-- ===== General ===== -->
    <div v-if="webSubTab === 'general'" class="grid grid-cols-1 gap-6 xl:grid-cols-2 items-start">
      <section class="admin-card" aria-labelledby="web-social">
        <h2 id="web-social" class="admin-h2">Redes sociales</h2>
        <p class="mt-1 text-sm text-muted">Pega el enlace completo. Si escribes solo <span class="font-mono">@usuario</span>, lo completamos al salir del campo.</p>
        <div class="mt-5 space-y-4">
          <div v-for="f in SOCIAL_FIELDS" :key="f.k">
            <label :for="`web-social-${f.k}`" class="admin-label mb-2">{{ f.label }}</label>
            <input :id="`web-social-${f.k}`" v-model="settings.web.social[f.k]" type="url" inputmode="url" :placeholder="f.ph" class="field" @blur="normalizeSocial(f.k)">
            <p v-if="settings.web.social[f.k] && !isUrl(settings.web.social[f.k])" class="mt-1.5 text-sm text-warning">Sin enlace completo no aparece en el sitio.</p>
          </div>
        </div>
      </section>
      <div class="space-y-6">
        <section class="admin-card" aria-labelledby="web-status">
          <h2 id="web-status" class="admin-h2">Estado del taller</h2>
          <p class="mt-1 text-sm text-muted">Se muestra en el pie de página del sitio.</p>
          <label for="web-status-select" class="sr-only">Estado del taller</label>
          <select id="web-status-select" v-model="settings.web.workshop_status" class="mt-4 field">
            <option value="Abierto de lunes a sábado">Abierto de lunes a sábado</option>
            <option value="operativo 24/7">Operativo 24/7</option>
            <option value="solo por encargo">Solo por encargo</option>
            <option value="cerrado temporalmente">Cerrado temporalmente</option>
            <option value="mantenimiento">En mantenimiento</option>
          </select>
        </section>
        <section class="admin-card" aria-labelledby="web-company-note">
          <h2 id="web-company-note" class="admin-h2">Datos de la empresa y textos legales</h2>
          <p class="mt-2 text-sm text-muted">El nombre, NIT, teléfono y correo se editan en <strong class="text-ink">Parámetros</strong>. La política de privacidad y los términos están en sus propias páginas del sitio (/privacy y /terms).</p>
        </section>
      </div>
    </div>

    <!-- ===== Catálogo ===== -->
    <div v-if="webSubTab === 'catalog'" class="space-y-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-muted">{{ settings?.web?.catalog?.length || 0 }} productos · solo los activos se ven en el sitio</p>
        <div class="flex gap-2">
          <button v-if="settings?.web?.catalog?.length" type="button" class="btn-outlined btn-sm text-danger" @click="handleResetCatalog">Vaciar catálogo</button>
          <button type="button" class="btn-primary btn-sm" @click="handleCreateCatalogProduct">Nuevo producto</button>
        </div>
      </div>

      <div v-if="!settings?.web?.catalog?.length" class="admin-card py-14 text-center">
        <p class="font-semibold">El catálogo está vacío.</p>
        <p class="mt-1 text-muted">Crea un producto para que aparezca en la página Catálogo.</p>
        <button type="button" class="mt-6 btn-primary" @click="handleCreateCatalogProduct">Crear producto</button>
      </div>

      <section v-for="(group, category) in groupedCatalog" :key="category" :aria-label="String(category)">
        <h2 class="admin-h2 mb-3">{{ category }} <span class="text-base font-normal text-muted">({{ group.length }})</span></h2>
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
          <article v-for="{ item, index } in group" :id="`catalog-item-${index}`" :key="item.id ?? index" class="admin-card">
            <div class="flex gap-4">
              <div class="w-24 h-24 shrink-0 rounded-[var(--radius-control)] bg-surface-2 overflow-hidden relative">
                <img v-if="item.image" :src="item.image" alt="" class="w-full h-full object-cover" @error="(e: any) => e.target.style.display = 'none'">
                <label class="absolute inset-x-0 bottom-0 py-1 text-center text-xs font-semibold bg-black/60 text-white cursor-pointer hover:bg-black/75">
                  {{ item.image ? 'Cambiar' : 'Subir foto' }}
                  <input type="file" accept="image/*" class="sr-only" @change="(e: any) => handleCatalogImageUpload(e, item, true)">
                </label>
              </div>
              <div class="min-w-0 flex-1 space-y-3">
                <div>
                  <label :for="`cat-name-${index}`" class="sr-only">Nombre del producto</label>
                  <input :id="`cat-name-${index}`" v-model="item.name" placeholder="Nombre del producto" class="field font-semibold">
                </div>
                <div class="grid grid-cols-2 gap-2">
                  <div><label :for="`cat-c-${index}`" class="admin-label mb-1">Categoría</label><input :id="`cat-c-${index}`" v-model="item.category" class="field h-10"></div>
                  <div><label :for="`cat-s-${index}`" class="admin-label mb-1">Subcategoría</label><input :id="`cat-s-${index}`" v-model="item.subcategory" class="field h-10"></div>
                </div>
              </div>
              <button type="button" class="icon-btn shrink-0 -mr-2 -mt-1 text-muted hover:text-danger hover:bg-danger/10" :aria-label="`Quitar ${item.name || 'producto'}`" title="Quitar" @click="removeCatalogItem(index)">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
              </button>
            </div>

            <div class="mt-4 grid grid-cols-3 gap-2">
              <div><label :for="`cat-p-${index}`" class="admin-label mb-1">Precio ($)</label><input :id="`cat-p-${index}`" v-model="item.price" inputmode="numeric" class="field h-10 tabular-nums"></div>
              <div>
                <label :for="`cat-op-${index}`" class="admin-label mb-1">Precio antes</label>
                <input :id="`cat-op-${index}`" v-model="item.original_price" inputmode="numeric" class="field h-10 tabular-nums">
              </div>
              <div>
                <label :for="`cat-st-${index}`" class="admin-label mb-1">Estado</label>
                <select :id="`cat-st-${index}`" v-model="item.status" class="field h-10">
                  <option value="active">Activo</option>
                  <option value="inactive">Oculto</option>
                </select>
              </div>
            </div>
            <div class="mt-2 grid grid-cols-3 gap-2">
              <div>
                <label :for="`cat-cost-${index}`" class="admin-label mb-1">Costo ($)</label>
                <input :id="`cat-cost-${index}`" v-model="item.cost" inputmode="numeric" placeholder="0" class="field h-10 tabular-nums">
              </div>
              <div class="col-span-2">
                <label :for="`cat-stock-${index}`" class="admin-label mb-1">Descontar del inventario</label>
                <select :id="`cat-stock-${index}`" v-model="item.stock_item" class="field h-10">
                  <option :value="null">No descontar</option>
                  <!-- Solo ítems por unidad: filamento y resina se miden en gramos y no aplican -->
                  <option v-for="mat in stockItems" :key="mat.id" :value="mat.id">{{ mat.name }}</option>
                </select>
              </div>
            </div>
            <p class="mt-1 text-sm text-muted">El costo es lo que te cuesta producirlo (para el margen real). Al confirmar el pago se descuentan las unidades del ítem elegido.</p>
            <p v-if="isDiscounted(item)" class="mt-2 text-sm text-success font-semibold">{{ discountPct(item) }} % de descuento · se ve {{ money(item.price) }} en lugar de {{ money(item.original_price) }}</p>

            <div class="mt-4">
              <label :for="`cat-d-${index}`" class="admin-label mb-1">Descripción</label>
              <textarea :id="`cat-d-${index}`" v-model="item.description" rows="2" class="field !h-auto py-2.5"></textarea>
            </div>

            <div class="mt-4">
              <p class="admin-label mb-2">Más fotos</p>
              <div class="flex flex-wrap gap-2">
                <div v-for="(img, imgIdx) in (item.images || [])" :key="imgIdx" class="relative w-16 h-16 rounded-[var(--radius-control)] overflow-hidden bg-surface-2">
                  <img :src="img" alt="" class="w-full h-full object-cover">
                  <button type="button" class="absolute top-0.5 right-0.5 w-6 h-6 rounded-full bg-black/70 text-white text-xs" :aria-label="`Quitar la foto ${Number(imgIdx) + 1}`" @click="removeCatalogImage(index, Number(imgIdx))">✕</button>
                </div>
                <label class="w-16 h-16 rounded-[var(--radius-control)] border border-dashed border-line flex items-center justify-center text-muted hover:text-ink cursor-pointer" title="Agregar foto">
                  <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14m-7-7h14"/></svg>
                  <span class="sr-only">Agregar foto</span>
                  <input type="file" accept="image/*" class="sr-only" @change="(e: any) => handleCatalogImageUpload(e, item)">
                </label>
              </div>
            </div>
          </article>
        </div>
      </section>
    </div>

    <!-- ===== Galería ===== -->
    <div
      v-if="webSubTab === 'gallery'"
      class="space-y-6 relative"
      @dragover.prevent="handleDragOver"
      @dragleave="handleDragLeave"
      @drop="handleDrop"
    >
      <div v-if="isDragging" class="absolute inset-0 z-10 rounded-[var(--radius-panel)] border-2 border-dashed border-primary bg-primary/10 flex items-center justify-center pointer-events-none">
        <p class="text-lg font-semibold text-brand">Suelta las fotos: cada una crea un proyecto</p>
      </div>
      <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-muted">{{ (settings?.web?.gallery || []).length }} proyectos · arrastra ⠿ para cambiar el orden</p>
        <div class="flex gap-2">
          <label :class="['btn-outlined btn-sm cursor-pointer', uploadingGallery ? 'opacity-60 pointer-events-none' : '']">
            {{ uploadingGallery ? 'Subiendo…' : 'Subir varias fotos' }}
            <input type="file" accept="image/*" multiple class="sr-only" :disabled="uploadingGallery" @change="uploadGalleryFiles">
          </label>
          <button type="button" class="btn-primary btn-sm" @click="handleCreateGalleryProject">Nuevo proyecto</button>
        </div>
      </div>

      <p v-if="!(settings?.web?.gallery || []).length" class="admin-card py-14 text-center text-muted">Aún no hay proyectos. Mientras esté vacía, la página Galería invita a cotizar y la Home oculta «Trabajos destacados».</p>

      <VueDraggable
        v-model="settings.web.gallery"
        tag="div"
        class="space-y-4"
        handle=".drag-handle"
        ghost-class="opacity-40"
        :animation="200"
        :item-key="(el: any, idx: number) => (el?.title || '') + idx"
      >
        <template #item="{ element: item, index: idx }">
          <article class="admin-card flex gap-4">
            <button type="button" class="drag-handle shrink-0 -ml-2 w-8 text-muted hover:text-ink cursor-grab text-xl leading-none" title="Arrastrar para ordenar" aria-label="Arrastrar para ordenar">⠿</button>
            <div class="w-28 h-28 shrink-0 rounded-[var(--radius-control)] bg-surface-2 overflow-hidden relative">
              <img v-if="typeof item === 'object' ? item.image : item" :src="typeof item === 'object' ? item.image : item" alt="" class="w-full h-full object-cover" @error="(e) => { const t = e.target as HTMLImageElement; if (t) t.style.display = 'none' }">
              <label class="absolute inset-x-0 bottom-0 py-1 text-center text-xs font-semibold bg-black/60 text-white cursor-pointer hover:bg-black/75">
                Cambiar
                <input type="file" accept="image/*" class="sr-only" @change="(e) => handleGalleryImageUpload(e, item, true)">
              </label>
            </div>
            <div class="min-w-0 flex-1 grid gap-3 md:grid-cols-2">
              <div class="md:col-span-2 flex flex-wrap items-center gap-3">
                <label :for="`gal-t-${idx}`" class="sr-only">Título del proyecto</label>
                <input :id="`gal-t-${idx}`" v-model="item.title" placeholder="Título del proyecto" class="field font-semibold flex-1 min-w-[12rem]">
                <label class="inline-flex items-center gap-2 text-sm font-medium cursor-pointer">
                  <input v-model="item.featured" type="checkbox" class="w-5 h-5 accent-[var(--brand-text)]">
                  Destacado
                </label>
              </div>
              <div><label :for="`gal-c-${idx}`" class="admin-label mb-1">Categoría</label><input :id="`gal-c-${idx}`" v-model="item.category" placeholder="Figuras, prototipos…" class="field h-10"></div>
              <div><label :for="`gal-tech-${idx}`" class="admin-label mb-1">Tecnología y material</label><input :id="`gal-tech-${idx}`" v-model="item.technology" placeholder="Resina 8K, PLA…" class="field h-10"></div>
              <div class="md:col-span-2"><label :for="`gal-tags-${idx}`" class="admin-label mb-1">Etiquetas <span class="opacity-70">(separadas por comas)</span></label><input :id="`gal-tags-${idx}`" v-model="item.tags" placeholder="anime, pintado a mano" class="field h-10"></div>
              <div class="md:col-span-2">
                <p class="admin-label mb-2">Más fotos del proyecto</p>
                <div class="flex flex-wrap gap-2">
                  <div v-for="(addImg, addIdx) in (item.images || [])" :key="addIdx" class="relative w-16 h-16 rounded-[var(--radius-control)] overflow-hidden bg-surface-2">
                    <img :src="addImg" alt="" class="w-full h-full object-cover">
                    <button type="button" class="absolute top-0.5 right-0.5 w-6 h-6 rounded-full bg-black/70 text-white text-xs" :aria-label="`Quitar la foto ${Number(addIdx) + 1}`" @click="removeGalleryImage(item, Number(addIdx))">✕</button>
                  </div>
                  <label class="w-16 h-16 rounded-[var(--radius-control)] border border-dashed border-line flex items-center justify-center text-muted hover:text-ink cursor-pointer" title="Agregar foto">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14m-7-7h14"/></svg>
                    <span class="sr-only">Agregar foto</span>
                    <input type="file" accept="image/*" class="sr-only" @change="(e) => handleGalleryImageUpload(e, item, false)">
                  </label>
                </div>
              </div>
            </div>
            <button type="button" class="icon-btn shrink-0 -mr-2 -mt-1 text-muted hover:text-danger hover:bg-danger/10" :aria-label="`Quitar ${item.title || 'proyecto'}`" title="Quitar" @click="removeFromList(settings.web.gallery, idx, 'este proyecto')">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </button>
          </article>
        </template>
      </VueDraggable>
    </div>

    <!-- ===== Noticias ===== -->
    <div v-if="webSubTab === 'news'" class="space-y-4">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-muted">{{ settings?.web?.news?.length || 0 }} noticias · la Home muestra las publicadas, en este orden</p>
        <button type="button" class="btn-primary btn-sm" @click="settings.web.news.push({ t: '', st: '', image: '', body: '', date: localDateISO(), status: 'draft', category: '', slug: '' })">Nueva noticia</button>
      </div>
      <p v-if="!settings?.web?.news?.length" class="admin-card py-14 text-center text-muted">Sin noticias publicadas: la sección «Novedades 3D Colombia» no aparece en la Home.</p>

      <article v-for="(article, idx) in settings?.web?.news || []" :key="idx" class="admin-card">
        <div class="flex gap-4">
          <div class="w-24 h-24 shrink-0 rounded-[var(--radius-control)] bg-surface-2 overflow-hidden">
            <img v-if="article.image" :src="article.image" alt="" class="w-full h-full object-cover" @error="(e) => { const t = e.target as HTMLImageElement; if (t) t.style.display = 'none' }">
          </div>
          <div class="min-w-0 flex-1 space-y-3">
            <div class="flex flex-wrap items-center gap-2">
              <label :for="`news-t-${idx}`" class="sr-only">Título</label>
              <input :id="`news-t-${idx}`" v-model="article.t" placeholder="Título de la noticia" class="field font-semibold flex-1 min-w-[12rem]">
              <span :class="['badge', article.status === 'published' ? 'bg-success/10 text-success' : 'bg-surface-2 text-muted']">{{ article.status === 'published' ? 'Publicada' : 'Borrador' }}</span>
            </div>
            <div><label :for="`news-st-${idx}`" class="admin-label mb-1">Resumen</label><input :id="`news-st-${idx}`" v-model="article.st" placeholder="Una o dos frases" class="field h-10"></div>
          </div>
          <div class="flex flex-col gap-1 shrink-0 -mr-2 -mt-1">
            <button type="button" class="icon-btn text-muted hover:text-danger hover:bg-danger/10" aria-label="Quitar noticia" title="Quitar" @click="removeFromList(settings.web.news, Number(idx), 'esta noticia')">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </button>
            <button v-if="Number(idx) > 0" type="button" class="icon-btn text-muted hover:text-ink hover:bg-surface-2" aria-label="Subir" title="Subir" @click="moveInList(settings.web.news, Number(idx), Number(idx) - 1)">↑</button>
            <button v-if="Number(idx) < (settings?.web?.news?.length || 1) - 1" type="button" class="icon-btn text-muted hover:text-ink hover:bg-surface-2" aria-label="Bajar" title="Bajar" @click="moveInList(settings.web.news, Number(idx), Number(idx) + 1)">↓</button>
          </div>
        </div>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <div class="lg:col-span-2"><label :for="`news-img-${idx}`" class="admin-label mb-1">Enlace de la imagen</label><input :id="`news-img-${idx}`" v-model="article.image" type="url" placeholder="https://…" class="field h-10"></div>
          <div><label :for="`news-date-${idx}`" class="admin-label mb-1">Fecha</label><input :id="`news-date-${idx}`" v-model="article.date" type="date" class="field h-10"></div>
          <div>
            <label :for="`news-status-${idx}`" class="admin-label mb-1">Estado</label>
            <select :id="`news-status-${idx}`" v-model="article.status" class="field h-10"><option value="draft">Borrador</option><option value="published">Publicada</option></select>
          </div>
          <div class="lg:col-span-2"><label :for="`news-cat-${idx}`" class="admin-label mb-1">Categoría</label><input :id="`news-cat-${idx}`" v-model="article.category" placeholder="Eventos, materiales…" class="field h-10"></div>
          <div class="lg:col-span-2">
            <label :for="`news-slug-${idx}`" class="admin-label mb-1">Dirección corta (slug)</label>
            <div class="flex gap-2">
              <input :id="`news-slug-${idx}`" v-model="article.slug" placeholder="se-genera-del-titulo" class="field h-10 font-mono text-sm">
              <button type="button" class="btn-outlined btn-sm shrink-0" @click="article.slug = article.t?.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'').replace(/[^a-z0-9]/g,'-').replace(/-+/g,'-').replace(/^-|-$/g,'') || ''">Generar</button>
            </div>
          </div>
          <div class="sm:col-span-2 lg:col-span-4"><label :for="`news-body-${idx}`" class="admin-label mb-1">Texto completo</label><textarea :id="`news-body-${idx}`" v-model="article.body" rows="4" class="field !h-auto py-2.5"></textarea></div>
        </div>
      </article>
    </div>

    <!-- ===== En redes (Novedades en tiempo real) ===== -->
    <div v-if="webSubTab === 'posts'" class="space-y-4">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-muted">{{ settings?.web?.posts?.length || 0 }} publicaciones · la Home muestra las 4 primeras publicadas</p>
        <button type="button" class="btn-primary btn-sm" @click="addPost">Nueva publicación</button>
      </div>
      <p v-if="!settings?.web?.posts?.length" class="admin-card py-14 text-center text-muted">Sin publicaciones: la Home solo muestra los botones de tus redes (si las configuraste en General).</p>

      <article v-for="(post, idx) in settings?.web?.posts || []" :key="idx" class="admin-card flex gap-4">
        <div class="w-20 h-24 shrink-0 rounded-[var(--radius-control)] bg-surface-2 overflow-hidden">
          <img v-if="post.i" :src="post.i" alt="" class="w-full h-full object-cover" @error="(e) => { const t = e.target as HTMLImageElement; if (t) t.style.display = 'none' }">
        </div>
        <div class="min-w-0 flex-1 grid gap-3 md:grid-cols-2">
          <div class="md:col-span-2"><label :for="'post-t-' + idx" class="admin-label mb-1">Título</label><input :id="'post-t-' + idx" v-model="post.t" placeholder="Ej. Sung Jinwoo, de Solo Leveling" class="field h-10"></div>
          <div class="md:col-span-2"><label :for="'post-d-' + idx" class="admin-label mb-1">Descripción corta</label><textarea :id="'post-d-' + idx" v-model="post.d" rows="2" class="field !h-auto py-2.5"></textarea></div>
          <div><label :for="'post-i-' + idx" class="admin-label mb-1">Enlace de la imagen <span class="opacity-70">(vertical 4:5)</span></label><input :id="'post-i-' + idx" v-model="post.i" type="url" placeholder="https://…" class="field h-10"></div>
          <div><label :for="'post-url-' + idx" class="admin-label mb-1">Enlace a la publicación</label><input :id="'post-url-' + idx" v-model="post.url" type="url" placeholder="https://www.tiktok.com/@…" class="field h-10"></div>
          <div><label :for="'post-tag-' + idx" class="admin-label mb-1">Etiqueta</label><input :id="'post-tag-' + idx" v-model="post.tag" placeholder="TikTok" class="field h-10"></div>
          <label class="self-end inline-flex items-center gap-2 h-10 text-sm font-medium cursor-pointer">
            <input type="checkbox" class="w-5 h-5 accent-[var(--brand-text)]" :checked="post.status !== 'draft'" @change="post.status = ($event.target as HTMLInputElement).checked ? 'published' : 'draft'">
            Publicada
          </label>
        </div>
        <div class="flex flex-col gap-1 shrink-0 -mr-2 -mt-1">
          <button type="button" class="icon-btn text-muted hover:text-danger hover:bg-danger/10" aria-label="Quitar publicación" title="Quitar" @click="removeFromList(settings.web.posts, Number(idx), 'esta publicación')">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
          </button>
          <button v-if="Number(idx) > 0" type="button" class="icon-btn text-muted hover:text-ink hover:bg-surface-2" aria-label="Subir" title="Subir" @click="movePost(Number(idx), Number(idx) - 1)">↑</button>
          <button v-if="Number(idx) < (settings?.web?.posts?.length || 1) - 1" type="button" class="icon-btn text-muted hover:text-ink hover:bg-surface-2" aria-label="Bajar" title="Bajar" @click="movePost(Number(idx), Number(idx) + 1)">↓</button>
        </div>
      </article>
    </div>

    <!-- ===== Reseñas de clientes (se guardan al instante, no con Publicar) ===== -->
    <ReviewsManager v-if="webSubTab === 'reviews'" />

    <!-- ===== Plataformas (botones "Síguenos" de la Home) ===== -->
    <div v-if="webSubTab === 'social'" class="space-y-4">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-muted">{{ settings?.web?.ecosystem?.length || 0 }} plataformas · se muestran como enlaces en la Home</p>
        <button type="button" class="btn-primary btn-sm" @click="settings.web.ecosystem.push({ name: '', url: '', platform: '', description: '' })">Nueva plataforma</button>
      </div>
      <p v-if="!settings?.web?.ecosystem?.length" class="admin-card py-14 text-center text-muted">Sin plataformas vinculadas.</p>

      <article v-for="(item, idx) in settings?.web?.ecosystem || []" :key="idx" class="admin-card grid gap-3 md:grid-cols-12 md:items-end">
        <div class="md:col-span-3"><label :for="`eco-n-${idx}`" class="admin-label mb-1">Nombre</label><input :id="`eco-n-${idx}`" v-model="item.name" placeholder="Ej. TikTok N3XT" class="field h-10"></div>
        <div class="md:col-span-2">
          <label :for="`eco-p-${idx}`" class="admin-label mb-1">Plataforma</label>
          <select :id="`eco-p-${idx}`" v-model="item.platform" class="field h-10">
            <option value="">Elegir…</option>
            <option v-for="(label, key) in platformLabels" :key="key" :value="key">{{ label }}</option>
          </select>
        </div>
        <div class="md:col-span-4"><label :for="`eco-u-${idx}`" class="admin-label mb-1">Enlace</label><input :id="`eco-u-${idx}`" v-model="item.url" type="url" placeholder="https://…" class="field h-10"></div>
        <div class="md:col-span-3 flex items-center justify-end gap-1">
          <button v-if="Number(idx) > 0" type="button" class="icon-btn text-muted hover:text-ink hover:bg-surface-2" aria-label="Subir" title="Subir" @click="moveInList(settings.web.ecosystem, Number(idx), Number(idx) - 1)">↑</button>
          <button v-if="Number(idx) < (settings?.web?.ecosystem?.length || 1) - 1" type="button" class="icon-btn text-muted hover:text-ink hover:bg-surface-2" aria-label="Bajar" title="Bajar" @click="moveInList(settings.web.ecosystem, Number(idx), Number(idx) + 1)">↓</button>
          <button type="button" class="icon-btn text-muted hover:text-danger hover:bg-danger/10" aria-label="Quitar plataforma" title="Quitar" @click="removeFromList(settings.web.ecosystem, Number(idx), 'esta plataforma')">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
          </button>
        </div>
        <div class="md:col-span-12"><label :for="`eco-d-${idx}`" class="admin-label mb-1">Descripción corta</label><input :id="`eco-d-${idx}`" v-model="item.description" class="field h-10"></div>
      </article>
    </div>

    <!-- Publicar (fijo abajo en móvil) -->
    <div class="fixed md:static inset-x-0 bottom-0 z-30 p-3 md:p-0 bg-canvas/95 md:bg-transparent border-t border-line md:border-0 flex justify-end">
      <button type="button" class="btn-primary btn-lg w-full md:w-auto" @click="emit('save-settings')">Publicar cambios</button>
    </div>
  </div>
</template>
