<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import StlViewer from '../components/StlViewer.vue'
import { sanitizeSVG } from '../utils/sanitize'
import { api } from '../services/api'
import { calcQuoteEstimate, calcExtraCost } from '../services/costCalculator'
import { useRevealAnim } from '../composables/useRevealAnim'
import AppNavbar from '../components/AppNavbar.vue'
import AppFooter from '../components/AppFooter.vue'
import QuoteSidebar from '../components/QuoteSidebar.vue'
import QuoteOrderModal from '../components/QuoteOrderModal.vue'
import logger from '../utils/logger'
import { useSplitTitle } from '../composables/useSplitTitle'
import { usePageMeta } from '../composables/usePageMeta'

useSplitTitle()

interface Material {
  id: string
  name: string
  type: string
  category: string
  density?: number | string
  cost_per_kg?: number | string
  unit?: string
}
interface CuraFactors {
  resolution?: number
  infill_density?: number
  shell_weight_g: number
  internal_weight_g: number
  support_weight_g: number
  purge_weight_g?: number
  filament_length_m?: number
  prep_time_h: number
  print_time_h: number
}
interface QuoteModel {
  id: number
  file: File | null
  /** Piezas de este modelo */
  qty: number
  volume: number
  totalArea: number
  dimensions: { x: number; y: number; z: number }
  supportArea: number
  currentScale: number
  hasModel: boolean
  hasSlicing: boolean
  weight: number
  duration: number
  name: string
  curaFactors: CuraFactors
}
interface ModelStats {
  key: number | string
  volume: number
  totalArea?: number
  dimensions: { x: number; y: number; z: number }
  scale?: number
  supportArea?: number
}
interface CustomerForm {
  name: string; company: string; document: string; email: string; phone: string
  city: string; address: string; use: string; comments: string
}
interface Coupon { code: string; discount_pct: number; label: string }

const MAX_MODELS = 10

const errMessage = (err: unknown): string => (err instanceof Error ? err.message : String(err))
const formatCop = (n: number) => `$ ${Math.round(n).toLocaleString('es-CO')}`

usePageMeta({
  title: 'Cotizador de impresión 3D | N3XT 3D',
  description: 'Sube tu modelo STL u OBJ y obtén un precio estimado de impresión 3D en resina o filamento.',
})

// --- N3XT CORE DATA ---
const appData = ref({
    infra: { luz_hr: 0, depr_hr: 0, mant_hr: 0, etiquetas: 0, load_factor: 0.4 },
    prep: { mano_obra_hr: 0, prep_time_pct: 10 },
    oper: { transporte: 0, ganancia: 0, marketing: 0, fallos: 0 },
    margin: { iva: 19 }
})
const materials = ref<Material[]>([])
const utilities = ref<Material[]>([])
const selectedExtras = ref<any[]>([])
const autoExtras = ref<any[]>([])
const loadingQuote = ref(true)
const quoteMessage = ref('')

const fetchMaterials = async () => {
  try {
    const data: Material[] = await api.get('/materials')
    materials.value = data.filter((m: Material) => m.type === 'material')
    utilities.value = data.filter((m: Material) => m.type !== 'material')

    if (materials.value.length > 0 && !selectedMaterial.value) {
      selectedMaterial.value = materials.value[0].id
    }
  } catch (err) {
    logger.error('Error fetching materials:', err)
  }
}

const fetchSettings = async () => {
  try {
    const data = await api.get('/settings')
    if (data && typeof data === 'object' && !Array.isArray(data)) {
      // Merge inteligente por categorías (idéntico al AdminDashboard)
      if (data.infra) appData.value.infra = { ...appData.value.infra, ...data.infra }
      if (data.prep) appData.value.prep = { ...appData.value.prep, ...data.prep }
      if (data.oper) appData.value.oper = { ...appData.value.oper, ...data.oper }
      if (data.margin) appData.value.margin = { ...appData.value.margin, ...data.margin }
      // Forzar conversión numérica de todos los parámetros de cálculo
      const i = appData.value.infra
      i.luz_hr = Number(i.luz_hr) || 0
      i.depr_hr = Number(i.depr_hr) || 0
      i.mant_hr = Number(i.mant_hr) || 0
      i.etiquetas = Number(i.etiquetas) || 0
      i.load_factor = Number(i.load_factor) || 0.4

      const p = appData.value.prep
      p.mano_obra_hr = Number(p.mano_obra_hr) || 0
      p.prep_time_pct = Number(p.prep_time_pct) || 10

      const o = appData.value.oper
      o.transporte = Number(o.transporte) || 0
      o.ganancia = Number(o.ganancia) || 0
      o.marketing = Number(o.marketing) || 0
      o.fallos = Number(o.fallos) || 0

      const m = appData.value.margin
      // ?? en lugar de || para permitir IVA 0%
      const ivaNum = m.iva === null || m.iva === undefined || String(m.iva) === '' ? NaN : Number(m.iva)
      m.iva = Number.isFinite(ivaNum) ? ivaNum : 19
    }
  } catch (err) {
    logger.error('Error fetching settings:', err)
  }
}

useRevealAnim({ delay: 200 })


