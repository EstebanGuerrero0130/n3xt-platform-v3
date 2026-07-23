<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted } from 'vue'
import StlViewer from '../components/StlViewer.vue'
import { sanitizeSVG } from '../utils/sanitize'
import { api } from '../services/api'
import { calcProductionCost, calcFinalPrice, calcExtraCost, calcVolumeDiscount } from '../services/costCalculator'
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

usePageMeta({
 title: 'Cotizador 3D Industrial | N3XT 3D',
 description: 'Cotiza tus piezas 3D al instante. Precios precisos con motor de calculo industrial.',
 image: '/assets/n3xt_og_quote.png',
})

// --- N3XT CORE DATA ---
const appData = ref({
 infra: { luz_hr: 0, depr_hr: 0, mant_hr: 0, etiquetas: 0, load_factor: 0.4 },
 prep: { mano_obra_hr: 0, prep_time_pct: 10 },
 oper: { transporte: 0, ganancia: 0, marketing: 0, fallos: 0 },
 margin: { iva: 0 }
})
const materials = ref<any[]>([])
const utilities = ref<any[]>([])
const selectedExtras = ref<any[]>([])
const autoExtras = ref<any[]>([])
const loadingQuote = ref(true)
const quoteMessage = ref('')

const fetchMaterials = async () => {
 try {
 const data = await api.get('/materials')
 materials.value = data.filter((m: any) => m.type === 'material')
 utilities.value = data.filter((m: any) => m.type !== 'material')
 
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
 m.iva = Number(m.iva) || 19
 }
 } catch (err) {
 logger.error('Error fetching settings:', err)
 }
}

useRevealAnim({ delay: 200 })

let resizeCleanup: any = null

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
  // Si el archivo es mayor a 15MB, usamos la estimación local directamente para evitar 413
  if (model.file.size > 15 * 1024 * 1024) {
    models.value[idx].hasSlicing = false
    calculatePrice()
    return
  }

  const mat = materials.value.find(m => m.id === selectedMaterial.value)
  const formData = new FormData()
  formData.append('file', model.file)
  formData.append('infill', String(model.infill || 15))
  formData.append('layer_height', String(model.layerHeight || 0.2))
  formData.append('total_area', String(model.totalArea || 0))
  formData.append('volume_mm3', String(model.volume || 0))
  formData.append('support_area', String(model.supportArea || 0))
  formData.append('technology', selectedTechnology.value)
  formData.append('height_mm', String(model.dimensions?.z || 0))
  if (mat) {
  formData.append('density', String(mat.density))
  formData.append('material_id', String(mat.id))
  }
  
  const res = await api.post('/process-stl', formData)
  const factors = res.data || res.factors || res;
  
  if (factors && (factors.shell_weight_g >= 0)) {
  models.value[idx].curaFactors = factors
  models.value[idx].hasSlicing = true
  models.value[idx].weight = (Number(factors.shell_weight_g) || 0) 
  + (Number(factors.internal_weight_g) || 0) 
  + (Number(factors.support_weight_g) || 0) 
  + (Number(factors.purge_weight_g) || 3.0)
  models.value[idx].duration = (Number(factors.prep_time_h) || 0) 
  + (Number(factors.print_time_h) || 0)
  calculatePrice()
  } else {
  // Silent fallback
  models.value[idx].hasSlicing = false
  calculatePrice()
  }
  } catch (err: any) {
   // Silently fall back to local estimation
   models.value[activeModelIdx.value].hasSlicing = false
   calculatePrice()
  } finally {
  isSlicing.value = false
 }
}

const removeModel = (idx: number) => {
 if (models.value.length > 1) {
 models.value.splice(idx, 1)
 if (activeModelIdx.value >= models.value.length) {
 activeModelIdx.value = models.value.length - 1
 }
 calculatePrice()
 }
}

const breakdown = ref({
 weight: 0, duration: 0, matCost: 0, infraCost: 0, laborCost: 0, utilityCost: 0, marginCost: 0, discount: 0, subtotal: 0, iva: 0, total: 0
})

