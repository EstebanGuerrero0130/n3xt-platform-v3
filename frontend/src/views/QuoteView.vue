<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted } from 'vue'
import StlViewer from '../components/StlViewer.vue'
import { sanitizeSVG } from '../utils/sanitize'
import { api } from '../services/api'
import { calcProductionCost, calcFinalPrice, calcExtraCost } from '../services/costCalculator'
import { useRevealAnim } from '../composables/useRevealAnim'
import AppNavbar from '../components/AppNavbar.vue'
import AppFooter from '../components/AppFooter.vue'
import QuoteSidebar from '../components/QuoteSidebar.vue'
import QuoteOrderModal from '../components/QuoteOrderModal.vue'
import logger from '../utils/logger'
import { useSplitTitle } from '../composables/useSplitTitle'
import { useSplitButton } from '../composables/useSplitButton'
import { usePageMeta } from '../composables/usePageMeta'

useSplitTitle()
useSplitButton()

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
  volume: number
  totalArea: number
  dimensions: { x: number; y: number; z: number }
  infill: number
  layerHeight: number
  supportArea: number
  currentScale: number
  hasModel: boolean
  hasSlicing: boolean
  weight: number
  duration: number
  name: string
  curaFactors: CuraFactors
}
interface CustomerForm {
  name: string; company: string; document: string; email: string; phone: string
  city: string; address: string; use: string; comments: string
}

const errMessage = (err: unknown): string => (err instanceof Error ? err.message : String(err))