// ─── Cotizaciones guardadas (cliente con correo verificado) ───
const route = useRoute()
const router = useRouter()
const account = ref<{ role: string | null; verified: boolean }>({ role: null, verified: false })
const canSaveQuote = computed(() => account.value.role === 'customer' && account.value.verified)
const saveOpen = ref(false)
const saveName = ref('')
const savingQuote = ref(false)

const openSave = () => {
  const loaded = models.value.filter(m => m.hasModel && m.file)
  saveName.value = loaded.length === 1 ? (loaded[0].file?.name.replace(/\.(stl|obj)$/i, '') || 'Mi cotización') : `${loaded.length} piezas`
  saveOpen.value = true
}

const saveQuote = async () => {
  const loaded = models.value.filter(m => m.hasModel && m.file)
  if (!loaded.length || savingQuote.value) return
  savingQuote.value = true
  try {
    const fd = new FormData()
    fd.append('name', saveName.value.trim() || 'Mi cotización')
    fd.append('technology', selectedTechnology.value)
    if (selectedMaterial.value) fd.append('material_id', selectedMaterial.value)
    fd.append('infill', String(Math.round(infill.value || 15)))
    fd.append('layer_height', String(layerHeight.value || 0.2))
    if (breakdown.value.total > 0) fd.append('estimated_total', String(Math.round(breakdown.value.total)))
    loaded.forEach((m, i) => {
      fd.append(`models[${i}][file]`, m.file as File)
      fd.append(`models[${i}][qty]`, String(Math.max(1, Math.floor(m.qty) || 1)))
      fd.append(`models[${i}][scale_factor]`, String(m.currentScale || 1))
    })
    selectedExtras.value.forEach((e, i) => {
      fd.append(`extra_items[${i}][material_id]`, e.id)
      fd.append(`extra_items[${i}][qty]`, String(e.qty))
    })
    await api.post('/customer/quotes', fd)
    saveOpen.value = false
    notify('Guardamos la cotización. La encuentras en tu cuenta, en Cotizaciones guardadas.', 'success', 7000)
  } catch (err) {
    notify(err instanceof Error ? err.message : 'No pudimos guardar la cotización.', 'error')
  } finally {
    savingQuote.value = false
  }
}

/** Reabre una cotización guardada: parámetros, archivos (en el visor), escala y cantidades. */
const restoreQuote = async (id: number) => {
  let saved: any
  try {
    const list = await api.get('/customer/quotes', true)
    saved = (Array.isArray(list) ? list : []).find((q: any) => q.id === id)
  } catch { /* sin sesión: se ignora */ }
  if (!saved) {
    notify('No encontramos esa cotización en tu cuenta.', 'warning')
    return
  }
  loadingQuote.value = true
  quoteMessage.value = `Abriendo «${saved.name}»…`
  selectedTechnology.value = saved.technology || 'FDM'
  await nextTick()
  if (saved.material_id && materials.value.some(m => m.id === saved.material_id)) selectedMaterial.value = saved.material_id
  if (saved.infill) infill.value = saved.infill
  if (saved.layer_height) layerHeight.value = Number(saved.layer_height)
  selectedExtras.value = (saved.extras || []).filter((e: any) => utilities.value.some((u: any) => u.id === e.id)).map((e: any) => ({ id: e.id, qty: e.qty }))

  for (const [i, m] of (saved.models || []).entries()) {
    if (i > 0) addModel()
    else activeModelIdx.value = 0
    await nextTick()
    const model = activeModel.value
    try {
      const res = await fetch(`${api.baseUrl}/customer/quotes/${id}/files/${i}`, { credentials: 'include' })
      if (!res.ok) throw new Error(String(res.status))
      const file = new File([await res.blob()], m.name || `modelo_${i + 1}.stl`)
      quoteMessage.value = `Cargando ${file.name}…`
      const ok = await viewerRef.value?.loadFileFor(file)
      if (ok && model) {
        model.qty = m.qty || 1
        if (m.scale_factor && m.scale_factor !== 1) viewerRef.value?.setScale(model.id, m.scale_factor)
      }
    } catch {
      notify(`No pudimos abrir ${m.name || 'un archivo'} de la cotización.`, 'error')
    }
  }
  activeModelIdx.value = 0
  loadingQuote.value = false
  notify(`Abrimos «${saved.name}». Revisa los datos y pulsa Calcular precio.`, 'success', 7000)
  router.replace({ query: {} })
}

onMounted(async () => {
  loadingQuote.value = true
  quoteMessage.value = 'Cargando materiales y configuración...'
  api.checkAuth().then(a => { account.value = { role: a.role, verified: !!a.user?.email_verified } })
  await fetchSettings()
  await fetchMaterials()
  loadingQuote.value = false
  calculatePrice()
  const quoteId = Number(route.query.cotizacion)
  if (quoteId) await restoreQuote(quoteId)
})

const selectedTechnology = ref('FDM')
const selectedMaterial = ref('')
// Parámetros FDM comunes a todo el pedido (el contrato los envía una sola vez)
const infill = ref(15)
const layerHeight = ref(0.2)