const materialGuide = [
 { 
 name: 'PLA', 
 desc: 'Facil de imprimir, biodegradable y rigido.', 
 bestFor: 'Prototipos esteticos y figuras.',
 icon: `<svg class="w-5 h-5 text-[#8dd6ff]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>`
 },
 { 
 name: 'PETG', 
 desc: 'Resistente al impacto y la quimica.', 
 bestFor: 'Piezas mecanicas y exteriores.',
 icon: `<svg class="w-5 h-5 text-[#8dd6ff]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>`
 },
 { 
 name: 'ABS', 
 desc: 'Alta resistencia termica y mecanica.', 
 bestFor: 'Carcasas y uso automotriz.',
 icon: `<svg class="w-5 h-5 text-[#8dd6ff]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>`
 },
 { 
 name: 'RESINA', 
 desc: 'Resolucion extrema y superficie lisa.', 
 bestFor: 'Joyeria, dental y miniaturas.',
 icon: `<svg class="w-5 h-5 text-[#8dd6ff]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.628.209a2 2 0 01-1.564 0l-.628-.209a6 6 0 00-3.86-.517L3.081 14.88a2 2 0 00-1.022.547l-.21.21a2 2 0 00.707 3.414l9.9 1.98a2 2 0 00.707 0l9.9-1.98a2 2 0 00.707-3.414l-.21-.21zM6.25 8.044a2 2 0 01.97-1.712l4-2.286a2 2 0 011.56 0l4 2.286a2 2 0 01.97 1.712v4.572a2 2 0 01-.97 1.712l-4 2.286a2 2 0 01-1.56 0l-4-2.286a2 2 0 01-.97-1.712V8.044z"/></svg>`
 }
]

const showModal = ref(false)
const isSubmitting = ref(false)
const previousTotal = ref(0)
const showSidebar = ref(window.innerWidth >= 1024)
const isDesktop = ref(window.innerWidth >= 1024)
const modalStep = ref(1)

// Multi-modelo support
const models = ref([{ id: 0, file: null, volume: 0, totalArea: 0, dimensions: { x: 0, y: 0, z: 0 }, infill: 15, layerHeight: 0.2, supportArea: 0, currentScale: 1.0, hasModel: false, hasSlicing: false, weight: 0, duration: 0, name: 'Modelo 1', curaFactors: { resolution: 1, infill_density: 1, shell_weight_g: 0, internal_weight_g: 0, support_weight_g: 0, filament_length_m: 0, prep_time_h: 0.233, print_time_h: 0 } }])
const activeModelIdx = ref(0)
const honeypot = ref('')
const lastSubmitTime = ref(0)
const customerForm = ref({
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

const _timerNotif = ref<any>(null)
const notification = ref({ show: false, message: '', type: 'info' })
const notify = (msg: string, type = 'info') => {
 notification.value = { show: true, message: msg, type }
 _timerNotif.value = setTimeout(() => { notification.value.show = false }, 5000)
 }

const handleModalSubmit = (payload: any) => {
 if (!payload.captchaVerified) {
 notify('Error de Verificación', 'error')
 return
 }
 customerForm.value = { ...customerForm.value, ...payload.customerForm }
 submitOrder()
}

const openModal = () => {
 showModal.value = true
}

const handleFileReady = (file: any) => {
 if (models.value[activeModelIdx.value]) {
 models.value[activeModelIdx.value].file = file
 models.value[activeModelIdx.value].hasSlicing = false
 }
}

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
 formData.append('shipping_address', `${customerForm.value.address} | ${customerForm.value.city}`)
 formData.append('comments', `USO: ${customerForm.value.use} | NOTAS: ${customerForm.value.comments}`)
 
 // Datos Técnicos
 formData.append('technology', selectedTechnology.value)
 formData.append('material_id', selectedMaterial.value)
 formData.append('material_name', mat ? mat.name : '')
 formData.append('volume_mm3', String(models.value.reduce((s, m) => s + (m.volume || 0), 0)))
 formData.append('estimated_weight_g', String(breakdown.value.weight))
 formData.append('estimated_duration_h', String(breakdown.value.duration))
 formData.append('qty', String(qty.value))
 formData.append('infill', String(models.value[0]?.infill || 15))
 formData.append('total_price', String(breakdown.value.total))
 
 if (models.value[0]?.file) {
 formData.append('file', models.value[0].file)
 }

 // Extras
 if (selectedExtras.value.length > 0) {
 selectedExtras.value.forEach((extra, index) => {
 formData.append(`extra_items[${index}][material_id]`, String(extra.id))
 formData.append(`extra_items[${index}][qty]`, String(extra.qty))
 })
 }
 
 await api.post('/orders', formData)
 
 lastSubmitTime.value = Date.now()
 showModal.value = false
 notify("¡Protocolo de Solicitud completado con éxito!", "success")
 
 // Reset form
 customerForm.value = { name: '', company: '', document: '', email: '', phone: '', city: '', address: '', use: '', comments: '' }
 } catch (err: any) {
 notify(err.response?.data?.message || err.message || "Error al procesar la cotización", "error")
 } finally {
 isSubmitting.value = false
 }
}

