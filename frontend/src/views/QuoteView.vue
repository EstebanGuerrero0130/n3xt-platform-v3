<script setup>
import { ref, watch, onMounted } from 'vue'
import StlViewer from '../components/StlViewer.vue'
import { api } from '../services/api'
import AppNavbar from '../components/AppNavbar.vue'
import AppFooter from '../components/AppFooter.vue'

// --- N3XT CORE DATA ---
const appData = ref({
    infra: { luz_hr: 926, depr_hr: 400, mant_hr: 700 },
    prep: { mano_obra_hr: 1000 },
    oper: { transporte: 50, ganancia: 50, marketing: 50, fallos: 30 },
    margin: { iva: 19 }
})
const companyLogo = ref(null)
const isDark = ref(localStorage.getItem('n3xt_theme') !== 'light')

const toggleDarkMode = () => {
  isDark.value = !isDark.value
  const theme = isDark.value ? 'dark' : 'light'
  localStorage.setItem('n3xt_theme', theme)
  if (isDark.value) document.documentElement.classList.add('dark')
  else document.documentElement.classList.remove('dark')
}

onMounted(() => {
    if (isDark.value) document.documentElement.classList.add('dark')
    else document.documentElement.classList.remove('dark')
})

const materials = ref([])
const utilities = ref([])
const selectedExtras = ref([])
const autoExtras = ref([])

const fetchMaterials = async () => {
  try {
    const data = await api.get('/materials')
    materials.value = data.filter(m => m.type === 'material')
    utilities.value = data.filter(m => m.type !== 'material')
    
    if (materials.value.length > 0 && !selectedMaterial.value) {
      selectedMaterial.value = materials.value[0].id
    }
  } catch (err) {
    console.error('Error fetching materials:', err)
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
      if (data.company_logo) companyLogo.value = data.company_logo
      
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
    console.error('Error fetching settings:', err)
  }
}

onMounted(async () => {
  document.title = 'Cotizador 3D Automático | N3XT 3D Systems'
  await fetchSettings()
  await fetchMaterials()
  calculatePrice()
})

const selectedTechnology = ref('FDM')
const selectedMaterial = ref('')
const volume = ref(0)
const totalArea = ref(0)
const supportArea = ref(0)
const dimensions = ref({ x: 0, y: 0, z: 0 })
const qty = ref(1)
const infill = ref(15)
const layerHeight = ref(0.2)
const currentScale = ref(1.0)
const hasModel = ref(false)
const uploadedFile = ref(null)

// --- CURAENGINE INTEGRATION (MANUAL SINCRO) ---
const isSlicing = ref(false)
const hasSlicingData = ref(false)
const curaFactors = ref({ 
    resolution: 1, 
    infill_density: 1, 
    shell_weight_g: 0, 
    internal_weight_g: 0,
    support_weight_g: 0,
    filament_length_m: 0,
    prep_time_h: 0.233, // 14 mins default (N3XT Guard)
    print_time_h: 0
})

const runCuraEngineAnalysis = async () => {
  if (!uploadedFile.value) {
      notify("Sube un modelo 3D primero", "error")
      return
  }
  
  isSlicing.value = true
  hasSlicingData.value = false
  
  try {
    const mat = materials.value.find(m => m.id === selectedMaterial.value)
    const formData = new FormData()
    formData.append('file', uploadedFile.value)
    formData.append('infill', infill.value)
    formData.append('layer_height', layerHeight.value)
    formData.append('total_area', totalArea.value)
    formData.append('volume_mm3', volume.value)
    formData.append('support_area', supportArea.value)
    if (mat) {
        formData.append('density', mat.density)
        formData.append('material_id', mat.id)
    }
    
    const res = await api.post('/process-stl', formData)
    const factors = res.data || res.factors || res;
    
    if (factors && (factors.shell_weight_g >= 0)) {
        curaFactors.value = factors
        hasSlicingData.value = true
        notify("Sincronización Industrial Exitosa", "success")
        calculatePrice()
    } else {
        throw new Error("El motor devolvió datos incompletos.")
    }
  } catch (err) {
    console.error("CuraEngine Error:", err)
    const statusInfo = err.message.includes('(') ? ` [${err.message.split('(')[1].split(')')[0]}]` : '';
    notify(`Error de Motor${statusInfo}: ${err.message.split(' (')[0] || 'Fallo de conexión'}`, "error")
  } finally {
    isSlicing.value = false
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
    icon: `<svg class="w-5 h-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>`
  },
  { 
    name: 'PETG', 
    desc: 'Resistente al impacto y la quimica.', 
    bestFor: 'Piezas mecanicas y exteriores.',
    icon: `<svg class="w-5 h-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>`
  },
  { 
    name: 'ABS', 
    desc: 'Alta resistencia termica y mecanica.', 
    bestFor: 'Carcasas y uso automotriz.',
    icon: `<svg class="w-5 h-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>`
  },
  { 
    name: 'RESINA', 
    desc: 'Resolucion extrema y superficie lisa.', 
    bestFor: 'Joyeria, dental y miniaturas.',
    icon: `<svg class="w-5 h-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.628.209a2 2 0 01-1.564 0l-.628-.209a6 6 0 00-3.86-.517L3.081 14.88a2 2 0 00-1.022.547l-.21.21a2 2 0 00.707 3.414l9.9 1.98a2 2 0 00.707 0l9.9-1.98a2 2 0 00.707-3.414l-.21-.21zM6.25 8.044a2 2 0 01.97-1.712l4-2.286a2 2 0 011.56 0l4 2.286a2 2 0 01.97 1.712v4.572a2 2 0 01-.97 1.712l-4 2.286a2 2 0 01-1.56 0l-4-2.286a2 2 0 01-.97-1.712V8.044z"/></svg>`
  }
]