// --- Multi-modelo ---
let nextModelId = 0
const newModel = (): QuoteModel => {
  const id = nextModelId++
  return {
    id, file: null, qty: 1, volume: 0, totalArea: 0, dimensions: { x: 0, y: 0, z: 0 }, supportArea: 0,
    currentScale: 1.0, hasModel: false, hasSlicing: false, weight: 0, duration: 0, name: `Modelo ${id + 1}`,
    curaFactors: { resolution: 1, infill_density: 1, shell_weight_g: 0, internal_weight_g: 0, support_weight_g: 0, filament_length_m: 0, prep_time_h: 0.233, print_time_h: 0 },
  }
}
const models = ref<QuoteModel[]>([newModel()])
const activeModelIdx = ref(0)
const activeModel = computed(() => models.value[activeModelIdx.value])
const viewerRef = ref<InstanceType<typeof StlViewer> | null>(null)

/** Nombre visible: el del archivo si ya lo hay, si no "Modelo N" según su posición */
const modelLabel = (m: QuoteModel, idx: number) => m.file?.name || `Modelo ${idx + 1}`

const addModel = () => {
  if (models.value.length >= MAX_MODELS) {
    notify(`Puedes cotizar hasta ${MAX_MODELS} modelos por pedido.`, 'warning')
    return
  }
  models.value.push(newModel())
  activeModelIdx.value = models.value.length - 1
}

const removeModel = (idx: number) => {
  const model = models.value[idx]
  if (!model) return
  viewerRef.value?.removeModel(model.id)
  slicedSignatures.delete(model.id)
  if (models.value.length === 1) {
    // Siempre queda al menos una pestaña: se reemplaza por una vacía
    models.value = [newModel()]
    activeModelIdx.value = 0
    return
  }
  models.value.splice(idx, 1)
  if (activeModelIdx.value > idx || activeModelIdx.value >= models.value.length) {
    activeModelIdx.value = Math.max(0, activeModelIdx.value - 1)
  }
}

const findModel = (key: number | string) => models.value.find(m => m.id === key)

// --- CURAENGINE INTEGRATION (laminado por modelo) ---
const isSlicing = ref(false)
const slicingProgress = ref({ current: 0, total: 0, label: '' })

/** Lamina un modelo. Devuelve '' si salió bien o el mensaje de error. */
const sliceModel = async (model: QuoteModel): Promise<string> => {
  if (!model.file) return 'Falta el archivo.'
  const sigAtStart = slicingSignature(model)
  try {
    const mat = materials.value.find(m => m.id === selectedMaterial.value)
    const formData = new FormData()
    formData.append('file', model.file)
    formData.append('infill', String(infill.value || 15))
    formData.append('layer_height', String(layerHeight.value || 0.2))
    formData.append('total_area', String(model.totalArea || 0))
    formData.append('volume_mm3', String(model.volume || 0))
    formData.append('support_area', String(model.supportArea || 0))
    if (mat) {
        formData.append('density', String(mat.density ?? ''))
        formData.append('material_id', mat.id)
    }

    const res = await api.post('/process-stl', formData)
    const factors = res.data || res.factors || res;

    if (!(factors && (factors.shell_weight_g >= 0))) throw new Error('El motor devolvió datos incompletos.')

    // El modelo pudo eliminarse o sus parámetros cambiar mientras se laminaba
    const current = findModel(model.id)
    if (!current || slicingSignature(current) !== sigAtStart) return ''

    current.curaFactors = factors
    current.hasSlicing = true
    slicedSignatures.set(current.id, sigAtStart)
    current.weight = (Number(factors.shell_weight_g) || 0)
                   + (Number(factors.internal_weight_g) || 0)
                   + (Number(factors.support_weight_g) || 0)
                   + (Number(factors.purge_weight_g) || 3.0)
    current.duration = (Number(factors.prep_time_h) || 0)
                     + (Number(factors.print_time_h) || 0)
    return ''
  } catch (err) {
    logger.error("CuraEngine Error:", err)
    const message = errMessage(err)
    const statusInfo = message.includes('(') ? ` [${message.split('(')[1].split(')')[0]}]` : '';
    return `${statusInfo}: ${message.split(' (')[0] || 'falló la conexión'}`
  }
}

/** "Calcular precio": lamina todos los modelos sin laminado vigente (o todos, si ya lo tenían). */
const runCuraEngineAnalysis = async () => {
  if (isSlicing.value) return
  const loaded = models.value.filter(m => m.hasModel && m.file)
  if (!loaded.length) {
      notify("Sube un modelo 3D primero", "error")
      return
  }
  const pending = loaded.filter(m => !m.hasSlicing)
  const targets = pending.length ? pending : loaded

  isSlicing.value = true
  const failures: string[] = []
  try {
    for (let i = 0; i < targets.length; i++) {
      const m = targets[i]
      const idx = models.value.indexOf(m)
      slicingProgress.value = { current: i + 1, total: targets.length, label: modelLabel(m, idx) }
      const error = await sliceModel(m)
      if (error) failures.push(`${modelLabel(m, idx)}${error}`)
    }
  } finally {
    isSlicing.value = false
  }
  calculatePrice()
  if (failures.length) {
    notify(`No pudimos calcular el precio de ${failures.length === 1 ? 'un modelo' : failures.length + ' modelos'} (${failures.join('; ')})`, 'error')
  } else {
    notify("Precio estimado calculado. Revisa el resumen en el panel.", "success")
  }
}