const toggleExtra = (id: any) => {
 const idx = selectedExtras.value.findIndex(e => e.id === id)
 if (idx > -1) selectedExtras.value.splice(idx, 1)
 else selectedExtras.value.push({ id, qty: 1 })
 calculatePrice()
}

watch([selectedTechnology, selectedMaterial, qty, models, selectedExtras], () => {
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
        + (Number((cf as any).purge_weight_g) || 3.0)
        mDuration = (Number(cf.prep_time_h) || 0) 
        + (Number(cf.print_time_h) || 0)
        model.weight = mWeight
        model.duration = mDuration
      } else if (model.volume > 0) {
        // BUG 1 FIX: estimación fallback para FDM sin slicing
        // Densidad promedio del filamento ~1.24 g/cm³, infill promedio ~30%
        const density = Number(mat?.density) || 1.24
        const infillFactor = (model.infill || 15) / 100
        const shellFactor = 0.25 // ~25% del volumen es paredes y techo/suelo
        const volCm3 = model.volume / 1000
        mWeight = volCm3 * density * (shellFactor + infillFactor * (1 - shellFactor)) * 1.1
        const maxDimMm = Math.max(model.dimensions?.x || 0, model.dimensions?.y || 0, model.dimensions?.z || 0) || 50
        mDuration = Math.max(0.25, (maxDimMm / (model.layerHeight || 0.2)) * 0.000167) // ~6 seg/capa a 0.2mm
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
 
  // User-selected extras: treat cost_per_kg as FLAT price per unit
  // These are services (painting, finishing) — NOT sold by weight
  selectedExtras.value.forEach(item => {
  const extra = utilities.value.find(u => u.id === item.id)
  if (extra) {
    const weightUnits = ['g', 'ml', 'kg', 'l']
    const unit = (extra.unit || '').toLowerCase().trim()
    if (weightUnits.includes(unit)) {
      // Actual consumable by weight (e.g. resins, powders)
      utilityCost += Number(extra.cost_per_kg) * (item.qty / 1000)
    } else {
      // Flat-price service (painting, finishing, curing, etc.)
      utilityCost += Number(extra.cost_per_kg) * item.qty
    }
  }
  })

 
 // Use shared services for calculations
 const prod = calcProductionCost({
 weightG: totalWeight,
 totalHours: totalDuration,
 costPerKg: Number(mat.cost_per_kg) || 0,
 infra: cfg.infra,
 prep: cfg.prep,
 extrasCost: 0,
 })
 
 const totalBaseCost = prod.total
 const baseUnitCost = qty.value > 0 ? totalBaseCost / qty.value : 0
 
 // Margins on base unit cost, then apply qty
 const pricePerUnit = calcFinalPrice({
 productionCost: baseUnitCost,
 oper: cfg.oper,
 margin: cfg.margin,
 })
 
 const subtotalBeforeExtras = pricePerUnit.subtotal * qty.value
 const subtotal = subtotalBeforeExtras + utilityCost
 
 // --- DESCUENTO POR VOLUMEN (#6) ---
 // Se aplica automáticamente si qty >= 5. El cupón puede combinarse sumando ambos descuentos.
 const volumeDiscountPct = calcVolumeDiscount(qty.value)
 const volumeDiscountAmount = volumeDiscountPct > 0 ? subtotal * (volumeDiscountPct / 100) : 0

 // Apply coupon discount on top of volume discount
 const couponDiscountAmount = activeCoupon.value ? (subtotal * activeCoupon.value.discount) : 0
 const totalDiscountAmount = Math.round(volumeDiscountAmount + couponDiscountAmount)
 const finalSubtotal = Math.max(Math.round(subtotal - totalDiscountAmount), 0)
 const ivaRate = (cfg.margin.iva || 19) / 100
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
 discount: totalDiscountAmount,
 subtotal: finalSubtotal,
 iva,
 total,
 }
}


const handleModelLoaded = (data: any) => {
 const idx = activeModelIdx.value
 if (models.value[idx]) {
 models.value[idx].volume = data.volume
 models.value[idx].totalArea = data.totalArea || 0
 models.value[idx].dimensions = { x: data.dimensions.x, y: data.dimensions.y, z: data.dimensions.z }
 models.value[idx].currentScale = data.scale || 1.0
 models.value[idx].supportArea = data.supportArea || 0
 models.value[idx].hasModel = true
 }
 // BUG 2 FIX: siempre recalcular al cargar modelo (FDM usa fallback si no tiene slicing)
 calculatePrice()
}