usePageMeta({
  title: 'Cotizador de impresión 3D | N3XT 3D',
  description: 'Sube tu modelo STL u OBJ y obtén un precio estimado de impresión 3D en resina o filamento.',
  image: '/assets/n3xt_og_quote.png',
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

let resizeCleanup: (() => void) | null = null

const setupResizeListener = () => {
  const handler = () => {
    const w = window.innerWidth
    isDesktop.value = w >= 1024
    if (w >= 1024) showSidebar.value = true
  }
  window.addEventListener('resize', handler)
  resizeCleanup = () => window.removeEventListener('resize', handler)
}

onMounted(async () => {
  loadingQuote.value = true
  quoteMessage.value = 'Cargando materiales y configuración...'
  await fetchSettings()
  await fetchMaterials()
  loadingQuote.value = false
  calculatePrice()
  
  setupResizeListener()
})

const selectedTechnology = ref('FDM')
const selectedMaterial = ref('')
const qty = ref(1)

// --- CURAENGINE INTEGRATION (MANUAL SINCRO) ---
const isSlicing = ref(false)

const runCuraEngineAnalysis = async () => {
  const idx = activeModelIdx.value
  const model = models.value[idx]
  if (!model || !model.file) {
      notify("Sube un modelo 3D primero", "error")
      return
  }
  
  isSlicing.value = true
  
  try {
    const mat = materials.value.find(m => m.id === selectedMaterial.value)
    const formData = new FormData()
    formData.append('file', model.file)
    formData.append('infill', String(model.infill || 15))
    formData.append('layer_height', String(model.layerHeight || 0.2))
    formData.append('total_area', String(model.totalArea || 0))
    formData.append('volume_mm3', String(model.volume || 0))
    formData.append('support_area', String(model.supportArea || 0))
    if (mat) {
        formData.append('density', String(mat.density ?? ''))
        formData.append('material_id', mat.id)
    }
    
    const res = await api.post('/process-stl', formData)
    const factors = res.data || res.factors || res;
    
    if (factors && (factors.shell_weight_g >= 0)) {
        models.value[idx].curaFactors = factors
        models.value[idx].hasSlicing = true
        slicedSignatures.set(models.value[idx].id, slicingSignature(models.value[idx]))
        // Store weight and duration from slicing
        models.value[idx].weight = (Number(factors.shell_weight_g) || 0) 
                                 + (Number(factors.internal_weight_g) || 0) 
                                 + (Number(factors.support_weight_g) || 0) 
                                 + (Number(factors.purge_weight_g) || 3.0)
        models.value[idx].duration = (Number(factors.prep_time_h) || 0) 
                                   + (Number(factors.print_time_h) || 0)
        notify("Precio calculado. Revisa el resumen en el panel.", "success")
        calculatePrice()
    } else {
        throw new Error("El motor devolvió datos incompletos.")
    }
  } catch (err) {
    logger.error("CuraEngine Error:", err)
    const message = errMessage(err)
    const statusInfo = message.includes('(') ? ` [${message.split('(')[1].split(')')[0]}]` : '';
    notify(`No pudimos calcular el precio${statusInfo}: ${message.split(' (')[0] || 'falló la conexión'}`, "error")
  } finally {
    isSlicing.value = false
  }
}

const breakdown = ref({
  weight: 0, duration: 0, matCost: 0, infraCost: 0, laborCost: 0, utilityCost: 0, marginCost: 0, discount: 0, subtotal: 0, iva: 0, ivaPct: 19, total: 0
})

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
const showSidebar = ref(window.innerWidth >= 1024)
const isDesktop = ref(window.innerWidth >= 1024)
const modalStep = ref(1)

// Multi-modelo support
const models = ref<QuoteModel[]>([{ id: 0, file: null, volume: 0, totalArea: 0, dimensions: { x: 0, y: 0, z: 0 }, infill: 15, layerHeight: 0.2, supportArea: 0, currentScale: 1.0, hasModel: false, hasSlicing: false, weight: 0, duration: 0, name: 'Modelo 1', curaFactors: { resolution: 1, infill_density: 1, shell_weight_g: 0, internal_weight_g: 0, support_weight_g: 0, filament_length_m: 0, prep_time_h: 0.233, print_time_h: 0 } }])
const activeModelIdx = ref(0)
const honeypot = ref('')
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

const couponCode = ref('')
const activeCoupon = ref<any>(null)
const validCoupons = [
    { code: 'N3XT10', discount: 0.10, label: 'Cupón Bienvenida' },
    { code: 'MAKER5', discount: 0.05, label: 'Especial Maker' },
    { code: 'FDM2026', discount: 0.15, label: 'Lanzamiento Industrial' }
]

const applyCoupon = () => {
    const coupon = validCoupons.find(c => c.code.toUpperCase() === couponCode.value.toUpperCase())
    if (coupon) {
        activeCoupon.value = coupon
        notify(`¡Cupón ${coupon.label} aplicado!`, 'success')
        calculatePrice()
    } else {
        activeCoupon.value = null
        notify('Cupón no válido', 'error')
        calculatePrice()
    }
}

const _timerNotif = ref<ReturnType<typeof setTimeout> | null>(null)
const notification = ref({ show: false, message: '', type: 'info' })
const notify = (msg: string, type = 'info') => {
    if (_timerNotif.value) clearTimeout(_timerNotif.value)
    notification.value = { show: true, message: msg, type }
    _timerNotif.value = setTimeout(() => { notification.value.show = false }, 5000)
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

const handleFileReady = (file: File) => {
  if (models.value[activeModelIdx.value]) {
    models.value[activeModelIdx.value].file = file
    models.value[activeModelIdx.value].hasSlicing = false
    slicedSignatures.delete(models.value[activeModelIdx.value].id)
  }
}

// --- Invalidación del laminado FDM ---
// El resultado de CuraEngine depende de estos parámetros; si cambian, hay que recalcular.
const slicedSignatures = new Map<number, string>()
const slicingSignature = (m: QuoteModel) =>
  [selectedMaterial.value, m.infill, m.layerHeight, m.currentScale, m.volume, m.supportArea].join('|')

watch(
  () => models.value.map(m => [m.id, slicingSignature(m)] as const),
  (sigs) => {
    sigs.forEach(([id, sig]) => {
      const model = models.value.find(m => m.id === id)
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
  if (honeypot.value) return;

  const now = Date.now();
  if (now - lastSubmitTime.value < 10000) {
    notify("Por favor espera unos segundos antes de enviar otra solicitud.", "warning");
    return;
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
    
    // Datos Técnicos
    formData.append('technology', selectedTechnology.value)
    formData.append('material_id', selectedMaterial.value)
    formData.append('material_name', mat ? mat.name : '')
    formData.append('volume_mm3', String(models.value.reduce((s, m) => s + (m.volume || 0), 0)))
    formData.append('estimated_weight_g', String(breakdown.value.weight))
    formData.append('estimated_duration_h', String(breakdown.value.duration))
    formData.append('qty', String(qty.value))
    formData.append('infill', String(Math.round(models.value[0]?.infill || 15)))
    formData.append('total_price', String(breakdown.value.total))

    const mainModel = models.value[0]
    if (mainModel?.hasModel) {
      const d = mainModel.dimensions
      formData.append('dimensions_mm', `${d.x.toFixed(1)}x${d.y.toFixed(1)}x${d.z.toFixed(1)}`)
      formData.append('scale_factor', String(mainModel.currentScale || 1))
    }
    if (mainModel?.file) {
      formData.append('file', mainModel.file)
      formData.append('job_name', mainModel.file.name)
    }

    // Extras
    if (selectedExtras.value.length > 0) {
      selectedExtras.value.forEach((extra, index) => {
        formData.append(`extra_items[${index}][material_id]`, extra.id)
        formData.append(`extra_items[${index}][qty]`, String(extra.qty))
      })
    }
    
    const res = await api.post('/orders', formData)
    const orderId = res?.order_id
    
    lastSubmitTime.value = Date.now()
    showModal.value = false
    notify(`Recibimos tu pedido${orderId ? ` #${orderId}` : ''}. Te escribiremos por WhatsApp o email para confirmar.`, "success")
    
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

watch([selectedTechnology, selectedMaterial, qty, models], () => {
  const mat = materials.value.find(m => m.id === selectedMaterial.value)
  if (mat && mat.category !== selectedTechnology.value) {
    const firstAvailable = materials.value.find(m => m.category === selectedTechnology.value)
    if (firstAvailable) selectedMaterial.value = firstAvailable.id
  }
  calculatePrice()
}, { deep: true })

const calculatePrice = () => {
  if (loadingQuote.value) return
  
  const mat = materials.value.find(m => m.id === selectedMaterial.value)
  if (!mat || !appData.value) {
    quoteMessage.value = 'Cargando datos de materiales...'
    return
  }
  if (!models.value.length || !models.value[0]?.hasModel) {
    quoteMessage.value = 'Sube un modelo 3D para ver el precio'
    // Mostrar breakdown en cero pero con mensaje
    breakdown.value = { ...breakdown.value, weight: 0, duration: 0, total: 0, subtotal: 0, iva: 0 }
    return
  }
  quoteMessage.value = ''
  
  const cfg = appData.value
  let totalWeight = 0
  let totalDuration = 0
  
  // Iterate over all models
  for (const model of models.value) {
    let mWeight = 0
    let mDuration = 0
    
    if (selectedTechnology.value === 'FDM') {
      if (model.hasSlicing && model.curaFactors) {
        const cf = model.curaFactors
        mWeight = (Number(cf.shell_weight_g) || 0) 
                + (Number(cf.internal_weight_g) || 0) 
                + (Number(cf.support_weight_g) || 0) 
                + (Number(cf.purge_weight_g) || 3.0)
        mDuration = (Number(cf.prep_time_h) || 0) 
                  + (Number(cf.print_time_h) || 0)
        // Store in model for display
        model.weight = mWeight
        model.duration = mDuration
      }
    } else {
      // SLA: volume viene en mm³, convertir a cm³
      const volCm3 = (model.volume || 0) / 1000 
      const density = Number(mat.density) || 1.1
      mWeight = volCm3 * density * 1.1 
      const heightMm = model.dimensions?.z || model.dimensions?.y || 0
      mDuration = Math.max(0.5, heightMm / 25)
      model.weight = mWeight
      model.duration = mDuration
    }
    
    totalWeight += mWeight
    totalDuration += mDuration
  }
  
  // Apply quantity to totals
  totalWeight *= qty.value
  totalDuration *= qty.value
  
  // Costo de material
  let utilityCost = 0
  autoExtras.value = []
  if (selectedTechnology.value === 'SLA') {
    const alcohol = utilities.value.find(u => u.id === 'Alco_ML_05')
    if (alcohol) {
      utilityCost += calcExtraCost(Number(alcohol.cost_per_kg) || 0, alcohol.unit || 'ml', 50 * qty.value)
      autoExtras.value.push({ id: alcohol.id, qty: 50 * qty.value, name: alcohol.name })
    }
    const curado = utilities.value.find(u => u.id === 'Cicl_Serv_06')
    if (curado) {
      utilityCost += calcExtraCost(Number(curado.cost_per_kg) || 0, curado.unit || 'servicio', qty.value)
      autoExtras.value.push({ id: curado.id, qty: qty.value, name: curado.name })
    }
  }
  
  selectedExtras.value.forEach(item => {
    const extra = utilities.value.find(u => u.id === item.id)
    if (extra) {
        utilityCost += calcExtraCost(Number(extra.cost_per_kg) || 0, extra.unit || 'g', item.qty)
    }
  })
  
  // Use shared services for calculations
  const prod = calcProductionCost({
    weightG: totalWeight,
    totalHours: totalDuration,
    costPerKg: Number(mat.cost_per_kg) || 0,
    infra: cfg.infra,
    prep: cfg.prep,
    extrasCost: utilityCost,
  })
  
  const totalBaseCost = prod.total
  const baseUnitCost = qty.value > 0 ? totalBaseCost / qty.value : 0
  
  // Margins on base unit cost, then apply qty
  const pricePerUnit = calcFinalPrice({
    productionCost: baseUnitCost,
    oper: cfg.oper,
    margin: cfg.margin,
  })
  
  const subtotal = pricePerUnit.subtotal * qty.value
  
  // Apply coupon discount separately (it's on total)
  const discountAmount = activeCoupon.value ? (subtotal * activeCoupon.value.discount) : 0
  const finalSubtotal = Math.round(subtotal - discountAmount)
  const ivaPct = cfg.margin.iva ?? 19
  const ivaRate = ivaPct / 100
  const iva = Math.round(finalSubtotal * ivaRate)
  const total = finalSubtotal + iva
  
  const newTotal = total
  if (newTotal !== breakdown.value.total) {
    previousTotal.value = breakdown.value.total
  }
  
  const marginCost = (pricePerUnit.logistics + pricePerUnit.marketing + pricePerUnit.failures + pricePerUnit.profit) * qty.value
  
  breakdown.value = {
    weight: totalWeight,
    duration: totalDuration,
    matCost: prod.material,
    infraCost: prod.luz + prod.depr + prod.mant,
    laborCost: prod.labor,
    utilityCost,
    marginCost,
    discount: Math.round(discountAmount),
    subtotal: finalSubtotal,
    iva,
    ivaPct,
    total,
  }
}


const handleModelLoaded = (data: { volume: number; totalArea?: number; dimensions: { x: number; y: number; z: number }; scale?: number; supportArea?: number }) => {
  const idx = activeModelIdx.value
  if (models.value[idx]) {
    models.value[idx].volume = data.volume
    models.value[idx].totalArea = data.totalArea || 0
    models.value[idx].dimensions = { x: data.dimensions.x, y: data.dimensions.y, z: data.dimensions.z }
    models.value[idx].currentScale = data.scale || 1.0
    models.value[idx].supportArea = data.supportArea || 0
    models.value[idx].hasModel = true
  }
  if (selectedTechnology.value !== 'FDM') calculatePrice()
}

const handleError = (msg: string) => {
  notify(msg, "error")
}

onUnmounted(() => {
  if (_timerNotif.value) clearTimeout(_timerNotif.value)
  if (resizeCleanup) resizeCleanup()
})

</script>

<template>
  <div class="min-h-screen flex flex-col bg-canvas text-ink overflow-x-hidden">
    <AppNavbar active-tab="quote" subtext="Cotizador" />

    <main class="flex-1 flex flex-col lg:flex-row">
      <!-- Visor + modelos -->
      <section class="flex-1 min-w-0 px-4 sm:px-6 lg:px-8 py-8 lg:py-10" aria-labelledby="quote-title">
        <div class="w-full max-w-5xl mx-auto">
          <header class="mb-6">
            <h1 id="quote-title" class="text-3xl sm:text-4xl font-extrabold tracking-tight text-ink">Cotiza tu pieza 3D</h1>
            <p class="mt-2 text-[0.9375rem] md:text-base text-muted max-w-2xl">Sube tu archivo, elige material y parámetros y obtén un precio estimado. El taller lo revisa antes de imprimir.</p>
          </header>

          <!-- Modelos -->
          <div class="flex flex-wrap items-center gap-2 mb-4" aria-label="Modelos de la cotización" role="group">
            <button
              v-for="(m, mi) in models" :key="m.id"
              type="button"
              :aria-pressed="activeModelIdx === mi"
              :class="activeModelIdx === mi ? 'bg-ink text-canvas border-ink' : 'border-line text-muted hover:text-ink'"
              class="h-9 px-4 rounded-full border text-sm font-medium inline-flex items-center gap-2 transition-colors"
              @click="activeModelIdx = mi"
            >
              {{ m.name || 'Modelo ' + (mi + 1) }}
              <svg v-if="m.hasModel" class="w-4 h-4" :class="activeModelIdx === mi ? '' : 'text-brand'" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
              <span v-if="m.hasModel" class="sr-only">(archivo cargado)</span>
            </button>
            <button type="button" class="btn-outlined btn-sm" @click="models.push({ id: models.length, file: null, volume: 0, totalArea: 0, dimensions: { x: 0, y: 0, z: 0 }, infill: 15, layerHeight: 0.2, supportArea: 0, currentScale: 1.0, hasModel: false, hasSlicing: false, weight: 0, duration: 0, name: 'Modelo ' + (models.length + 1), curaFactors: { resolution: 1, infill_density: 1, shell_weight_g: 0, internal_weight_g: 0, support_weight_g: 0, filament_length_m: 0, prep_time_h: 0.233, print_time_h: 0 } }); activeModelIdx = models.length - 1">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14m-7-7h14"/></svg>
              Añadir modelo
            </button>
          </div>

          <!-- Estado de carga / mensaje -->
          <div v-if="loadingQuote" class="mb-4 flex items-center gap-3 text-sm text-muted" role="status">
            <span class="w-4 h-4 border-2 border-primary border-t-transparent rounded-full animate-spin" aria-hidden="true"></span>
            {{ quoteMessage }}
          </div>

          <!-- Visor -->
          <div class="relative h-[480px] sm:h-[540px] lg:h-[600px] bg-surface rounded-[var(--radius-panel)] border border-line overflow-hidden">
            <div class="technical-grid absolute inset-0 pointer-events-none" aria-hidden="true"></div>
            <StlViewer :key="'viewer-' + activeModelIdx" @model-loaded="handleModelLoaded" @model-transformed="handleModelLoaded" @file-ready="handleFileReady" @error="handleError" />
            <transition enter-active-class="transition duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition duration-200" leave-from-class="opacity-100" leave-to-class="opacity-0">
              <div v-if="isSlicing" class="absolute inset-0 z-30 flex items-center justify-center p-4 bg-canvas/70 backdrop-blur-sm" role="status" aria-live="polite">
                <div class="bg-surface border border-line rounded-[var(--radius-card)] shadow-xl px-6 py-5 flex items-center gap-4 max-w-sm">
                  <span class="w-8 h-8 shrink-0 border-[3px] border-primary border-t-transparent rounded-full animate-spin" aria-hidden="true"></span>
                  <div>
                    <p class="font-semibold text-ink">Calculando el precio del modelo {{ activeModelIdx + 1 }}</p>
                    <p class="text-sm text-muted">Estamos laminando el archivo. Puede tardar unos segundos.</p>
                  </div>
                </div>
              </div>
            </transition>
          </div>

          <!-- Guía de materiales -->
          <section class="mt-12 reveal" aria-labelledby="material-guide-title">
            <h2 id="material-guide-title" class="text-xl md:text-2xl font-extrabold tracking-tight text-ink">¿Qué material elegir?</h2>
            <p class="mt-1 text-sm text-muted">Una guía rápida. Si tienes dudas, el taller te recomienda al revisar tu pedido.</p>
            <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
              <div v-for="mat in materialGuide" :key="mat.name" class="bg-surface p-5 rounded-[var(--radius-card)] border border-line">
                <div class="w-10 h-10 bg-primary/10 rounded-[var(--radius-control)] flex items-center justify-center mb-4" aria-hidden="true" :innerHTML="sanitizeSVG(mat.icon)"></div>
                <h3 class="text-base font-semibold text-ink [font-stretch:100%] tracking-normal">{{ mat.name }}</h3>
                <p class="mt-1 text-sm text-muted leading-relaxed">{{ mat.desc }}</p>
                <p class="mt-3 pt-3 border-t border-line text-sm text-ink"><span class="text-muted">Ideal para:</span> {{ mat.bestFor }}</p>
              </div>
            </div>
          </section>
        </div>
      </section>

      <!-- Panel de configuración -->
      <aside class="w-full lg:w-[420px] xl:w-[460px] shrink-0 bg-surface border-t lg:border-t-0 lg:border-l border-line lg:sticky lg:top-16 lg:self-start lg:max-h-[calc(100vh-4rem)] lg:overflow-y-auto" aria-label="Configuración y precio">
        <QuoteSidebar
          :models="models"
          :active-model-idx="activeModelIdx"
          :materials="materials"
          :utilities="utilities"
          :selected-technology="selectedTechnology"
          :selected-material="selectedMaterial"
          :qty="qty"
          :selected-extras="selectedExtras"
          :active-coupon="activeCoupon"
          :coupon-code="couponCode"
          :breakdown="breakdown"
          :is-slicing="isSlicing"
          :previous-total="previousTotal"
          @update:selected-technology="selectedTechnology = $event"
          @update:selected-material="selectedMaterial = $event"
          @update:qty="qty = $event"
          @update:coupon-code="couponCode = $event"
          @toggle-extra="toggleExtra"
          @apply-coupon="applyCoupon"
          @calculate="runCuraEngineAnalysis"
          @request-quote="openModal"
          @update:models="models = $event"
        />
      </aside>
    </main>

    <QuoteOrderModal
      :show="showModal"
      :models="models"
      :materials="materials"
      :selected-technology="selectedTechnology"
      :selected-material="selectedMaterial"
      :qty="qty"
      :breakdown="breakdown"
      :active-coupon="activeCoupon"
      :is-submitting="isSubmitting"
      :notification="notification"
      @close="showModal = false; modalStep = 1"
      @submit="handleModalSubmit"
    />
    <AppFooter />
  </div>
</template>

<style scoped>
/* --- Scroll Reveal --- */
.reveal {
  opacity: 0;
  transform: translateY(16px);
  transition: opacity 0.6s cubic-bezier(0.22, 1, 0.36, 1),
              transform 0.6s cubic-bezier(0.22, 1, 0.36, 1);
}
.reveal.revealed {
  opacity: 1;
  transform: translateY(0);
}
</style>