const breakdown = ref({
  pieces: 0, weight: 0, duration: 0, matCost: 0, infraCost: 0, laborCost: 0, utilityCost: 0, marginCost: 0, discount: 0, subtotal: 0, iva: 0, ivaPct: 19, total: 0
})
/** Total estimado (IVA incluido) atribuido a cada modelo, por id */
const modelTotals = ref<Record<number, number>>({})

const materialGuide = [
  { 
    name: 'PLA', 
    desc: 'Fácil de imprimir, biodegradable y rígido.', 
    bestFor: 'prototipos estéticos y figuras.',
    icon: `<svg class="w-5 h-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>`
  },
  { 
    name: 'PETG', 
    desc: 'Resistente al impacto y a químicos.', 
    bestFor: 'piezas mecánicas y de exterior.',
    icon: `<svg class="w-5 h-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>`
  },
  { 
    name: 'ABS', 
    desc: 'Alta resistencia térmica y mecánica.', 
    bestFor: 'carcasas y uso automotriz.',
    icon: `<svg class="w-5 h-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>`
  },
  { 
    name: 'Resina', 
    desc: 'Detalle muy fino y superficie lisa.', 
    bestFor: 'joyería, dental y miniaturas.',
    icon: `<svg class="w-5 h-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.628.209a2 2 0 01-1.564 0l-.628-.209a6 6 0 00-3.86-.517L3.081 14.88a2 2 0 00-1.022.547l-.21.21a2 2 0 00.707 3.414l9.9 1.98a2 2 0 00.707 0l9.9-1.98a2 2 0 00.707-3.414l-.21-.21zM6.25 8.044a2 2 0 01.97-1.712l4-2.286a2 2 0 011.56 0l4 2.286a2 2 0 01.97 1.712v4.572a2 2 0 01-.97 1.712l-4 2.286a2 2 0 01-1.56 0l-4-2.286a2 2 0 01-.97-1.712V8.044z"/></svg>`
  }
]

const showModal = ref(false)
const isSubmitting = ref(false)
const previousTotal = ref(0)
const lastSubmitTime = ref(0)
const emptyCustomerForm = (): CustomerForm => ({
  name: '',
  company: '',
  document: '',
  email: '',
  phone: '',
  city: '',
  address: '',
  use: 'Funcional',
  comments: ''
})
const customerForm = ref<CustomerForm>(emptyCustomerForm())
const turnstileToken = ref('')
/** Último pedido creado: el total es el que calculó el servidor */
const lastOrder = ref<{ id: number | string | null; total: number | null } | null>(null)

// --- Cupones (validados por el servidor) ---
const couponCode = ref('')
const activeCoupon = ref<Coupon | null>(null)
const couponError = ref('')
const couponLoading = ref(false)

const couponErrorMessage = (err: unknown) => {
  const msg = errMessage(err)
  if (/\(404\)|no v[aá]lid|no existe|not found|inactiv/i.test(msg)) return 'Ese cupón no existe o ya no está activo.'
  if (/\(429\)|too many|demasiad/i.test(msg)) return 'Hiciste muchos intentos seguidos. Espera un minuto y vuelve a probar.'
  if (/\(422\)/.test(msg)) return 'Revisa el código: solo letras y números.'
  return 'No pudimos validar el cupón en este momento. Inténtalo de nuevo.'
}

const applyCoupon = async () => {
  const code = couponCode.value.trim().toUpperCase()
  couponError.value = ''
  if (!code) {
    couponError.value = 'Escribe el código del cupón.'
    return
  }
  if (couponLoading.value) return
  couponLoading.value = true
  try {
    const data = await api.post('/quote/coupon', { code })
    const pct = Number(data?.discount_pct)
    if (!Number.isFinite(pct) || pct <= 0) throw new Error('Cupón no válido (404)')
    activeCoupon.value = { code: String(data.code || code), discount_pct: pct, label: String(data.label || code) }
    notify(`Cupón ${activeCoupon.value.label} aplicado.`, 'success')
  } catch (err) {
    activeCoupon.value = null
    couponError.value = couponErrorMessage(err)
  } finally {
    couponLoading.value = false
  }
}

const removeCoupon = () => {
  activeCoupon.value = null
  couponCode.value = ''
  couponError.value = ''
}

// Si el usuario edita el código, el error anterior deja de aplicar
watch(couponCode, () => { couponError.value = '' })

const _timerNotif = ref<ReturnType<typeof setTimeout> | null>(null)
const notification = ref({ show: false, message: '', type: 'info' })
const notify = (msg: string, type = 'info', durationMs = 5000) => {
    if (_timerNotif.value) clearTimeout(_timerNotif.value)
    notification.value = { show: true, message: msg, type }
    _timerNotif.value = setTimeout(() => { notification.value.show = false }, durationMs)
  }

const handleModalSubmit = (payload: { customerForm: CustomerForm; captchaVerified: boolean; turnstileToken: string }) => {
  if (!payload.captchaVerified) {
    notify('La verificación no es correcta. Inténtalo de nuevo.', 'error')
    return
  }
  customerForm.value = { ...customerForm.value, ...payload.customerForm }
  turnstileToken.value = payload.turnstileToken || ''
  submitOrder()
}

const openModal = () => {
  showModal.value = true
}

const handleFileReady = (file: File, key: number | string) => {
  const model = findModel(key)
  if (model) {
    model.file = file
    model.hasSlicing = false
    model.weight = 0
    model.duration = 0
    slicedSignatures.delete(model.id)
  }
}