const handleError = (msg: any) => {
 notify(msg, "error")
}

onUnmounted(() => {
 if (_timerNotif.value) clearTimeout(_timerNotif.value)
 if (resizeCleanup) resizeCleanup()
})

</script>

<template>
 <div class="min-h-screen flex flex-col bg-[#f8fafc] dark:bg-[#0a0f14] text-[#ffffff] dark:text-white transition-colors duration-500 overflow-x-hidden selection:bg-[#08872b]/20">
 <AppNavbar active-tab="quote" subtext="Centro de Precisión Industrial" />

 <main class="flex-1 flex flex-col lg:flex-row relative">
 <div class="absolute inset-0 technical-grid opacity-20 dark:opacity-10 pointer-events-none"></div>
 
 <section class="flex-1 relative flex flex-col items-center justify-start pt-12 lg:pt-20 px-6 pb-16 lg:px-16">
 <div class="w-full max-w-5xl text-center mb-16 relative z-10">
 <div class="relative inline-block">
 <!-- Glow background -->
 <div class="absolute -inset-20 bg-gradient-to-r from-primary/5 via-emerald-500/5 to-primary/5 rounded-[60px] blur-[100px] animate-pulse pointer-events-none"></div>
 <div class="flex items-center justify-center gap-4 relative">
 <h1 class="text-5xl md:text-7xl lg:text-8xl font-black text-slate-900 dark:text-white tracking-tight uppercase leading-none mb-6 animate-fade-in">
 COTIZA. <br class="md:hidden" />
 <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-500 via-primary to-emerald-300 dark:from-emerald-400 dark:via-primary dark:to-emerald-300 drop-shadow-[0_0_15px_rgba(16,185,129,0.3)] italic">PRODUCE.</span>
 </h1>
 <button class="lg:hidden w-14 h-14 bg-[#151a22] dark:bg-[#151a22]/5 rounded-[24px] border border-[#21262d] dark:border-[#21262d] flex items-center justify-center hover:bg-[#08872b]/10 hover:border-primary/30 transition-all shrink-0 self-start mt-2 opacity-40 hover:opacity-100" @click="showSidebar = !showSidebar">
 <svg class="w-6 h-6 text-[#a4aea6]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
 </button>
 </div>
 </div>
 </div>

 <div class="flex flex-wrap gap-3 mb-4">
 <button v-for="(m, mi) in models" :key="m.id" :class="activeModelIdx === mi ? 'bg-[#08872b] text-white -primary/20 scale-105' : 'bg-[#151a22] dark:bg-[#151a22]/5 text-[#a4aea6] border border-[#21262d] dark:border-[#21262d] hover:border-primary/30'" class="px-6 py-3 rounded-[24px] text-[8px] font-black uppercase tracking-widest transition-all flex items-center gap-2 group/tab" @click="activeModelIdx = mi">
 <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
 {{ m.name || 'Modelo ' + (mi + 1) }}
 <span v-if="m.hasModel" class="w-1.5 h-1.5 bg-emerald-400 rounded-[60px]"></span>
 <span v-if="models.length > 1" class="ml-2 opacity-50 hover:opacity-100 hover:text-red-400 transition-all p-1 rounded-full" @click.stop="removeModel(mi)">
 <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"/></svg>
 </span>
 </button>
 <button class="px-5 py-3 bg-[#151a22]/5 border border-dashed border-[#21262d] dark:border-[#21262d] rounded-[24px] text-[8px] font-black text-[#c3c4c5] uppercase tracking-widest hover:border-primary/40 hover:text-[#8dd6ff] transition-all" @click="models.push({ id: models.length, file: null, volume: 0, totalArea: 0, dimensions: { x: 0, y: 0, z: 0 }, infill: 15, layerHeight: 0.2, supportArea: 0, currentScale: 1.0, hasModel: false, hasSlicing: false, weight: 0, duration: 0, name: 'Modelo ' + (models.length + 1), curaFactors: { resolution: 1, infill_density: 1, shell_weight_g: 0, internal_weight_g: 0, support_weight_g: 0, filament_length_m: 0, prep_time_h: 0.233, print_time_h: 0 } }); activeModelIdx = models.length - 1">
 + Añadir Modelo
 </button>
 </div>
 <!-- Loading / Message State -->
 <div v-if="loadingQuote" class="w-full max-w-5xl mb-4 flex items-center justify-center gap-3 py-4 bg-[#08872b]/5 rounded-[24px] border border-primary/20">
 <div class="w-5 h-5 border-2 border-primary border-t-transparent rounded-[60px] animate-spin"></div>
 <span class="text-[10px] font-black text-[#8dd6ff] uppercase tracking-[0.3em]">{{ quoteMessage }}</span>
 </div>
 <div v-else-if="quoteMessage && !models.some(m => m.hasModel)" class="w-full max-w-5xl mb-4 flex items-center justify-center gap-3 py-4 bg-amber-500/10 rounded-[24px] border border-amber-500/20 animate-in fade-in slide-in-from-top-2 duration-500">
 <svg class="w-5 h-5 text-amber-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
 <span class="text-[10px] font-black text-amber-400 uppercase tracking-[0.2em]">{{ quoteMessage }}</span>
 </div>
 <div class="w-full max-w-5xl aspect-square md:aspect-[16/9] bg-[#151a22] dark:bg-[#151a22]/5 rounded-[4rem] border border-[#21262d] dark:border-[#21262d] relative overflow-hidden group mb-16">
 <StlViewer :key="'viewer-' + activeModelIdx" :file="(models[activeModelIdx]?.file as any)" @model-loaded="handleModelLoaded" @model-transformed="handleModelLoaded" @file-ready="handleFileReady" @error="handleError" />
 <transition enter-active-class="transition duration-300" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition duration-300" leave-from-class="opacity-100" leave-to-class="opacity-0">
 <div v-if="isSlicing" class="absolute inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
 <div class="bg-[#151a22] border border-primary/40 p-8 rounded-[24px] flex flex-col items-center gap-6">
 <div class="w-16 h-16 border-4 border-primary border-t-transparent rounded-[60px] animate-spin"></div>
 <p class="text-[#8dd6ff] font-black uppercase tracking-[0.5em] text-xs">Procesando modelo {{ activeModelIdx + 1 }}</p>
 </div>
 </div>
 </transition>
 </div>

 <div class="w-full max-w-5xl grid grid-cols-1 md:grid-cols-4 gap-6 mb-16 reveal">
 <div v-for="mat in materialGuide" :key="mat.name" class="bg-[#151a22] dark:bg-[#151a22]/5 p-8 rounded-[2.5rem] border border-[#21262d] dark:border-[#21262d] hover:border-primary/30 transition-all group">
 <div class="w-10 h-10 bg-[#08872b]/10 rounded-[24px] flex items-center justify-center mb-4 group-hover:bg-[#08872b]/20 transition-all" :innerHTML="sanitizeSVG(mat.icon)"></div>
 <h2 class="text-[#ffffff] dark:text-white uppercase mb-3">{{ mat.name }}</h2>
 <p class="text-[9px] text-[#a4aea6] dark:text-[#c3c4c5] font-bold leading-relaxed uppercase mb-4">{{ mat.desc }}</p>
 <div class="pt-4 border-t border-[#21262d] dark:border-[#21262d]">
 <p class="text-micro text-[#8dd6ff]">Ideal para:</p>
 <p class="text-[9px] font-bold text-[#f0f6fc] dark:text-white uppercase mt-1">{{ mat.bestFor }}</p>
 </div>
 </div>
 </div>
 </section>

 <aside v-show="showSidebar || isDesktop" :class="['w-full lg:w-[540px] bg-[#f8fafc] dark:bg-[#0a0f14] flex flex-col border-l border-[#21262d] dark:border-[#21262d] z-10', showSidebar || isDesktop ? '' : '']">
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
 :previous-total="previousTotal"
 :is-submitting="isSubmitting"
 :notification="notification"
 @close="showModal = false; modalStep = 1"
 @submit="handleModalSubmit"
 />
 <AppFooter />
 </div>
</template>

<style scoped>
.technical-grid {
 background-size: 50px 50px;
 background-image: linear-gradient(to right, rgba(30, 58, 52, 0.08) 1px, transparent 1px), linear-gradient(to bottom, rgba(30, 58, 52, 0.08) 1px, transparent 1px);
}

/* Count-up animation for price */
.price-count {
 display: inline-block;
 transition: all 0.5s cubic-bezier(0.22, 1, 0.36, 1);
}
.price-count.bump {
 animation: priceBump 0.4s cubic-bezier(0.22, 1, 0.36, 1);
}
@keyframes priceBump {
 0% { transform: scale(1); }
 50% { transform: scale(1.08); }
 100% { transform: scale(1); }
}

/* Sidebar mobile transition */
@media (max-width: 1023px) {
 .quote-sidebar-enter { max-height: 0; opacity: 0; }
 .quote-sidebar-enter-active { transition: all 0.4s ease-out; }
 .quote-sidebar-enter-to { max-height: 2000px; opacity: 1; }
}


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