const showModal = ref(false)
const isSubmitting = ref(false)
const showMobileMenu = ref(false)
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
const activeCoupon = ref(null)
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

const notification = ref({ show: false, message: '', type: 'info' })
const notify = (msg, type = 'info') => {
    notification.value = { show: true, message: msg, type }
    setTimeout(() => { notification.value.show = false }, 5000)
}

const captchaChallenge = ref({ a: 0, b: 0, result: 0 })
const captchaAnswer = ref('')
const generateCaptcha = () => {
    captchaChallenge.value.a = Math.floor(Math.random() * 10) + 1
    captchaChallenge.value.b = Math.floor(Math.random() * 10) + 1
    captchaChallenge.value.result = captchaChallenge.value.a + captchaChallenge.value.b
    captchaAnswer.value = ''
}

const openModal = () => {
  generateCaptcha()
  showModal.value = true
}

const handleFileReady = (file) => {
  uploadedFile.value = file
  hasSlicingData.value = false 
}

const submitOrder = async () => {
  if (honeypot.value) return;

  const now = Date.now();
  if (now - lastSubmitTime.value < 10000) {
    notify("Por favor espera unos segundos antes de enviar otra solicitud.", "warning");
    return;
  }

  if (parseInt(captchaAnswer.value) !== captchaChallenge.value.result) {
    notify("Error de Verificación", "error")
    generateCaptcha()
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
    formData.append('shipping_address', `${customerForm.value.address} | ${customerForm.value.city}`)
    formData.append('comments', `USO: ${customerForm.value.use} | NOTAS: ${customerForm.value.comments}`)
    
    // Datos Técnicos
    formData.append('technology', selectedTechnology.value)
    formData.append('material_id', selectedMaterial.value)
    formData.append('material_name', mat ? mat.name : '')
    formData.append('volume_mm3', volume.value)
    formData.append('estimated_weight_g', breakdown.value.weight)
    formData.append('estimated_duration_h', breakdown.value.duration)
    formData.append('qty', qty.value)
    formData.append('infill', infill.value)
    formData.append('total_price', breakdown.value.total)
    
    if (uploadedFile.value) {
      formData.append('file', uploadedFile.value)
    }

    // Extras
    if (selectedExtras.value.length > 0) {
      selectedExtras.value.forEach((extra, index) => {
        formData.append(`extra_items[${index}][material_id]`, extra.id)
        formData.append(`extra_items[${index}][qty]`, extra.qty)
      })
    }
    
    await api.post('/orders', formData)
    
    lastSubmitTime.value = Date.now()
    showModal.value = false
    notify("¡Protocolo de Solicitud completado con éxito!", "success")
    
    // Reset form
    customerForm.value = { name: '', email: '', phone: '', comments: '' }
  } catch (err) {
    notify(err.response?.data?.message || err.message, "error")
  } finally {
    isSubmitting.value = false
  }
}

const toggleExtra = (id) => {
    const idx = selectedExtras.value.findIndex(e => e.id === id)
    if (idx > -1) selectedExtras.value.splice(idx, 1)
    else selectedExtras.value.push({ id, qty: 1 })
    calculatePrice()
}

watch([selectedTechnology, selectedMaterial, qty, infill, layerHeight, volume], () => {
  const mat = materials.value.find(m => m.id === selectedMaterial.value)
  if (mat && mat.category !== selectedTechnology.value) {
    const firstAvailable = materials.value.find(m => m.category === selectedTechnology.value)
    if (firstAvailable) selectedMaterial.value = firstAvailable.id
  }
  calculatePrice()
}, { deep: true })