// --- Invalidación del laminado FDM (por modelo) ---
// El resultado de CuraEngine depende de estos parámetros; si cambian, hay que recalcular ese modelo.
const slicedSignatures = new Map<number, string>()
const slicingSignature = (m: QuoteModel) =>
  [selectedMaterial.value, infill.value, layerHeight.value, m.currentScale, m.volume, m.supportArea].join('|')

watch(
  () => models.value.map(m => [m.id, slicingSignature(m)] as const),
  (sigs) => {
    sigs.forEach(([id, sig]) => {
      const model = findModel(id)
      if (model?.hasSlicing && slicedSignatures.get(id) !== sig) {
        model.hasSlicing = false
        model.weight = 0
        model.duration = 0
        slicedSignatures.delete(id)
      }
    })
  }
)

const submitOrder = async () => {
  const now = Date.now();
  if (now - lastSubmitTime.value < 10000) {
    notify("Por favor espera unos segundos antes de enviar otra solicitud.", "warning");
    return;
  }

  const loaded = models.value.filter(m => m.hasModel && m.file)
  if (!loaded.length) {
    notify('Sube al menos un modelo 3D antes de enviar el pedido.', 'error')
    return
  }

  isSubmitting.value = true
  try {
    const mat = materials.value.find(m => m.id === selectedMaterial.value)
    const formData = new FormData()

    // Datos del Cliente
    formData.append('customer_name', customerForm.value.name)
    formData.append('customer_company', customerForm.value.company)
    formData.append('customer_email', customerForm.value.email)
    formData.append('customer_phone', customerForm.value.phone)
    formData.append('customer_id_document', customerForm.value.document)
    formData.append('shipping_address', customerForm.value.address)
    formData.append('shipping_city', customerForm.value.city)
    formData.append('comments', `USO: ${customerForm.value.use} | NOTAS: ${customerForm.value.comments}`)
    if (turnstileToken.value) formData.append('cf_turnstile_response', turnstileToken.value)

    // Parámetros comunes del pedido
    formData.append('technology', selectedTechnology.value)
    formData.append('material_id', selectedMaterial.value)
    formData.append('material_name', mat ? mat.name : '')
    formData.append('infill', String(Math.round(infill.value || 15)))
    formData.append('layer_height', String(layerHeight.value || 0.2))
    if (activeCoupon.value) formData.append('coupon_code', activeCoupon.value.code)
    // Solo informativo: el servidor calcula el precio definitivo
    if (breakdown.value.total > 0) formData.append('total_price', String(breakdown.value.total))
    formData.append('job_name', loaded.length === 1 ? (loaded[0].file?.name || 'Modelo 1') : `${loaded.length} modelos`)

    // Modelos
    loaded.forEach((m, i) => {
      const d = m.dimensions
      formData.append(`models[${i}][file]`, m.file as File)
      formData.append(`models[${i}][qty]`, String(Math.max(1, Math.floor(m.qty) || 1)))
      formData.append(`models[${i}][scale_factor]`, String(m.currentScale || 1))
      formData.append(`models[${i}][dimensions_mm]`, `${d.x.toFixed(1)} x ${d.y.toFixed(1)} x ${d.z.toFixed(1)}`)
      formData.append(`models[${i}][support_area_mm2]`, String(Math.round((m.supportArea || 0) * 100) / 100))
    })

    // Extras
    if (selectedExtras.value.length > 0) {
      selectedExtras.value.forEach((extra, index) => {
        formData.append(`extra_items[${index}][material_id]`, extra.id)
        formData.append(`extra_items[${index}][qty]`, String(extra.qty))
      })
    }

    const res = await api.post('/orders', formData)
    const orderId = res?.order_id ?? null
    const serverTotal = Number(res?.total_price ?? res?.breakdown?.total)
    const total = Number.isFinite(serverTotal) && serverTotal > 0 ? serverTotal : null

    lastSubmitTime.value = Date.now()
    lastOrder.value = { id: orderId, total }
    showModal.value = false
    notify(
      `Recibimos tu pedido${orderId ? ` #${orderId}` : ''}${total !== null ? ` por ${formatCop(total)}` : ''}. Te escribiremos por WhatsApp o email para confirmar.`,
      "success",
      10000,
    )

    // Reset form
    customerForm.value = emptyCustomerForm()
  } catch (err) {
    notify(errMessage(err), "error")
  } finally {
    turnstileToken.value = ''
    isSubmitting.value = false
  }
}

const toggleExtra = (id: string) => {
    const idx = selectedExtras.value.findIndex(e => e.id === id)
    if (idx > -1) selectedExtras.value.splice(idx, 1)
    else selectedExtras.value.push({ id, qty: 1 })
    calculatePrice()
}

const updateActiveQty = (q: number) => {
  if (activeModel.value) activeModel.value.qty = Math.max(1, Math.floor(Number(q)) || 1)
}

watch([selectedTechnology, selectedMaterial, models, infill, layerHeight, activeCoupon], () => {
  const mat = materials.value.find(m => m.id === selectedMaterial.value)
  if (mat && mat.category !== selectedTechnology.value) {
    const firstAvailable = materials.value.find(m => m.category === selectedTechnology.value)
    if (firstAvailable) selectedMaterial.value = firstAvailable.id
  }
  calculatePrice()
}, { deep: true })

/** Peso y tiempo de UNA pieza del modelo, según la tecnología */
const modelUnitStats = (model: QuoteModel, mat: Material) => {
  if (selectedTechnology.value === 'FDM') {
    if (!model.hasSlicing || !model.curaFactors) return { weightG: 0, hours: 0 }
    const cf = model.curaFactors
    return {
      weightG: (Number(cf.shell_weight_g) || 0)
             + (Number(cf.internal_weight_g) || 0)
             + (Number(cf.support_weight_g) || 0)
             + (Number(cf.purge_weight_g) || 3.0),
      hours: (Number(cf.prep_time_h) || 0) + (Number(cf.print_time_h) || 0),
    }
  }
  // SLA: volume viene en mm³, convertir a cm³
  const volCm3 = (model.volume || 0) / 1000
  const density = Number(mat.density) || 1.1
  const heightMm = model.dimensions?.z || model.dimensions?.y || 0
  return { weightG: volCm3 * density * 1.1, hours: Math.max(0.5, heightMm / 25) }
}

/** Precio ESTIMADO en el navegador: suma de todos los modelos (cada uno × su cantidad). */
const calculatePrice = () => {
  if (loadingQuote.value) return

  const mat = materials.value.find(m => m.id === selectedMaterial.value)
  if (!mat || !appData.value) {
    quoteMessage.value = 'Cargando datos de materiales...'
    return
  }
  const loaded = models.value.filter(m => m.hasModel)
  if (!loaded.length) {
    quoteMessage.value = 'Sube un modelo 3D para ver el precio'
    // Mostrar breakdown en cero pero con mensaje
    breakdown.value = { ...breakdown.value, pieces: 0, weight: 0, duration: 0, total: 0, subtotal: 0, iva: 0 }
    modelTotals.value = {}
    return
  }
  quoteMessage.value = ''

  const cfg = appData.value
  const quoteModels = loaded.map(m => ({ id: m.id, qty: m.qty, ...modelUnitStats(m, mat) }))
  const pieces = quoteModels.reduce((s, m) => s + Math.max(1, Math.floor(m.qty) || 1), 0)

  // Consumibles
  let utilityCost = 0
  autoExtras.value = []
  if (selectedTechnology.value === 'SLA') {
    const alcohol = utilities.value.find(u => u.id === 'Alco_ML_05')
    if (alcohol) {
      utilityCost += calcExtraCost(Number(alcohol.cost_per_kg) || 0, alcohol.unit || 'ml', 50 * pieces)
      autoExtras.value.push({ id: alcohol.id, qty: 50 * pieces, name: alcohol.name })
    }
    const curado = utilities.value.find(u => u.id === 'Cicl_Serv_06')
    if (curado) {
      utilityCost += calcExtraCost(Number(curado.cost_per_kg) || 0, curado.unit || 'servicio', pieces)
      autoExtras.value.push({ id: curado.id, qty: pieces, name: curado.name })
    }
  }

  selectedExtras.value.forEach(item => {
    const extra = utilities.value.find(u => u.id === item.id)
    if (extra) {
        utilityCost += calcExtraCost(Number(extra.cost_per_kg) || 0, extra.unit || 'g', item.qty)
    }
  })

  const r = calcQuoteEstimate({
    models: quoteModels,
    costPerKg: Number(mat.cost_per_kg) || 0,
    infra: cfg.infra,
    prep: cfg.prep,
    oper: cfg.oper,
    margin: cfg.margin,
    extrasCost: utilityCost,
    discountPct: activeCoupon.value?.discount_pct ?? 0,
  })

  if (r.total !== breakdown.value.total) {
    previousTotal.value = breakdown.value.total
  }

  modelTotals.value = Object.fromEntries(r.perModel.map(p => [p.id, p.total]))
  breakdown.value = {
    pieces: r.pieces,
    weight: r.weight,
    duration: r.duration,
    matCost: r.matCost,
    infraCost: r.infraCost,
    laborCost: r.laborCost,
    utilityCost: r.utilityCost,
    marginCost: r.marginCost,
    discount: r.discount,
    subtotal: r.subtotal,
    iva: r.iva,
    ivaPct: r.ivaPct,
    total: r.total,
  }
}

/** Resumen de modelos para el modal y el panel */
const modelSummaries = computed(() =>
  models.value
    .map((m, i) => ({ m, i }))
    .filter(({ m }) => m.hasModel)
    .map(({ m, i }) => ({
      id: m.id,
      name: modelLabel(m, i),
      qty: m.qty,
      dimensions: m.dimensions,
      scale: m.currentScale,
      total: modelTotals.value[m.id] ?? 0,
      priced: selectedTechnology.value === 'SLA' || m.hasSlicing,
    }))
)

const handleModelLoaded = (data: ModelStats) => {
  const model = findModel(data.key)
  if (model) {
    model.volume = data.volume
    model.totalArea = data.totalArea || 0
    model.dimensions = { x: data.dimensions.x, y: data.dimensions.y, z: data.dimensions.z }
    model.currentScale = data.scale || 1.0
    model.supportArea = data.supportArea || 0
    model.hasModel = true
  }
  if (selectedTechnology.value !== 'FDM') calculatePrice()
}