const calculatePrice = () => {
  const mat = materials.value.find(m => m.id === selectedMaterial.value)
  if (!mat || !appData.value) return
  
  const cfg = appData.value
  let weight = 0
  let duration = 0
  
  if (selectedTechnology.value === 'FDM') {
    if (hasSlicingData.value) {
        weight = (Number(curaFactors.value.shell_weight_g) || 0) 
               + (Number(curaFactors.value.internal_weight_g) || 0) 
               + (Number(curaFactors.value.support_weight_g) || 0) 
               + (Number(curaFactors.value.purge_weight_g) || 3.0)
        duration = (Number(curaFactors.value.prep_time_h) || 0) 
                 + (Number(curaFactors.value.print_time_h) || 0)
    } else {
        breakdown.value = { ...breakdown.value, weight: 0, duration: 0, total: 0 }
        return
    }
  } else {
    // SLA: volume viene en mm³, convertir a cm³
    const volCm3 = (volume.value || 0) / 1000 
    const density = Number(mat.density) || 1.1
    weight = volCm3 * density * 1.1 
    // Duración basada en la altura del modelo (eje Z) en horas
    const heightMm = dimensions.value.z || dimensions.value.y || 0
    duration = Math.max(0.5, heightMm / 25) // ~25mm/h velocidad típica SLA
  }

  const totalWeight = weight * qty.value
  const totalDuration = duration * qty.value
  
  // Costo de material
  const costPerG = (Number(mat.cost_per_kg) || 0) / 1000
  const matCost = totalWeight * costPerG
  
  // Infraestructura (usando load_factor y prep_time_pct del admin)
  const loadFactor = cfg.infra.load_factor || 0.4
  const prepPct = (cfg.prep.prep_time_pct || 10) / 100
  
  const luz = totalDuration * loadFactor * cfg.infra.luz_hr
  const labor = (totalDuration * prepPct) * cfg.prep.mano_obra_hr
  const depr = totalDuration * cfg.infra.depr_hr
  const mant = totalDuration * cfg.infra.mant_hr
  const infraCost = luz + labor + depr + mant
  
  // Extras/Consumibles
  let utilityCost = 0
  autoExtras.value = []
  if (selectedTechnology.value === 'SLA') {
    const alcohol = utilities.value.find(u => u.id === 'Alco_ML_05')
    if (alcohol) {
      utilityCost += (Number(alcohol.cost_per_kg) || 0) * 50 * qty.value
      autoExtras.value.push({ id: alcohol.id, qty: 50 * qty.value, name: alcohol.name })
    }
    const curado = utilities.value.find(u => u.id === 'Cicl_Serv_06')
    if (curado) {
      utilityCost += (Number(curado.cost_per_kg) || 0) * qty.value
      autoExtras.value.push({ id: curado.id, qty: qty.value, name: curado.name })
    }
  }
  
  selectedExtras.value.forEach(item => {
    const extra = utilities.value.find(u => u.id === item.id)
    if (extra) {
        utilityCost += ((Number(extra.cost_per_kg) || 0) * item.qty)
    }
  })
  
  const totalBaseCost = matCost + infraCost + utilityCost
  const baseUnitCost = qty.value > 0 ? totalBaseCost / qty.value : 0
  
  // Márgenes operativos (porcentajes del admin)
  const m_transporte = baseUnitCost * (cfg.oper.transporte / 100)
  const m_ganancia = baseUnitCost * (cfg.oper.ganancia / 100)
  const m_marketing = baseUnitCost * (cfg.oper.marketing / 100)
  const m_fallos = baseUnitCost * (cfg.oper.fallos / 100)
  const unitPrice = baseUnitCost + m_transporte + m_ganancia + m_marketing + m_fallos
  const subtotal = unitPrice * qty.value
  
  const discountAmount = activeCoupon.value ? (subtotal * activeCoupon.value.discount) : 0
  const finalSubtotal = subtotal - discountAmount
  
  const ivaRate = (cfg.margin.iva || 19) / 100
  const iva = finalSubtotal * ivaRate
  const total = finalSubtotal + iva
  
  breakdown.value = {
    weight, duration, matCost, infraCost, laborCost: labor, utilityCost, 
    marginCost: (m_transporte + m_ganancia + m_marketing + m_fallos) * qty.value, 
    discount: discountAmount,
    subtotal: finalSubtotal, iva, total: Math.round(total)
  }
}

const handleModelLoaded = (data) => {
  volume.value = data.volume
  totalArea.value = data.totalArea || 0
  dimensions.value = { x: data.dimensions.x, y: data.dimensions.y, z: data.dimensions.z }
  currentScale.value = data.scale || 1.0
  supportArea.value = data.supportArea || 0
  hasModel.value = true
  if (selectedTechnology.value !== 'FDM') calculatePrice()
}

const handleError = (msg) => {
  notify(msg, "error")
}