const handleError = (msg: string) => {
  notify(msg, "error")
}

onUnmounted(() => {
  if (_timerNotif.value) clearTimeout(_timerNotif.value)
})

</script>

<template>
  <div class="min-h-screen flex flex-col bg-canvas text-ink overflow-x-clip">
    <AppNavbar active-tab="quote" subtext="Cotizador" />

    <!-- Grid: en móvil visor → configuración → guía; en escritorio el panel va a la derecha y la guía bajo el visor -->
    <main id="main" class="flex-1 grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_420px] xl:grid-cols-[minmax(0,1fr)_460px] lg:grid-rows-[auto_1fr]">
      <!-- Visor + modelos -->
      <section class="min-w-0 px-4 sm:px-6 lg:px-8 pt-8 lg:pt-10 pb-8 lg:pb-0" aria-labelledby="quote-title">
        <div class="w-full max-w-5xl mx-auto">
          <header class="mb-8">
            <h1 id="quote-title" class="section-title">Cotiza tu <span class="section-title-accent">pieza 3D</span></h1>
            <p class="section-lead mt-4 max-w-[60ch]">Sube uno o varios archivos, elige material y parámetros y obtén un precio estimado. El taller revisa el pedido y confirma el precio definitivo antes de imprimir.</p>
          </header>

          <!-- Modelos -->
          <div class="mb-4">
            <div class="flex flex-wrap items-center gap-2" aria-label="Modelos de la cotización" role="group">
              <div
                v-for="(m, mi) in models" :key="m.id"
                class="inline-flex items-center h-9 rounded-full border text-sm font-medium transition-colors max-w-full"
                :class="activeModelIdx === mi ? 'bg-ink text-canvas border-ink' : 'border-line text-muted hover:text-ink'"
              >
                <button
                  type="button"
                  :aria-pressed="activeModelIdx === mi"
                  :title="modelLabel(m, mi)"
                  class="h-full pl-4 inline-flex items-center gap-2 min-w-0 rounded-l-full"
                  :class="models.length > 1 || m.hasModel ? 'pr-1' : 'pr-4 rounded-r-full'"
                  @click="activeModelIdx = mi"
                >
                  <span class="truncate max-w-[10rem] sm:max-w-[14rem]">{{ modelLabel(m, mi) }}</span>
                  <span v-if="m.hasModel && m.qty > 1" class="tabular-nums" :class="activeModelIdx === mi ? '' : 'text-ink'">× {{ m.qty }}</span>
                  <svg v-if="m.hasModel" class="w-4 h-4 shrink-0" :class="activeModelIdx === mi ? '' : 'text-brand'" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                  <span v-if="m.hasModel" class="sr-only">(archivo cargado)</span>
                </button>
                <button
                  v-if="models.length > 1 || m.hasModel"
                  type="button"
                  class="h-full px-2.5 inline-flex items-center rounded-r-full opacity-80 hover:opacity-100"
                  :aria-label="`Eliminar ${modelLabel(m, mi)}`"
                  :title="`Eliminar ${modelLabel(m, mi)}`"
                  :disabled="isSlicing"
                  @click="removeModel(mi)"
                >
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
              </div>
              <button
                type="button"
                class="btn-outlined btn-sm"
                :disabled="models.length >= MAX_MODELS"
                :aria-describedby="models.length >= MAX_MODELS ? 'quote-max-models' : undefined"
                @click="addModel"
              >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14m-7-7h14"/></svg>
                Añadir modelo
              </button>
            </div>
            <p v-if="models.length >= MAX_MODELS" id="quote-max-models" class="mt-2 text-sm text-muted">Llegaste al máximo de {{ MAX_MODELS }} modelos por pedido.</p>

            <!-- Guardar para retomar después -->
            <div v-if="models.some(m => m.hasModel)" class="mt-3">
              <template v-if="canSaveQuote">
                <button v-if="!saveOpen" type="button" class="text-sm font-semibold text-brand hover:underline" @click="openSave">Guardar esta cotización para después</button>
                <form v-else class="flex flex-wrap items-end gap-2" @submit.prevent="saveQuote">
                  <div class="flex-1 min-w-[12rem]">
                    <label for="save-quote-name" class="block text-sm font-medium mb-1">Nombre de la cotización</label>
                    <input id="save-quote-name" v-model="saveName" maxlength="120" class="field h-10">
                  </div>
                  <button type="submit" class="btn-primary btn-sm" :disabled="savingQuote">{{ savingQuote ? 'Guardando…' : 'Guardar' }}</button>
                  <button type="button" class="btn-outlined btn-sm" :disabled="savingQuote" @click="saveOpen = false">Cancelar</button>
                </form>
              </template>
              <p v-else-if="account.role === 'customer'" class="text-sm text-muted"><router-link to="/customer/dashboard" class="font-semibold text-brand hover:underline">Verifica tu correo</router-link> para guardar cotizaciones.</p>
              <p v-else-if="account.role !== 'admin'" class="text-sm text-muted"><router-link to="/admin/login?registro=1" class="font-semibold text-brand hover:underline">Crea tu cuenta</router-link> para guardar esta cotización y retomarla cuando quieras.</p>
            </div>
          </div>

          <!-- Estado de carga / mensaje -->
          <div v-if="loadingQuote" class="mb-4 flex items-center gap-3 text-sm text-muted" role="status">
            <span class="w-4 h-4 border-2 border-primary border-t-transparent rounded-full animate-spin" aria-hidden="true"></span>
            {{ quoteMessage }}
          </div>

          <!-- Visor (persistente: un solo StlViewer para todos los modelos) -->
          <div class="surface-card relative h-[480px] sm:h-[540px] lg:h-[600px] bg-surface rounded-[var(--radius-panel)] border border-line overflow-hidden">
            <StlViewer
              ref="viewerRef"
              :active-key="activeModel?.id ?? 0"
              @model-loaded="handleModelLoaded"
              @model-transformed="handleModelLoaded"
              @file-ready="handleFileReady"
              @error="handleError"
            />
            <transition enter-active-class="transition duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition duration-200" leave-from-class="opacity-100" leave-to-class="opacity-0">
              <div v-if="isSlicing" class="absolute inset-0 z-30 flex items-center justify-center p-4 bg-canvas/70 backdrop-blur-sm" role="status" aria-live="polite">
                <div class="bg-surface border border-line rounded-[var(--radius-card)] shadow-xl px-6 py-5 flex items-center gap-4 max-w-sm">
                  <span class="w-8 h-8 shrink-0 border-[3px] border-primary border-t-transparent rounded-full animate-spin" aria-hidden="true"></span>
                  <div class="min-w-0">
                    <p class="font-semibold text-ink">
                      Calculando el precio<template v-if="slicingProgress.total > 1"> ({{ slicingProgress.current }} de {{ slicingProgress.total }})</template>
                    </p>
                    <p class="text-sm text-muted truncate">{{ slicingProgress.label }}</p>
                    <p class="text-sm text-muted">Estamos laminando el archivo. Puede tardar unos segundos.</p>
                  </div>
                </div>
              </div>
            </transition>
          </div>

        </div>
      </section>

      <!-- Guía de materiales -->
      <section class="min-w-0 px-4 sm:px-6 lg:px-8 py-12 lg:pt-16 lg:pb-10 lg:col-start-1 lg:row-start-2 order-last lg:order-none" aria-labelledby="material-guide-title">
        <div class="w-full max-w-5xl mx-auto">
            <h2 id="material-guide-title" class="section-title">¿Qué material <span class="section-title-accent">elegir?</span></h2>
            <p class="section-lead mt-4">Una guía rápida. Si tienes dudas, el taller te recomienda al revisar tu pedido.</p>
            <div class="mt-8 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
              <div v-for="mat in materialGuide" :key="mat.name" class="bg-surface p-5 rounded-[var(--radius-card)]">
                <div class="w-10 h-10 bg-primary/10 rounded-[var(--radius-control)] flex items-center justify-center mb-4" aria-hidden="true" :innerHTML="sanitizeSVG(mat.icon)"></div>
                <h3 class="text-base font-semibold text-ink [font-stretch:100%] tracking-normal">{{ mat.name }}</h3>
                <p class="mt-1 text-sm text-muted leading-relaxed">{{ mat.desc }}</p>
                <p class="mt-4 text-sm text-ink"><span class="text-muted">Ideal para:</span> {{ mat.bestFor }}</p>
              </div>
            </div>
        </div>
      </section>

      <!-- Panel de configuración -->
      <aside class="lg:col-start-2 lg:row-start-1 lg:row-span-2 bg-surface border-t lg:border-t-0 lg:border-l border-line lg:sticky lg:top-16 lg:self-start lg:max-h-[calc(100vh-4rem)] lg:overflow-y-auto" aria-label="Configuración y precio">
        <QuoteSidebar
          :models="models"
          :active-model-idx="activeModelIdx"
          :materials="materials"
          :utilities="utilities"
          :selected-technology="selectedTechnology"
          :selected-material="selectedMaterial"
          :qty="activeModel?.qty ?? 1"
          :infill="infill"
          :layer-height="layerHeight"
          :selected-extras="selectedExtras"
          :active-coupon="activeCoupon"
          :coupon-code="couponCode"
          :coupon-error="couponError"
          :coupon-loading="couponLoading"
          :breakdown="breakdown"
          :model-summaries="modelSummaries"
          :is-slicing="isSlicing"
          :previous-total="previousTotal"
          :last-order="lastOrder"
          @update:selected-technology="selectedTechnology = $event"
          @update:selected-material="selectedMaterial = $event"
          @update:qty="updateActiveQty"
          @update:infill="infill = $event"
          @update:layer-height="layerHeight = $event"
          @update:coupon-code="couponCode = $event"
          @toggle-extra="toggleExtra"
          @apply-coupon="applyCoupon"
          @remove-coupon="removeCoupon"
          @calculate="runCuraEngineAnalysis"
          @request-quote="openModal"
        />
      </aside>
    </main>

    <QuoteOrderModal
      :show="showModal"
      :models="modelSummaries"
      :materials="materials"
      :selected-technology="selectedTechnology"
      :selected-material="selectedMaterial"
      :breakdown="breakdown"
      :active-coupon="activeCoupon"
      :is-submitting="isSubmitting"
      :notification="notification"
      @close="showModal = false"
      @submit="handleModalSubmit"
    />
    <AppFooter />
  </div>
</template>