const formatTime = (h) => {
    if (!h || h < 0) return "0m";
    const hours = Math.floor(h);
    const minutes = Math.round((h - hours) * 60);
    if (hours === 0) return `${minutes}m`;
    return `${hours}h ${minutes}m`;
}


</script>

<template>
  <div :class="{'dark': isDark}" class="min-h-screen flex flex-col bg-[#f8fafc] dark:bg-[#0a0f14] text-gray-900 dark:text-white transition-colors duration-500 overflow-x-hidden selection:bg-primary/20">
    <AppNavbar activeTab="quote" subtext="Centro de Precisión Industrial" />

    <main class="flex-1 flex flex-col lg:flex-row relative">
      <div class="absolute inset-0 technical-grid opacity-20 dark:opacity-10 pointer-events-none"></div>
      
      <section class="flex-1 relative flex flex-col items-center justify-center p-6 lg:p-16">
        <div class="w-full max-w-5xl text-center mb-16 relative z-10">
            <h1 class="text-5xl md:text-[8rem] font-black text-gray-900 dark:text-white tracking-tighter uppercase leading-[0.8] mb-8">COTIZA.<br/><span class="text-transparent bg-clip-text bg-gradient-to-r from-primary to-emerald-500">PRODUCE.</span></h1>
        </div>

        <div class="w-full max-w-5xl aspect-square md:aspect-[16/9] bg-white dark:bg-white/5 rounded-[4rem] border border-gray-100 dark:border-white/5 shadow-2xl relative overflow-hidden group mb-16">
          <StlViewer @model-loaded="handleModelLoaded" @model-transformed="handleModelLoaded" @file-ready="handleFileReady" @error="handleError" />
          <transition enter-active-class="transition duration-300" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition duration-300" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="isSlicing" class="absolute inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
                <div class="bg-gray-900 border border-primary/40 p-8 rounded-3xl shadow-2xl flex flex-col items-center gap-6">
                    <div class="w-16 h-16 border-4 border-primary border-t-transparent rounded-full animate-spin"></div>
                    <p class="text-primary font-black uppercase tracking-[0.5em] text-xs">Procesando modelo</p>
                </div>
            </div>
          </transition>
        </div>

        <div class="w-full max-w-5xl grid grid-cols-1 md:grid-cols-4 gap-6 mb-16">
            <div v-for="mat in materialGuide" :key="mat.name" class="bg-white dark:bg-white/5 p-8 rounded-[2.5rem] border border-gray-100 dark:border-white/5 hover:border-primary/30 transition-all group">
                <div class="w-10 h-10 bg-primary/10 rounded-2xl flex items-center justify-center mb-4 group-hover:bg-primary/20 transition-all" v-html="mat.icon"></div>
                <h4 class="text-xs font-black text-gray-900 dark:text-white uppercase tracking-widest mb-3">{{ mat.name }}</h4>
                <p class="text-[9px] text-gray-500 dark:text-gray-400 font-bold leading-relaxed uppercase mb-4">{{ mat.desc }}</p>
                <div class="pt-4 border-t border-gray-100 dark:border-white/5">
                    <p class="text-[8px] font-black text-primary uppercase tracking-widest">Ideal para:</p>
                    <p class="text-[9px] font-bold text-gray-800 dark:text-white uppercase mt-1">{{ mat.bestFor }}</p>
                </div>
            </div>
        </div>
      </section>

      <aside class="w-full lg:w-[540px] bg-white dark:bg-black flex flex-col border-l border-gray-100 dark:border-white/5 shadow-2xl z-10 overflow-y-auto">
        <div class="p-8 lg:p-10 space-y-8">

          <!-- HEADER -->
          <div class="border-b border-gray-100 dark:border-white/5 pb-6">
            <p class="text-[9px] font-black text-primary uppercase tracking-[0.5em] mb-1">Motor OrcaEngine</p>
            <p class="text-[8px] font-black text-gray-400 uppercase tracking-[0.2em]">Configuracion de Fabricacion</p>
          </div>

          <!-- TECNOLOGIA -->
          <div>
            <label class="text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-3 block">Tecnologia</label>
            <div class="flex bg-gray-100 dark:bg-white/5 p-1.5 rounded-2xl">
              <button v-for="t in ['FDM','SLA']" :key="t" @click="selectedTechnology = t" :class="selectedTechnology === t ? 'bg-white dark:bg-primary text-gray-900 dark:text-white shadow-lg' : 'text-gray-400 hover:text-gray-600'" class="flex-1 py-3.5 text-[10px] font-black uppercase tracking-widest rounded-xl transition-all">{{ t }}</button>
            </div>
          </div>

          <!-- MATERIAL -->
          <div>
            <label class="text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-3 block">Material</label>
            <select v-model="selectedMaterial" class="w-full bg-gray-100 dark:bg-[#0f172a] border border-transparent dark:border-white/5 rounded-2xl px-6 py-4 text-[11px] font-black text-gray-900 dark:text-white uppercase tracking-widest outline-none focus:ring-2 focus:ring-primary/20 appearance-none cursor-pointer transition-all">
              <option v-for="mat in materials.filter(m => m.category === selectedTechnology)" :key="mat.id" :value="mat.id" class="bg-[#0f172a] text-white">{{ mat.name }} — ${{ mat.cost_per_kg }}/kg</option>
            </select>
          </div>

          <!-- PARAMETROS FDM -->
          <div v-if="selectedTechnology === 'FDM'" class="grid grid-cols-2 gap-4">
            <div>
              <label class="text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-2 block">Relleno %</label>
              <input type="number" v-model.number="infill" min="5" max="100" step="5" class="w-full bg-gray-100 dark:bg-white/5 border-none rounded-2xl px-6 py-4 text-sm font-black text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary/20">
            </div>
            <div>
              <label class="text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-2 block">Capa (mm)</label>
              <select v-model.number="layerHeight" class="w-full bg-gray-100 dark:bg-[#0f172a] border border-transparent dark:border-white/5 rounded-2xl px-6 py-4 text-sm font-black text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary/20 appearance-none cursor-pointer transition-all">
                <option :value="0.12" class="bg-[#0f172a] text-white">0.12 Ultra</option>
                <option :value="0.16" class="bg-[#0f172a] text-white">0.16 Alta</option>
                <option :value="0.2" class="bg-[#0f172a] text-white">0.20 Estandar</option>
                <option :value="0.28" class="bg-[#0f172a] text-white">0.28 Rapida</option>
              </select>
            </div>
          </div>

          <!-- CANTIDAD -->
          <div>
            <label class="text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-3 block">Cantidad</label>
            <div class="flex items-center bg-gray-100 dark:bg-white/5 rounded-2xl overflow-hidden">
              <button @click="qty = Math.max(1, qty - 1)" class="px-6 py-4 text-gray-400 hover:text-primary font-black text-lg transition-colors">-</button>
              <input type="number" v-model.number="qty" min="1" class="flex-1 bg-transparent text-center text-lg font-black text-gray-900 dark:text-white outline-none border-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
              <button @click="qty++" class="px-6 py-4 text-gray-400 hover:text-primary font-black text-lg transition-colors">+</button>
            </div>
          </div>

          <!-- EXTRAS -->
          <div v-if="utilities.length > 0">
            <label class="text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-3 block">Extras / Consumibles</label>
            <div class="space-y-2">
              <button v-for="u in utilities" :key="u.id" @click="toggleExtra(u.id)" :class="selectedExtras.find(e => e.id === u.id) ? 'bg-primary/10 border-primary/30 text-primary' : 'bg-gray-50 dark:bg-white/5 border-transparent text-gray-500'" class="w-full flex items-center justify-between px-5 py-3.5 rounded-2xl border-2 text-[10px] font-black uppercase tracking-widest transition-all hover:border-primary/20">
                <span>{{ u.name }}</span>
                <span class="text-[9px]">${{ u.cost_per_kg }}/u</span>
              </button>
            </div>
          </div>

          <!-- CUPON -->
          <div>
            <label class="text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-3 block">Cupon de Descuento</label>
            <div class="flex gap-2">
              <input type="text" v-model="couponCode" placeholder="Codigo..." class="flex-1 bg-gray-100 dark:bg-[#0f172a] border border-transparent dark:border-white/5 rounded-2xl px-6 py-4 text-[11px] font-black text-gray-900 dark:text-white uppercase outline-none focus:ring-2 focus:ring-primary/20 transition-all">
              <button @click="applyCoupon" class="px-6 py-4 bg-gray-900 dark:bg-white/10 text-white rounded-2xl text-[9px] font-black uppercase tracking-widest hover:bg-primary transition-all">Aplicar</button>
            </div>
            <p v-if="activeCoupon" class="text-[9px] font-black text-primary uppercase tracking-widest mt-2 ml-2">{{ activeCoupon.label }} (-{{ (activeCoupon.discount * 100) }}%)</p>
          </div>

          <!-- BOTON CALCULAR (FDM) -->
          <button v-if="selectedTechnology === 'FDM' && !hasSlicingData" @click="runCuraEngineAnalysis" :disabled="isSlicing || !hasModel" :class="!hasModel ? 'opacity-40 cursor-not-allowed' : 'hover:bg-emerald-600 active:scale-95'" class="w-full py-5 bg-gray-900 dark:bg-white/10 text-white rounded-[2rem] font-black text-[10px] uppercase tracking-[0.3em] transition-all flex items-center justify-center gap-3">
            <span v-if="isSlicing" class="w-5 h-5 border-3 border-white/30 border-t-white rounded-full animate-spin"></span>
            <span>{{ isSlicing ? 'Procesando...' : 'Calcular Cotizacion' }}</span>
          </button>

          <!-- RESULTADO -->
          <div v-if="hasSlicingData || selectedTechnology === 'SLA'" class="bg-gray-900 dark:bg-[#0f172a] rounded-[3rem] p-8 text-white relative overflow-hidden border border-white/5">
            <div class="absolute -top-10 -right-10 w-48 h-48 bg-primary/20 rounded-full blur-[80px]"></div>
            
            <div class="flex justify-between items-center mb-6 relative z-10">
              <p class="text-[9px] font-black text-primary uppercase tracking-[0.5em]">Total Estimado</p>
              <span class="bg-primary text-white text-[8px] font-black px-4 py-1.5 rounded-full uppercase tracking-widest">Listo</span>
            </div>

            <h3 class="text-6xl lg:text-7xl font-black tracking-tighter italic text-white relative z-10 mb-8">${{ Math.round(breakdown.total).toLocaleString('es-CO') }}</h3>

            <!-- Desglose -->
            <div class="space-y-3 relative z-10">
              <div class="flex justify-between items-center bg-white/5 px-4 py-3 rounded-xl">
                <span class="text-[9px] font-black text-white/40 uppercase tracking-widest">Masa unitaria</span>
                <span class="text-sm font-black text-white">{{ breakdown.weight.toFixed(2) }}g</span>
              </div>
              <div class="flex justify-between items-center bg-white/5 px-4 py-3 rounded-xl">
                <span class="text-[9px] font-black text-white/40 uppercase tracking-widest">Tiempo estimado</span>
                <span class="text-sm font-black text-white">{{ formatTime(breakdown.duration) }}</span>
              </div>
              <div class="flex justify-between items-center px-4 py-2">
                <span class="text-[9px] font-black text-white/30 uppercase tracking-widest">Material</span>
                <span class="text-xs font-black text-white/60">${{ Math.round(breakdown.matCost).toLocaleString() }}</span>
              </div>
              <div class="flex justify-between items-center px-4 py-2">
                <span class="text-[9px] font-black text-white/30 uppercase tracking-widest">Infraestructura</span>
                <span class="text-xs font-black text-white/60">${{ Math.round(breakdown.infraCost).toLocaleString() }}</span>
              </div>
              <div class="flex justify-between items-center px-4 py-2">
                <span class="text-[9px] font-black text-white/30 uppercase tracking-widest">Extras</span>
                <span class="text-xs font-black text-white/60">${{ Math.round(breakdown.utilityCost).toLocaleString() }}</span>
              </div>
              <div class="flex justify-between items-center px-4 py-2">
                <span class="text-[9px] font-black text-white/30 uppercase tracking-widest">Margenes Operativos</span>
                <span class="text-xs font-black text-white/60">${{ Math.round(breakdown.marginCost).toLocaleString() }}</span>
              </div>
              <div v-if="breakdown.discount > 0" class="flex justify-between items-center px-4 py-2">
                <span class="text-[9px] font-black text-emerald-400 uppercase tracking-widest">Descuento</span>
                <span class="text-xs font-black text-emerald-400">-${{ Math.round(breakdown.discount).toLocaleString() }}</span>
              </div>
              <div class="border-t border-white/10 pt-3 mt-2">
                <div class="flex justify-between items-center px-4 py-2">
                  <span class="text-[9px] font-black text-white/50 uppercase tracking-widest">Subtotal</span>
                  <span class="text-sm font-black text-white">${{ Math.round(breakdown.subtotal).toLocaleString() }}</span>
                </div>
                <div class="flex justify-between items-center px-4 py-2">
                  <span class="text-[9px] font-black text-white/30 uppercase tracking-widest">IVA (19%)</span>
                  <span class="text-xs font-black text-white/60">${{ Math.round(breakdown.iva).toLocaleString() }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- DIMENSIONES -->
          <div v-if="hasModel" class="grid grid-cols-3 gap-3">
            <div v-for="(val, axis) in dimensions" :key="axis" class="bg-gray-50 dark:bg-white/5 p-4 rounded-2xl text-center border border-gray-100 dark:border-white/5">
              <p class="text-[8px] font-black text-gray-400 uppercase tracking-widest mb-1">{{ axis.toUpperCase() }}</p>
              <p class="text-sm font-black text-gray-900 dark:text-white">{{ val.toFixed(1) }}mm</p>
            </div>
          </div>

          <!-- CTA -->
          <button @click="openModal" class="w-full bg-primary hover:bg-emerald-600 text-white font-black py-7 rounded-[2.5rem] shadow-2xl shadow-primary/20 uppercase tracking-[0.3em] text-[11px] transition-all active:scale-[0.98]">Solicitar Cotizacion</button>
        </div>
      </aside>
    </main>

    <!-- MODAL DE CONTACTO INDUSTRIAL -->
    <transition enter-active-class="transition duration-300 ease-out" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100" leave-active-class="transition duration-200 ease-in" leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
      <div v-if="showModal" class="fixed inset-0 z-[100] flex items-center justify-center p-6 bg-black/80 backdrop-blur-md">
        <div class="bg-white dark:bg-[#0a0f14] w-full max-w-lg rounded-[3.5rem] shadow-2xl border border-gray-100 dark:border-white/5 overflow-hidden flex flex-col relative">
          
          <!-- Banner Superior -->
          <div class="bg-primary p-12 text-white relative">
            <button @click="showModal = false" class="absolute top-8 right-8 w-10 h-10 flex items-center justify-center bg-white/20 rounded-full hover:bg-white hover:text-primary transition-all">✕</button>
            <p class="text-[10px] font-black uppercase tracking-[0.5em] mb-2 text-white/80">N3XT Protocol</p>
            <h2 class="text-4xl font-black tracking-tighter uppercase leading-none">Finalizar<br/>Solicitud</h2>
          </div>

          <div class="p-8 lg:p-10 space-y-6 overflow-y-auto max-h-[65vh]">
            <!-- SECCION: DATOS CLIENTE -->
            <div class="space-y-4">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="group">
                  <label class="text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-2 block ml-4">Nombre Completo</label>
                  <input type="text" v-model="customerForm.name" class="w-full bg-gray-50 dark:bg-[#0f172a] border border-transparent dark:border-white/5 rounded-2xl px-6 py-4 text-sm font-black text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary/20 transition-all">
                </div>
                <div class="group">
                  <label class="text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-2 block ml-4">Empresa / Razon Social</label>
                  <input type="text" v-model="customerForm.company" class="w-full bg-gray-50 dark:bg-[#0f172a] border border-transparent dark:border-white/5 rounded-2xl px-6 py-4 text-sm font-black text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary/20 transition-all">
                </div>
              </div>
              
              <div class="grid grid-cols-2 gap-4">
                <div class="group">
                  <label class="text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-2 block ml-4">NIT / Documento</label>
                  <input type="text" v-model="customerForm.document" class="w-full bg-gray-50 dark:bg-[#0f172a] border border-transparent dark:border-white/5 rounded-2xl px-6 py-4 text-sm font-black text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary/20 transition-all">
                </div>
                <div class="group">
                  <label class="text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-2 block ml-4">Email Corporativo</label>
                  <input type="email" v-model="customerForm.email" class="w-full bg-gray-50 dark:bg-[#0f172a] border border-transparent dark:border-white/5 rounded-2xl px-6 py-4 text-sm font-black text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary/20 transition-all">
                </div>
              </div>

              <div class="grid grid-cols-2 gap-4">
                <div class="group">
                  <label class="text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-2 block ml-4">WhatsApp / Tel</label>
                  <input type="tel" v-model="customerForm.phone" class="w-full bg-gray-50 dark:bg-[#0f172a] border border-transparent dark:border-white/5 rounded-2xl px-6 py-4 text-sm font-black text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary/20 transition-all">
                </div>
                <div class="group">
                  <label class="text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-2 block ml-4">Ciudad / Ubicacion</label>
                  <input type="text" v-model="customerForm.city" class="w-full bg-gray-50 dark:bg-[#0f172a] border border-transparent dark:border-white/5 rounded-2xl px-6 py-4 text-sm font-black text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary/20 transition-all">
                </div>
              </div>

              <div class="group">
                <label class="text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-2 block ml-4">Direccion de Envio</label>
                <input type="text" v-model="customerForm.address" class="w-full bg-gray-50 dark:bg-[#0f172a] border border-transparent dark:border-white/5 rounded-2xl px-6 py-4 text-sm font-black text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary/20 transition-all">
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="group">
                  <label class="text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-2 block ml-4">Uso de la Pieza</label>
                  <select v-model="customerForm.use" class="w-full bg-gray-50 dark:bg-[#0f172a] border border-transparent dark:border-white/5 rounded-2xl px-6 py-4 text-sm font-black text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary/20 transition-all appearance-none cursor-pointer">
                    <option value="Prototipo" class="bg-[#0f172a] text-white">Prototipo Rapido</option>
                    <option value="Funcional" class="bg-[#0f172a] text-white">Pieza Mecanica / Funcional</option>
                    <option value="Decorativo" class="bg-[#0f172a] text-white">Arte / Decorativo</option>
                    <option value="Medico" class="bg-[#0f172a] text-white">Industrial / Medico</option>
                    <option value="Joyeria" class="bg-[#0f172a] text-white">Joyeria / Dental</option>
                  </select>
                </div>
                <div class="group">
                  <label class="text-[9px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-2 block ml-4">Notas Tecnicas</label>
                  <textarea v-model="customerForm.comments" rows="1" class="w-full bg-gray-50 dark:bg-[#0f172a] border border-transparent dark:border-white/5 rounded-2xl px-6 py-4 text-sm font-black text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary/20 transition-all resize-none" placeholder="EJ. ACABADO ESPEJO..."></textarea>
                </div>
              </div>
            </div>

            <!-- SECCION: RESUMEN TECNICO -->
            <div class="bg-primary/5 rounded-[2rem] p-6 border border-primary/20">
              <p class="text-[8px] font-black text-primary uppercase tracking-[0.4em] mb-4">Resumen Industrial</p>
              <div class="grid grid-cols-2 gap-y-3">
                <div class="flex flex-col">
                  <span class="text-[8px] font-black text-gray-400 uppercase">Tecnologia</span>
                  <span class="text-xs font-black dark:text-white uppercase">{{ selectedTechnology }}</span>
                </div>
                <div class="flex flex-col">
                  <span class="text-[8px] font-black text-gray-400 uppercase">Material</span>
                  <span class="text-xs font-black dark:text-white uppercase">{{ materials.find(m => m.id === selectedMaterial)?.name }}</span>
                </div>
                <div class="flex flex-col">
                  <span class="text-[8px] font-black text-gray-400 uppercase">Cantidad</span>
                  <span class="text-xs font-black dark:text-white uppercase">{{ qty }} Unidades</span>
                </div>
                <div class="flex flex-col">
                  <span class="text-[8px] font-black text-gray-400 uppercase text-right">Inversion Total</span>
                  <span class="text-xs font-black text-primary text-right uppercase italic font-italic">${{ Math.round(breakdown.total).toLocaleString() }} COP</span>
                </div>
              </div>
            </div>

            <!-- CAPTCHA SEGURIDAD -->
            <div class="bg-gray-100 dark:bg-white/5 p-6 rounded-3xl border border-dashed border-gray-200 dark:border-white/10">
              <div class="flex items-center gap-4">
                <div class="w-10 h-10 bg-primary/20 rounded-xl flex items-center justify-center shrink-0">
                  <svg class="w-5 h-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                </div>
                <div class="flex-1">
                  <p class="text-[8px] font-black text-primary uppercase tracking-[0.3em]">Validacion Maker</p>
                  <p class="text-[10px] font-black dark:text-white uppercase">{{ captchaChallenge.a }} + {{ captchaChallenge.b }} = ?</p>
                </div>
                <input type="number" v-model="captchaAnswer" class="w-20 bg-white dark:bg-white/10 border-none rounded-xl p-3 text-center text-sm font-black text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary/20 shadow-inner">
              </div>
            </div>

            <button @click="submitOrder" :disabled="isSubmitting" class="w-full bg-primary hover:bg-emerald-600 text-white py-6 rounded-2xl font-black text-xs uppercase tracking-[0.4em] transition-all flex items-center justify-center gap-3 active:scale-95 disabled:opacity-50 shadow-xl shadow-primary/20">
              <span v-if="isSubmitting" class="w-5 h-5 border-3 border-white/30 border-t-white rounded-full animate-spin"></span>
              <span>{{ isSubmitting ? 'PROCESANDO ORDEN...' : 'CONFIRMAR PEDIDO INDUSTRIAL' }}</span>
            </button>
          </div>
        </div>
      </div>
    </transition>

    <!-- NOTIFICACION -->
    <transition enter-active-class="transition transform duration-500 ease-out" enter-from-class="translate-y-10 opacity-0" enter-to-class="translate-y-0 opacity-100" leave-active-class="transition transform duration-400 ease-in" leave-from-class="translate-y-0 opacity-100" leave-to-class="translate-y-10 opacity-0">
        <div v-if="notification.show" :class="notification.type === 'success' ? 'bg-primary' : 'bg-rose-600'" class="fixed bottom-10 left-1/2 -translate-x-1/2 z-[200] px-10 py-5 rounded-full shadow-2xl flex items-center gap-4 border border-white/20">
            <div class="w-8 h-8 bg-white/20 rounded-full flex items-center justify-center">
                <span v-if="notification.type === 'success'" class="text-white font-black">✓</span>
                <span v-else class="text-white font-black">!</span>
            </div>
            <p class="text-[10px] font-black text-white uppercase tracking-widest">{{ notification.message }}</p>
        </div>
    </transition>
    <AppFooter />
  </div>
</template>

<style scoped>
.technical-grid {
  background-size: 50px 50px;
  background-image: linear-gradient(to right, rgba(30, 58, 52, 0.08) 1px, transparent 1px), linear-gradient(to bottom, rgba(30, 58, 52, 0.08) 1px, transparent 1px);
}
</style>
