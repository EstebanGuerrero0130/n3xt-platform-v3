<script setup>
import { ref, onMounted, onUnmounted, computed, watch, reactive, defineAsyncComponent, provide } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { api } from '../services/api'


// Sub-components (Carga Asíncrona para optimizar recursos)
const KanbanBoard = defineAsyncComponent(() => import('../components/admin/KanbanBoard.vue'))
const InventoryManager = defineAsyncComponent(() => import('../components/admin/InventoryManager.vue'))
const AccountingDashboard = defineAsyncComponent(() => import('../components/admin/AccountingDashboard.vue'))
const MachineMonitor = defineAsyncComponent(() => import('../components/admin/MachineMonitor.vue'))
const OrderHistory = defineAsyncComponent(() => import('../components/admin/OrderHistory.vue'))
const PurchaseLog = defineAsyncComponent(() => import('../components/admin/PurchaseLog.vue'))
const ContactManager = defineAsyncComponent(() => import('../components/admin/ContactManager.vue'))
const DiscountManager = defineAsyncComponent(() => import('../components/admin/DiscountManager.vue'))

const router = useRouter()

// --- State ---
const orders = ref([])
const inventoryData = ref([])
const printers = ref([])
const analyticsData = ref(null)
const loading = ref(true)
const loadingAnalytics = ref(false)
const activeTab = ref('kanban')
const editingCatalogItemIndex = ref(null)
const isSidebarOpen = ref(false)
const isDark = ref(localStorage.getItem('n3xt_theme') !== 'light')
const webSubTab = ref('general')

const toggleDarkMode = () => {
  isDark.value = !isDark.value
  localStorage.setItem('n3xt_theme', isDark.value ? 'dark' : 'light')
  updateTheme()
  showNotify(`SISTEMA N3XT: Modo ${isDark.value ? 'Oscuro' : 'Claro'} Activo`, 'success')
}

const updateTheme = () => {
  if (isDark.value) {
    document.documentElement.classList.add('dark')
  } else {
    document.documentElement.classList.remove('dark')
  }
}

// Watcher para sincronización global del DOM
watch(isDark, (val) => {
  if (val) {
    document.documentElement.classList.add('dark')
  } else {
    document.documentElement.classList.remove('dark')
  }
}, { immediate: true })

const submitting = ref(false)
const savingSettings = ref(false)
const showMobileMenu = ref(false)
const contacts = reactive({ customers: [], suppliers: [] })

// --- Premium UI Feedback ---
const notification = reactive({ show: false, message: '', type: 'success' })
const confirmDialog = reactive({ 
    show: false, 
    title: '', 
    message: '', 
    icon: '', 
    onConfirm: null, 
    mode: 'confirm' 
})

const showNotify = (msg, type = 'success') => {
  notification.message = msg
  notification.type = type
  notification.show = true
  setTimeout(() => { notification.show = false }, 4000)
}

const askConfirm = (title, message, icon, onConfirm) => {
  confirmDialog.title = title
  confirmDialog.message = message
  confirmDialog.icon = icon || ''
  confirmDialog.onConfirm = onConfirm
  confirmDialog.mode = 'confirm'
  confirmDialog.show = true
}

provide('showNotify', showNotify)
provide('askConfirm', askConfirm)

const showAlert = (title, message, icon = '') => {
  confirmDialog.title = title
  confirmDialog.message = message
  confirmDialog.icon = icon || ''
  confirmDialog.onConfirm = null
  confirmDialog.mode = 'alert'
  confirmDialog.show = true
}

const handleConfirm = async () => {
  if (confirmDialog.onConfirm) {
    await confirmDialog.onConfirm()
  }
  confirmDialog.show = false
}

// --- CLOUDINARY UPLOAD PIPELINE ---
const triggerCardUpload = (idx) => {
    const input = document.getElementById(`card-upload-${idx}`)
    if (input) input.click()
}

const triggerProductUpload = (idx) => {
    const input = document.getElementById(`product-upload-${idx}`)
    if (input) input.click()
}

const triggerNewsUpload = (idx) => {
    const input = document.getElementById(`news-upload-${idx}`)
    if (input) input.click()
}

const triggerPostUpload = (idx) => {
    const input = document.getElementById(`post-upload-${idx}`)
    if (input) input.click()
}

const handleImageUploadSEO = async (event, targetObj, nameField) => {
    const file = event.target.files[0]
    if (!file) return

    showNotify('SISTEMA N3XT: Optimizando SEO y subiendo imagen...', 'success')
    
    // Generar Public ID SEO-friendly basado en el nombre del producto/item
    const sanitizedName = (targetObj[nameField] || 'n3xt_asset')
        .toLowerCase()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "") // Quitar acentos
        .replace(/[^a-z0-9]/g, '_') // Cambiar espacios y raros por _
        .substring(0, 50)
    
    const publicId = `n3xt_${sanitizedName}_${Date.now().toString().slice(-4)}`

    const formData = new FormData()
    formData.append('file', file)
    formData.append('upload_preset', 'ml_default')
    formData.append('public_id', publicId) // SEO Filename

    const cloudName = settings.value.web.cloudinary_name || 'dplcy7vbm' 

    try {
        const resp = await fetch(`https://api.cloudinary.com/v1_1/${cloudName}/image/upload`, {
            method: 'POST',
            body: formData
        })
        const data = await resp.json()
        
        if (data.secure_url) {
            // Aplicar optimización automática de Cloudinary (f_auto, q_auto)
            targetObj.image = data.secure_url
            // Auto-generar Alt tag si no existe
            if (!targetObj.alt) targetObj.alt = `Imagen de ${targetObj[nameField]} - N3XT 3D Systems`
            showNotify('MANUFACTURA DIGITAL: Imagen con SEO optimizado', 'success')
        } else {
            throw new Error(data.error?.message || 'Error en subida')
        }
    } catch (err) {
        showNotify('ERROR SEO: Revisa tu Cloud Name o conexión.', 'error')
    }
}

const settings = ref({
  infra: { luz_hr: 0, depr_hr: 0, mant_hr: 0, etiquetas: 0, load_factor: 0.4 },
  prep: { mano_obra_hr: 0, prep_time_pct: 10 },
  oper: { transporte: 0, ganancia: 0, marketing: 0, fallos: 0 },
  margin: { iva: 0 },
  company: { name: 'N3XT 3D TECHNOLOGY', nit: '10271500341', email: 'servicion3xt@gmail.com', phone: '+57 3118796416', address: 'Guateque-Boyaca', slogan: 'Manufactura Digital Avanzada', website: '' },
  web: {
    social: { tiktok: '', instagram: '', facebook: '', whatsapp: 'https://wa.me/573118796416', youtube: '' },
    workshop_status: 'operativo 24/7',
    privacy_policy: '',
    terms_conditions: '',
    ecosystem: [],
    news: [],
    posts: [],
    catalog: [],
    pdf_catalog_url: '',
    pdf_catalog_desc: '',
    cloudinary_name: ''
  },
  discounts: []
})

// --- Modals State (Unificado para estabilidad) ---
const modalState = reactive({
  manualOrder: false,
  newMaterial: false,
  newPrinter: false,
  printerStatus: false, // showPrinterModal
  orderDetails: false,
  simulator: false,
  editMaterial: false,
  editPrinter: false,
  shipping: false,
  shipping_details: false
})

const pdfPreviewData = ref({ title: '', content: '', orderId: '' })

const printerToDelete = ref(null)
const editingPrinter = ref({ id: '', name: '', model: '', technology: 'FDM', status: 'idle', maintenance_interval_h: 200, next_maintenance: '', total_hours_run: 0, maintenance_notes: '' })

const handleAddPrinter = () => {
    editingPrinter.value = { name: '', model: '', technology: 'FDM', status: 'idle', maintenance_interval_h: 200, next_maintenance: '' }
    modalState.editPrinter = true
}

// Selections
const selectedOrderForPrinter = ref(null)
const selectedOrderDetails = ref(null)
const editingMaterial = ref({ id: '', name: '', cost_per_kg: 0, density: 1.24, color: '#cccccc', location: '' })
const selectedPrinter = ref(null)

// Forms
const newMaterial = reactive({ id: '', name: '', category: 'FDM', type: 'material', unit: 'g', cost_per_kg: 0, density: 1.24, color: '#000000', initial_stock: 1000, 
  low_stock_threshold: 200,
  package_price: null, package_qty: null, package_units: 1 
})
const newPrinter = reactive({ name: '', model: '', technology: 'FDM' })
const simulator = reactive({
  customer_id: '',
  job_name: '', customer_name: '', customer_company: '', customer_id_document: '', material_id: '', weight_g: 0, time_str: '0:00', profit_pct: 20, 
  customer_email: '', customer_phone: '',
  shipping_address: '', shipping_city: '', shipping_zip: '', shipping_reference: '',
  discount_pct: 0, pieces_per_batch: 1, transporte_pct: 0, marketing_pct: 0, fallos_pct: 0, etiquetas: 400,
  extra_items: [], // { id, name, cost, qty }
  comments: ''
})
const manualOrder = reactive({
  customer_id: '',
  customer_name: '', customer_company: '', customer_id_document: '', customer_email: '', customer_phone: '',
  shipping_address: '', shipping_city: '', shipping_zip: '', shipping_reference: '',
  material_id: '', weight_g: 0, duration_h: 0, total_price: 0,
  qty: 1, // Added for non-material items (utilities/services)
  technology: 'FDM', comments: '',
  transporte_pct: 5, marketing_pct: 10, fallos_pct: 5, profit_pct: 40,
  etiquetas: 400, extra_items: []
})

const logoUrl = computed(() => {
  if (!settings.value?.company_logo) return '/logo.png';
  if (settings.value.company_logo.startsWith('http')) return settings.value.company_logo;
  // Soporte para rutas relativas del Motor N3XT
  return `${api.storageUrl}/${settings.value.company_logo}`;
});

const uploadingLogo = ref(false)
const logoInput = ref(null)

const isLocalhost = computed(() => window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1');

// --- SEO Engine Automático ---
const globalSeoOptimizer = () => {
  // Optimizar Catálogo
  if (settings.value.web.catalog) {
    settings.value.web.catalog.forEach(item => {
      if (!item.name) return
      // Generar slug limpio
      item.slug = item.name.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/[^a-z0-9]/g, '-').replace(/-+/g, '-')
      // Auto-Alt tags
      if (!item.alt) item.alt = `${item.name} - ${item.category} | N3XT 3D`
      // Optimización de imágenes Cloudinary
      if (item.image && item.image.includes('cloudinary.com') && !item.image.includes('f_auto')) {
        item.image = item.image.replace('/upload/', '/upload/f_auto,q_auto/')
      }
    })
  }

  // Optimizar Noticias
  if (settings.value.web.news) {
    settings.value.web.news.forEach(item => {
      if (!item.alt) item.alt = `Noticia: ${item.t} | Manufactura Digital N3XT`
    })
  }
}

// --- Data Fetching ---

const syncAll = async (silent = false) => {
  // Si silent es un evento de Vue (objeto), lo tratamos como false para mostrar carga
  const isSilent = typeof silent === 'boolean' ? silent : false
  if (!isSilent) loading.value = true
  
  // Ejecución paralela pero resiliente
  const tasks = [
    { name: 'Pedidos', fn: fetchOrders },
    { name: 'Inventario', fn: fetchInventory },
    { name: 'Maquinaria', fn: fetchPrinters },
    { name: 'Ajustes', fn: fetchSettings },
    { name: 'Analíticas', fn: fetchAnalytics },
    { name: 'Contactos', fn: fetchContacts }
  ]

  try {
    await Promise.all(tasks.map(async task => {
      try {
        await task.fn()
      } catch (err) {
        console.error(`Error en módulo ${task.name}:`, err)
        if (!silent) showNotify(`Fallo parcial: ${task.name}`, 'warning')
      }
    }))
  } finally {
    if (!isSilent) {
      setTimeout(() => { loading.value = false }, 500)
    }
  }
}

const fetchOrders = async () => {
  try {
    const res = await api.get('/admin/orders', true)
    orders.value = Array.isArray(res) ? res : (res?.data || [])
  } catch (err) {
    console.error('Error fetching orders:', err)
    orders.value = []
  }
}

const fetchInventory = async () => {
  try {
    const res = await api.get('/materials', true)
    inventoryData.value = Array.isArray(res) ? res : (res?.data || [])
  } catch (err) {
    inventoryData.value = []
  }
}

const fetchPrinters = async () => {
  try {
    const res = await api.get('/admin/printers', true)
    printers.value = Array.isArray(res) ? res : (res?.data || [])
  } catch (err) {
    printers.value = []
  }
}

const fetchSettings = async () => {
  try {
    const data = await api.get('/settings', true)
    if (data && typeof data === 'object' && !Array.isArray(data)) {
      // Merge inteligente por categorías para no perder campos nuevos
      if (data.infra) settings.value.infra = { ...settings.value.infra, ...data.infra }
      if (data.prep) settings.value.prep = { ...settings.value.prep, ...data.prep }
      if (data.oper) settings.value.oper = { ...settings.value.oper, ...data.oper }
      if (data.margin) settings.value.margin = { ...settings.value.margin, ...data.margin }
      if (data.company) settings.value.company = { ...settings.value.company, ...data.company }
      if (data.web) {
        settings.value.web = { ...settings.value.web, ...data.web }
        // Garantía N3XT: Asegurar que el catálogo siempre sea un array para evitar crash de UI
        if (!Array.isArray(settings.value.web.catalog)) {
            settings.value.web.catalog = []
        }
      }
      if (data.company_logo) settings.value.company_logo = data.company_logo
      if (data.discounts) settings.value.discounts = data.discounts
    }
    
    // Ensure company info exists
    if (!settings.value.company) {
      settings.value.company = { name: 'N3XT 3D TECHNOLOGY', nit: '', email: '', phone: '', address: '', slogan: 'Manufactura Digital Avanzada', website: '' }
    }
    
    // Sync simulator defaults from settings
    if (settings.value.infra?.etiquetas) {
      simulator.etiquetas = settings.value.infra.etiquetas
      manualOrder.etiquetas = settings.value.infra.etiquetas
    }
    if (settings.value.oper?.transporte) {
      simulator.transporte_pct = settings.value.oper.transporte
      manualOrder.transporte_pct = settings.value.oper.transporte
    }
    if (settings.value.oper?.marketing) {
      simulator.marketing_pct = settings.value.oper.marketing
      manualOrder.marketing_pct = settings.value.oper.marketing
    }
    if (settings.value.oper?.fallos) {
      simulator.fallos_pct = settings.value.oper.fallos
      manualOrder.fallos_pct = settings.value.oper.fallos
    }
    if (settings.value.oper?.ganancia) {
      simulator.profit_pct = settings.value.oper.ganancia
      manualOrder.profit_pct = settings.value.oper.ganancia
    }
  } catch (err) {
    console.error('Error settings:', err)
  }
}

// Sincronización reactiva: si cambias los ajustes, el simulador se actualiza al instante
watch(() => settings.value, (newVal) => {
  if (newVal.infra?.etiquetas) {
    simulator.etiquetas = newVal.infra.etiquetas
    manualOrder.etiquetas = newVal.infra.etiquetas
  }
  if (newVal.oper?.transporte) {
    simulator.transporte_pct = newVal.oper.transporte
    manualOrder.transporte_pct = newVal.oper.transporte
  }
  if (newVal.oper?.marketing) {
    simulator.marketing_pct = newVal.oper.marketing
    manualOrder.marketing_pct = newVal.oper.marketing
  }
  if (newVal.oper?.fallos) {
    simulator.fallos_pct = newVal.oper.fallos
    manualOrder.fallos_pct = newVal.oper.fallos
  }
  if (newVal.oper?.ganancia) {
    simulator.profit_pct = newVal.oper.ganancia
    manualOrder.profit_pct = newVal.oper.ganancia
  }
}, { deep: true })

const fetchAnalytics = async () => {
  loadingAnalytics.value = true
  try {
    analyticsData.value = await api.get('/admin/analytics', true)
  } finally {
    loadingAnalytics.value = false
  }
}

const fetchContacts = async () => {
  try {
    const [cData, sData] = await Promise.all([
      api.get('/admin/contacts/customers', true),
      api.get('/admin/contacts/suppliers', true)
    ])
    contacts.customers = cData
    contacts.suppliers = sData
  } catch (err) {
    console.error('Error fetching contacts:', err)
  }
}

const handleSelectCustomer = (e) => {
  const id = e.target.value
  if (!id) return
  const customer = contacts.customers.find(c => String(c.id) === String(id))
  if (customer) {
    simulator.customer_id = customer.id
    simulator.customer_name = customer.name
    simulator.customer_company = customer.company || ''
    simulator.customer_id_document = customer.customer_id_document || ''
    simulator.customer_email = customer.email || ''
    simulator.customer_phone = customer.phone || ''
    simulator.shipping_address = customer.address_full || customer.location || ''
    simulator.shipping_city = customer.city_dept_country || ''
    simulator.shipping_zip = customer.zip_code || ''
    simulator.shipping_reference = customer.location_reference || ''
    if (customer.notes) simulator.comments = customer.notes
  }
}

const handleSelectCustomerManual = (e) => {
  const id = e.target.value
  if (!id) return
  const customer = contacts.customers.find(c => String(c.id) === String(id))
  if (customer) {
    manualOrder.customer_id = customer.id
    manualOrder.customer_name = customer.name
    manualOrder.customer_company = customer.company || ''
    manualOrder.customer_id_document = customer.customer_id_document || ''
    manualOrder.customer_email = customer.email || ''
    manualOrder.customer_phone = customer.phone || ''
    manualOrder.shipping_address = customer.address_full || customer.location || ''
    manualOrder.shipping_city = customer.city_dept_country || ''
    manualOrder.shipping_zip = customer.zip_code || ''
    manualOrder.shipping_reference = customer.location_reference || ''
    if (customer.notes) manualOrder.comments = customer.notes
  }
}

// --- Mutations ---

const orderToShip = ref(null)
const trackingGuide = ref('')
const trackingCarrier = ref('')

const handleStatusUpdate = async ({ orderId, status, tracking_guide, tracking_carrier }) => {
  console.log(`[ACTION] Actualizando estado: Orden ${orderId} -> ${status}`);
  if (status === 'shipped' && !tracking_guide) {
    orderToShip.value = orders.value.find(o => o.id === orderId)
    trackingGuide.value = ''
    trackingCarrier.value = ''
    modalState.shipping = true
    return
  }

  try {
    const order = orders.value.find(o => o.id === orderId);
    
    // Lógica de Deducción Automática de Stock al terminar
    if (status === 'completed' && order && order.material_id) {
        const material = inventoryData.value.find(m => m.id === order.material_id);
        if (material) {
            const usedWeight = parseFloat(order.estimated_weight_g || 0);
            const currentStock = parseFloat(material.stock_available || 0);
            const newStock = Math.max(0, currentStock - usedWeight);
            
            console.log(`[STOCK] Deduciendo ${usedWeight}g de ${material.name}. Nuevo: ${newStock}`);
            await api.post(`/materials/${material.id}/stock`, { stock_available: newStock }, true);
        }
    }

    await api.patch(`/admin/orders/${orderId}/status`, { 
        status, 
        tracking_guide, 
        tracking_carrier 
    }, true)
    
    // Liberación de impresora y actualización de horas en web
    if ((status === 'completed' || status === 'cancelled') && order && order.printer_id) {
        const printer = printers.value.find(p => p.id === order.printer_id);
        const updateData = { status: 'idle' };
        
        if (status === 'completed' && printer) {
            updateData.total_hours_run = (parseFloat(printer.total_hours_run) || 0) + (parseFloat(order.estimated_duration_h) || 0);
        }
        
        await api.patch(`/admin/printers/${order.printer_id}`, updateData, true);
    }
    
    showNotify('Estado actualizado: ' + status.toUpperCase(), 'success');
    syncAll(true);
  } catch (err) {
    showNotify('Error al actualizar estado: ' + err.message, 'error')
  }
}

const handleTogglePaid = async (orderId) => {
  console.log(`[ACTION] Toggle Pago: Orden ${orderId}`);
  try {
    const order = orders.value.find(o => o.id === orderId)
    if (!order) return
    const newState = order.is_paid ? 0 : 1

    await api.post(`/admin/orders/${orderId}/toggle-paid`, { is_paid: newState }, true)
    showNotify(newState ? 'Pedido marcado como pagado' : 'Pago pendiente', 'success')
    syncAll(true)
  } catch (err) {
    showNotify('Error al procesar pago: ' + err.message, 'error')
  }
}

const confirmShipping = async () => {
  if (!trackingGuide.value || !trackingCarrier.value) {
    showNotify('Por favor ingrese el numero de guia y la transportadora.', 'warning')
    return
  }
  await handleStatusUpdate({ 
    orderId: orderToShip.value.id, 
    status: 'shipped', 
    tracking_guide: trackingGuide.value,
    tracking_carrier: trackingCarrier.value
  })
  modalState.shipping = false
}

// --- Deletion Logic (Unificada con askConfirm) ---
const handleDeleteOrder = (id) => {
  console.log(`[ACTION] Solicitud de borrado: Orden ${id}`);
  askConfirm(
    '¿Eliminar Orden?',
    'Esta acción es permanente y borrará todos los registros de producción y finanzas asociados.',
    '',
    async () => {
      try {
        await api.delete(`/admin/orders/${id}`, true)
        await fetchOrders()
        showNotify('Orden eliminada correctamente', 'success')
      } catch (err) {
        showNotify('Error: ' + err.message, 'error')
      }
    }
  )
}

const handleDeleteMaterial = (id) => {
  askConfirm(
    '¿Eliminar Material?',
    'Se eliminará el insumo y su existencia en inventario. No se puede deshacer.',
    '',
    async () => {
      try {
        await api.delete(`/materials/${id}`, true)
        await fetchInventory()
        showNotify('Inventario actualizado: Material eliminado', 'success')
      } catch (err) {
        showNotify('Error: ' + err.message, 'error')
      }
    }
  )
}

const handleDeleteContact = (type, id) => {
  const label = type === 'customers' ? 'Cliente' : 'Proveedor'
  askConfirm(
    `¿Eliminar ${label}?`,
    `Se borrará la ficha de contacto del ${label.toLowerCase()} de forma permanente.`,
    '',
    async () => {
      try {
        await api.delete(`/admin/contacts/${type}/${id}`, true)
        await fetchContacts()
        showNotify(`${label} eliminado de la base de datos`, 'success')
      } catch (err) {
        showNotify('Error: ' + err.message, 'error')
      }
    }
  )
}


const handleEditPrinter = (printer) => {
  editingPrinter.value = { ...printer }
  modalState.editPrinter = true
}

const updatePrinter = async () => {
  try {
    await api.patch(`/admin/printers/${editingPrinter.value.id}`, editingPrinter.value, true)
    modalState.editPrinter = false
    await fetchPrinters()
    showNotify('Máquina actualizada', 'success')
  } catch (err) {
    showNotify('Error: ' + err.message, 'error')
  }
}


const downloadFile = async (order) => {
  if (!order.file_path && !order.id) {
    showNotify('No hay archivo digital asociado a este pedido.', 'warning')
    return
  }
  
  try {
    const token = localStorage.getItem('n3xt_admin_token')
    const res = await fetch(`${api.baseUrl}/admin/orders/${order.id}/download`, {
      headers: { 'Authorization': `Bearer ${token}` }
    })
    const blob = await res.blob()
    const url = window.URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = order.original_filename || `order_${order.id}.stl`
    a.click()
    showNotify('Iniciando descarga del archivo...', 'success')
  } catch (err) {
    showNotify('Error en la descarga: ' + err.message, 'error')
  }
}

const saveTrackingGuide = async () => {
  if (!selectedOrderDetails.value) return
  try {
    await api.patch(`/admin/orders/${selectedOrderDetails.value.id}/status`, {
      tracking_guide: selectedOrderDetails.value.tracking_guide,
      tracking_carrier: selectedOrderDetails.value.tracking_carrier
    }, true)
    showNotify('Guía de seguimiento guardada con éxito', 'success')
    await fetchOrders()
  } catch (err) {
    showNotify('Error al guardar guía: ' + err.message, 'error')
  }
}

const handleDownloadShippingLabel = (order) => {
    let iframe = document.getElementById('pdf-print-frame');
    if (!iframe) {
        iframe = document.createElement('iframe');
        iframe.id = 'pdf-print-frame';
        iframe.style.position = 'absolute';
        iframe.style.top = '-9999px';
        iframe.style.left = '-9999px';
        iframe.style.visibility = 'hidden';
        document.body.appendChild(iframe);
    }

    const companyLogo = settings.value.company_logo ? (settings.value.company_logo.startsWith('http') ? settings.value.company_logo : api.storageUrl + '/' + settings.value.company_logo) : '/logo.png';

    const content = `
        <html>
            <head>
                <title>Documento de Entrega - ${order.customer_name}</title>
                <style>
                    @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;900&display=swap');
                    @page { size: A4; margin: 15mm; }
                    body { font-family: 'Outfit', sans-serif; padding: 0; color: #0f172a; background: #fff; line-height: 1.4; }
                    
                    .page-border { border: 1px solid #e2e8f0; padding: 40px; height: calc(100vh - 120px); display: flex; flex-direction: column; position: relative; }
                    
                    .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 4px solid #0f172a; padding-bottom: 25px; margin-bottom: 35px; }
                    .brand h1 { font-size: 32px; font-weight: 900; letter-spacing: -2px; margin: 0; text-transform: uppercase; }
                    .brand p { font-size: 10px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 3px; margin: 5px 0 0 0; }
                    
                    .doc-type { text-align: right; }
                    .doc-badge { background: #0f172a; color: white; padding: 8px 15px; border-radius: 8px; font-size: 10px; font-weight: 900; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; display: inline-block; }
                    .doc-num { font-size: 20px; font-weight: 900; color: #0f172a; }
                    
                    .logistics-grid { display: grid; grid-template-cols: 1fr 1fr; gap: 40px; margin-bottom: 40px; }
                    .info-card { background: #f8fafc; padding: 25px; border-radius: 20px; border: 1px solid #f1f5f9; }
                    .card-label { font-size: 9px; font-weight: 900; text-transform: uppercase; color: #94a3b8; letter-spacing: 2px; margin-bottom: 12px; display: block; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; }
                    
                    .name { font-size: 22px; font-weight: 900; color: #0f172a; margin-bottom: 4px; }
                    .company { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 12px; }
                    .addr { font-size: 15px; font-weight: 600; color: #334155; line-height: 1.3; }
                    .city { font-size: 16px; font-weight: 900; text-transform: uppercase; color: #0f172a; margin-top: 5px; }
                    .contact { font-size: 13px; font-weight: 700; color: #0f172a; margin-top: 10px; display: flex; gap: 10px; align-items: center; }
                    
                    .order-box { border: 2px solid #0f172a; border-radius: 20px; padding: 30px; margin-bottom: 40px; }
                    .order-ref { font-size: 12px; font-weight: 900; color: #94a3b8; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 5px; }
                    .order-title { font-size: 28px; font-weight: 900; color: #0f172a; letter-spacing: -1px; }
                    
                    .ref-tag { background: #f1f5f9; padding: 15px; border-radius: 12px; margin-top: 15px; font-size: 13px; color: #475569; font-weight: 600; border-left: 5px solid #0f172a; }
                    
                    .footer-sig { margin-top: auto; display: grid; grid-template-cols: 1fr 1fr; gap: 60px; padding-top: 40px; }
                    .sig-line { border-top: 2px solid #0f172a; padding-top: 10px; text-align: center; font-size: 10px; font-weight: 900; text-transform: uppercase; color: #64748b; letter-spacing: 1px; }
                    
                    .stamp { position: absolute; bottom: 40px; right: 40px; font-size: 8px; color: #cbd5e1; text-transform: uppercase; transform: rotate(-90deg); transform-origin: bottom right; }
                    
                    @media print { body { -webkit-print-color-adjust: exact; } .page-border { border: none; padding: 0; } }
                </style>
            </head>
            <body>
                <div class="page-border">
                    <div class="header">
                        <div class="brand">
                            <h1>${settings.value.company?.name || 'N3XT 3D'}</h1>
                            <p>Manufactura Digital Avanzada</p>
                        </div>
                        <div class="doc-type">
                            <div class="doc-badge">Remisión de Entrega</div>
                            <div class="doc-num">ORDEN #${order.id}</div>
                        </div>
                    </div>

                    <div class="logistics-grid">
                        <div class="info-card">
                            <span class="card-label">Remitente (Origen)</span>
                            <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
                                ${companyLogo ? `<img src="${companyLogo}" style="height: 45px; width: auto;">` : ''}
                                <div class="name" style="font-size: 18px;">${settings.value.company?.name || 'N3XT 3D'}</div>
                            </div>
                            ${settings.value.company?.nit ? `<div class="company">NIT: ${settings.value.company.nit}</div>` : ''}
                            <div class="addr">${settings.value.company?.address || ''}</div>
                            ${settings.value.company?.phone ? `<div class="contact">TEL: ${settings.value.company.phone}</div>` : ''}
                            ${settings.value.company?.email ? `<div class="contact" style="font-size: 11px;">EMAIL: ${settings.value.company.email}</div>` : ''}
                        </div>

                        <div class="info-card">
                            <span class="card-label">Destinatario (Entrega)</span>
                            <div class="name">${order.customer_name}</div>
                            ${order.customer_id_document ? `<div class="company" style="font-size: 13px; color: #000;">ID: ${order.customer_id_document}</div>` : ''}
                            <div class="company">${order.customer_company || 'Cliente Registrado'}</div>
                            <div class="addr">${order.shipping_address || 'Dirección por confirmar'}</div>
                            <div class="city">${order.shipping_city || 'Ciudad / Depto'} ${order.shipping_zip ? `(${order.shipping_zip})` : ''}</div>
                            <div class="contact">WhatsApp: ${order.customer_phone}</div>
                        </div>
                    </div>

                    <div class="order-box">
                        <div class="order-ref">Detalles del Despacho</div>
                        <div class="order-title">${order.job_name || 'Fabricación 3D Bajo Demanda'}</div>
                        ${order.shipping_reference ? `
                            <div class="ref-tag">
                                <strong>Indicaciones de Entrega:</strong><br>
                                ${order.shipping_reference}
                            </div>
                        ` : ''}
                    </div>

                    <div class="footer-sig">
                        <div class="sig-line">Entregado por (N3XT 3D)</div>
                        <div class="sig-line">Recibido a Conformidad (Cliente)</div>
                    </div>

                    <div class="stamp">
                        Generado por N3XT OS v3.2 • Fecha: ${new Date().toLocaleDateString()} • Auditoría Digital
                    </div>
                </div>
            </body>
        </html>
    `;

    const printWindow = iframe.contentWindow || iframe.contentDocument.defaultView;
    printWindow.document.open();
    printWindow.document.write(content);
    printWindow.document.close();
    setTimeout(() => { 
        printWindow.focus();
        printWindow.print(); 
    }, 500);
}

const handleDownloadSimulationPDF = () => {
  if (!simulator.material_id) {
    showNotify('Selecciona un material para generar la cotizacion.', 'warning')
    return
  }
  
  // Mapeamos los datos del simulador a una estructura que entienda el generador de PDF
  const fakeOrder = {
    id: 'SIM-' + Date.now().toString().slice(-4),
    customer_name: simulator.customer_name || 'Cliente Prospecto',
    customer_id_document: simulator.customer_id_document || '',
    customer_company: simulator.customer_company || '',
    customer_email: simulator.customer_email || 'Sin email',
    customer_phone: simulator.customer_phone || 'Sin teléfono',
    shipping_address: simulator.shipping_address || '',
    shipping_city: simulator.shipping_city || '',
    shipping_zip: simulator.shipping_zip || '',
    shipping_reference: simulator.shipping_reference || '',
    job_name: simulator.job_name || 'Simulación de Proyecto',
    technology: inventoryData.value.find(m => m.id === simulator.material_id)?.category || 'FDM',
    material_id: simulator.material_id,
    estimated_weight_g: simulator.weight_g,
    estimated_duration_h: simulatedResult.value.total_hours,
    total_price: simulatedResult.value.total,
    extras_cost: simulatedResult.value.extras,
    extra_items: JSON.parse(JSON.stringify(simulator.extra_items))
  }
  
  handleDownloadQuotePDF(fakeOrder)
}

const handleDownloadQuotePDF = (order) => {
  const mat = inventoryData.value.find(m => String(m.id) === String(order.material_id));
  const companyLogo = settings.value.company_logo ? (settings.value.company_logo.startsWith('http') ? settings.value.company_logo : api.storageUrl + '/' + settings.value.company_logo) : '/logo.png';
  
  const trackingUrl = `${window.location.origin}/#/track?order_id=${order.id}&email=${order.customer_email || ''}`
  
  // QR de Rastreo (Generado vía API para evitar bloqueos)
  const qrDataUrl = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(trackingUrl)}`;

  const content = `
    <html>
      <head>
        <title>N3XT 3D - Cotización #${order.id}</title>
        <style>
                    @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;900&display=swap');
                    @page { size: A4; margin: 8mm; }
                    body { font-family: 'Outfit', sans-serif; padding: 0; color: #0f172a; line-height: 1.25; font-size: 13px; background: white; }
                    
                    .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 5px solid #0f172a; padding-bottom: 15px; margin-bottom: 20px; }
                    
                    .company-brand { display: flex; align-items: center; gap: 20px; }
                    .company-logo { height: 70px; width: auto; object-fit: contain; }
                    .logo-text { font-size: 32px; font-weight: 900; letter-spacing: -1.5px; text-transform: uppercase; color: #0f172a; }
                    .logo-text span { color: #10b981; }
                    
                    .company-info-mini { font-size: 11px; color: #334155; font-weight: 600; line-height: 1.4; border-left: 3px solid #10b981; padding-left: 15px; }
                    
                    .quote-badge { background: #0f172a; color: white; padding: 10px 25px; border-radius: 18px; text-align: right; }
                    .badge-label { font-size: 9px; font-weight: 900; text-transform: uppercase; letter-spacing: 2px; opacity: 0.8; margin-bottom: 2px; }
                    .badge-val { font-size: 22px; font-weight: 900; }

                    .grid { display: grid; grid-template-cols: 1fr; gap: 15px; margin-bottom: 20px; }
                    .info-box { background: #f8fafc; padding: 20px; border-radius: 20px; border: 1.5px solid #f1f5f9; }
                    .info-label { font-size: 10px; font-weight: 900; text-transform: uppercase; color: #94a3b8; margin-bottom: 5px; display: block; letter-spacing: 1px; }
                    .info-value { font-weight: 900; font-size: 18px; color: #0f172a; }

                    .table { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 20px; }
                    .table th { background: #f8fafc; padding: 12px 15px; text-align: left; font-size: 10px; font-weight: 900; text-transform: uppercase; color: #64748b; letter-spacing: 1.5px; border-bottom: 3px solid #0f172a; }
                    .table td { padding: 15px; border-bottom: 1.5px solid #f1f5f9; font-size: 14px; font-weight: 700; }

                    .rec-container { display: grid; grid-template-cols: 1.2fr 0.8fr; gap: 15px; margin-bottom: 20px; }
                    .rec-box { background: #f0fdf4; border: 1.5px solid #bbf7d0; padding: 15px; border-radius: 20px; }
                    .rec-title { font-size: 12px; font-weight: 900; color: #166534; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px; display: flex; align-items: center; gap: 8px; }
                    .rec-list { margin: 0; padding-left: 20px; font-size: 11px; color: #166534; font-weight: 600; line-height: 1.6; }
                    
                    .cert-box { background: #eff6ff; border: 1.5px solid #bfdbfe; padding: 15px; border-radius: 20px; display: flex; flex-direction: column; justify-content: center; }
                    .cert-title { font-size: 12px; font-weight: 900; color: #1e40af; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px; }
                    .cert-text { font-size: 11px; color: #1e40af; font-weight: 600; margin: 0; line-height: 1.4; }

                    .total-section { display: flex; justify-content: flex-end; padding-top: 5px; }
                    .total-box { text-align: right; background: #0f172a; color: white; padding: 25px 40px; border-radius: 25px; border: 2px solid #10b981; }
                    .total-label { font-size: 12px; font-weight: 900; text-transform: uppercase; letter-spacing: 2px; color: #10b981; margin-bottom: 5px; }
                    .total-val-main { font-size: 42px; font-weight: 900; letter-spacing: -2px; }

                    .footer { font-size: 10px; color: #94a3b8; text-align: center; margin-top: 30px; border-top: 1.5px solid #f1f5f9; padding-top: 20px; font-weight: 600; }
                    
                    @media print {
                        body { -webkit-print-color-adjust: exact; }
                        .total-box { -webkit-print-color-adjust: exact; background-color: #0f172a !important; color: white !important; border-color: #10b981 !important; }
                        .rec-box, .cert-box { -webkit-print-color-adjust: exact; }
                    }
        </style>
      </head>
      <body>
        <div class="header">
          <div class="company-brand">
            ${companyLogo ? `<img src="${companyLogo}" class="company-logo">` : ''}
            <div class="company-info-mini" style="border-left: 5px solid #0f172a; padding-left: 20px;">
              <div style="color: #0f172a; font-weight: 900; text-transform: uppercase; font-size: 24px; margin-bottom: 6px;">${settings.value.company?.name || 'N3XT 3D'}</div>
              <div style="font-size: 12px; font-weight: 800; color: #64748b; text-transform: uppercase;">
                ${settings.value.company?.nit ? `NIT: ${settings.value.company.nit} | ` : ''} 
                ${settings.value.company?.address || ''}<br>
                ${settings.value.company?.phone ? `TEL: ${settings.value.company.phone} | ` : ''} 
                ${settings.value.company?.email ? `EMAIL: ${settings.value.company.email}` : ''}
              </div>
            </div>
          </div>
          <div class="quote-badge" style="display: flex; gap: 15px; align-items: center; padding: 10px 20px;">
            <div style="background: white; padding: 4px; border-radius: 12px; line-height: 0; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
              <img src="${qrDataUrl}" style="width: 75px; height: 75px;">
            </div>
            <div style="text-align: right;">
              <div class="badge-label">Propuesta Técnica</div>
              <div class="badge-val">#${order.id}</div>
              <div style="font-size: 6px; margin-top: 5px; opacity: 0.5; font-family: monospace;">TRACKING ENABLED</div>
            </div>
          </div>
        </div>

        <div class="grid">
          <div class="info-box">
            <span class="info-label">Proyecto / Cliente Destino</span>
            <div class="info-value">${order.customer_name} ${order.customer_company ? `(${order.customer_company})` : ''}</div>
            <div style="font-size: 14px; color: #475569; font-weight: 600; margin-top: 5px;">
                ${order.customer_id_document ? `ID: ${order.customer_id_document} | ` : ''} 
                ${order.customer_phone ? `${order.customer_phone} | ` : ''} 
                ${order.customer_email || ''}
            </div>
            ${order.shipping_address ? `
                <div style="font-size: 13px; color: #64748b; font-weight: 500; margin-top: 10px; padding-top: 10px; border-top: 1px solid #e2e8f0;">
                    <strong>Envío:</strong> ${order.shipping_address}, ${order.shipping_city} ${order.shipping_zip ? `(ZIP: ${order.shipping_zip})` : ''}
                    ${order.shipping_reference ? `<br><small>Ref: ${order.shipping_reference}</small>` : ''}
                </div>
            ` : ''}
          </div>
        </div>

        <table class="table">
          <thead>
            <tr>
              <th>Descripción Industrial</th>
              <th style="width: 150px;">Proceso</th>
              <th style="width: 200px;">Material</th>
              <th style="text-align: right; width: 180px;">Inversión</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td style="font-weight: 800; color: #0f172a;">
                ${order.job_name || 'Fabricación Digital Bajo Demanda'}<br>
                <span style="font-size: 13px; color: #64748b; font-weight: 500;">Producción 3D Especializada - Prototipado / Final</span>
              </td>
              <td>${order.technology}</td>
              <td>${mat ? mat.name : (order.material_name || order.material_id)}</td>
              <td style="text-align: right; font-weight: 900; color: #0f172a;">$${Number(order.total_price - (order.extras_cost || 0)).toLocaleString()}</td>
            </tr>
            ${(order.extra_items || []).map(extra => `
              <tr>
                <td>${extra.name} (Adicional / Empaque)</td>
                <td>N/A</td>
                <td>${extra.qty} x Und</td>
                <td style="text-align: right; font-weight: 900; color: #0f172a;">$${Number(extra.cost * extra.qty).toLocaleString()}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>

        <div class="rec-container">
          <div class="rec-box">
            <div class="rec-title">Recomendaciones de Fabricacion</div>
            <ul class="rec-list">
              <li><strong>Estructura:</strong> Optimización de perímetros para mayor solidez.</li>
              <li><strong>Acabado:</strong> Altura de capa industrial de alta definición.</li>
              <li><strong>Post-Proceso:</strong> ${order.technology === 'SLA' ? 'Curado UV intensivo para estabilidad química.' : 'Tratamiento térmico para alivio de tensiones.'}</li>
            </ul>
          </div>
          <div class="cert-box">
            <div class="cert-title">Sello de Calidad N3XT</div>
            <p class="cert-text">
                Este documento certifica que el proyecto ha sido analizado bajo los estándares de precisión N3XT. Garantía de estabilidad dimensional y resistencia estructural según ficha técnica del material.
            </p>
          </div>
        </div>

        <div class="total-section">
          <div class="total-box">
            <div class="total-label">Inversión Total del Proyecto</div>
            <div class="total-val-main">$${Number(order.total_price).toLocaleString()}</div>
            <div style="font-size: 11px; margin-top: 10px; opacity: 0.9; font-weight: 700; color: #10b981; text-transform: uppercase;">
              IVA INCLUIDO (${settings.value.margin?.iva || 0}%) • VALIDEZ: 15 DÍAS • ANTICIPO: 50%
            </div>
          </div>
        </div>

        <div class="footer">
          <p style="margin-bottom: 5px; font-weight: 800;">N3XT 3D Administrative System • Soluciones de Manufactura Aditiva de Alta Precisión</p>
          <p>© 2026 ${settings.value.company?.name || 'N3XT 3D SYSTEMS'} - Tecnología Digital Avanzada</p>
          <p>Email: ${settings.value.company?.email || 'ventas@n3xt.com'} • ${settings.value.company?.address || ''}</p>
          <p style="font-size: 8px; opacity: 0.5; margin-top: 10px;">Generado el ${new Date().toLocaleString()} • Copia Digital Autenticada</p>
        </div>
      </body>
    </html>
  `;

  let iframe = document.getElementById('pdf-print-frame');
  if (!iframe) {
      iframe = document.createElement('iframe');
      iframe.id = 'pdf-print-frame';
      iframe.style.position = 'absolute';
      iframe.style.top = '-9999px';
      iframe.style.left = '-9999px';
      iframe.style.visibility = 'hidden';
      document.body.appendChild(iframe);
  }
  const printWindow = iframe.contentWindow || iframe.contentDocument.defaultView;
  printWindow.document.open();
  printWindow.document.write(content);
  printWindow.document.close();
  setTimeout(() => {
      printWindow.focus();
      printWindow.print();
  }, 500);
}



const handleUpdateStock = async (mat) => {
  try {
    if (mat.newStock === null || mat.newStock === undefined || mat.newStock === 0) return
    
    // Cálculo preventivo: Sumamos al stock actual para evitar sobrescritura accidental
    const currentStock = mat.inventory?.stock_available || 0
    const totalNewStock = currentStock + mat.newStock
    
    await api.post(`/materials/${mat.id}/stock`, { stock_available: totalNewStock }, true)
    await fetchInventory()
    
    showNotify(`Stock incrementado: +${mat.newStock}${mat.unit || ''} en ${mat.name}`, 'success')
    mat.newStock = null // Limpiar campo
  } catch (err) {
    showNotify('Error al actualizar stock: ' + err.message, 'error')
  }
}

const handlePrintLabel = (order) => {
  let iframe = document.getElementById('pdf-print-frame');
  if (!iframe) {
    iframe = document.createElement('iframe');
    iframe.id = 'pdf-print-frame';
    iframe.style.position = 'absolute';
    iframe.style.top = '-9999px';
    iframe.style.left = '-9999px';
    iframe.style.visibility = 'hidden';
    document.body.appendChild(iframe);
  }
  
  const content = `
    <html>
      <head>
        <style>
          @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;700;900&display=swap');
          @page { size: 100mm 150mm; margin: 0; }
          body { font-family: 'Outfit', sans-serif; padding: 25px; text-transform: uppercase; color: #000; margin: 0; background: white; }
          .label-box { border: 10px solid #000; padding: 30px; height: 125mm; display: flex; flex-direction: column; position: relative; box-sizing: border-box; }
          .header { border-bottom: 5px solid #000; padding-bottom: 15px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; }
          .logo { font-size: 28px; font-weight: 900; letter-spacing: -1.5px; }
          .order-id { font-size: 16px; font-weight: 900; background: #000; color: #fff; padding: 8px 15px; border-radius: 8px; }
          .destinatario { flex: 1; display: flex; flex-direction: column; justify-content: center; }
          .label-text { font-size: 11px; font-weight: 900; margin-bottom: 8px; color: #666; letter-spacing: 2px; }
          .value-text { font-size: 32px; font-weight: 900; margin-bottom: 25px; line-height: 0.9; }
          .address-box { border-top: 3px solid #eee; padding-top: 20px; }
          .address-text { font-size: 22px; font-weight: 900; line-height: 1.1; margin-bottom: 10px; }
          .city-text { font-size: 18px; font-weight: 700; color: #444; }
          .footer { border-top: 5px solid #000; padding-top: 20px; font-size: 11px; font-weight: 900; display: flex; justify-content: space-between; align-items: flex-end; }
          .qr-placeholder { width: 70px; height: 70px; border: 4px solid #000; display: flex; align-items: center; justify-content: center; font-size: 10px; text-align: center; border-radius: 12px; }
          .stamp { position: absolute; bottom: 120px; right: -40px; transform: rotate(-90deg); font-size: 10px; font-weight: 900; color: #eee; }
        </style>
      </head>
      <body>
        <div class="label-box">
          <div class="stamp">N3XT 3D SYSTEMS INDUSTRIAL LOGISTICS</div>
          <div class="header">
            <div class="logo">N3XT SHIPMENT</div>
            <div class="order-id">#${order.id}</div>
          </div>
          <div class="destinatario">
            <div class="label-text">DESTINATARIO:</div>
            <div class="value-text">${order.customer_name}</div>
            <div class="address-box">
                <div class="label-text">DIRECCIÓN DE ENTREGA:</div>
                <div class="address-text">${order.shipping_address || 'RECOGE EN TALLER'}</div>
                <div class="city-text">${order.shipping_city || ''} ${order.shipping_zip || ''}</div>
                <div class="city-text" style="margin-top: 10px;">TEL: ${order.customer_phone || 'S/D'}</div>
            </div>
          </div>
          <div class="footer">
            <div>
              ORIGEN: N3XT 3D SYSTEMS TALLER<br>
              CONTROL: ${new Date().toLocaleDateString()}<br>
              LOG: ${order.technology} / ${order.estimated_weight_g}G
            </div>
            <div style="background: #000; padding: 8px; border-radius: 15px;">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&color=ffffff&bgcolor=000000&data=${encodeURIComponent(window.location.origin + '/#/track?order_id=' + order.id + '&email=' + (order.customer_email || ''))}" style="width: 80px; height: 80px; display: block;">
            </div>
          </div>
        </div>
      </body>
    </html>
  `;

  const printWindow = iframe.contentWindow || iframe.contentDocument.defaultView;
  printWindow.document.open();
  printWindow.document.write(content);
  printWindow.document.close();
  
  setTimeout(() => {
    printWindow.focus();
    printWindow.print();
  }, 500);
}

const handleEditMaterial = (mat) => {
  editingMaterial.value = { ...mat, ...mat.inventory, id: mat.id }
  modalState.editMaterial = true
}

const updateMaterial = async () => {
  if (submitting.value) return
  submitting.value = true
  try {
    const id = editingMaterial.value.id
    await api.patch(`/materials/${id}`, editingMaterial.value, true)
    modalState.editMaterial = false
    await fetchInventory()
    showNotify('Material actualizado con éxito', 'success')
  } catch (err) {
    showNotify('Error al guardar: ' + err.message, 'error')
  } finally {
    submitting.value = false
  }
}

const addMaterial = async () => {
  if (submitting.value) return
  submitting.value = true
  try {
    await api.post('/materials', newMaterial, true)
    modalState.newMaterial = false
    await fetchInventory()
    showNotify('Material creado correctamente', 'success')
  } catch (err) {
    showNotify('Error al crear material: ' + err.message, 'error')
  } finally {
    submitting.value = false
  }
}

const addPrinter = async () => {
  if (submitting.value) return
  submitting.value = true
  try {
    await api.post('/admin/printers', newPrinter, true)
    modalState.newPrinter = false
    await fetchPrinters()
    showNotify('Impresora añadida', 'success')
  } catch (err) {
    showNotify('Error: ' + err.message, 'error')
  } finally {
    submitting.value = false
  }
}

const handleAssignment = (order) => {
  selectedOrderForPrinter.value = order
  modalState.printerStatus = true
}

const confirmAssignment = async (printerId) => {
  try {
    const orderId = selectedOrderForPrinter.value.id;
    
    // 1. Actualizar Pedido
    await api.patch(`/admin/orders/${orderId}/status`, { 
      status: 'printing',
      printer_id: printerId
    }, true)

    // 2. Actualizar Impresora a OCUPADA
    await api.patch(`/admin/printers/${printerId}`, { 
      status: 'printing'
    }, true)

    modalState.printerStatus = false
    await syncAll(true) 
    showNotify('Produccion Iniciada: Pedido asignado a maquina', 'success')
  } catch (err) {
    showNotify('Fallo en asignacion: ' + err.message, 'error')
  }
}

const handleUpdatePrinterStatus = async ({ id, status }) => {
  try {
    await api.patch(`/admin/printers/${id}`, { status }, true)
    await fetchPrinters()
    showNotify(`Estado actualizado: ${status}`, 'success')
  } catch (err) {
    showNotify('Error: ' + err.message, 'error')
  }
}

const handleMaintenanceComplete = async (id) => {
  try {
    await api.post(`/admin/printers/${id}/maintenance-complete`, {}, true)
    await fetchPrinters()
    showAlert('Puesta a Punto', 'Mantenimiento registrado. Horas reiniciadas.', '')
  } catch (err) {
    showAlert('Error', 'No se pudo registrar el mantenimiento: ' + err.message, '')
  }
}

const handleResetPrinter = async (id) => {
  try {
    await api.post(`/admin/printers/${id}/reset`, {}, true)
    await fetchPrinters()
    showNotify('Máquina reseteada a estado Libre', 'success')
  } catch (err) {
    showNotify('Error: ' + err.message, 'error')
  }
}

const handleDeletePrinter = (id) => {
  askConfirm(
    'Eliminar Impresora',
    '¿Estás seguro de que deseas retirar esta máquina de la granja? Esta acción no se puede deshacer.',
    '',
    async () => {
      try {
        await api.delete(`/admin/printers/${id}`, true)
        await fetchPrinters()
        showNotify('Impresora eliminada', 'success')
      } catch (err) {
        showNotify('Error: ' + err.message, 'error')
      }
    }
  )
}

const saveMaterial = async () => {
  await api.post('/materials', newMaterial, true)
  modalState.newMaterial = false
  await fetchInventory()
}

const saveSettings = (silent = false) => {
  const executeSave = async () => {
    if (savingSettings.value) return
    savingSettings.value = true
    console.log('N3XT Sync: Iniciando proceso de guardado...');
    
    try {
      try {
        if (typeof globalSeoOptimizer === 'function') {
            globalSeoOptimizer()
        }
      } catch (seoErr) {
        console.warn('Advertencia SEO:', seoErr)
      }
      
      const payload = JSON.parse(JSON.stringify(settings.value))
      
      const response = await api.post('/admin/settings', { settings: payload }, true)
      
      if (response) {
        showNotify('Ajustes publicados con exito', 'success')
        if (response.settings) {
           settings.value = { ...settings.value, ...response.settings }
        }
      }
    } catch (err) {
      console.error('Error crítico en guardado:', err)
      showNotify('Error al sincronizar: ' + (err.message || 'Error de red'), 'error')
    } finally {
      savingSettings.value = false
    }
  }

  if (silent) {
    executeSave()
  } else {
    askConfirm(
      'Publicar Cambios',
      '¿Estás seguro de que deseas publicar los ajustes actuales? Esto actualizará la configuración de costos, SEO y catálogo en la web pública.',
      '',
      executeSave
    )
  }
}

// Asistente de Costo por Paquete (Watch)
watch(() => [newMaterial.package_price, newMaterial.package_qty, newMaterial.package_units], () => {
  if (newMaterial.package_price > 0 && newMaterial.package_qty > 0 && newMaterial.package_units > 0) {
    const totalQty = newMaterial.package_qty * newMaterial.package_units
    newMaterial.cost_per_kg = Math.round((newMaterial.package_price / totalQty) * 100) / 100
  }
})

const handleLogoUpload = async (event) => {
  const file = event.target.files[0]
  if (!file) return

  const formData = new FormData()
  formData.append('logo', file)

  uploadingLogo.value = true
  showNotify('SISTEMA N3XT: Actualizando identidad visual...', 'success')

  try {
    const res = await api.post('/admin/settings/logo', formData, true)
    
    // Actualizamos el logo en el estado reactivo
    if (res && res.logo_url) {
      settings.value.company_logo = res.logo_url
      showNotify('IDENTIDAD ACTUALIZADA: Marca sincronizada con éxito', 'success')
    }
  } catch (err) {
    console.error('Error Logo Upload:', err)
    showNotify('FALLO DE IDENTIDAD: ' + err.message, 'error')
  } finally {
    uploadingLogo.value = false
    // Limpiar input para permitir subir el mismo archivo si es necesario
    if (event.target) event.target.value = ''
  }
}




const handleManualOrderSubmit = async () => {
  if (submitting.value) return
  submitting.value = true
  try {
    await api.post('/orders', {
      ...manualOrder,
      estimated_weight_g: manualOrder.weight_g,
      estimated_duration_h: manualOrder.duration_h,
      volume_mm3: (manualOrder.weight_g / 1.25) * 1000,
      cost_snapshot: {
        settings: JSON.parse(JSON.stringify(settings.value)),
        material_cost_per_kg: inventoryData.value.find(m => m.id === manualOrder.material_id)?.cost_per_kg || 0
      }
    }, true)
    modalState.manualOrder = false
    await fetchOrders()
    // Reset form
    Object.assign(manualOrder, { customer_id: '', customer_name: '', customer_id_document: '', customer_email: '', customer_phone: '', material_id: '', weight_g: 0, duration_h: 0, total_price: 0, technology: 'FDM', comments: '' })
    showNotify('Orden manual creada exitosamente', 'success')
  } catch (err) {
    showNotify('Error: ' + err.message, 'error')
  } finally {
    submitting.value = false
  }
}


// Watcher for Manual Order Price
watch(() => [manualOrder.weight_g, manualOrder.qty, manualOrder.duration_h, manualOrder.material_id, manualOrder.technology], () => {
  const mat = inventoryData.value.find(m => m.id === manualOrder.material_id)
  if (!mat || !settings.value.infra) return
  
  // Logic: If it's a material (FDM/SLA), use weight. If it's utility/service, use qty.
  const matCost = mat.type === 'material' 
    ? (manualOrder.weight_g / 1000) * mat.cost_per_kg 
    : (manualOrder.qty || 1) * mat.cost_per_kg
    
  const totalHours = manualOrder.duration_h
  
  const luz = totalHours * (settings.value.infra.load_factor || 0.4) * (settings.value.infra.luz_hr || 0)
  const labor = (totalHours * ((settings.value.prep?.prep_time_pct || 10) / 100)) * (settings.value.prep?.mano_obra_hr || 0)
  const depr = totalHours * (settings.value.infra.depr_hr || 0)
  const mant = totalHours * (settings.value.infra.mant_hr || 0)
  
  // Base Production Cost
  const extrasCost = (manualOrder.extra_items || []).reduce((acc, i) => acc + (i.cost * i.qty), 0)
  const productionCost = matCost + luz + labor + depr + mant + (manualOrder.etiquetas || 0) + extrasCost
  
  // Margins (Following Simulator Logic)
  const logistics = productionCost * (manualOrder.transporte_pct / 100)
  const marketing = productionCost * (manualOrder.marketing_pct / 100)
  const failures = productionCost * (manualOrder.fallos_pct / 100)
  const profitAmount = productionCost * (manualOrder.profit_pct / 100)
  
  const subtotal = productionCost + logistics + marketing + failures + profitAmount
  const iva = subtotal * (settings.value.margin?.iva / 100 || 0)
  
  manualOrder.total_price = Math.round(subtotal + iva)
}, { deep: true })

const handleExportCSV = async () => {
  try {
    const token = localStorage.getItem('n3xt_admin_token')
    const response = await fetch(`${api.baseUrl}/admin/export-csv`, {
      headers: {
        'Authorization': `Bearer ${token}`
      }
    });
    
    if (!response.ok) throw new Error('Error al generar el reporte');
    
    const blob = await response.blob();
    const fileBlob = new Blob([blob], { type: 'text/csv;charset=utf-8' });
    const url = window.URL.createObjectURL(fileBlob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `Reporte_N3XT_${new Date().toLocaleDateString().replace(/\//g, '-')}.csv`;
    document.body.appendChild(a);
    a.click();
    setTimeout(() => {
      document.body.removeChild(a);
      window.URL.revokeObjectURL(url);
    }, 100);
    showNotify('Reporte generado con éxito', 'success');
  } catch (err) {
    showNotify('Error al exportar: ' + err.message, 'error');
  }
}

// Eliminado duplicado de handleTogglePaid para usar la versión híbrida del inicio del script

// --- Computed & Logic ---

const openOrderDetails = (order) => {
  // Integrity Logic: Use snapshot if available, fallback to current settings
  const hasSnapshot = order.cost_snapshot && order.cost_snapshot.settings
  const snap = hasSnapshot ? order.cost_snapshot : null
  
  const currentMat = inventoryData.value.find(m => m.id === order.material_id)
  const matCostPerKg = hasSnapshot ? snap.material_cost_per_kg : (currentMat ? currentMat.cost_per_kg : 0)
  
  const s = hasSnapshot ? snap.settings : settings.value
  
  const totalHours = parseFloat(order.estimated_duration_h) || 0
  const luz = totalHours * 0.4 * (s.infra?.luz_hr || 0)
  const labor = (totalHours * 0.1) * (s.prep?.mano_obra_hr || 0)
  const depr = totalHours * (s.infra?.depr_hr || 0)
  const mant = totalHours * (s.infra?.mant_hr || 0)
  const etiquetas = parseFloat(s.infra?.etiquetas || 0)
  
  const materialCost = (order.estimated_weight_g / 1000) * matCostPerKg
  const extrasCost = parseFloat(order.extras_cost) || 0
  const productionCost = materialCost + luz + labor + depr + mant + etiquetas + extrasCost

  selectedOrderDetails.value = {
    ...order,
    breakdown: {
      material: materialCost,
      luz: luz,
      labor: labor,
      depr: depr,
      mant: mant,
      etiquetas: etiquetas,
      extras: extrasCost,
      total_cost: productionCost,
      margin: Number(order.total_price) - productionCost
    }
  }
  modalState.orderDetails = true
}

const statusSteps = [
  { id: 'pending', label: 'Recibido', desc: 'En cola de revision.', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
  { id: 'printing', label: 'Impresion', desc: 'En maquina.', icon: 'M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z' },
  { id: 'post-processing', label: 'Acabado', desc: 'Limpieza y Curado.', icon: 'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.618.309a6 6 0 01-3.86.517l-2.387-.477a2 2 0 00-1.022.547l-1.16 1.16a2 2 0 00.442 3.102l1.644.822a6 6 0 005.366 0l1.644-.822a2 2 0 00.442-3.102l-1.16-1.16zM15.75 9a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z' },
  { id: 'completed', label: 'Terminado', desc: 'Listo para entrega.', icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' },
  { id: 'shipped', label: 'Enviado', desc: 'En camino al cliente.', icon: 'M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6' }
]

const getStatusIndex = (status) => {
  const mapping = {
    'pending': 0,
    'printing': 1,
    'post-processing': 2,
    'completed': 3,
    'shipped': 4
  }
  return mapping[status] ?? -1
}

const showAddExtraForm = ref(false)
const newExtra = ref({ material_id: '', qty: 1 })

const handleAddExtra = async () => {
  if (!newExtra.value.material_id || newExtra.value.qty <= 0) return;
  
  const submitExtra = async () => {
    try {
      await api.post(`/admin/orders/${selectedOrderDetails.value.id}/extras`, newExtra.value, true)
      await fetchOrders()
      const updated = orders.value.find(o => o.id === selectedOrderDetails.value.id)
      if (updated) {
        selectedOrderDetails.value = JSON.parse(JSON.stringify(updated))
        openOrderDetails(selectedOrderDetails.value)
      }
      showAddExtraForm.value = false
      newExtra.value = { material_id: '', qty: 1 }
      showNotify('Extra agregado e inventario actualizado', 'success')
    } catch (err) {
      showNotify('Error: ' + err.message, 'error')
    }
  }

  // Prevención de Duplicados
  if (selectedOrderDetails.value.extra_items) {
    const existing = selectedOrderDetails.value.extra_items.find(e => e.material_id === newExtra.value.material_id)
    if (existing) {
      askConfirm(
        'Ítem Duplicado',
        `El consumible "${existing.name}" ya está registrado. ¿Deseas añadirlo nuevamente?`,
        '',
        submitExtra
      )
      return
    }
  }

  await submitExtra()
}

const handleCreateCatalogProduct = () => {
  const newProduct = {
    name: '',
    category: 'General',
    subcategory: '',
    price: '0',
    original_price: '0',
    image: '',
    images: [],
    description: '',
    status: 'active'
  }
  settings.value.web.catalog.unshift(newProduct)
  editingCatalogItemIndex.value = 0
  showNotify('Nuevo producto creado en el borrador del catalogo', 'success')
}

const handleResetCatalog = () => {
  askConfirm(
    'Reiniciar Catalogo',
    '¿Estás seguro de que deseas restablecer el catálogo? Se eliminarán todos los productos y podrás empezar de cero.',
    '',
    () => {
      settings.value.web.catalog = []
      showNotify('Catálogo limpio. Crea tus productos desde cero.', 'warning')
    }
  )
}

const isDiscounted = (item) => {
  if (!item.price || !item.original_price) return false
  const p = parseFloat(String(item.price).replace(/[^0-9.-]+/g,""))
  const op = parseFloat(String(item.original_price).replace(/[^0-9.-]+/g,""))
  return p < op && p > 0
}

const scrollToCatalogItem = (index) => {
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

const groupedCatalog = computed(() => {
  const groups = {}
  const catalog = settings.value?.web?.catalog || []
  
  catalog.forEach((item, index) => {
    if (!item) return
    const cat = item.category || 'General'
    if (!groups[cat]) groups[cat] = []
    groups[cat].push({ ...item, originalIndex: index })
  })
  return groups
})


const stats = computed(() => {
  return {
    totalSales: analyticsData.value?.summary?.total_revenue || orders.value.filter(o => o.status !== 'cancelled').reduce((acc, o) => acc + (parseFloat(o.total_price) || 0), 0),
    activeOrders: orders.value.filter(o => ['pending', 'printing', 'ready'].includes(o.status)).length,
    inventoryValue: inventoryData.value.reduce((acc, item) => acc + (item.stock_available * (item.cost_per_kg / 1000)), 0)
  }
})

const simulatedResult = computed(() => {
  const mat = inventoryData.value.find(m => m.id === simulator.material_id)
  if (!mat || !settings.value.infra) return { 
    material: 0, luz: 0, labor: 0, depr: 0, mant: 0, etiquetas: 0, extras: 0,
    production: 0, subtotal: 0, iva: 0, total: 0, unit_price: 0, discount: 0,
    isSafetyAlert: false,
    pcts: { material: 0, infra: 0, extras: 0, profit: 0 }
  }
  
  // 1. Costo Material (El peso ingresado ya es el total del lote/placa)
  const qty = Math.max(1, simulator.pieces_per_batch || 1)
  const matCost = (simulator.weight_g / 1000) * mat.cost_per_kg
  
  // 2. Costo Infraestructura (El tiempo ingresado ya es el total del lote/placa)
  const [hours, minutes] = simulator.time_str.split(':').map(Number)
  const totalHours = (hours || 0) + ((minutes || 0) / 60)
  
  const luz = totalHours * (settings.value.infra.load_factor || 0.4) * (settings.value.infra.luz_hr || 0)
  const labor = (totalHours * ((settings.value.prep?.prep_time_pct || 10) / 100)) * (settings.value.prep?.mano_obra_hr || 0)
  const depr = totalHours * (settings.value.infra.depr_hr || 0)
  const mant = totalHours * (settings.value.infra.mant_hr || 0)
  const etiquetas = Number(simulator.etiquetas || 0) // Costo base por pedido
  
  // 3. Adicionales y Empaque (Ya vienen multiplicados por su propia cantidad interna)
  const extrasCost = simulator.extra_items.reduce((acc, item) => acc + (item.cost * item.qty), 0)
  
  // 4. Costo de Producción Total (Para el lote completo)
  const productionCost = matCost + luz + labor + depr + mant + etiquetas + extrasCost
  
  // 5. Márgenes Operativos y Ganancia (Sobre el costo total del lote)
  const logistics = productionCost * (simulator.transporte_pct / 100)
  const marketing = productionCost * (simulator.marketing_pct / 100)
  const failures = productionCost * (simulator.fallos_pct / 100)
  const profitAmount = productionCost * (simulator.profit_pct / 100)
  
  // 6. Precio de Lista del Lote
  const listPrice = productionCost + logistics + marketing + failures + profitAmount
  
  // 7. Descuento Especial
  const discountAmount = listPrice * (simulator.discount_pct / 100)
  const subtotal = Math.max(listPrice - discountAmount, productionCost)
  const effectiveDiscount = listPrice - subtotal
  
  // 8. IVA
  const iva = subtotal * (settings.value.margin?.iva / 100 || 0)
  
  const totalGrand = subtotal + iva
  
  return {
    material: matCost,
    luz,
    labor,
    depr,
    mant,
    etiquetas,
    extras: extrasCost,
    production: productionCost,
    logistics,
    marketing,
    failures,
    profit: profitAmount,
    subtotal: Math.round(subtotal),
    discount: Math.round(effectiveDiscount),
    iva: Math.round(iva),
    total: Math.round(totalGrand),
    unit_price: Math.round(totalGrand / qty),
    total_hours: totalHours,
    profit_margin_pct: productionCost > 0 ? ((subtotal - productionCost) / subtotal) * 100 : 0,
    isSafetyAlert: simulator.discount_pct === 100,
    pcts: {
        material: subtotal > 0 ? (matCost / subtotal) * 100 : 0,
        infra: subtotal > 0 ? ((luz + labor + depr + mant + etiquetas) / subtotal) * 100 : 0,
        extras: subtotal > 0 ? (extrasCost / subtotal) * 100 : 0,
        profit: subtotal > 0 ? (Math.max(0, subtotal - productionCost) / subtotal) * 100 : 0
    }
  }
})
const handleConvertSimulationToOrder = async () => {
  if (submitting.value) return
  submitting.value = true
  try {
    const material = inventoryData.value.find(m => m.id === simulator.material_id)
    const density = material?.density || 1.25
    const estimatedVolume = (simulator.weight_g / density) * 1000

    const qty = Math.max(1, simulator.pieces_per_batch || 1)
    const totalHours = (parseFloat(simulator.time_str.split(':')[0]) || 0) + ((parseFloat(simulator.time_str.split(':')[1]) || 0) / 60)

    await api.post('/orders', {
      customer_id: simulator.customer_id || null,
      customer_name: simulator.customer_name || 'Cliente Simulación',
      customer_id_document: simulator.customer_id_document || '',
      customer_email: simulator.customer_email || '',
      customer_phone: simulator.customer_phone || '',
      material_id: simulator.material_id,
      volume_mm3: estimatedVolume,
      estimated_weight_g: simulator.weight_g,
      estimated_duration_h: totalHours,
      total_price: simulatedResult.value.total,
      technology: inventoryData.value.find(m => m.id === simulator.material_id)?.category || 'FDM',
      comments: `SIMULACIÓN N3XT: ${simulator.job_name || 'Proyecto'}. Lote: ${qty} pzs.` + (simulator.comments ? `\n\nNOTAS INTERNAS:\n${simulator.comments}` : ''),
      status: 'pending',
      qty: qty,
      extra_items: simulator.extra_items.map(e => ({ material_id: e.id, qty: e.qty })),
      cost_snapshot: {
        settings: JSON.parse(JSON.stringify(settings.value)),
        material_cost_per_kg: material?.cost_per_kg || 0,
        material_density: density
      }
    }, true)
    
    modalState.simulator = false
    await fetchOrders()
    
    // Reset Simulator to prevent duplicates
    Object.assign(simulator, { 
        customer_id: '', job_name: '', customer_name: '', customer_company: '', 
        customer_id_document: '', material_id: '', weight_g: 0, time_str: '0:00', 
        extra_items: [], comments: '' 
    })
    
    showNotify('¡Orden Creada! La simulación ahora es un pedido real.', 'success')
  } catch (err) {
    console.error('Error Conversion:', err)
    showNotify('Error al convertir: ' + err.message, 'error')
  } finally {
    submitting.value = false
  }
}

const addSimulatorExtra = (event) => {
  const matId = event.target.value
  if (!matId) return
  const item = inventoryData.value.find(m => m.id === matId)
  if (item) {
    // Si ya existe, incrementar cantidad
    const existing = simulator.extra_items.find(i => i.id === item.id)
    if (existing) {
        existing.qty++
    } else {
        simulator.extra_items.push({
            id: item.id,
            name: item.name,
            cost: item.cost_per_kg,
            unit: item.unit,
            qty: 1
        })
    }
  }
  event.target.value = "" // Reset select
}

const removeSimulatorExtra = (index) => {
  simulator.extra_items.splice(index, 1)
}

// --- Lifecycle & Watchers ---

onMounted(async () => {
  document.body.style.overflow = 'hidden'
  updateTheme()
  await syncAll()
  
  // Motor de Sincronización Proactiva: Cada 5 min intenta un silent sync si hay conexión
  setInterval(async () => {
    try {
      await syncAll(true)
    } catch (e) {
      // Fallo silencioso, no interrumpir al usuario
    }
  }, 1000 * 60 * 5)
  
  const currentToken = localStorage.getItem('n3xt_admin_token')
  if (!currentToken) {
    router.push('/admin/login')
    return
  }

  // --- Auto-Open Order from URL (Admin Bridge) ---
  const route = useRoute()
  if (route.query.open_order) {
    setTimeout(async () => {
      const orderToOpen = orders.value.find(o => String(o.id) === String(route.query.open_order))
      if (orderToOpen) {
        openOrderDetails(orderToOpen)
      } else {
        // Si no está cargado aún, intentamos fetch directo
        try {
            const res = await api.get(`/admin/orders/${route.query.open_order}`, true)
            if (res) openOrderDetails(res)
        } catch (e) {}
      }
    }, 1000)
  }
  
  try {
    await syncAll()
  } catch (err) {
    if (err.message.includes('401')) {
       showNotify('Sesion expirada. Por favor ingresa de nuevo.', 'warning')
       logout()
    } else {
       console.error('Error inicial de carga:', err)
    }
  }
})

onUnmounted(() => {
  document.body.style.overflow = ''
})

watch(activeTab, (tab) => {
  if (tab === 'accounting') fetchAnalytics()
  // Asegurar que cerramos modales al cambiar de pestaña para evitar bloqueos
  Object.keys(modalState).forEach(k => modalState[k] = false)
})

const logout = () => {
  localStorage.removeItem('n3xt_admin_token')
  router.push('/admin/login')
}

// Eliminado duplicado de downloadFile para usar la versión híbrida del inicio del script

const orderToPrint = ref(null)

const handleGeneratePDF = (order) => {
  // Redirigimos al generador industrial en ventana nueva para evitar bloqueos del hilo principal
  handleDownloadQuotePDF(order)
}

const handleExportReport = async ({ type, start, end, filteredOrders }) => {
  loadingAnalytics.value = true
  try {
    const reportData = await api.get(`/admin/analytics?start_date=${start}&end_date=${end}`, true)
    
    let iframe = document.getElementById('pdf-print-frame');
    if (!iframe) {
      iframe = document.createElement('iframe');
      iframe.id = 'pdf-print-frame';
      iframe.style.position = 'absolute';
      iframe.style.top = '-9999px';
      iframe.style.left = '-9999px';
      iframe.style.visibility = 'hidden';
      document.body.appendChild(iframe);
    }
    
    const company = settings.value.company || { name: 'N3XT 3D Technology', nit: 'N/A' };
    const companyLogo = settings.value.company_logo ? (settings.value.company_logo.startsWith('http') ? settings.value.company_logo : api.storageUrl + '/' + settings.value.company_logo) : '/logo.png';
    let content = '';

    if (type === 'metrics') {
      
      // EXECUTIVE MANUFACTURING AUDIT (PREMIUM REDESIGN)
      content = `
        <html>
          <head>
            <title>N3XT 3D - Balance Ejecutivo [${start || 'HISTÓRICO'}]</title>
            <style>
              @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;900&display=swap');
              @page { size: A4; margin: 10mm; }
              
              body { 
                font-family: 'Outfit', sans-serif; 
                padding: 0; 
                margin: 0;
                color: #0f172a; 
                background: #fff; 
                line-height: 1.2; 
                font-size: 11px; 
              }

              /* Layout Containers */
              .page-wrapper { padding: 30px; }
              
              /* Premium Header */
              .header-bar { 
                display: flex; 
                justify-content: space-between; 
                align-items: flex-start; 
                border-bottom: 2px solid #f1f5f9;
                padding-bottom: 25px;
                margin-bottom: 30px;
              }
              
              .brand-box { display: flex; align-items: center; gap: 15px; }
              .logo-img { height: 55px; width: auto; object-fit: contain; }
              .brand-info h1 { 
                font-size: 26px; 
                font-weight: 900; 
                letter-spacing: -1.5px; 
                margin: 0; 
                text-transform: uppercase;
                color: #0f172a;
              }
              .brand-info p { 
                font-size: 8px; 
                font-weight: 800; 
                color: #64748b; 
                text-transform: uppercase; 
                letter-spacing: 3px; 
                margin: 4px 0 0 0;
              }

              .audit-info { text-align: right; }
              .status-badge {
                display: inline-block;
                background: #1e3a34;
                color: white;
                padding: 6px 12px;
                border-radius: 8px;
                font-size: 9px;
                font-weight: 900;
                text-transform: uppercase;
                letter-spacing: 1px;
                margin-bottom: 10px;
              }
              .period-label { font-size: 8px; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-bottom: 2px; }
              .period-val { font-size: 11px; font-weight: 900; color: #1e293b; }

              /* Hero Metrics Cards */
              .hero-grid { 
                display: grid; 
                grid-template-cols: repeat(4, 1fr); 
                gap: 15px; 
                margin-bottom: 30px; 
              }
              .card { 
                padding: 20px; 
                border-radius: 24px; 
                border: 1px solid #f1f5f9;
                background: #f8fafc;
              }
              .card.dark { background: #0f172a; color: white; border: none; }
              .card.emerald { background: #1e3a34; color: white; border: none; }
              
              .card-label { 
                font-size: 7px; 
                font-weight: 900; 
                text-transform: uppercase; 
                letter-spacing: 1.5px; 
                margin-bottom: 8px; 
                opacity: 0.7;
              }
              .card-val { font-size: 24px; font-weight: 900; letter-spacing: -0.5px; }
              .card-sub { font-size: 8px; font-weight: 700; margin-top: 5px; opacity: 0.6; }

              /* Main Content Grid */
              .content-grid { 
                display: grid; 
                grid-template-cols: 1fr 1.6fr; 
                gap: 30px; 
              }

              /* Section Styling */
              .section { margin-bottom: 25px; }
              .section-header { 
                display: flex; 
                align-items: center; 
                gap: 8px;
                margin-bottom: 15px;
                border-bottom: 1px solid #f1f5f9;
                padding-bottom: 8px;
              }
              .dot { width: 6px; height: 6px; border-radius: 50%; background: #1e3a34; }
              .section-title { 
                font-size: 9px; 
                font-weight: 900; 
                text-transform: uppercase; 
                letter-spacing: 2px; 
                color: #0f172a;
              }

              /* Data Lists */
              .data-item { 
                display: flex; 
                justify-content: space-between; 
                align-items: center;
                padding: 10px 0; 
                border-bottom: 1px solid #f8fafc;
              }
              .data-item:last-child { border: none; }
              .item-label { font-weight: 600; color: #64748b; font-size: 10px; }
              .item-val { font-weight: 900; color: #0f172a; font-size: 11px; }
              .item-val.neg { color: #ef4444; }
              .item-val.pos { color: #059669; }

              /* Nested items for breakdown */
              .sub-item { padding-left: 15px; opacity: 0.8; font-size: 9px; padding-top: 5px; padding-bottom: 5px; }
              .sub-item .item-label { font-weight: 400; font-size: 9px; }

              /* Tables */
              .audit-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
              .audit-table th { 
                text-align: left; 
                background: #f1f5f9; 
                padding: 12px; 
                font-weight: 900; 
                color: #64748b; 
                text-transform: uppercase; 
                font-size: 7px;
                letter-spacing: 1px;
                border-radius: 8px 0 0 8px;
              }
              .audit-table th:last-child { border-radius: 0 8px 8px 0; }
              .audit-table td { padding: 12px; border-bottom: 1px solid #f1f5f9; }
              .audit-table tr:last-child td { border: none; }

              /* Footer */
              .footer { 
                margin-top: 40px; 
                padding-top: 20px; 
                border-top: 1px solid #f1f5f9;
                display: flex;
                justify-content: space-between;
                align-items: flex-end;
              }
              .sys-stamp { font-size: 7px; font-weight: 800; color: #94a3b8; line-height: 1.5; }
              .sign-box { width: 180px; text-align: center; }
              .sign-line { border-top: 1.5px solid #0f172a; margin-top: 15px; padding-top: 5px; font-weight: 900; font-size: 8px; text-transform: uppercase; }

              @media print {
                body { -webkit-print-color-adjust: exact; }
                .card.dark { background-color: #0f172a !important; color: white !important; }
                .card.emerald { background-color: #1e3a34 !important; color: white !important; }
                .status-badge { background-color: #1e3a34 !important; color: white !important; }
              }
            </style>
          </head>
          <body>
            <div class="page-wrapper">
              <!-- Header -->
              <div class="header-bar">
                <div class="brand-box">
                  ${companyLogo ? `<img src="${companyLogo}" class="logo-img">` : ''}
                  <div class="brand-info">
                    <h1>${settings.value.company?.name || 'N3XT 3D'}</h1>
                    <p>
                      ${settings.value.company?.nit ? `NIT: ${settings.value.company.nit} | ` : ''} 
                      ${settings.value.company?.address || ''}<br>
                      ${settings.value.company?.phone ? `TEL: ${settings.value.company.phone} | ` : ''} 
                      ${settings.value.company?.email || ''}
                    </p>
                  </div>
                </div>
                <div class="audit-info">
                  <div class="status-badge">Reporte de Auditoría Verificado</div>
                  <div class="period-label">Intervalo de Análisis</div>
                  <div class="period-val">${start || 'ORIGEN'} AL ${end || 'HOY'}</div>
                </div>
              </div>

              <!-- Hero Metrics -->
              <div class="hero-grid">
                <div class="card">
                  <div class="card-label">Ingresos Brutos</div>
                  <div class="card-val">$${Number(reportData.summary.total_revenue).toLocaleString()}</div>
                  <div class="card-sub">${reportData.summary.orders_count} órdenes procesadas</div>
                </div>
                <div class="card">
                  <div class="card-label">Gastos de Operación</div>
                  <div class="card-val" style="color: #ef4444;">$${Number(reportData.summary.total_expenses).toLocaleString()}</div>
                  <div class="card-sub">Costo de producción base</div>
                </div>
                <div class="card emerald">
                  <div class="card-label">Utilidad Neta Real</div>
                  <div class="card-val">$${Number(reportData.summary.net_profit).toLocaleString()}</div>
                  <div class="card-sub" style="color: #4ade80;">Flujo de caja positivo</div>
                </div>
                <div class="card">
                  <div class="card-label">Margen de Beneficio</div>
                  <div class="card-val">${reportData.summary.profit_margin_pct}%</div>
                  <div class="card-sub">Retorno sobre inversión</div>
                </div>
              </div>

              <!-- Content Sections -->
              <div class="content-grid">
                <div class="left-col">
                  <!-- Manufacturing Volume -->
                  <div class="section">
                    <div class="section-header"><div class="dot"></div><div class="section-title">Actividad de Taller</div></div>
                    <div class="data-item"><span class="item-label">Horas Máquina</span><span class="item-val">${reportData.summary.total_hours} H</span></div>
                    <div class="data-item"><span class="item-label">Masa Total Procesada</span><span class="item-val">${reportData.summary.total_weight_kg} KG</span></div>
                    <div class="data-item"><span class="item-label">Tasa de Entrega</span><span class="item-val">${reportData.summary.completed_count} / ${reportData.summary.orders_count}</span></div>
                  </div>

                  <!-- Waste Management -->
                  <div class="section">
                    <div class="section-header"><div class="dot" style="background: #ef4444;"></div><div class="section-title">Control de Pérdidas</div></div>
                    <div class="data-item"><span class="item-label">Costo por Fallos</span><span class="item-val neg">$${Number(reportData.summary.waste_cost).toLocaleString()}</span></div>
                    <div class="data-item"><span class="item-label">Material Desperdiciado</span><span class="item-val">${reportData.summary.waste_weight_g} g</span></div>
                  </div>

                  <!-- Technologies -->
                  <div class="section">
                    <div class="section-header"><div class="dot" style="background: #64748b;"></div><div class="section-title">Mix Tecnológico</div></div>
                    ${reportData.by_technology.map(tech => `
                      <div class="data-item">
                        <span class="item-label">${tech.technology === 'FDM' ? 'Filamento (FDM)' : 'Resina (SLA)'}</span>
                        <span class="item-val">${tech.count} trabajos</span>
                      </div>
                    `).join('')}
                  </div>
                </div>

                <div class="right-col">
                  <!-- Detailed Financials -->
                  <div class="section">
                    <div class="section-header"><div class="dot"></div><div class="section-title">Estado de Gastos Detallado</div></div>
                    
                    <div class="data-item"><span class="item-label">Materia Prima e Insumos</span><span class="item-val">$${Number(reportData.summary.total_material_cost).toLocaleString()}</span></div>
                    <div class="data-item sub-item"><span class="item-label">Consumo FDM</span><span class="item-val">$${Number(reportData.summary.breakdown.mat_fdm).toLocaleString()}</span></div>
                    <div class="data-item sub-item"><span class="item-label">Consumo SLA</span><span class="item-val">$${Number(reportData.summary.breakdown.mat_sla).toLocaleString()}</span></div>
                    
                    <div class="data-item"><span class="item-label">Mano de Obra Especializada</span><span class="item-val">$${Number(reportData.summary.breakdown.labor).toLocaleString()}</span></div>
                    <div class="data-item"><span class="item-label">Costos de Energía y Luz</span><span class="item-val">$${Number(reportData.summary.breakdown.luz).toLocaleString()}</span></div>
                    <div class="data-item"><span class="item-label">Plan de Mantenimiento</span><span class="item-val">$${Number(reportData.summary.breakdown.mant).toLocaleString()}</span></div>
                    <div class="data-item"><span class="item-label">Depreciación Activos</span><span class="item-val">$${Number(reportData.summary.breakdown.depr).toLocaleString()}</span></div>
                    <div class="data-item"><span class="item-label">Logística y Extras</span><span class="item-val">$${Number(reportData.summary.total_extras_cost).toLocaleString()}</span></div>
                  </div>

                  <!-- Top Customers -->
                  <div class="section">
                    <div class="section-header"><div class="dot"></div><div class="section-title">Contribuyentes Principales (LTV)</div></div>
                    <table class="audit-table">
                      <thead>
                        <tr>
                          <th>Identificación Cliente</th>
                          <th style="text-align: right">Contribución Total</th>
                        </tr>
                      </thead>
                      <tbody>
                        ${reportData.top_customers.slice(0, 5).map(c => `
                          <tr>
                            <td style="font-weight: 700;">${c.customer_name} <span style="font-size: 7px; color: #94a3b8; display: block;">${c.orders_count} órdenes registradas</span></td>
                            <td style="text-align: right; font-weight: 900; color: #1e3a34; font-size: 12px;">$${Number(c.total_spent).toLocaleString()}</td>
                          </tr>
                        `).join('')}
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>

              <!-- Footer -->
              <div class="footer">
                <div class="sys-stamp">
                  SISTEMA DE GESTIÓN N3XT CORE | VERSIÓN 3.2.4<br>
                  ID ÚNICO DE AUDITORÍA: ${Math.random().toString(36).substr(2, 9).toUpperCase()}<br>
                  EMISIÓN: ${new Date().toLocaleString()}
                </div>
                <div class="sign-box">
                  <div style="font-size: 7px; color: #94a3b8; font-weight: 700; margin-bottom: 20px;">VERIFICACIÓN DIGITAL REQUERIDA</div>
                  <div class="sign-line">Firma Autorizada Auditoría</div>
                  <div style="font-size: 8px; font-weight: 600; margin-top: 4px;">${company.name}</div>
                </div>
              </div>
            </div>
          </body>
        </html>
      `;
    } else {
      // LIBRO DE VENTAS PREMIUM (CLEAN LEDGER AESTHETIC)
      content = `
        <html>
          <head>
            <title>N3XT 3D - Libro de Ventas [${start || 'General'}]</title>
            <style>
              @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap');
              body { font-family: 'Inter', sans-serif; padding: 40px; color: #1e293b; background: #fff; }
              
              .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #f1f5f9; padding-bottom: 25px; margin-bottom: 35px; }
              .logo-img { height: 50px; width: auto; object-fit: contain; margin-right: 15px; }
              .header-left { display: flex; align-items: center; }
              .logo { font-size: 22px; font-weight: 900; letter-spacing: -1px; text-transform: uppercase; color: #0f172a; line-height: 1; }
              .logo span { color: #94a3b8; }
              .doc-title { font-size: 10px; font-weight: 900; color: #64748b; text-transform: uppercase; letter-spacing: 4px; margin-bottom: 8px; }
              
              table { width: 100%; border-collapse: collapse; font-size: 10px; }
              th { text-align: left; background: #f8fafc; padding: 15px 12px; text-transform: uppercase; font-weight: 900; color: #64748b; border-bottom: 2px solid #e2e8f0; letter-spacing: 1px; }
              td { padding: 15px 12px; border-bottom: 1px solid #f1f5f9; font-weight: 600; color: #334155; }
              tr:nth-child(even) { background: #fcfdfe; }
              
              .id-cell { font-family: monospace; font-size: 11px; color: #94a3b8; font-weight: 900; }
              .price-cell { font-weight: 900; text-align: right; color: #0f172a; font-size: 11px; }
              .tech-badge { background: #f1f5f9; padding: 4px 8px; border-radius: 6px; font-size: 8px; font-weight: 900; text-transform: uppercase; }
              
              .summary-row { background: #0f172a !important; color: white !important; }
              .summary-row td { border: none; padding: 20px 12px; }
              
              .footer { margin-top: 50px; font-size: 9px; color: #94a3b8; font-weight: 700; display: flex; justify-content: space-between; }
            </style>
          </head>
          <body>
            <div class="header">
              <div class="header-left">
                ${companyLogo ? `<img src="${companyLogo}" class="logo-img">` : ''}
                <div>
                  <div class="doc-title">Reporte de Libro Mayor</div>
                  <div class="logo">N3XT<span> 3D</span></div>
                </div>
              </div>
              <div style="text-align: right">
                <div style="font-weight: 900; font-size: 14px;">${filteredOrders.length} ÓRDENES</div>
                <div style="font-weight: 600; font-size: 10px; color: #94a3b8;">FILTRO: ${start || 'TODOS'} / ${end || 'TODOS'}</div>
                <div style="font-size: 7px; color: #10b981; font-weight: 900; margin-top: 5px; text-transform: uppercase;">Auditoria Financiera Validada</div>
              </div>
            </div>

            <table>
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Fecha</th>
                  <th>Cliente</th>
                  <th>Proyecto / Trabajo</th>
                  <th>Tec</th>
                  <th>Masa</th>
                  <th style="text-align: right">Monto Total</th>
                </tr>
              </thead>
              <tbody>
                ${filteredOrders.map(o => `
                  <tr>
                    <td class="id-cell">#${String(o.id).padStart(5, '0')}</td>
                    <td>${new Date(o.created_at).toLocaleDateString()}</td>
                    <td style="color: #0f172a; font-weight: 700;">${o.customer_name}</td>
                    <td style="color: #64748b;">${o.job_name || 'Servicio de Impresión'}</td>
                    <td><span class="tech-badge">${o.technology}</span></td>
                    <td>${o.estimated_weight_g}g</td>
                    <td class="price-cell">$${Number(o.total_price).toLocaleString()}</td>
                  </tr>
                `).join('')}
                <tr class="summary-row">
                  <td colspan="6" style="text-align: right; font-weight: 900; text-transform: uppercase; letter-spacing: 2px; font-size: 10px;">Venta Total del Periodo</td>
                  <td style="text-align: right; font-size: 18px; font-weight: 900;">$${filteredOrders.reduce((acc, o) => acc + parseFloat(o.total_price), 0).toLocaleString()}</td>
                </tr>
              </tbody>
            </table>

            <div class="footer">
              <div>EMITIDO POR SISTEMA N3XT | ID: ${Math.random().toString(36).substr(2, 9).toUpperCase()}</div>
              <div>${company.name} | NIT: ${company.nit} | PÁGINA 1 DE 1</div>
            </div>
          </body>
        </html>
      `;
    }
    
    const printWindow = iframe.contentWindow || iframe.contentDocument.defaultView;
    printWindow.document.open();
    printWindow.document.write(content);
    printWindow.document.close();
    setTimeout(() => { 
      printWindow.focus();
      printWindow.print(); 
    }, 500);
    
  } catch (err) {
    console.error('Error:', err);
    showNotify('Error: ' + err.message, 'error')
  } finally {
    loadingAnalytics.value = false;
  }
}

const getSimulatorUnit = () => {
  if (!simulator.material_id) return 'Peso (Gramos)'
  const mat = (inventoryData.value || []).find(m => String(m.id) === String(simulator.material_id))
  return mat?.category === 'SLA' ? 'Volumen (ML)' : 'Peso (Gramos)'
}

const handlePurgeAll = () => {
  askConfirm(
    'PELIGRO: PURGA DE PRODUCCIÓN',
    'Esta acción eliminará de forma irreversible todos los pedidos, materiales, inventarios, clientes y maquinaria del sistema. Volverá el estado a CERO. ¿Estás seguro?',
    'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
    async () => {
      try {
        await api.post('/admin/purge-all');
        showNotify('SISTEMA PURGADO EXITOSAMENTE. ENTORNO CERO INICIADO.', 'success');
        orders.value = [];
        inventoryData.value = [];
        printers.value = [];
        contacts.customers = [];
        contacts.suppliers = [];
        syncAll(true);
      } catch (err) {
        showNotify('Error al purgar el sistema: ' + err.message, 'error');
      }
    }
  );
}
</script>

<template>
  <div :class="{'dark': isDark}" class="min-h-screen bg-[#f8fafc] dark:bg-[#0a0f14] text-gray-900 dark:text-white flex overflow-hidden font-sans selection:bg-primary/20 transition-colors duration-500">
    
    <!-- Background Industrial Engine -->
    <div class="fixed inset-0 technical-grid opacity-20 dark:opacity-10 pointer-events-none z-0"></div>
    <div class="fixed top-[-10%] right-[-10%] w-[600px] h-[600px] bg-primary/10 rounded-full blur-[180px] pointer-events-none z-0"></div>

    <!-- Backdrop for mobile sidebar -->
    <div 
      v-if="isSidebarOpen" 
      @click="isSidebarOpen = false" 
      class="fixed inset-0 bg-black/60 dark:bg-black/80 backdrop-blur-xs z-40 md:hidden transition-all duration-300"
    ></div>

    <!-- N3XT PREMIUM SIDEBAR -->
    <aside 
      :class="[
        'fixed inset-y-0 left-0 z-50 w-72 bg-white/80 dark:bg-black/60 backdrop-blur-3xl text-gray-900 dark:text-white transition-all duration-700 ease-[cubic-bezier(0.19,1,0.22,1)] transform md:static md:block md:translate-x-0 border-r border-gray-100 dark:border-white/5 animate-slide-up',
        isSidebarOpen ? 'translate-x-0' : '-translate-x-full'
      ]"
    >
      <!-- Glow Effect Sidebar -->
      <div class="absolute -top-24 -left-24 w-64 h-64 bg-primary/20 rounded-full blur-[100px] pointer-events-none opacity-50"></div>
      
      <div class="flex flex-col h-full relative z-10">
        <!-- Sidebar Brand -->
        <!-- Sidebar Brand -->
        <div @click="router.push('/')" class="p-8 pb-12 flex flex-col items-center cursor-pointer group/brand relative">
            <div class="absolute inset-0 bg-primary/5 opacity-0 group-hover/brand:opacity-100 transition-opacity rounded-[3rem] -m-4"></div>
            <div class="relative w-16 h-16 bg-gradient-to-br from-primary to-emerald-500 rounded-[1.5rem] flex items-center justify-center shadow-2xl shadow-primary/30 mb-4 transition-transform group-hover/brand:scale-110 group-hover/brand:rotate-6 duration-500">
                <span class="text-white font-black text-3xl italic">N</span>
            </div>
            <h1 class="relative text-2xl font-black tracking-tighter uppercase italic leading-none text-gray-900 dark:text-white group-hover/brand:text-primary transition-colors">N3XT<span class="text-gray-400 not-italic ml-1">3D</span></h1>
            <span class="relative text-[8px] font-black text-primary/60 uppercase tracking-[0.4em] mt-2">Industrial OS v3.2</span>
        </div>

        <!-- Navigation Links -->
        <nav class="flex-1 px-4 space-y-1 overflow-y-auto no-scrollbar">
          <button 
            v-for="item in [
              { id: 'kanban', label: 'Produccion', icon: 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10' },
              { id: 'analytics', label: 'Metricas BI', icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z' },
              { id: 'inventory', label: 'Inventario', icon: 'M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0a2 2 0 012 2v4a2 2 0 01-2 2H4a2 2 0 01-2-2v-4a2 2 0 012-2m16 0h-2M4 13H6m8 10V10m-4 10V10' },
              { id: 'purchases', label: 'Registro Compras', icon: 'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z' },
              { id: 'history', label: 'Historial', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
              { id: 'discounts', label: 'Codigos / Descuentos', icon: 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z' },
              { id: 'machines', label: 'Maquinaria', icon: 'M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z' },
              { id: 'contacts', label: 'Base Contactos', icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z' },
              { id: 'web', label: 'Gestion Web', icon: 'M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9' },
              { id: 'settings', label: 'Parametros', icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z' }
            ]" 
            :key="item.id"
            @click="activeTab = item.id === 'analytics' ? 'accounting' : item.id; isSidebarOpen = false"
            :class="[
              'w-full flex items-center gap-4 px-6 py-4 rounded-2xl group relative overflow-hidden transition-all duration-300',
              activeTab === (item.id === 'analytics' ? 'accounting' : item.id) 
                ? 'bg-primary/10 text-primary dark:bg-white/10 dark:text-white shadow-sm border border-primary/10 dark:border-white/10' 
                : 'text-gray-500 hover:text-primary dark:hover:text-white hover:bg-primary/5 dark:hover:bg-white/5'
            ]"
          >
            <!-- Active Indicator Dot -->
            <div v-if="activeTab === (item.id === 'analytics' ? 'accounting' : item.id)" class="absolute left-0 w-1.5 h-6 bg-primary rounded-r-full shadow-[0_0_15px_#1e3a34]"></div>
            
            <div class="text-sm transition-transform group-hover:scale-125 duration-500 w-8 h-8 bg-primary/10 rounded-xl flex items-center justify-center">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" :d="item.icon" />
                </svg>
                <circle v-if="item.id === 'settings'" cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="2.5" />
            </div>
            <span class="text-[10px] font-black uppercase tracking-[0.2em]">{{ item.label }}</span>
            
            <!-- Hover Glow -->
            <div class="absolute inset-0 bg-gradient-to-r from-primary/10 to-transparent opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none"></div>
          </button>
        </nav>

        <!-- Sidebar Contact Quick Links -->
        <div class="px-8 mb-6 flex justify-center gap-4">
            <a :href="'mailto:' + (settings.company?.email || 'servicion3xt@gmail.com')" class="w-12 h-12 rounded-2xl bg-primary/10 dark:bg-white/5 flex items-center justify-center text-primary hover:bg-primary hover:text-white transition-all shadow-sm border border-primary/20 dark:border-white/10 group" title="Soporte Técnico">
                <svg class="w-5 h-5 group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            </a>
            <a :href="'https://wa.me/' + (settings.company?.phone || '573118796416').replace(/\D/g, '')" target="_blank" class="w-12 h-12 rounded-2xl bg-emerald-500/10 dark:bg-white/5 flex items-center justify-center text-emerald-500 hover:bg-emerald-500 hover:text-white transition-all shadow-sm border border-emerald-500/20 dark:border-white/10 group" title="WhatsApp Directo">
                <svg class="w-5 h-5 group-hover:scale-110 transition-transform" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.477 2 12c0 1.846.498 3.578 1.365 5.051L2 22l5.09-1.31A9.957 9.957 0 0012 22c5.523 0 10-4.477 10-10S17.523 2 12 2zm.003 18.232c-1.488 0-2.91-.382-4.17-1.076l-2.98.766.78-2.88a8.214 8.214 0 01-1.15-4.225c0-4.57 3.738-8.293 8.337-8.293 4.598 0 8.336 3.722 8.336 8.293 0 4.57-3.738 8.293-8.336 8.293zm4.275-6.002c-.234-.117-1.385-.68-1.598-.758-.214-.078-.37-.117-.526.117-.156.233-.603.758-.74.914-.136.155-.272.175-.506.058-.234-.116-1.002-.367-1.914-1.176-.71-.628-1.19-1.405-1.33-1.638-.135-.234-.014-.36.103-.477.106-.105.234-.272.35-.408.117-.136.156-.233.234-.388.078-.156.039-.292-.02-.408-.058-.117-.525-1.264-.72-1.73-.188-.453-.38-.392-.525-.4-.136-.008-.292-.008-.448-.008s-.41.058-.624.292c-.214.233-.817.797-.817 1.942 0 1.146.837 2.253.953 2.408.117.155 1.666 2.513 4.037 3.513.565.238 1.005.38 1.35.486.565.18 1.08.154 1.486.094.453-.067 1.386-.563 1.58-1.107.195-.544.195-1.01.136-1.107-.058-.097-.214-.155-.448-.272z"/></svg>
            </a>
        </div>

        <!-- Sidebar Footer -->
        <div class="p-8 border-t border-white/5">
          <button @click="logout" class="w-full flex items-center gap-4 px-6 py-4 text-rose-400 hover:text-rose-300 hover:bg-rose-500/5 rounded-2xl transition-all group">
            <svg class="w-5 h-5 group-hover:rotate-12 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            <span class="text-[10px] font-black uppercase tracking-widest">Cerrar Sesión</span>
          </button>
        </div>
      </div>
    </aside>

    <!-- MAIN CONTENT AREA -->
    <main class="flex-1 flex flex-col min-w-0 bg-transparent h-screen overflow-hidden relative z-10">

        <!-- PREMIUM HEADER -->
        <header class="h-20 md:h-24 bg-white/70 dark:bg-black/40 backdrop-blur-3xl border-b border-gray-100/50 dark:border-white/5 flex items-center justify-between px-4 md:px-12 relative z-40 shadow-sm">
            <div class="flex items-center gap-6">
                <!-- Mobile Toggle -->
                <button @click="isSidebarOpen = !isSidebarOpen" class="md:hidden w-12 h-12 flex items-center justify-center bg-gray-900 dark:bg-white dark:text-gray-900 text-white rounded-2xl">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>

                <div class="flex flex-col">
                    <div class="flex items-center gap-3">
                        <div class="w-2.5 h-2.5 bg-primary rounded-full animate-pulse shadow-[0_0_8px_#1e3a34]"></div>
                        <h2 class="text-lg md:text-2xl font-black text-slate-900 dark:text-white tracking-tighter uppercase italic">{{ activeTab === 'kanban' ? 'Producción' : activeTab.toUpperCase() }}</h2>
                    </div>
                    <span class="hidden sm:block text-[9px] font-black text-gray-400 dark:text-gray-300 uppercase tracking-[0.4em] mt-1 ml-5">Real-time Operations</span>
                </div>
            </div>

            <div class="flex items-center gap-2 md:gap-8">
                <!-- System Status Desktop -->
                <div class="hidden lg:flex flex-col items-end">
                    <span class="text-[9px] font-black text-gray-900 dark:text-white uppercase tracking-widest flex items-center gap-2">
                        N3XT OS PREMIUM
                        <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
                    </span>
                    <span class="text-[8px] font-bold text-gray-400 dark:text-gray-400 uppercase tracking-widest mt-0.5">Modo Central Activo</span>
                </div>

                <div class="hidden md:block h-10 w-px bg-gray-100 dark:bg-white/10"></div>


                <!-- Action Group -->
                <div class="flex items-center gap-2 md:gap-4">
                    <!-- Dark Mode Toggle -->
                    <button 
                        @click="toggleDarkMode" 
                        class="w-10 h-10 md:w-12 md:h-12 bg-[var(--bg-surface)] text-gray-400 hover:text-primary rounded-xl md:rounded-2xl flex items-center justify-center active:scale-95 shadow-sm border border-[var(--border-main)]"
                    >
                        <svg v-if="isDark" class="w-6 h-6 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 9H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707m12.728 0l-.707-.707M6.343 6.343l-.707-.707M12 8a4 4 0 100 8 4 4 0 000-8z"/></svg>
                        <svg v-else class="w-6 h-6 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                    </button>

                    <button 
                        @click="syncAll(false)" 
                        :disabled="loading"
                        class="group w-10 h-10 md:w-12 md:h-12 bg-[var(--bg-surface)] hover:bg-primary hover:text-white text-gray-400 rounded-xl md:rounded-2xl flex items-center justify-center transition-all duration-500 active:scale-90 border border-[var(--border-main)]"
                        title="Sincronizar Datos"
                    >
                        <svg :class="['w-5 h-5', loading ? 'animate-spin' : 'group-hover:rotate-180 transition-transform duration-700']" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                    </button>

                    <!-- Logout Button in Header -->
                    <button 
                        @click="logout" 
                        class="hidden md:flex w-10 h-10 md:w-12 md:h-12 bg-rose-50 dark:bg-rose-500/10 hover:bg-rose-500 hover:text-white text-rose-500 rounded-xl md:rounded-2xl items-center justify-center transition-all duration-300 active:scale-90 shadow-sm border border-rose-500/20"
                        title="Cerrar Sesión"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                    </button>

                    <div class="hidden sm:block w-10 h-10 md:w-12 md:h-12 rounded-xl md:rounded-2xl overflow-hidden border-2 border-primary/20 p-0.5 shadow-xl shadow-primary/10">
                        <img v-if="settings.company_logo" :src="logoUrl" class="w-full h-full object-contain bg-white" />
                        <div v-else class="w-full h-full bg-primary flex items-center justify-center text-white font-black italic">N</div>
                    </div>
                </div>
            </div>
        </header>

        <!-- VIEWPORT: DINAMIC CONTENT -->
        <div class="flex-1 flex flex-col min-w-0 overflow-y-auto overflow-x-hidden relative p-3 pb-24 md:p-12 md:pb-12 bg-transparent">
            
            <div class="w-full max-w-[1600px] mx-auto">
                <transition 
                    name="view-fade" 
                    mode="out-in"
                    enter-active-class="animate-slide-up"
                >
                    <div :key="activeTab">
        
        <!-- Módulo: Producción (Kanban) -->
        <div v-if="activeTab === 'kanban'" class="p-0 md:p-8 animate-in fade-in slide-in-from-bottom-2 duration-300">
          <div class="flex flex-col lg:flex-row items-center justify-between gap-8 mb-12 bg-white/40 dark:bg-gray-950/40 backdrop-blur-md p-8 md:p-10 rounded-[3rem] border border-white/60 dark:border-white/5 shadow-2xl shadow-gray-200/20 dark:shadow-none relative overflow-hidden group">
            <div class="absolute -right-20 -top-20 w-64 h-64 bg-primary/5 rounded-full blur-3xl group-hover:bg-primary/10 transition-all duration-1000"></div>
            <div class="relative z-10">
                <h2 class="text-3xl md:text-5xl font-black text-gray-900 dark:text-white tracking-tighter uppercase italic leading-none">
                    Panel de <span class="text-primary">Control</span>
                </h2>
                <p class="text-[10px] md:text-xs text-gray-400 font-bold uppercase tracking-[0.4em] mt-4 flex items-center gap-3">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 shadow-[0_0_8px_#10b981]"></span>
                    Workshop Status: Operational
                </p>
            </div>
            
            <div class="flex flex-wrap justify-center lg:justify-end gap-4 relative z-10">
              <div class="bg-white/80 dark:bg-gray-900/80 p-6 rounded-[2.5rem] border border-gray-100 dark:border-white/5 min-w-[160px] shadow-sm hover:shadow-xl transition-all group relative overflow-hidden">
                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Carga Activa</p>
                <div class="flex items-end gap-2">
                  <h3 class="text-3xl font-black text-gray-900 dark:text-white tracking-tighter leading-none">{{ orders.filter(o => o.status === 'printing').length }}</h3>
                  <span class="text-[8px] font-black text-primary uppercase tracking-widest mb-0.5 italic">Jobs</span>
                </div>
              </div>
            </div>
          </div>
          <!-- Estadísticas Rápidas de Producción -->
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-8 mb-8 md:mb-12">
            <div class="bg-white dark:bg-gray-900 p-6 md:p-10 rounded-[2.5rem] md:rounded-[3rem] shadow-sm border border-gray-100 dark:border-white/5 group relative overflow-hidden">
              <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3">Total Proyectos</p>
              <h3 class="text-4xl font-black text-gray-900 dark:text-white tracking-tighter">{{ orders.length }}</h3>
              <div class="mt-6">
                <button @click="modalState.simulator = true" class="btn-primary w-full py-4 !rounded-2xl">
                  <svg class="w-4 h-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                  </svg>
                  <span>Análisis de Manufactura</span>
                </button>
              </div>
            </div>

            <div class="bg-white dark:bg-gray-900 p-6 md:p-10 rounded-[2.5rem] md:rounded-[3rem] shadow-sm border border-gray-100 dark:border-white/5 group">
              <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3">Pendientes</p>
              <div class="flex items-center justify-between">
                <h3 class="text-5xl font-black text-red-500 tracking-tighter">{{ orders.filter(o => o.status === 'pending').length }}</h3>
                <div class="w-12 h-12 bg-red-50 dark:bg-red-500/10 text-red-500 rounded-2xl flex items-center justify-center animate-pulse">
                  <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
              </div>
            </div>

            <div class="bg-white dark:bg-gray-900 p-6 md:p-10 rounded-[2.5rem] md:rounded-[3rem] shadow-sm border border-gray-100 dark:border-white/5 group">
              <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-3">En Producción</p>
              <div class="flex items-center justify-between">
                <h3 class="text-5xl font-black text-amber-500 tracking-tighter">{{ orders.filter(o => o.status === 'printing').length }}</h3>
                <div class="w-12 h-12 bg-amber-50 dark:bg-amber-500/10 text-amber-500 rounded-2xl flex items-center justify-center">
                  <svg class="w-6 h-6 animate-spin-slow" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                </div>
              </div>
            </div>

            <!-- Terminados & Eficiencia -->
            <div class="bg-white dark:bg-gray-900 p-6 md:p-8 rounded-[2.5rem] md:rounded-[3rem] shadow-sm border border-gray-100 dark:border-white/5 group flex flex-col justify-between overflow-hidden relative">
              <div class="flex items-center justify-between mb-4">
                <div>
                  <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Terminados</p>
                  <h3 class="text-4xl font-black text-emerald-500 tracking-tighter">{{ orders.filter(o => o.status === 'completed').length }}</h3>
                </div>
                <div class="w-12 h-12 bg-emerald-50 dark:bg-emerald-500/10 text-emerald-500 rounded-2xl flex items-center justify-center">
                  <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
              </div>

              <!-- El Cuadro Negro (Eficiencia Bruta) -->
              <div class="bg-gray-950 p-6 rounded-[2rem] shadow-2xl shadow-black/40 relative overflow-hidden border border-white/5 group-hover:scale-[1.02] transition-transform duration-500">
                <div class="absolute -right-10 -top-10 w-32 h-32 bg-primary/5 rounded-full blur-2xl group-hover:bg-primary/10 transition-all"></div>
                <p class="text-[8px] font-black text-gray-500 uppercase tracking-[0.3em] mb-2">Eficiencia Bruta</p>
                <div class="flex items-end gap-2">
                  <h3 class="text-2xl font-black text-white tracking-tighter leading-none">${{ Math.round(orders.reduce((acc, o) => acc + (o.status === 'completed' ? o.total_price : 0), 0)).toLocaleString() }}</h3>
                  <span class="text-[8px] font-black text-primary uppercase tracking-widest mb-0.5 italic">COP</span>
                </div>
              </div>
            </div>
          </div>

          <KanbanBoard 
            :orders="orders"
            :loading="loading"
            @update-status="handleStatusUpdate"
            @assign="handleAssignment"
            @download="downloadFile"
            @view="openOrderDetails($event)"
            @delete="handleDeleteOrder"
            @download-pdf="handleDownloadQuotePDF"
            @toggle-paid="handleTogglePaid"
            @new-order="modalState.manualOrder = true"
          />
        </div>

        <!-- Módulo: Finanzas (Accounting Dashboard) -->
        <div v-if="activeTab === 'accounting'" class="p-0 md:p-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
          <AccountingDashboard 
            :analytics="analyticsData"
            :loading="loadingAnalytics"
            @export="handleExportCSV"
            @toggle-paid="handleTogglePaid"
          />
        </div>

        <!-- Módulo: Inventario (Inventory Manager) -->
        <div v-if="activeTab === 'inventory'" class="p-0 md:p-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
          <InventoryManager 
            :inventory="inventoryData" 
            @add-item="modalState.newMaterial = true"
            @edit-item="handleEditMaterial"
            @delete-item="handleDeleteMaterial"
            @update-stock="handleUpdateStock"
          />
        </div>

        <!-- Módulo: Registro de Compras (Purchase Log) -->
        <div v-if="activeTab === 'purchases'" class="p-0 md:p-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
          <PurchaseLog 
            :inventory="inventoryData"
            :suppliers="contacts.suppliers"
            :settings="settings"
            @refresh-inventory="fetchInventory"
          />
        </div>

        <!-- Módulo: Historial de Pedidos -->
        <div v-if="activeTab === 'history'" class="p-0 md:p-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
          <OrderHistory 
            :orders="orders"
            :loadingAnalytics="loadingAnalytics"
            @view-details="openOrderDetails"
            @download-pdf="handleDownloadQuotePDF"
            @export-report="handleExportReport"
            @delete="handleDeleteOrder"
            @print-label="handlePrintLabel"
          />
        </div>

        <!-- Módulo: Códigos y Descuentos -->
        <div v-if="activeTab === 'discounts'" class="p-0 md:p-8 animate-in fade-in slide-in-from-bottom-4 duration-500 max-w-[1400px] mx-auto">
          <DiscountManager 
            :discounts="settings.discounts"
            @update-discounts="(newDiscounts) => { settings.discounts = newDiscounts; saveSettings(true) }"
          />
        </div>

        <!-- Módulo: Estado de la Granja (Machine Monitor) -->
        <div v-if="activeTab === 'machines'" class="p-0 md:p-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
          <MachineMonitor 
            :printers="printers"
            :loading="loading"
            @add-printer="modalState.newPrinter = true"
            @edit-printer="handleEditPrinter"
            @update-printer-status="handleUpdatePrinterStatus"
            @reset-printer="handleResetPrinter"
            @maintenance-complete="handleMaintenanceComplete"
            @delete-printer="handleDeletePrinter"
            @sync="syncAll"
          />
        </div>

        <!-- Módulo: Base de Contactos -->
        <div v-if="activeTab === 'contacts'" class="p-0 md:p-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
          <ContactManager 
            :customers="contacts.customers"
            :suppliers="contacts.suppliers"
            @refresh="fetchContacts" 
            @delete-contact="handleDeleteContact"
          />
        </div>

        <!-- Módulo: Gestión Web (Landing Page Control) -->
        <div v-if="activeTab === 'web'" class="p-0 md:p-8 lg:p-12 animate-in fade-in slide-in-from-bottom-4 duration-500 max-w-[1600px] mx-auto space-y-12 pb-12">
            <!-- Header de Módulo -->
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-8 mb-12 bg-white/40 dark:bg-gray-950/40 backdrop-blur-xl p-8 md:p-12 rounded-[4rem] border border-white/60 dark:border-white/5 shadow-2xl relative overflow-hidden group">
                <div class="absolute -right-20 -top-20 w-64 h-64 bg-primary/5 rounded-full blur-3xl group-hover:bg-primary/10 transition-all duration-1000"></div>
                <div class="relative z-10">
                    <h2 class="text-3xl md:text-5xl font-black text-gray-900 dark:text-white tracking-tighter uppercase mb-4 italic">Gestión <span class="text-primary">Landing Page</span></h2>
                    <p class="text-[10px] md:text-sm text-gray-400 font-bold uppercase tracking-[0.3em] flex items-center gap-3">
                        <span class="w-3 h-3 rounded-full bg-primary animate-pulse"></span>
                        Control de Interfaz Pública • N3XT Web Core
                    </p>

                </div>
                
                <!-- Sub-Navegación Interna (Pill Style) -->
                <div class="flex bg-gray-100 dark:bg-white/5 p-2 rounded-[2rem] border border-gray-200 dark:border-white/10 relative z-10">
                    <button 
                        v-for="st in [
                            {id: 'general', n: 'General', i: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z'}, 
                            {id: 'catalog', n: 'Catalogo Master', i: 'M4 6h16M4 10h16M4 14h16M4 18h16'},
                            {id: 'news', n: 'Noticias', i: 'M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10l4 4v10a2 2 0 01-2 2zM7 8h5M7 12h8M7 16h8'}, 
                            {id: 'social', n: 'Social Hub', i: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z'}
                        ]" 
                        :key="st.id"
                        @click="webSubTab = st.id"
                        :class="[
                            'px-8 py-3.5 rounded-[1.5rem] text-[10px] font-black uppercase tracking-widest transition-all flex items-center gap-3',
                            webSubTab === st.id ? 'bg-white dark:bg-primary text-gray-900 dark:text-white shadow-xl' : 'text-gray-400 hover:text-primary'
                        ]"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" :d="st.i" /></svg> {{ st.n }}
                    </button>
                </div>

                <button @click="saveSettings" class="bg-primary hover:bg-emerald-600 text-white w-full md:w-auto px-12 py-6 rounded-[2rem] shadow-xl shadow-primary/20 flex items-center justify-center gap-4 active:scale-95 transition-all duration-200 z-50 cursor-pointer">
                    <span v-if="savingSettings" class="w-6 h-6 border-4 border-white/30 border-t-white rounded-full animate-spin"></span>
                    <svg v-else class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 21a9.003 9.003 0 008.354-5.646m-1.172-1.946A9 9 0 005.646 5.646m1.172 1.946A9 9 0 0112 3c1.258 0 2.454.258 3.535.72M12 12h.01" /></svg>
                    <span class="text-lg font-black uppercase tracking-widest">{{ savingSettings ? 'Guardando...' : 'Publicar Cambios' }}</span>
                </button>
            </div>

            <transition 
                enter-active-class="transition duration-500 ease-out"
                enter-from-class="opacity-0 translate-y-8"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition duration-300 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
                mode="out-in"
            >
                <!-- ZONA: GENERAL (Social & Status) -->
                <div v-if="webSubTab === 'general'" key="general" class="grid grid-cols-1 lg:grid-cols-3 gap-12">
                    <div class="space-y-12">
                        <div class="bg-white dark:bg-gray-900 p-10 rounded-[3rem] border border-gray-100 dark:border-white/5 shadow-xl">
                            <h3 class="text-xl font-black text-gray-900 dark:text-white uppercase tracking-tighter mb-10 border-l-8 border-primary pl-6 italic">Enlaces de Redes</h3>
                            <div class="space-y-6">
                                <div v-for="(info, key) in { 
                                    tiktok: { l: 'TikTok URL', i: 'M9 12a3 3 0 103 3V2h3.5A3.5 3.5 0 0119 5.5V9h-3.5v3a3 3 0 01-3 3z' }, 
                                    instagram: { l: 'Instagram URL', i: 'M7.75 2h8.5C18.873 2 21 4.127 21 6.75v10.5c0 2.623-2.127 4.75-4.75 4.75h-8.5C5.127 22 3 19.873 3 17.25V6.75C3 4.127 5.127 2 7.75 2zm4.25 5a5 5 0 100 10 5 5 0 000-10zm5.5 1.5a1.25 1.25 0 100 2.5 1.25 1.25 0 000-2.5z' }, 
                                    facebook: { l: 'Facebook URL', i: 'M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3V2z' }, 
                                    whatsapp: { l: 'WhatsApp Link', i: 'M12 2C6.477 2 2 6.477 2 12c0 1.846.498 3.578 1.365 5.051L2 22l5.09-1.31A9.957 9.957 0 0012 22c5.523 0 10-4.477 10-10S17.523 2 12 2zm.003 18.232c-1.488 0-2.91-.382-4.17-1.076l-2.98.766.78-2.88a8.214 8.214 0 01-1.15-4.225c0-4.57 3.738-8.293 8.337-8.293 4.598 0 8.336 3.722 8.336 8.293 0 4.57-3.738 8.293-8.336 8.293zm4.275-6.002c-.234-.117-1.385-.68-1.598-.758-.214-.078-.37-.117-.526.117-.156.233-.603.758-.74.914-.136.155-.272.175-.506.058-.234-.116-1.002-.367-1.914-1.176-.71-.628-1.19-1.405-1.33-1.638-.135-.234-.014-.36.103-.477.106-.105.234-.272.35-.408.117-.136.156-.233.234-.388.078-.156.039-.292-.02-.408-.058-.117-.525-1.264-.72-1.73-.188-.453-.38-.392-.525-.4-.136-.008-.292-.008-.448-.008s-.41.058-.624.292c-.214.233-.817.797-.817 1.942 0 1.146.837 2.253.953 2.408.117.155 1.666 2.513 4.037 3.513.565.238 1.005.38 1.35.486.565.18 1.08.154 1.486.094.453-.067 1.386-.563 1.58-1.107.195-.544.195-1.01.136-1.107-.058-.097-.214-.155-.448-.272z' }, 
                                    youtube: { l: 'YouTube URL', i: 'M22.54 6.42a2.78 2.78 0 00-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.42a2.78 2.78 0 00-1.94 2A29 29 0 001 11.75a29 29 0 00.46 5.33 2.78 2.78 0 001.94 2c1.72.42 8.6.42 8.6.42s6.88 0 8.6-.42a2.78 2.78 0 001.94-2 29 29 0 00.46-5.33 29 29 0 00-.46-5.33zM9.75 15.02V8.48l5.75 3.27-5.75 3.27z' }
                                }" :key="key" class="group/input">
                                    <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-5 mb-2 block group-focus-within/input:text-primary transition-colors flex items-center gap-3">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path :d="info.i"/></svg> {{ info.l }}
                                    </label>
                                    <input type="text" v-model="settings.web.social[key]" placeholder="https://..." class="w-full bg-gray-50 dark:bg-gray-800 border-2 border-transparent rounded-2xl px-8 py-4 text-[11px] font-bold text-gray-900 dark:text-white outline-none focus:border-primary/20 transition-all">
                                </div>
                                
                                <div class="pt-8 border-t border-gray-100 dark:border-white/5">
                                    <div class="group/input">
                                        <label class="text-[9px] font-black text-emerald-500 uppercase tracking-widest ml-5 mb-2 block flex items-center gap-2">
                                            Soporte Tecnico Global (WhatsApp)
                                        </label>
                                        <input type="text" v-model="settings.company.phone" placeholder="+57 311..." class="w-full bg-emerald-500/5 border-2 border-emerald-500/10 rounded-2xl px-8 py-5 text-lg font-black text-emerald-600 outline-none focus:border-emerald-500/30 transition-all">
                                        <p class="text-[8px] text-gray-400 font-bold uppercase mt-2 ml-5">Este número se usará por defecto para todos los botones de 'Comprar' y 'Consultar'.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-950 p-10 rounded-[3rem] border border-white/5 shadow-2xl relative overflow-hidden group/status">
                            <div class="absolute -right-10 -top-10 w-40 h-40 bg-emerald-500/10 rounded-full blur-3xl group-hover/status:scale-150 transition-transform"></div>
                            <h3 class="text-xl font-black text-white uppercase tracking-tighter mb-8 italic">Estado Operativo</h3>
                            <label class="text-[9px] font-black text-gray-500 uppercase tracking-widest ml-5 mb-2 block">Texto de Disponibilidad</label>
                            <input type="text" v-model="settings.web.workshop_status" class="w-full bg-white/5 border border-white/10 rounded-2xl px-8 py-5 text-lg font-black text-emerald-500 outline-none focus:border-emerald-500/50 transition-all uppercase italic tracking-widest mb-10">

                            <h3 class="text-xl font-black text-white uppercase tracking-tighter mb-8 italic">Contenido Legal</h3>
                            <div class="space-y-6">
                                <div class="group/input">
                                    <label class="text-[9px] font-black text-gray-500 uppercase tracking-widest ml-5 mb-2 block">Política de Privacidad</label>
                                    <textarea v-model="settings.web.privacy_policy" rows="3" class="w-full bg-white/5 border border-white/10 rounded-2xl px-6 py-4 text-xs font-medium text-gray-400 outline-none focus:border-primary/50" placeholder="Escribe aquí tu política de privacidad..."></textarea>
                                </div>
                                <div class="group/input">
                                    <label class="text-[9px] font-black text-gray-500 uppercase tracking-widest ml-5 mb-2 block">Términos y Condiciones</label>
                                    <textarea v-model="settings.web.terms_conditions" rows="3" class="w-full bg-white/5 border border-white/10 rounded-2xl px-6 py-4 text-xs font-medium text-gray-400 outline-none focus:border-primary/50" placeholder="Escribe aquí tus términos de servicio..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- SECCIÓN: CONFIGURACIÓN DE ECOSISTEMA N3XT -->
                    <div class="lg:col-span-2 space-y-12">
                        <div class="bg-white dark:bg-gray-900 p-10 md:p-16 rounded-[4rem] border border-gray-100 dark:border-white/5 shadow-2xl">
                            <div class="flex flex-col md:flex-row justify-between items-center gap-8 mb-16">
                                <div>
                                    <h3 class="text-3xl font-black text-gray-900 dark:text-white uppercase tracking-tighter italic border-l-8 border-emerald-500 pl-8">Configuración de Ecosistema N3XT</h3>
                                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mt-2 ml-10 italic">Módulos principales de la landing page</p>
                                </div>
                                <button @click="settings.web.ecosystem.push({t1: 'Nuevo', t2: 'Modulo', d: '', type: 'icon', i: 'M13 10V3L4 14h7v7l9-11h-7z', items: []})" class="px-8 py-4 bg-emerald-500 text-white rounded-2xl font-black text-[10px] uppercase tracking-widest shadow-xl shadow-emerald-500/20 hover:scale-105 transition-all">
                                    Anadir Modulo
                                </button>
                            </div>

                            <div class="space-y-12">
                                <div v-for="(card, idx) in settings.web.ecosystem" :key="idx" class="group/card bg-gray-50 dark:bg-white/5 p-10 md:p-14 rounded-[3.5rem] md:rounded-[4.5rem] border-2 border-transparent hover:border-emerald-500/20 transition-all relative overflow-hidden">
                                    <button @click="settings.web.ecosystem.splice(idx, 1)" class="absolute top-8 right-8 w-10 h-10 bg-rose-500/10 text-rose-500 rounded-xl flex items-center justify-center opacity-0 group-hover/card:opacity-100 transition-opacity hover:bg-rose-500 hover:text-white z-20">✕</button>
                                    
                                    <div class="absolute -right-20 -top-20 w-64 h-64 bg-emerald-500/5 rounded-full blur-[100px] pointer-events-none"></div>
                                    
                                    <div class="flex flex-col lg:flex-row gap-10 md:gap-16 relative z-10">
                                        <!-- Info General -->
                                        <div class="flex-1 space-y-8 md:space-y-10">
                                            <div class="flex items-center gap-4">
                                                <span class="text-[8px] md:text-[10px] font-black px-4 md:px-6 py-1.5 md:py-2 bg-emerald-500 text-white rounded-xl md:rounded-2xl uppercase tracking-widest shadow-lg shadow-emerald-500/20">Módulo #{{ idx + 1 }}</span>
                                                <div class="h-px flex-1 bg-gray-200 dark:bg-white/5"></div>
                                            </div>

                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 md:gap-10">
                                                <div class="space-y-4 md:space-y-6">
                                                    <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-4 block">Título Principal (2 Líneas)</label>
                                                    <input type="text" v-model="card.t1" class="w-full bg-white dark:bg-gray-800 border-2 border-transparent rounded-2xl px-6 py-4 text-xs font-black text-gray-900 dark:text-white outline-none focus:border-primary/20 transition-all" placeholder="Línea 1">
                                                    <input type="text" v-model="card.t2" class="w-full bg-white dark:bg-gray-800 border-2 border-transparent rounded-2xl px-6 py-4 text-xs font-black text-primary outline-none focus:border-primary/20 transition-all" placeholder="Línea 2 (Énfasis)">
                                                </div>
                                                <div class="space-y-4 md:space-y-6">
                                                    <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-4 block">Descripción del Módulo</label>
                                                    <textarea v-model="card.d" rows="4" class="w-full bg-white dark:bg-gray-800 border-2 border-transparent rounded-3xl px-6 py-5 text-xs font-bold text-gray-400 outline-none focus:border-primary/20 resize-none transition-all" placeholder="Describe brevemente este componente del ecosistema..."></textarea>
                                                </div>
                                            </div>

                                            <!-- Lista Técnica Dinámica -->
                                            <div class="pt-8 border-t border-gray-200 dark:border-white/5">
                                                <div class="flex justify-between items-center mb-6">
                                                    <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-4 block">Lista Técnica / Especificaciones</label>
                                                    <button @click="card.items ? card.items.push('Nueva Especificación') : card.items = ['Nueva Especificación']" class="text-[8px] font-black text-emerald-500 uppercase tracking-widest hover:underline">
                                                        + Añadir Item
                                                    </button>
                                                </div>
                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                    <div v-for="(sit, sidx) in card.items" :key="sidx" class="flex gap-2 group/item">
                                                        <input type="text" v-model="card.items[sidx]" class="flex-1 bg-white dark:bg-gray-800 border border-gray-100 dark:border-white/5 rounded-xl px-4 py-3 text-[10px] font-bold text-gray-400 outline-none focus:border-primary/30 transition-all">
                                                        <button @click="card.items.splice(sidx, 1)" class="p-2 text-rose-500 opacity-0 group-hover/item:opacity-100 transition-opacity">✕</button>
                                                    </div>
                                                </div>
                                                <p v-if="!card.items || card.items.length === 0" class="text-[8px] text-gray-400 italic">No hay especificaciones en este módulo</p>
                                            </div>
                                        </div>

                                        <!-- Media Control -->
                                        <div class="w-full lg:w-96 space-y-8">
                                            <div class="bg-white dark:bg-gray-900/50 p-8 rounded-[3rem] border border-gray-100 dark:border-white/5 shadow-inner space-y-6">
                                                <label class="text-[9px] font-black text-emerald-500 uppercase tracking-widest block text-center mb-4">Activo Visual</label>
                                                
                                                <div class="flex flex-col gap-4">
                                                    <div class="flex gap-4">
                                                        <select v-model="card.type" class="bg-gray-100 dark:bg-white/5 border-none rounded-xl px-4 py-2 text-[10px] font-black uppercase text-emerald-500 outline-none shadow-sm">
                                                            <option value="image">IMG</option>
                                                            <option value="icon">ICO</option>
                                                        </select>
                                                        <div class="flex-1 flex items-center justify-center text-4xl bg-gray-100 dark:bg-white/5 rounded-2xl border border-dashed border-gray-200 dark:border-white/10 aspect-square overflow-hidden">
                                                            <img v-if="card.type === 'image' && card.i" :src="card.i" class="w-full h-full object-cover">
                                                            <svg v-if="card.type === 'icon'" class="w-8 h-8 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="card.i" /></svg>
                                                            <svg v-else class="w-8 h-8 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                                        </div>
                                                    </div>

                                                    <div class="space-y-4">
                                                        <div class="group/input">
                                                            <label class="text-[8px] font-black text-gray-400 uppercase tracking-widest ml-2 mb-1 block">Ruta / Emoji</label>
                                                            <input type="text" v-model="card.i" class="w-full bg-gray-100 dark:bg-white/5 border-none rounded-xl px-5 py-4 text-[9px] font-mono text-primary outline-none" :placeholder="card.type === 'image' ? 'URL imagen...' : 'Emoji'">
                                                        </div>
                                                        
                                                        <button 
                                                            v-if="card.type === 'image'" 
                                                            @click="triggerCardUpload(idx)" 
                                                            type="button"
                                                            class="w-full flex items-center justify-center gap-3 py-4 bg-emerald-600 text-white rounded-xl hover:bg-emerald-500 transition-all text-[11px] font-black uppercase shadow-xl shadow-emerald-500/20 active:scale-95"
                                                        >
                                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                                            Optimizar y Subir
                                                        </button>
                                                        <input type="file" :id="'card-upload-' + idx" @change="handleImageUploadSEO($event, settings.web.ecosystem[idx], 't1')" accept="image/*" class="hidden">
                                                    </div>
                                                </div>
                                                <p v-if="card.type === 'image'" class="text-[7px] text-gray-500 font-bold uppercase text-center tracking-widest">IA Compression Engine Active</p>
                                            </div>

                                            <div class="p-6 bg-emerald-500/5 rounded-[2.5rem] border border-emerald-500/10">
                                                <h5 class="text-[9px] font-black text-emerald-500 uppercase tracking-widest mb-1 flex items-center gap-2">
                                                    Tip de Diseno
                                                </h5>
                                                <p class="text-[8px] text-gray-400 font-bold uppercase leading-relaxed tracking-tight">
                                                    {{ idx === 0 ? 'La primera tarjeta soporta parallax. Usa una imagen vertical para un efecto inmersivo.' : 'Manten las especificaciones cortas para no saturar la vista móvil.' }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div v-if="settings.web.ecosystem.length === 0" class="py-20 text-center space-y-6">
                                <svg class="w-16 h-16 text-gray-300 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-[0.4em]">Ecosistema no configurado</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ZONA: CATÁLOGO (Items & PDF) -->
                <div v-else-if="webSubTab === 'catalog'" key="catalog" class="space-y-12 pb-20">
                    <!-- Configuración Técnica de Imágenes -->
                    <div class="bg-gray-950 p-10 rounded-[3rem] border border-white/10 shadow-2xl relative overflow-hidden group/opt">
                        <div class="absolute -right-20 -top-20 w-64 h-64 bg-emerald-500/10 rounded-full blur-3xl"></div>
                        <div class="flex flex-col md:flex-row justify-between items-center gap-8 relative z-10">
                            <div class="max-w-xl">
                                <h3 class="text-xl font-black text-white uppercase tracking-tighter mb-2 italic">Optimización de Imágenes (Cloudinary)</h3>
                                <p class="text-[9px] font-black text-gray-500 uppercase tracking-widest leading-relaxed">
                                    N3XT utiliza algoritmos de compresión sin pérdida. Ingresa tu Cloud Name para activar la entrega en formato WebP progresivo.
                                </p>
                            </div>
                            <div class="w-full md:w-72 group/input">
                                <label class="text-[8px] font-black text-gray-400 uppercase tracking-widest ml-4 mb-2 block">Cloud Name API</label>
                                <input type="text" v-model="settings.web.cloudinary_name" placeholder="n3xt-cloud-01" class="w-full bg-white/5 border border-white/10 rounded-2xl px-6 py-4 text-xs font-black text-emerald-500 outline-none focus:border-emerald-500/50">
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-900 p-10 md:p-16 rounded-[4rem] border border-gray-100 dark:border-white/5 shadow-2xl">
                        <div class="flex justify-between items-center mb-16">
                            <div>
                                <h3 class="text-3xl font-black text-gray-900 dark:text-white uppercase tracking-tighter italic border-l-8 border-primary pl-8">Inventario de Catálogo</h3>
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mt-2 ml-10 italic">Gestiona los productos que ven tus clientes</p>
                            </div>
                            <div class="flex gap-4">
                                <button @click="handleResetCatalog" class="px-8 py-5 bg-gray-100 dark:bg-white/5 text-gray-500 dark:text-gray-400 rounded-2xl font-black text-[10px] uppercase tracking-widest hover:bg-rose-500/10 hover:text-rose-500 transition-all border border-transparent hover:border-rose-500/20">
                                    Reiniciar
                                </button>
                                <button @click="handleCreateCatalogProduct" class="btn-primary px-10 py-5">
                                    Crear Nuevo Producto
                                </button>
                            </div>
                        </div>

                        <div class="space-y-16">
                            <template v-for="(item, index) in settings.web.catalog" :key="index">
                              <div v-if="editingCatalogItemIndex === index" :id="`catalog-item-${index}`" class="p-10 md:p-12 bg-gray-50 dark:bg-white/5 rounded-[3.5rem] border border-gray-200 dark:border-white/5 relative group/card transition-all hover:bg-white dark:hover:bg-white/10">
                                <button @click="settings.web.catalog.splice(index, 1); editingCatalogItemIndex = null" class="absolute top-8 right-8 w-10 h-10 bg-rose-500/10 text-rose-500 rounded-xl flex items-center justify-center opacity-0 group-hover/card:opacity-100 transition-opacity hover:bg-rose-500 hover:text-white">✕</button>
                                
                                <div v-if="isDiscounted(item)" class="absolute -top-4 left-10 px-6 py-2 bg-rose-500 text-white text-[10px] font-black uppercase tracking-widest rounded-full shadow-xl animate-bounce z-20">
                                     EN DESCUENTO
                                 </div>

                                <div class="grid grid-cols-1 lg:grid-cols-3 gap-12">
                                    <div class="lg:col-span-2 space-y-8">
                                        <div class="group/input">
                                            <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-5 mb-2 block">Título del Producto</label>
                                            <input type="text" v-model="item.name" class="w-full bg-white dark:bg-gray-800 border-2 border-transparent rounded-2xl px-8 py-5 text-lg font-black text-gray-900 dark:text-white outline-none focus:border-primary/20">
                                        </div>
                                        <div class="group/input">
                                            <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-5 mb-2 block">Descripción Detallada (Rich Text)</label>
                                            <textarea v-model="item.description" rows="4" class="w-full bg-white dark:bg-gray-800 border-2 border-transparent rounded-[2rem] px-8 py-6 text-sm font-medium text-gray-600 dark:text-gray-400 outline-none focus:border-primary/20 resize-none"></textarea>
                                        </div>
                                        <div class="grid grid-cols-4 gap-6">
                                            <div class="group/input">
                                                <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-5 mb-2 block">Categoría</label>
                                                <input type="text" v-model="item.category" class="w-full bg-white dark:bg-gray-800 border-2 border-transparent rounded-2xl px-8 py-4 text-xs font-bold text-gray-700 dark:text-gray-300 outline-none focus:border-primary/20">
                                            </div>
                                            <div class="group/input">
                                                <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-5 mb-2 block">Subcategoría</label>
                                                <input type="text" v-model="item.subcategory" placeholder="Opcional..." class="w-full bg-white dark:bg-gray-800 border-2 border-transparent rounded-2xl px-8 py-4 text-xs font-bold text-gray-700 dark:text-gray-300 outline-none focus:border-primary/20">
                                            </div>
                                            <div class="group/input">
                                                <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-5 mb-2 block">Precio Original</label>
                                                <input type="text" v-model="item.original_price" placeholder="$ 50.000" class="w-full bg-white dark:bg-gray-800 border-2 border-transparent rounded-2xl px-8 py-4 text-xs font-bold text-gray-400 outline-none focus:border-primary/20 line-through opacity-60">
                                            </div>
                                            <div class="group/input">
                                                <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-5 mb-2 block">Precio Oferta (Ej: $ 10.000)</label>
                                                <input type="text" v-model="item.price" class="w-full bg-white dark:bg-gray-800 border-2 border-transparent rounded-2xl px-8 py-4 text-xs font-bold text-primary outline-none focus:border-primary/20">
                                            </div>
                                        </div>
                                        
                                        <div class="pt-6 border-t border-gray-100 dark:border-white/5 group/input">
                                            <label class="text-[9px] font-black text-emerald-500 uppercase tracking-widest ml-5 mb-2 block flex items-center gap-2">
                                                WhatsApp de Soporte para este Producto
                                            </label>
                                            <input type="text" v-model="item.support_phone" placeholder="Dejar vacío para usar el global..." class="w-full bg-emerald-500/5 border-2 border-transparent rounded-2xl px-8 py-4 text-xs font-black text-emerald-600 outline-none focus:border-emerald-500/20">
                                        </div>
                                    </div>

                                    <div class="space-y-8 bg-white/50 dark:bg-black/20 p-8 rounded-[3rem] border border-gray-100 dark:border-white/5">
                                        <div class="space-y-3">
                                            <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-2 block">Estado de Publicación</label>
                                            <select v-model="item.status" class="w-full bg-white dark:bg-gray-800 border-none rounded-xl px-6 py-4 text-[10px] font-black uppercase tracking-widest text-emerald-500 outline-none">
                                                <option value="active">Activo</option>
                                                <option value="draft">Borrador</option>
                                                <option value="archived">Archivado</option>
                                            </select>
                                        </div>
                                        <div class="space-y-3">
                                            <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-2 block">Imágenes (Selecciona Múltiples)</label>
                                            <div class="aspect-square bg-white dark:bg-gray-800 rounded-2xl overflow-hidden border-2 border-dashed border-gray-200 dark:border-white/10 flex flex-wrap items-center justify-center relative group/img gap-1 p-2">
                                                <template v-if="item.images && item.images.length > 0">
                                                    <div v-for="(img, imgIdx) in item.images" :key="imgIdx" class="w-1/3 aspect-square relative border border-gray-200 dark:border-white/10 rounded-lg overflow-hidden">
                                                        <img :src="img" class="w-full h-full object-cover" />
                                                        <button @click="item.images.splice(imgIdx, 1)" class="absolute top-1 right-1 bg-rose-500 text-white rounded-full w-4 h-4 flex items-center justify-center text-[8px]">✕</button>
                                                    </div>
                                                </template>
                                                <img v-else-if="item.image" :src="item.image" class="w-full h-full object-cover" />
                                                <svg v-else class="w-10 h-10 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                                <div class="absolute inset-0 bg-black/80 flex flex-col items-center justify-center p-4 opacity-0 group-hover/img:opacity-100 transition-opacity">
                                                    <input type="text" v-model="item.image" placeholder="Main URL fallback..." class="w-full bg-white text-gray-900 rounded-lg p-2 text-[8px] font-mono mb-2">
                                                    <button @click="triggerProductUpload(index)" class="w-full py-2 bg-primary text-white rounded-lg text-[9px] font-black uppercase mb-2">Subir Múltiples</button>
                                                    <input type="file" :id="'product-upload-' + index" multiple @change="handleMultipleImagesUploadSEO($event, item, 'name')" accept="image/*" class="hidden">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                              </div>
                            </template>
                        </div>
                        <!-- Apartado de Productos Creados (Vista Previa Grupal) -->
                        <div class="mt-24 pt-20 border-t-4 border-dashed border-gray-100 dark:border-white/5">
                             <h4 class="text-2xl font-black text-gray-900 dark:text-white uppercase tracking-tighter italic mb-12 text-center">Visualización por Categorías</h4>
                             
                             <div v-for="(products, category) in groupedCatalog" :key="category" class="mb-16 last:mb-0">
                                 <div class="flex items-center gap-6 mb-8">
                                     <h5 class="text-xs font-black text-primary uppercase tracking-[0.3em] whitespace-nowrap">{{ category }}</h5>
                                     <div class="h-px w-full bg-gradient-to-r from-primary/20 to-transparent"></div>
                                 </div>

                                 <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-6">
                                     <div v-for="item in products" :key="item.originalIndex" class="bg-gray-50 dark:bg-white/5 p-4 rounded-3xl border border-gray-100 dark:border-white/5 relative group/mini overflow-hidden">
                                         <div class="aspect-square rounded-2xl overflow-hidden mb-3 bg-white dark:bg-gray-800 relative">
                                             <img v-if="item.image" :src="item.image" class="w-full h-full object-cover">
                                             <div v-else class="w-full h-full flex items-center justify-center text-sm font-black text-gray-300">IMG</div>
                                             
                                             <!-- Overlay de Acción -->
                                             <div class="absolute inset-0 bg-black/80 opacity-0 group-hover/mini:opacity-100 transition-opacity flex flex-col items-center justify-center gap-3">
                                                 <button @click="scrollToCatalogItem(item.originalIndex)" class="w-10 h-10 bg-primary text-white rounded-full flex items-center justify-center text-xs shadow-lg hover:scale-110 transition-transform"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></button>
                                                 <button @click="settings.web.catalog.splice(item.originalIndex, 1)" class="w-10 h-10 bg-rose-500 text-white rounded-full flex items-center justify-center text-xs shadow-lg hover:scale-110 transition-transform"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
                                             </div>
                                         </div>
                                         <div class="text-[8px] font-black text-primary/60 uppercase tracking-widest truncate mb-1" v-if="item.subcategory">{{ item.subcategory }}</div>
                                         <div class="text-[10px] font-black text-gray-900 dark:text-white truncate">{{ item.name }}</div>
                                         <div class="mt-1 flex items-center gap-2">
                                             <span v-if="isDiscounted(item)" class="text-[7px] font-black text-rose-500 line-through opacity-50">{{ item.original_price }}</span>
                                             <span class="text-[10px] font-black text-primary">{{ item.price }}</span>
                                         </div>
                                         <div v-if="isDiscounted(item)" class="absolute top-2 right-2 px-2 py-0.5 bg-rose-500 text-white text-[6px] font-black rounded-full">OFERTA</div>
                                     </div>
                                 </div>
                             </div>
                        </div>

                        <div class="mt-16 pt-12 border-t border-gray-100 dark:border-white/10">
                            <div class="flex flex-col md:flex-row justify-between items-center gap-8">
                                <div class="max-w-md">
                                    <h4 class="text-xl font-black text-gray-900 dark:text-white uppercase tracking-tighter italic">Catálogo PDF Digital</h4>
                                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mt-2 leading-relaxed">
                                        Vincula tu archivo PDF para que los clientes puedan descargarlo directamente desde la web.
                                    </p>
                                </div>
                                <div class="w-full md:w-96 space-y-6">
                                    <div class="group/input">
                                        <label class="text-[8px] font-black text-gray-400 uppercase tracking-widest ml-5 mb-2 block">Descripción del Catálogo (Mensaje)</label>
                                        <input type="text" v-model="settings.web.pdf_catalog_desc" placeholder="Mensaje corto..." class="w-full bg-gray-50 dark:bg-gray-800 border-2 border-transparent rounded-2xl px-8 py-4 text-xs font-bold text-gray-600 dark:text-gray-400 outline-none focus:border-primary/20">
                                    </div>
                                    <div class="group/input">
                                        <label class="text-[8px] font-black text-gray-400 uppercase tracking-widest ml-5 mb-2 block">URL del Archivo PDF</label>
                                        <input type="text" v-model="settings.web.pdf_catalog_url" placeholder="https://..." class="w-full bg-gray-50 dark:bg-gray-800 border-2 border-transparent rounded-2xl px-8 py-4 text-xs font-bold text-primary outline-none focus:border-primary/20">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ZONA: NOTICIAS (Novedades Colombia) -->
                <div v-else-if="webSubTab === 'news'" key="news" class="space-y-12 animate-in fade-in slide-in-from-bottom-10 duration-700 pb-20">
                    <div class="bg-white dark:bg-gray-900 p-10 md:p-16 rounded-[4rem] border border-gray-100 dark:border-white/5 shadow-2xl">
                        <div class="flex flex-col md:flex-row justify-between items-center gap-8 mb-16">
                            <div>
                                <h3 class="text-3xl font-black text-gray-900 dark:text-white uppercase tracking-tighter italic border-l-8 border-emerald-500 pl-8">Noticias 3D Colombia</h3>
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mt-2 ml-10 italic">Artículos y novedades del ecosistema nacional</p>
                            </div>
                            <button @click="settings.web.news.push({t: '', d: '', tag: 'EVENTO', i: '', url: ''})" class="px-8 py-4 bg-emerald-500 text-white rounded-2xl font-black text-[10px] uppercase tracking-widest shadow-xl shadow-emerald-500/20 hover:scale-105 transition-all">
                                Anadir Noticia
                            </button>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
                            <div v-for="(item, index) in settings.web.news" :key="index" class="p-10 bg-gray-50 dark:bg-white/5 rounded-[3rem] border border-gray-100 dark:border-white/5 space-y-8 hover:border-emerald-500/30 transition-all relative group/news">
                                <button @click="settings.web.news.splice(index, 1)" class="absolute top-6 right-6 w-10 h-10 bg-rose-500/10 text-rose-500 rounded-xl flex items-center justify-center opacity-0 group-hover/news:opacity-100 transition-opacity hover:bg-rose-500 hover:text-white">✕</button>
                                
                                <div class="flex items-center gap-4 mb-4">
                                    <span class="text-[9px] font-black px-4 py-1.5 bg-emerald-500/10 text-emerald-500 rounded-full uppercase tracking-widest">Entrada #{{ index + 1 }}</span>
                                    <input type="text" v-model="item.tag" class="bg-white dark:bg-gray-800 border-none rounded-lg px-4 py-1.5 text-[8px] font-black text-primary outline-none uppercase tracking-widest" placeholder="TAG">
                                </div>

                                <div class="space-y-6">
                                    <div class="group/input">
                                        <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-5 mb-2 block">Título de la Noticia</label>
                                        <input type="text" v-model="item.t" class="w-full bg-white dark:bg-gray-800 border-2 border-transparent rounded-2xl px-6 py-4 text-xs font-black text-gray-900 dark:text-white outline-none focus:border-emerald-500/20" placeholder="Título impactante...">
                                    </div>
                                    <div class="group/input">
                                        <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-5 mb-2 block">Resumen Ejecutivo</label>
                                        <textarea v-model="item.d" rows="3" class="w-full bg-white dark:bg-gray-800 border-2 border-transparent rounded-2xl px-6 py-4 text-[10px] font-medium text-gray-600 dark:text-gray-400 outline-none focus:border-emerald-500/20 resize-none" placeholder="Breve descripción del contenido..."></textarea>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 gap-6">
                                        <div class="group/input">
                                            <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-5 mb-2 block">URL de Imagen (Cloudinary / Web)</label>
                                            <div class="flex gap-4">
                                                <input type="text" v-model="item.i" class="flex-1 bg-white dark:bg-gray-800 border-2 border-transparent rounded-xl px-6 py-4 text-[9px] font-mono text-primary outline-none focus:border-emerald-500/20">
                                                <button @click="triggerNewsUpload(index)" class="px-6 bg-gray-900 text-white rounded-xl text-[9px] font-black uppercase hover:bg-primary transition-all"><svg class="w-3 h-3 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg></button>
                                                <input type="file" :id="'news-upload-' + index" @change="handleImageUploadSEO($event, item, 't')" accept="image/*" class="hidden">
                                            </div>
                                        </div>
                                        <div class="group/input">
                                            <label class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-5 mb-2 block">Enlace al Artículo Completo</label>
                                            <input type="text" v-model="item.url" class="w-full bg-white dark:bg-gray-800 border-2 border-transparent rounded-xl px-6 py-4 text-[9px] font-mono text-blue-400 outline-none focus:border-emerald-500/20" placeholder="https://...">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div v-if="settings.web.news.length === 0" class="py-20 text-center space-y-6">
                            <svg class="w-16 h-16 text-gray-300 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>
                            <p class="text-[10px] font-black text-gray-400 uppercase tracking-[0.4em]">Sin noticias activas en el portal</p>
                        </div>
                    </div>
                </div>

                <!-- ZONA: SOCIAL HUB (Manager de Posts) -->
                <div v-else-if="webSubTab === 'social'" key="social" class="space-y-12 animate-in fade-in slide-in-from-bottom-10 duration-700 pb-20">
                    <div class="bg-white dark:bg-gray-900 p-10 md:p-16 rounded-[4rem] border border-gray-100 dark:border-white/5 shadow-2xl">
                        <div class="flex flex-col md:flex-row justify-between items-center gap-8 mb-16">
                            <div>
                                <h3 class="text-3xl font-black text-gray-900 dark:text-white uppercase tracking-tighter italic border-l-8 border-primary pl-8">Social Hub Manager</h3>
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mt-2 ml-10 italic">Gestiona el feed de redes sociales en tiempo real</p>
                            </div>
                            <button @click="settings.web.posts.push({t: '', d: '', i: '', c: 'Instagram', l: '1.2K Likes', url: ''})" class="px-8 py-4 bg-primary text-white rounded-2xl font-black text-[10px] uppercase tracking-widest shadow-xl shadow-primary/20 hover:scale-105 transition-all">
                                Nueva Publicacion
                            </button>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                            <div v-for="(post, index) in settings.web.posts" :key="index" class="p-8 bg-gray-50 dark:bg-white/5 rounded-[3rem] border border-gray-100 dark:border-white/5 space-y-6 hover:border-primary/30 transition-all relative group/post">
                                <button @click="settings.web.posts.splice(index, 1)" class="absolute top-6 right-6 w-10 h-10 bg-rose-500/10 text-rose-500 rounded-xl flex items-center justify-center opacity-0 group-hover/post:opacity-100 transition-opacity hover:bg-rose-500 hover:text-white">✕</button>
                                
                                <div class="flex justify-between items-center">
                                    <span class="text-[10px] font-black px-4 py-1.5 bg-primary/10 text-primary rounded-full uppercase tracking-widest">Post #{{ index + 1 }}</span>
                                    <div class="flex items-center gap-2">
                                        <div class="w-6 h-6 flex items-center justify-center">
                                            <svg v-if="post.c === 'Instagram'" class="w-4 h-4 text-rose-500" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.058-1.69-.072-4.949-.072zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c.796 0 1.441.645 1.441 1.44s-.645 1.44-1.441 1.44c-.795 0-1.439-.645-1.439-1.44s.644-1.44 1.439-1.44z"/></svg>
                                            <svg v-else-if="post.c === 'TikTok'" class="w-4 h-4 text-black dark:text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-1.13-.32-2.43-.23-3.41.45-.83.53-1.33 1.47-1.38 2.44-.01.52.16 1.03.45 1.46.39.58 1.02.95 1.7 1.02 1.3.16 2.61-.41 3.17-1.58.34-.7.4-1.5.39-2.28-.02-5.91-.01-11.83-.01-17.74z"/></svg>
                                            <svg v-else-if="post.c === 'YouTube'" class="w-4 h-4 text-red-600" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                                            <svg v-else class="w-3 h-3 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                                        </div>
                                        <select v-model="post.c" class="bg-white dark:bg-gray-800 px-4 py-1.5 rounded-lg text-[9px] font-black text-gray-400 uppercase outline-none border-none shadow-sm cursor-pointer hover:text-primary transition-colors">
                                            <option>TikTok</option>
                                            <option>Instagram</option>
                                            <option>YouTube</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="aspect-[4/5] bg-white dark:bg-gray-800 rounded-[2.5rem] overflow-hidden border-2 border-dashed border-gray-200 dark:border-white/10 relative group/img">
                                    <img v-if="post.i" :src="post.i" class="w-full h-full object-cover" />
                                    <div v-else class="w-full h-full flex flex-col items-center justify-center gap-3">
                                        <span class="text-sm font-black text-gray-300">IMG</span>
                                        <span class="text-[8px] font-black text-gray-400 uppercase tracking-widest">Media Visual</span>
                                    </div>
                                    <div class="absolute inset-0 bg-black/60 flex flex-col items-center justify-center p-4 opacity-0 group-hover/img:opacity-100 transition-opacity">
                                        <input type="text" v-model="post.i" placeholder="URL imagen..." class="w-full bg-white text-gray-900 rounded-lg p-2 text-[8px] font-mono mb-4">
                                        <button @click="triggerPostUpload(index)" class="w-full py-3 bg-primary text-white rounded-xl text-[9px] font-black uppercase mb-2">Subir Media</button>
                                        <input type="file" :id="'post-upload-' + index" @change="handleImageUploadSEO($event, post, 't')" accept="image/*" class="hidden">
                                    </div>
                                </div>

                                <div class="space-y-4">
                                    <input type="text" v-model="post.t" class="w-full bg-white dark:bg-gray-800 border-none rounded-xl px-6 py-4 text-[11px] font-black text-gray-900 dark:text-white outline-none" placeholder="Título / Pie de Foto">
                                    
                                    <div class="space-y-4">
                                        <div class="group/input">
                                            <label class="text-[8px] font-black text-gray-400 uppercase tracking-widest ml-4 mb-1 block">Interacción Visual (Likes/Vistas)</label>
                                            <input type="text" v-model="post.l" class="w-full bg-white dark:bg-gray-800 border-none rounded-xl px-6 py-3 text-[9px] font-bold text-rose-500 outline-none" placeholder="1.5K Likes">
                                        </div>
                                        <div class="group/input">
                                            <label class="text-[8px] font-black text-blue-400 uppercase tracking-widest ml-4 mb-1 block">Enlace Directo al Post (URL)</label>
                                            <input type="text" v-model="post.url" class="w-full bg-white dark:bg-gray-800 border-none rounded-xl px-6 py-3 text-[9px] font-bold text-blue-500 outline-none" placeholder="https://www.instagram.com/p/...">
                                        </div>
                                    </div>

                                    <textarea v-model="post.d" rows="2" class="w-full bg-white dark:bg-gray-800 border-none rounded-xl px-6 py-3 text-[10px] font-bold text-gray-500 outline-none resize-none" placeholder="Breve descripción del contenido..."></textarea>
                                </div>
                            </div>
                        </div>

                        <div v-if="settings.web.posts.length === 0" class="py-20 text-center space-y-6">
                            <svg class="w-16 h-16 text-gray-300 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                            <p class="text-[10px] font-black text-gray-400 uppercase tracking-[0.4em]">Feed de redes sociales vacío</p>
                        </div>
                    </div>
                </div>
            </transition>
        </div>

        <!-- Módulo: Configuración Global (Settings) -->
        <div v-if="activeTab === 'settings'" class="p-0 md:p-8 lg:p-12 animate-in fade-in slide-in-from-bottom-4 duration-500 max-w-[1600px] mx-auto space-y-12 pb-12">
            <!-- Header Seccional Premium -->
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-8 mb-16 bg-white/40 dark:bg-gray-950/40 backdrop-blur-xl p-8 md:p-12 rounded-[3rem] md:rounded-[4rem] border border-white/60 dark:border-white/5 shadow-2xl shadow-gray-200/30 dark:shadow-none relative overflow-hidden group">
              <div class="absolute -right-20 -top-20 w-64 h-64 bg-primary/5 rounded-full blur-3xl group-hover:bg-primary/10 transition-all duration-1000"></div>
              <div class="relative z-10">
                <h2 class="text-3xl md:text-5xl font-black text-gray-900 dark:text-white tracking-tighter uppercase mb-4">Configuración <span class="text-primary">Global</span></h2>
                <p class="text-[10px] md:text-sm text-gray-400 font-bold uppercase tracking-[0.3em] flex items-center gap-3">
                  <span class="w-3 h-3 rounded-full bg-emerald-500 animate-pulse"></span>
                  Motor de Gestión Financiera • N3XT Engine
                </p>
              </div>

              <button @click="saveSettings" class="bg-primary hover:bg-emerald-600 text-white w-full md:w-auto px-12 py-6 rounded-[2rem] shadow-xl shadow-primary/20 flex items-center justify-center gap-4 active:scale-95 transition-all duration-200 z-50 cursor-pointer">
                <span v-if="savingSettings" class="w-6 h-6 border-4 border-white/30 border-t-white rounded-full animate-spin"></span>
                <svg v-else class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                <span class="text-lg font-black uppercase tracking-widest">{{ savingSettings ? 'Guardando...' : 'Sincronizar Cambios' }}</span>
              </button>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-10 lg:gap-16">
              <!-- Columna 1: Identidad y Seguridad -->
              <div class="space-y-12">
                <!-- Branding Card -->
                <div class="bg-white dark:bg-gray-900 p-8 md:p-12 rounded-[3rem] md:rounded-[4rem] border border-gray-100 dark:border-white/5 shadow-xl shadow-gray-100/50 relative overflow-hidden group">
                  <h3 class="text-xl font-black text-gray-900 dark:text-white uppercase tracking-tighter mb-10 border-l-8 border-primary pl-6">Identidad Visual</h3>
                  
                  <div class="space-y-10">
                    <div class="flex flex-col items-center justify-center p-12 bg-gray-50/80 dark:bg-white/5 backdrop-blur-sm rounded-[3rem] border-2 border-dashed border-gray-200 dark:border-white/10 group/upload hover:bg-white dark:hover:bg-white/10 hover:border-primary/50 transition-all duration-700 relative shadow-inner">
                      <div v-if="settings.company_logo" class="relative group/img">
                        <img :src="logoUrl" @error="(e) => e.target.src = '/logo.png'" class="h-32 w-auto object-contain mb-8 drop-shadow-[0_20px_50px_rgba(0,0,0,0.15)] group-hover/upload:scale-110 transition-transform duration-700">
                        <div class="absolute inset-0 bg-primary/20 rounded-full blur-3xl opacity-0 group-hover/img:opacity-30 transition-opacity pointer-events-none"></div>
                      </div>
                      <div v-else class="w-32 h-32 bg-gray-200/50 dark:bg-white/5 rounded-[2.5rem] mb-8 flex items-center justify-center text-gray-400 border border-gray-200 dark:border-white/10">
                        <svg class="w-12 h-12 opacity-20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                      </div>
                      
                      <input 
                        ref="logoInput"
                        type="file" 
                        @change="handleLogoUpload" 
                        class="hidden" 
                        accept="image/*"
                      >
                      
                      <button 
                        @click="$refs.logoInput.click()"
                        :disabled="uploadingLogo"
                        class="bg-gray-900 dark:bg-primary text-white px-12 py-5 rounded-2xl shadow-2xl text-[10px] font-black uppercase tracking-[0.2em] hover:scale-105 active:scale-95 transition-all duration-300 disabled:opacity-50 disabled:cursor-wait flex items-center gap-3"
                      >
                        <span v-if="uploadingLogo" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                        <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        {{ uploadingLogo ? 'Sincronizando...' : 'Actualizar Marca' }}
                      </button>
                    </div>

                    <div class="space-y-6">
                      <div class="group/input">
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-5 mb-2 block group-focus-within/input:text-primary transition-colors">Nombre Oficial</label>
                        <input type="text" v-model="settings.company.name" class="w-full bg-gray-50 dark:bg-gray-800 border-2 border-transparent rounded-[2rem] px-8 py-5 text-sm font-bold text-gray-900 dark:text-white outline-none focus:bg-white dark:focus:bg-gray-700 focus:border-primary/20 focus:shadow-xl transition-all duration-300">
                      </div>
                      <div class="group/input">
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-5 mb-2 block group-focus-within/input:text-primary transition-colors">Slogan Corporativo</label>
                        <input type="text" v-model="settings.company.slogan" class="w-full bg-gray-50 dark:bg-gray-800 border-2 border-transparent rounded-[2rem] px-8 py-5 text-sm font-bold text-gray-900 dark:text-white outline-none focus:bg-white dark:focus:bg-gray-700 focus:border-primary/20 focus:shadow-xl transition-all duration-300">
                      </div>
                    </div>

                    <!-- Workshop PIN Decommissioned -->
                  </div>
                </div>

                <!-- Danger Zone -->
                <div class="bg-rose-50/50 p-8 md:p-12 rounded-[3rem] border border-rose-100 shadow-xl shadow-rose-100/20 relative overflow-hidden group">
                  <div class="relative z-10 flex flex-col items-center">
                    <h3 class="text-xl font-black text-rose-900 uppercase tracking-tighter mb-2 text-center">Zona Crítica</h3>
                    <p class="text-[10px] text-rose-400 font-bold uppercase mb-8 tracking-widest text-center">Purga y mantenimiento profundo</p>

                    <button 
                        @click="handlePurgeAll"
                        class="px-8 py-4 bg-rose-500 hover:bg-rose-600 text-white rounded-2xl font-black uppercase tracking-widest text-xs transition-all duration-300 shadow-lg shadow-rose-500/30 hover:shadow-rose-500/50 active:scale-95 flex items-center gap-3 z-20 relative"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        Ejecutar Purga Total
                    </button>

                    <p class="text-[9px] text-rose-300 text-center mt-8 font-bold uppercase tracking-[0.3em] opacity-80">N3XT OS v3.2.4 Premium • Build Stable</p>
                  </div>
                  <div class="absolute -bottom-12 -right-12 opacity-[0.03] text-rose-900 group-hover:scale-110 transition-transform duration-1000">
                    <svg class="w-56 h-56" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.47 2 2 6.47 2 12s4.47 10 10 10 10-4.47 10-10S17.53 2 12 2zm5 13.59L15.59 17 12 13.41 8.41 17 7 15.59 10.59 12 7 8.41 8.41 7 12 10.59 15.59 7 17 8.41 13.41 12 17 15.59z"/></svg>
                  </div>
                </div>
              </div>

              <!-- Columna 2 y 3: Configuración Maestra -->
              <div class="lg:col-span-2 space-y-12">
                <!-- Master Costs Card -->
                <div class="bg-white p-8 md:p-16 rounded-[4rem] border border-gray-100 shadow-2xl shadow-gray-200/40 relative group">
                  <div class="flex flex-col md:flex-row md:items-center justify-between gap-8 mb-16">
                    <div class="border-l-8 border-primary pl-8">
                      <h3 class="text-2xl md:text-4xl font-black text-gray-900 uppercase tracking-tighter">Matriz de Costos Industriales</h3>
                      <p class="text-[10px] md:text-xs font-bold text-gray-400 uppercase tracking-[0.4em] mt-2">Algoritmo de Cálculo Operativo</p>
                    </div>
                    <div class="flex items-center gap-4 px-6 py-3 bg-emerald-50 text-emerald-600 rounded-[2rem] text-[10px] font-black uppercase tracking-widest animate-pulse border border-emerald-100/50 shadow-lg shadow-emerald-500/5">
                      <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 shadow-[0_0_10px_rgba(16,185,129,0.5)]"></span>
                      Motor Financiero Optimizado
                    </div>
                  </div>

                  <div class="grid grid-cols-1 xl:grid-cols-2 gap-12 lg:gap-16">
                    <!-- Infraestructura -->
                    <div class="space-y-10">

                      <div class="flex items-center gap-4 border-b border-gray-100 pb-6">
                        <div class="w-12 h-12 rounded-2xl bg-rose-500/10 text-rose-500 flex items-center justify-center shadow-inner">
                          <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                        </div>
                        <div>
                          <p class="text-[12px] font-black text-gray-900 uppercase tracking-[0.2em]">Gastos de Planta</p>
                          <p class="text-[9px] font-bold text-gray-400 uppercase tracking-widest">Costos fijos operacionales</p>
                        </div>
                      </div>
                      <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div v-for="(label, key) in { luz_hr: 'Energía / Luz', depr_hr: 'Depreciación', mant_hr: 'Mantenimiento', etiquetas: 'Etiquetas/Empaque' }" :key="key" class="bg-gray-50/50 p-8 rounded-[2.5rem] border border-gray-100 hover:border-rose-500/30 hover:bg-white hover:shadow-2xl hover:shadow-rose-500/10 transition-all duration-500 group relative overflow-hidden">
                          <div class="absolute -right-6 -top-6 w-20 h-20 bg-rose-500/5 rounded-full blur-2xl group-hover:bg-rose-500/10 transition-all duration-700"></div>
                          <p class="text-[10px] font-black text-gray-400 uppercase mb-4 group-hover:text-rose-500 transition-colors tracking-widest">{{ label }}</p>
                          <div class="flex items-end gap-3">
                            <span class="text-gray-300 font-black text-2xl leading-none mb-1 group-hover:text-rose-300 transition-colors">$</span>
                            <input type="number" v-model.number="settings.infra[key]" min="0" step="any" class="w-full bg-transparent border-none p-0 font-black text-4xl text-gray-900 outline-none tracking-tighter focus:text-rose-600 transition-colors">
                          </div>
                        </div>
                        
                        <!-- Factor de Carga con Explicación Premium -->
                        <div class="col-span-1 sm:col-span-2 bg-gray-900 p-8 md:p-10 rounded-[3rem] border border-gray-800 hover:border-primary transition-all duration-700 group relative overflow-hidden shadow-2xl shadow-black/40">
                          <div class="absolute -right-10 -top-10 w-40 h-40 bg-primary/10 rounded-full blur-[80px] group-hover:bg-primary/20 transition-all duration-1000"></div>
                          <div class="flex justify-between items-start mb-6 relative z-10">
                            <div>
                              <p class="text-[10px] font-black text-gray-400 uppercase tracking-[0.3em] group-hover:text-primary transition-colors">Factor de Carga Operativa</p>
                              <p class="text-[8px] font-bold text-gray-600 uppercase tracking-widest mt-1">Eficiencia Real vs Teórica</p>
                            </div>
                            <span class="text-[9px] font-black bg-primary/20 text-primary px-4 py-1.5 rounded-full border border-primary/20">Modo Alta Precisión</span>
                          </div>
                          <div class="flex items-center gap-8 relative z-10">
                            <input type="number" step="0.1" min="0" max="1" v-model.number="settings.infra.load_factor" class="w-28 bg-transparent border-none p-0 font-black text-5xl text-white outline-none tracking-tighter focus:text-primary transition-all">
                            <div class="flex-1 h-3 bg-white/5 rounded-full overflow-hidden border border-white/5 p-[2px]">
                                <div :style="{ width: (Math.min(Math.max(settings.infra.load_factor * 100, 0), 100)) + '%' }" class="h-full bg-primary transition-all duration-1000 shadow-[0_0_20px_rgba(var(--primary),0.8)] rounded-full"></div>
                            </div>
                          </div>
                          <p class="text-[9px] font-medium text-gray-500 mt-6 leading-relaxed uppercase tracking-widest opacity-80">
                            Ajuste de consumo para compensar tiempos muertos. <br>
                            <span class="text-primary font-black">Estándar N3XT: 0.4 (40%)</span>
                          </p>
                        </div>
                      </div>
                    </div>

                    <!-- Operativos -->
                    <div class="space-y-10">
                      <div class="flex items-center gap-4 border-b border-gray-100 pb-6">
                        <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center shadow-inner">
                          <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
                        </div>
                        <div>
                          <p class="text-[12px] font-black text-gray-900 uppercase tracking-[0.2em]">Estrategia de Margen</p>
                          <p class="text-[9px] font-bold text-gray-400 uppercase tracking-widest">Riesgo & Utilidad Neta</p>
                        </div>
                      </div>
                      <div class="space-y-6">
                        <div class="bg-gradient-to-br from-emerald-50/50 to-white p-10 rounded-[3rem] border border-emerald-100 group relative overflow-hidden shadow-xl shadow-emerald-500/5">
                          <div class="absolute -right-12 -bottom-12 w-48 h-48 bg-emerald-500/10 rounded-full blur-3xl group-hover:scale-125 transition-transform duration-1000"></div>
                          <div class="flex justify-between items-center mb-8 relative z-10">
                            <div>
                                <p class="text-[12px] font-black text-emerald-700 uppercase tracking-[0.2em]">Margen de Utilidad Objetivo</p>
                                <p class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mt-1">Retorno sobre costo de producción</p>
                            </div>
                            <div class="px-8 py-4 bg-emerald-500 text-white rounded-[2rem] font-black text-3xl shadow-2xl shadow-emerald-500/40 border border-emerald-400/30 transform group-hover:scale-110 transition-all duration-500">
                                {{ settings.oper.ganancia }}%
                            </div>
                          </div>
                          <input type="range" v-model.number="settings.oper.ganancia" min="0" max="500" class="w-full h-3 bg-emerald-100 rounded-full appearance-none cursor-pointer accent-emerald-500 shadow-inner">
                        </div>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                          <div class="bg-gray-50/50 p-8 rounded-[2.5rem] border border-gray-100 hover:border-primary/30 hover:bg-white hover:shadow-2xl transition-all duration-500 group">
                            <p class="text-[10px] font-black text-gray-400 uppercase mb-4 group-hover:text-primary transition-colors tracking-widest">Mano de Obra ($/Hr)</p>
                            <div class="flex items-end gap-3">
                              <span class="text-gray-300 font-black text-2xl leading-none mb-1 group-hover:text-primary/50 transition-colors">$</span>
                              <input type="number" v-model.number="settings.prep.mano_obra_hr" min="0" step="any" class="w-full bg-transparent border-none p-0 font-black text-3xl text-gray-900 outline-none tracking-tighter">
                            </div>
                          </div>
                          
                          <div class="bg-gray-50/50 p-8 rounded-[2.5rem] border border-gray-100 hover:border-primary/30 hover:bg-white hover:shadow-2xl transition-all duration-500 group">
                            <p class="text-[10px] font-black text-gray-400 uppercase mb-4 group-hover:text-primary transition-colors tracking-widest">% Prep / Post</p>
                            <div class="flex items-end gap-3">
                              <input type="number" v-model.number="settings.prep.prep_time_pct" min="0" max="100" class="w-full bg-transparent border-none p-0 font-black text-3xl text-gray-900 outline-none tracking-tighter text-right">
                              <span class="text-gray-300 font-black text-2xl leading-none mb-1 group-hover:text-primary/50 transition-colors">%</span>
                            </div>
                          </div>

                          <div class="bg-gray-50/50 p-8 rounded-[2.5rem] border border-gray-100 hover:border-rose-500/30 hover:bg-white hover:shadow-2xl transition-all duration-500 group">
                            <p class="text-[10px] font-black text-gray-400 uppercase mb-4 group-hover:text-rose-500 transition-colors tracking-widest">Fallas & Scrap</p>
                            <div class="flex items-end gap-3">
                              <input type="number" v-model.number="settings.oper.fallos" min="0" max="100" class="w-full bg-transparent border-none p-0 font-black text-3xl text-gray-900 outline-none tracking-tighter text-right">
                              <span class="text-gray-300 font-black text-2xl leading-none mb-1 group-hover:text-rose-300 transition-colors">%</span>
                            </div>
                          </div>

                          <div class="bg-indigo-950 p-8 rounded-[2.5rem] border border-indigo-900 hover:border-indigo-400/50 transition-all duration-700 group relative overflow-hidden shadow-xl shadow-indigo-900/20">
                             <div class="absolute -right-6 -top-6 w-24 h-24 bg-white/5 rounded-full blur-2xl group-hover:bg-white/10 transition-all duration-1000"></div>
                             <p class="text-[10px] font-black text-indigo-300 uppercase mb-4 tracking-widest">IVA / Fiscal</p>
                             <div class="flex items-end gap-3">
                               <input type="number" v-model.number="settings.margin.iva" min="0" max="100" class="w-full bg-transparent border-none p-0 font-black text-3xl text-white outline-none tracking-tighter text-right focus:text-indigo-400">
                               <span class="text-indigo-500 font-black text-2xl leading-none mb-1">%</span>
                             </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div> <!-- Cierre Grid Principal de Costos (2087) -->
                </div> <!-- Cierre Master Costs Card (2079) -->
              </div> <!-- Cierre Columna 2 y 3 (2077) -->


              <!-- Facturación y Fiscal Card -->

              <div class="lg:col-span-2 bg-gray-900 p-8 md:p-16 rounded-[4rem] shadow-2xl relative overflow-hidden h-fit border border-white/5">
                <div class="absolute -right-20 -bottom-20 w-96 h-96 bg-indigo-500/5 rounded-full blur-[120px] pointer-events-none"></div>
                <div class="relative z-10">
                  <h3 class="text-xl md:text-3xl font-black text-white uppercase tracking-tighter mb-12 border-l-8 border-indigo-500 pl-8">Parámetros Fiscales & Despacho</h3>
                  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">

                    <div v-for="(label, key) in { nit: 'NIT / Identificación', phone: 'WhatsApp / Tel', email: 'Email de Ventas', address: 'Dirección de Taller', website: 'Website / Portafolio' }" :key="key" class="space-y-3 group/fiscal">
                      <label class="text-[10px] font-black text-gray-500 uppercase tracking-[0.2em] ml-6 group-focus-within/fiscal:text-indigo-400 transition-colors">{{ label }}</label>
                      <input :type="key === 'email' ? 'email' : 'text'" v-model="settings.company[key]" class="w-full bg-white/5 border border-white/10 rounded-[2rem] px-8 py-5 text-sm font-bold text-white outline-none focus:bg-white/10 focus:border-indigo-500 focus:shadow-[0_0_30px_rgba(99,102,241,0.1)] transition-all duration-300">
                    </div>
                  </div>
                </div>
                <div class="absolute -top-20 -right-20 opacity-[0.03] pointer-events-none transform rotate-12">
                  <svg class="w-96 h-96 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M19 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
                </div>
              </div> <!-- Cierre lg:col-span-2 -->



            </div> <!-- Cierre grid Principal de Ajustes -->
        </div> <!-- Cierre activeTab === settings -->
                    </div> <!-- Cierre key=activeTab -->
                </transition>
            </div> <!-- Cierre max-w -->
        </div> <!-- Cierre Scrollable Container -->
        
        <!-- Mobile Bottom Nav -->
        <nav class="md:hidden fixed bottom-0 left-0 right-0 bg-white/80 dark:bg-black/80 backdrop-blur-3xl border-t border-gray-100 dark:border-white/5 z-40 pb-safe">
            <div class="flex items-center justify-around p-2">
                <button v-for="item in [
                    { id: 'kanban', label: 'Inicio', icon: 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10' },
                    { id: 'inventory', label: 'Material', icon: 'M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0a2 2 0 012 2v4a2 2 0 01-2 2H4a2 2 0 01-2-2v-4a2 2 0 012-2m16 0h-2M4 13H6m8 10V10m-4 10V10' },
                    { id: 'machines', label: 'Máquinas', icon: 'M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z' },
                    { id: 'analytics', label: 'Métricas', icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z' },
                    { id: 'settings', label: 'Ajustes', icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z' }
                ]" 
                :key="item.id"
                @click="activeTab = item.id === 'analytics' ? 'accounting' : item.id"
                class="flex flex-col items-center p-2 rounded-xl transition-colors min-w-[64px]"
                :class="activeTab === (item.id === 'analytics' ? 'accounting' : item.id) ? 'text-primary' : 'text-gray-400 hover:text-gray-900 dark:hover:text-white'"
                >
                    <svg class="w-6 h-6 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
                    </svg>
                    <span class="text-[9px] font-bold uppercase tracking-wider">{{ item.label }}</span>
                </button>
            </div>
        </nav>
    </main> <!-- Cierre Main Content Area -->

<!-- Modals -->
    <div v-if="modalState.shipping" class="fixed inset-0 bg-gray-950/60 backdrop-blur-md flex items-center justify-center p-4 z-[500]">
      <div class="bg-white dark:bg-gray-900 rounded-[3rem] shadow-2xl max-w-md w-full p-10 animate-in zoom-in-95 duration-200 border border-gray-100 dark:border-white/5">
            <div class="w-16 h-16 bg-emerald-100 dark:bg-emerald-500/10 text-emerald-500 rounded-2xl flex items-center justify-center mb-6 mx-auto">
              <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" /></svg>
            </div>
            <h3 class="text-3xl font-black text-gray-900 dark:text-white text-center uppercase tracking-tighter mb-2">Despachar Orden</h3>
            <div class="space-y-4 mb-8">
                <select 
                    v-model="trackingCarrier"
                    class="w-full bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-2xl px-6 py-4 text-xs font-bold text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-emerald-500 appearance-none cursor-pointer"
                >
                    <option value="" class="bg-white dark:bg-gray-800 text-gray-900 dark:text-white">Seleccionar Transportadora...</option>
                    <option value="Servientrega" class="bg-white dark:bg-gray-800 text-gray-900 dark:text-white">Servientrega</option>
                    <option value="Interrapidisimo" class="bg-white dark:bg-gray-800 text-gray-900 dark:text-white">Interrapidísimo</option>
                    <option value="Envia" class="bg-white dark:bg-gray-800 text-gray-900 dark:text-white">Envía</option>
                    <option value="Coordinadora" class="bg-white dark:bg-gray-800 text-gray-900 dark:text-white">Coordinadora</option>
                    <option value="TCC" class="bg-white dark:bg-gray-800 text-gray-900 dark:text-white">TCC</option>
                    <option value="Recoge en Taller" class="bg-white dark:bg-gray-800 text-gray-900 dark:text-white">Recoge en Taller</option>
                    <option value="Personalizado" class="bg-white dark:bg-gray-800 text-gray-900 dark:text-white">Otro / Personalizado</option>
                </select>

                <input 
                    type="text" 
                    v-model="trackingGuide" 
                    placeholder="Ej: GUIA-123456" 
                    class="w-full bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 text-gray-900 dark:text-white text-lg font-bold rounded-2xl px-6 py-4 outline-none focus:border-emerald-500 text-center tracking-widest uppercase shadow-inner"
                >
            </div>
            
            <div class="flex gap-4">
              <button @click="modalState.shipping = false" class="btn-secondary flex-1 py-4">Cancelar</button>
              <button @click="confirmShipping" class="btn-primary flex-1 py-4 !bg-emerald-500 hover:!bg-emerald-600 shadow-emerald-500/20">Confirmar</button>
            </div>
          </div>
        </div>

    <div v-if="modalState.printerStatus" class="fixed inset-0 z-[500] flex items-center justify-center p-6 bg-gray-950/60 backdrop-blur-md">
        <div class="bg-white dark:bg-gray-900 w-full max-w-lg rounded-[3rem] shadow-2xl p-10 animate-in zoom-in duration-300">
            <div class="flex justify-between items-center mb-8">
                <h3 class="text-2xl font-black text-gray-900 dark:text-white uppercase tracking-tighter">Asignar Producción</h3>
                <div class="flex items-center gap-2 px-4 py-2 bg-gray-900 dark:bg-primary text-white rounded-full text-[10px] font-black uppercase tracking-widest shadow-xl">
                    <svg v-if="selectedOrderForPrinter?.technology === 'FDM'" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                    <svg v-else class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.618.309a6 6 0 01-3.86.517l-2.387-.477a2 2 0 00-1.022.547l-1.16 1.16a2 2 0 00.442 3.102l1.644.822a6 6 0 005.366 0l1.644-.822a2 2 0 00.442-3.102l-1.16-1.16zM15.75 9a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" /></svg>
                    {{ selectedOrderForPrinter?.technology }}
                </div>
            </div>
            
            <p class="text-[10px] text-gray-400 dark:text-gray-500 font-black uppercase tracking-widest mb-6 px-1">Selecciona una máquina compatible:</p>
            
            <div class="grid grid-cols-1 gap-4 max-h-[50vh] overflow-y-auto no-scrollbar pr-2">
                <button 
                    v-for="p in printers.filter(p => p.technology === selectedOrderForPrinter?.technology)" 
                    :key="p.id" 
                    @click="confirmAssignment(p.id)" 
                    :disabled="p.status === 'maintenance' || p.status === 'offline'"
                    class="p-6 border-2 border-gray-100 dark:border-white/5 rounded-2xl hover:border-primary hover:bg-primary/5 transition-all flex justify-between items-center group disabled:opacity-50 disabled:grayscale"
                >
                    <div class="flex items-center gap-4">
                        <div :class="p.status === 'idle' ? 'bg-emerald-500' : 'bg-amber-500'" class="w-3 h-3 rounded-full animate-pulse"></div>
                        <div>
                            <span class="font-black text-gray-900 dark:text-white block">{{ p.name }}</span>
                            <span class="text-[10px] text-gray-400 dark:text-gray-500 font-bold uppercase">{{ p.model }}</span>
                        </div>
                    </div>
                    <div class="text-right">
                        <span :class="[
                            'text-[9px] font-black uppercase tracking-widest px-3 py-1 rounded-lg border',
                            p.status === 'idle' ? 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-100 dark:border-emerald-500/20' : 'bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-100 dark:border-amber-500/20'
                        ]">
                            {{ p.status === 'idle' ? 'Libre' : 'OCUPADA' }}
                        </span>
                    </div>
                </button>
            </div>
            
            <button @click="modalState.printerStatus = false" class="w-full mt-10 py-4 bg-gray-50 dark:bg-white/5 text-gray-400 dark:text-gray-300 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-gray-900 dark:hover:bg-primary hover:text-white transition-all">
                Cerrar Panel
            </button>
        </div>
    </div>
    
    <!-- Modal Simulador Interno PRO -->
    <div v-if="modalState.simulator" class="fixed inset-0 z-[500] flex items-center justify-center p-0 md:p-6 bg-gray-950/80 backdrop-blur-md">
        <div class="bg-white dark:bg-gray-900 w-full max-w-6xl md:rounded-[4rem] shadow-2xl overflow-hidden flex flex-col md:flex-row h-full md:h-[90vh] animate-in zoom-in duration-500">
            <!-- Columna Izquierda: Entradas de Usuario (Scrollable) -->
            <div class="w-full md:w-2/5 bg-gray-900 dark:bg-black p-6 md:p-12 text-white flex flex-col overflow-y-auto no-scrollbar border-b md:border-b-0 md:border-r border-white/5 h-[45vh] md:h-full">
                <div class="mb-6 md:mb-10">
                    <span class="text-[9px] md:text-[10px] font-black text-primary px-3 py-1 bg-primary/10 rounded-full uppercase tracking-widest border border-primary/20">N3XT Intelligence v3.0</span>
                    <h3 class="text-xl md:text-3xl font-black tracking-tighter mt-4 uppercase">Análisis de<br class="hidden md:block"> Manufactura</h3>
                </div>

                <div class="space-y-6 flex-1">
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-gray-500 uppercase tracking-widest">Nombre del Proyecto / Trabajo</label>
                        <input type="text" v-model="simulator.job_name" placeholder="Ej: Casco IronMan" class="w-full bg-gray-800 border-none rounded-2xl p-4 font-bold text-sm text-white outline-none focus:ring-2 focus:ring-primary">
                    </div>

                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-primary uppercase tracking-widest">Seleccionar Cliente Registrado</label>
                        <select @change="handleSelectCustomer" class="w-full bg-gray-800 border-none rounded-2xl p-4 font-bold text-sm text-white outline-none focus:ring-2 focus:ring-primary">
                            <option value="">-- Cliente Nuevo / Invitado --</option>
                            <option v-for="c in contacts.customers" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Nombre (Manual)</label>
                            <input type="text" v-model="simulator.customer_name" placeholder="Nombre" class="w-full bg-gray-800 border-none rounded-2xl p-4 font-bold text-sm text-white outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">WhatsApp / Tel</label>
                            <input type="text" v-model="simulator.customer_phone" placeholder="+57 ..." class="w-full bg-gray-800 border-none rounded-2xl p-4 font-bold text-sm text-white outline-none focus:ring-2 focus:ring-primary">
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Documento ID (CC / NIT)</label>
                        <input type="text" v-model="simulator.customer_id_document" placeholder="Ej: 1.027.xxx.xxx" class="w-full bg-gray-800 border-none rounded-2xl p-4 font-bold text-sm text-white outline-none focus:ring-2 focus:ring-primary">
                    </div>

                    <!-- Logistics Fields (Visible for accurate quotes) -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Empresa / Negocio</label>
                            <input type="text" v-model="simulator.customer_company" placeholder="Nombre de Empresa" class="w-full bg-gray-800 border-none rounded-2xl p-4 font-bold text-sm text-white outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Email de Contacto</label>
                            <input type="email" v-model="simulator.customer_email" placeholder="email@ejemplo.com" class="w-full bg-gray-800 border-none rounded-2xl p-4 font-bold text-sm text-white outline-none focus:ring-2 focus:ring-primary">
                        </div>
                    </div>

                    <div v-if="modalState.shipping_details" class="space-y-4 animate-in slide-in-from-top-2 duration-300">
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Dirección de Envío</label>
                            <input type="text" v-model="simulator.shipping_address" placeholder="Calle, Carrera, Apto..." class="w-full bg-gray-800 border-none rounded-2xl p-4 font-bold text-sm text-white outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <input type="text" v-model="simulator.shipping_city" placeholder="Ciudad" class="w-full bg-gray-800 border-none rounded-2xl p-4 font-bold text-sm text-white outline-none focus:ring-2 focus:ring-primary">
                            <input type="text" v-model="simulator.shipping_zip" placeholder="Cód. Postal" class="w-full bg-gray-800 border-none rounded-2xl p-4 font-bold text-sm text-white outline-none focus:ring-2 focus:ring-primary">
                        </div>
                    </div>
                    <button @click="modalState.shipping_details = !modalState.shipping_details" class="text-[9px] font-black text-primary uppercase tracking-widest hover:underline">
                        {{ modalState.shipping_details ? '- Ocultar Datos de Envío' : '+ Añadir Datos de Envío' }}
                    </button>

                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-gray-500 uppercase tracking-widest">Material Base (Filamento / Resina)</label>
                        <select v-model="simulator.material_id" class="w-full bg-gray-800 border-none rounded-2xl p-4 font-bold text-sm text-white outline-none focus:ring-2 focus:ring-primary">
                            <option value="">Seleccionar...</option>
                            <option v-for="m in (inventoryData || []).filter(i => i.type === 'material')" :key="m.id" :value="m.id">{{ m.name }} ({{ m.category }})</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-gray-500 uppercase tracking-widest ml-4">
                                {{ getSimulatorUnit() }}
                            </label>
                            <input type="number" v-model.number="simulator.weight_g" class="w-full bg-gray-800 border-none rounded-2xl p-4 font-bold text-sm text-white outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-gray-500 uppercase tracking-widest">Tiempo (H:M)</label>
                            <input type="text" v-model="simulator.time_str" placeholder="0:00" class="w-full bg-gray-800 border-none rounded-2xl p-4 font-bold text-sm text-white outline-none focus:ring-2 focus:ring-primary">
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-gray-500 uppercase tracking-widest">Piezas por Lote (Batch Size)</label>
                        <input type="number" v-model.number="simulator.pieces_per_batch" min="1" class="w-full bg-gray-800 border-none rounded-2xl p-4 font-bold text-sm text-white outline-none focus:ring-2 focus:ring-primary">
                        <p class="text-[9px] text-gray-500 font-medium">Esto divide el costo total entre el número de unidades.</p>
                    </div>

                    <!-- Añadir Adicionales -->
                    <div class="pt-6 border-t border-gray-800">
                        <label class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-4 block">Añadir Adicionales y Empaque</label>
                        <select @change="addSimulatorExtra" class="w-full bg-gray-800 border-none rounded-2xl p-4 font-bold text-sm text-white outline-none mb-4 focus:ring-2 focus:ring-primary">
                            <option value="">Seleccionar Insumo...</option>
                            <option v-for="m in inventoryData.filter(i => i.type !== 'material')" :key="m.id" :value="m.id">
                                {{ m.name }} (${{ m.cost_per_kg }} / {{ m.unit || 'unid' }})
                            </option>
                        </select>

                        <!-- Cuadro de Insumos Seleccionados -->
                        <div v-if="simulator.extra_items.length" class="space-y-2 max-h-52 overflow-y-auto scrollbar-thin pr-2 mt-4">
                            <div v-for="(item, idx) in simulator.extra_items" :key="idx" class="bg-gray-900/50 border border-white/5 p-4 rounded-2xl flex items-center gap-4 group hover:bg-gray-800 transition-all">
                                <div class="flex-1">
                                    <p class="text-[10px] font-black text-white uppercase tracking-tighter">{{ item.name }}</p>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="text-[8px] font-bold text-gray-500 uppercase">UNIT:</span>
                                        <span class="text-[9px] font-black text-primary italic">${{ item.cost }} / {{ item.unit || 'unid' }}</span>
                                    </div>
                                </div>

                                <div class="flex items-center gap-4">
                                    <div class="flex flex-col items-center">
                                        <span class="text-[7px] font-black text-gray-600 uppercase mb-1">Cant.</span>
                                        <input type="number" v-model.number="item.qty" min="1" class="w-12 bg-gray-950 border-none rounded-lg p-2 text-[10px] font-black text-white text-center outline-none focus:ring-1 focus:ring-primary">
                                    </div>

                                    <div class="flex flex-col items-end min-w-[60px]">
                                        <span class="text-[7px] font-black text-gray-600 uppercase mb-1">Total Item</span>
                                        <span class="text-[10px] font-black text-emerald-400">$ {{ (item.cost * item.qty).toLocaleString() }}</span>
                                    </div>

                                    <button @click="removeSimulatorExtra(idx)" class="p-2 text-gray-700 hover:text-rose-500 transition-colors">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- Notas Internas / Comentarios -->
                    <div class="mt-8 pt-8 border-t border-gray-800">
                        <label class="text-[10px] font-black text-primary uppercase tracking-widest mb-4 block flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
                            Notas Internas (Solo Taller)
                        </label>
                        <textarea 
                            v-model="simulator.comments" 
                            placeholder="Ej: Requiere lijado extra, el cliente prefiere acabado mate, entrega urgente..." 
                            class="w-full bg-gray-800 border-none rounded-3xl p-6 font-bold text-xs text-white outline-none focus:ring-2 focus:ring-primary min-h-[120px] resize-none"
                        ></textarea>
                    </div>
                </div>

                <div class="mt-6 md:mt-10 pt-6 md:pt-10 border-t border-gray-800 flex flex-col gap-3 md:gap-4">
                    <button 
                        @click="handleConvertSimulationToOrder" 
                        :disabled="submitting"
                        class="w-full py-4 md:py-5 bg-primary text-white rounded-2xl md:rounded-3xl font-black text-[10px] md:text-[11px] uppercase tracking-[0.2em] shadow-2xl shadow-primary/40 hover:bg-white hover:text-black transition-all active:scale-95 disabled:opacity-50"
                    >
                        {{ submitting ? 'Procesando...' : 'Convertir en Pedido Real' }}
                    </button>
                    <button @click="modalState.simulator = false" class="w-full py-4 md:py-5 bg-gray-800 text-gray-500 rounded-2xl md:rounded-3xl font-black text-[10px] md:text-[11px] uppercase tracking-[0.2em] hover:bg-gray-700 transition-all">Regresar</button>
                </div>
            </div>

            <!-- Columna Derecha: Análisis Económico Rediseñado (Scrollable) -->
            <div class="flex-1 p-6 md:p-12 overflow-y-auto scrollbar-thin flex flex-col bg-white dark:bg-gray-900 h-[55vh] md:h-full">
                <div class="flex flex-col lg:flex-row justify-between items-start mb-10 md:mb-14 gap-8">
                    <div>
                        <div class="flex flex-wrap gap-2 mb-4">
                            <span v-if="simulator.customer_name" class="text-[10px] font-black text-primary px-3 py-1 bg-primary/5 rounded-full uppercase tracking-widest border border-primary/10">Cliente: {{ simulator.customer_name }}</span>
                            <span v-if="simulator.customer_phone" class="text-[10px] font-black text-emerald-600 px-3 py-1 bg-emerald-50 rounded-full uppercase tracking-widest border border-emerald-100">WhatsApp: {{ simulator.customer_phone }}</span>
                        </div>
                        <h4 class="text-3xl md:text-4xl font-black text-gray-900 dark:text-white tracking-tighter uppercase leading-none">
                            {{ simulator.job_name || 'Simulación de Proyecto' }}
                        </h4>
                        <p class="text-gray-400 font-medium text-xs mt-2">Análisis técnico basado en parámetros de taller</p>
                    </div>
                        <div class="text-left md:text-right">
                            <p class="text-[9px] md:text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Precio Sugerido Venta</p>
                            <p class="text-4xl md:text-5xl font-black text-primary tracking-tighter">$ {{ simulatedResult.total.toLocaleString() }}</p>
                            
                            <!-- Health Margin Badge -->
                            <div class="flex items-center gap-2 mt-2 md:justify-end">
                                <div class="h-1.5 w-24 bg-gray-100 rounded-full overflow-hidden flex">
                                    <div :class="[
                                        simulatedResult.profit_margin_pct < 15 ? 'bg-red-500' : 
                                        simulatedResult.profit_margin_pct < 35 ? 'bg-amber-400' : 'bg-emerald-500'
                                    ]" :style="{ width: Math.min(100, simulatedResult.profit_margin_pct * 2) + '%' }" class="h-full transition-all duration-1000"></div>
                                </div>
                                <span :class="[
                                    simulatedResult.profit_margin_pct < 15 ? 'text-red-500' : 
                                    simulatedResult.profit_margin_pct < 35 ? 'text-amber-500' : 'text-emerald-500'
                                ]" class="text-[9px] font-black uppercase tracking-widest">
                                    {{ Math.round(simulatedResult.profit_margin_pct) }}% Margen
                                </span>
                            </div>

                            <p v-if="simulator.pieces_per_batch > 1" class="text-[10px] md:text-xs font-black text-gray-400 mt-2 uppercase tracking-widest">
                                $ {{ simulatedResult.unit_price.toLocaleString() }} por unidad
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <button @click="handleDownloadSimulationPDF" class="btn-pdf group" title="Descargar Cotización PDF">
                                <svg class="w-5 h-5 text-primary group-hover:text-white transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                <span>Descargar PDF</span>
                            </button>
                        </div>
                    </div>

                <!-- Gráfico de Distribución de Costos -->
                <div class="mb-12">
                    <div class="flex justify-between items-center mb-3">
                        <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Distribución de Ingresos</span>
                        <span class="text-[10px] font-black text-primary uppercase tracking-widest">Rentabilidad Optimizada</span>
                    </div>
                    <div class="h-4 w-full bg-gray-100 dark:bg-white/5 rounded-full overflow-hidden flex shadow-inner">
                        <div :style="{ width: simulatedResult.pcts.material + '%' }" class="h-full bg-gray-900 dark:bg-white transition-all duration-500" title="Material"></div>
                        <div :style="{ width: simulatedResult.pcts.infra + '%' }" class="h-full bg-primary transition-all duration-500" title="Infraestructura"></div>
                        <div :style="{ width: simulatedResult.pcts.extras + '%' }" class="h-full bg-amber-400 transition-all duration-500" title="Extras"></div>
                        <div :style="{ width: simulatedResult.pcts.profit + '%' }" class="h-full bg-emerald-400 transition-all duration-500" title="Ganancia"></div>
                    </div>
                    <div class="flex gap-4 mt-3">
                        <div class="flex items-center gap-1.5"><div class="w-2 h-2 rounded-full bg-gray-900 dark:bg-white"></div><span class="text-[8px] font-black text-gray-400 uppercase">Material</span></div>
                        <div class="flex items-center gap-1.5"><div class="w-2 h-2 rounded-full bg-primary"></div><span class="text-[8px] font-black text-gray-400 uppercase">Infra</span></div>
                        <div class="flex items-center gap-1.5"><div class="w-2 h-2 rounded-full bg-amber-400"></div><span class="text-[8px] font-black text-gray-400 uppercase">Extras</span></div>
                        <div class="flex items-center gap-1.5"><div class="w-2 h-2 rounded-full bg-emerald-400"></div><span class="text-[8px] font-black text-gray-400 uppercase">Ganancia</span></div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 md:gap-12 flex-1">
                    <div class="space-y-6">
                        <h5 class="text-[10px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-100 dark:border-white/10 pb-2">Costos Operativos</h5>
                        <div class="space-y-4">
                            <div class="flex justify-between items-center"><span class="text-xs font-bold text-gray-500 uppercase">Material Base</span><span class="font-black text-gray-900 dark:text-white">$ {{ Math.round(simulatedResult.material).toLocaleString() }}</span></div>
                            <div class="flex justify-between items-center"><span class="text-xs font-bold text-gray-500 uppercase">Energía (Luz)</span><span class="font-black text-gray-900 dark:text-white">$ {{ Math.round(simulatedResult.luz).toLocaleString() }}</span></div>
                            <div class="flex justify-between items-center"><span class="text-xs font-bold text-gray-500 uppercase">Mano de Obra</span><span class="font-black text-gray-900 dark:text-white">$ {{ Math.round(simulatedResult.labor).toLocaleString() }}</span></div>
                            <div class="flex justify-between items-center"><span class="text-xs font-bold text-gray-500 uppercase">Maquinaria (Depr+Mant)</span><span class="font-black text-gray-900 dark:text-white">$ {{ Math.round(simulatedResult.depr + simulatedResult.mant).toLocaleString() }}</span></div>
                            <div v-if="simulator.extra_items.length" class="flex justify-between items-center pt-2 border-t border-gray-100 dark:border-white/10 mt-2">
                                <span class="text-xs font-black text-primary uppercase">Insumos Adicionales</span>
                                <span class="font-black text-primary">$ {{ simulator.extra_items.reduce((acc, item) => acc + (item.cost * item.qty), 0).toLocaleString() }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Resumen de Totales -->
                    <div class="flex flex-col justify-between">
                        <div class="bg-gray-50 dark:bg-white/5 rounded-[3rem] p-8 border border-gray-100 dark:border-white/10 space-y-5">
                            <div class="flex justify-between items-center group">
                                <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest group-hover:text-primary transition-colors">Subtotal Bruto</span>
                                <span class="font-black text-gray-800 dark:text-white text-xl tracking-tighter">$ {{ simulatedResult.subtotal.toLocaleString() }}</span>
                            </div>
                            
                            <div v-if="simulator.discount_pct > 0" class="flex justify-between items-center bg-rose-50/50 p-4 rounded-2xl border border-rose-100/50">
                                <div class="flex flex-col">
                                    <span class="text-[10px] font-black text-rose-500 uppercase tracking-widest">Descuento Especial</span>
                                    <span class="text-[8px] font-bold text-rose-400 uppercase tracking-widest">Aplicado sobre ganancia</span>
                                </div>
                                <span class="font-black text-rose-500 text-xl tracking-tighter">-$ {{ simulatedResult.discount.toLocaleString() }}</span>
                            </div>

                            <div class="flex justify-between items-center">
                                <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">IVA ({{ settings.margin?.iva || 0 }}%)</span>
                                <span class="font-black text-gray-600 text-lg tracking-tighter">$ {{ simulatedResult.iva.toLocaleString() }}</span>
                            </div>

                            <div class="h-px bg-gray-200/60 my-2"></div>

                            <!-- Sliders de Control -->
                            <div class="space-y-6 pt-2">
                                <div class="space-y-3">
                                    <div class="flex justify-between items-center">
                                        <div class="flex flex-col">
                                            <label class="text-[10px] font-black text-gray-500 uppercase tracking-widest">Margen de Ganancia</label>
                                            <span class="text-[9px] font-black text-emerald-500 tracking-tighter mt-0.5">$ {{ Math.round(simulatedResult.profit).toLocaleString() }}</span>
                                        </div>
                                        <div class="flex items-center gap-1 bg-primary/10 px-3 py-1 rounded-lg">
                                            <input type="number" v-model.number="simulator.profit_pct" min="0" max="1000" class="w-10 bg-transparent border-none text-sm font-black text-primary text-center outline-none p-0">
                                            <span class="text-xs font-black text-primary">%</span>
                                        </div>
                                    </div>
                                    <input type="range" v-model.number="simulator.profit_pct" min="0" max="300" class="w-full h-2 bg-gray-200 dark:bg-white/10 rounded-lg appearance-none cursor-pointer accent-primary">
                                </div>

                                <div class="space-y-3">
                                    <div class="flex justify-between items-center">
                                        <div class="flex flex-col">
                                            <label class="text-[10px] font-black text-rose-500 uppercase tracking-widest">Descuento Cliente</label>
                                            <span class="text-[9px] font-black text-rose-500 tracking-tighter mt-0.5">-$ {{ Math.round(simulatedResult.discount).toLocaleString() }}</span>
                                        </div>
                                        <div class="flex items-center gap-1 bg-rose-50 px-3 py-1 rounded-lg">
                                            <input type="number" v-model.number="simulator.discount_pct" min="0" max="100" class="w-10 bg-transparent border-none text-sm font-black text-rose-500 text-center outline-none p-0">
                                            <span class="text-xs font-black text-rose-500">%</span>
                                        </div>
                                    </div>
                                    <input type="range" v-model.number="simulator.discount_pct" min="0" max="100" class="w-full h-2 bg-gray-200 dark:bg-white/10 rounded-lg appearance-none cursor-pointer accent-rose-500">
                                </div>
                            </div>
                        </div>

                        <!-- Panel de Provisiones Operativas -->
                        <div class="mt-10">
                            <h5 class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-6 px-4">Provisiones y Gastos Operativos</h5>
                            <div class="grid grid-cols-2 gap-3 md:gap-4">
                                <div class="bg-gray-50 dark:bg-white/5 p-6 rounded-[2.5rem] border border-gray-100 dark:border-white/10 hover:bg-white dark:hover:bg-white/10 hover:shadow-xl hover:shadow-gray-200/50 dark:hover:shadow-none transition-all group relative overflow-hidden">
                                    <p class="text-[9px] font-black text-gray-400 uppercase mb-3 text-center group-hover:text-primary transition-colors">Logística %</p>
                                    <div class="flex items-center justify-center">
                                        <input type="number" v-model.number="simulator.transporte_pct" class="w-16 bg-transparent text-center font-black text-2xl text-gray-800 dark:text-white outline-none">
                                        <span class="text-xs font-black text-gray-300">%</span>
                                    </div>
                                    <p class="text-[9px] font-black text-emerald-500 text-center mt-2">$ {{ Math.round(simulatedResult.logistics).toLocaleString() }}</p>
                                </div>
                                <div class="bg-gray-50 p-6 rounded-[2.5rem] border border-gray-100 hover:bg-white hover:shadow-xl hover:shadow-gray-200/50 transition-all group relative overflow-hidden">
                                    <p class="text-[9px] font-black text-gray-400 uppercase mb-3 text-center group-hover:text-primary transition-colors">Marketing %</p>
                                    <div class="flex items-center justify-center">
                                        <input type="number" v-model.number="simulator.marketing_pct" class="w-16 bg-transparent text-center font-black text-2xl text-gray-800 outline-none">
                                        <span class="text-xs font-black text-gray-300">%</span>
                                    </div>
                                    <p class="text-[9px] font-black text-emerald-500 text-center mt-2">$ {{ Math.round(simulatedResult.marketing).toLocaleString() }}</p>
                                </div>
                                <div class="bg-gray-50 p-6 rounded-[2.5rem] border border-gray-100 hover:bg-white hover:shadow-xl hover:shadow-rose-200/50 transition-all group relative overflow-hidden">
                                    <p class="text-[9px] font-black text-gray-400 uppercase mb-3 text-center group-hover:text-rose-500 transition-colors">Fallos %</p>
                                    <div class="flex items-center justify-center">
                                        <input type="number" v-model.number="simulator.fallos_pct" class="w-16 bg-transparent text-center font-black text-2xl text-gray-800 outline-none">
                                        <span class="text-xs font-black text-gray-300">%</span>
                                    </div>
                                    <p class="text-[9px] font-black text-rose-500 text-center mt-2">$ {{ Math.round(simulatedResult.failures).toLocaleString() }}</p>
                                </div>
                                <div class="bg-gray-50 p-6 rounded-[2.5rem] border border-gray-100 hover:bg-white hover:shadow-xl hover:shadow-primary/20 transition-all group">
                                    <p class="text-[9px] font-black text-gray-400 uppercase mb-3 text-center group-hover:text-primary transition-colors">Etiquetas $</p>
                                    <div class="flex items-center justify-center">
                                        <span class="text-xs font-black text-gray-300">$</span>
                                        <input type="number" v-model.number="simulator.etiquetas" class="w-20 bg-transparent text-center font-black text-2xl text-gray-800 outline-none">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Orden Manual -->
    <div v-if="modalState.manualOrder" class="fixed inset-0 bg-gray-950/60 backdrop-blur-md z-[500] flex items-center justify-center p-0 md:p-4 overflow-y-auto">
      <div class="bg-white dark:bg-gray-900 rounded-none md:rounded-[4rem] w-full max-w-6xl shadow-2xl overflow-hidden animate-in zoom-in duration-300 flex flex-col h-full md:h-[90vh] my-auto">
        <div class="bg-gray-950 p-8 md:p-12 text-white flex justify-between items-center shrink-0">
          <div>
            <h3 class="text-3xl md:text-4xl font-black uppercase tracking-tighter mb-2">Crear Orden Manual</h3>
            <p class="text-gray-400 text-[10px] font-black uppercase tracking-widest">Registra un nuevo trabajo en la cola de producción</p>
          </div>
          <button @click="modalState.manualOrder = false" class="w-10 h-10 md:w-12 md:h-12 bg-white/10 text-white rounded-2xl flex items-center justify-center hover:bg-rose-500 transition-all text-2xl">
            ✕
          </button>
        </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 md:gap-8 mb-8 overflow-y-auto pr-2 md:pr-4 scrollbar-thin flex-1 p-8">
            <!-- Columna 1: Datos del Cliente y Envío -->
            <div class="space-y-4">
                <div class="space-y-2">
                  <label class="text-[10px] font-black text-primary uppercase tracking-widest">Seleccionar Cliente Registrado</label>
                  <select @change="handleSelectCustomerManual" class="w-full bg-gray-50 dark:bg-white/5 border-none rounded-2xl p-4 font-bold text-sm text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="">-- Cliente Nuevo --</option>
                    <option v-for="c in contacts.customers" :key="c.id" :value="c.id">{{ c.name }}</option>
                  </select>
                </div>
                <div class="space-y-2">
                  <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Nombre Cliente</label>
                  <input type="text" v-model="manualOrder.customer_name" class="w-full bg-gray-50 dark:bg-white/5 border-none rounded-2xl p-4 font-bold text-sm text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary/20">
                </div>
                <div class="space-y-2">
                  <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">WhatsApp</label>
                  <input type="text" v-model="manualOrder.customer_phone" placeholder="WhatsApp" class="w-full bg-gray-50 dark:bg-white/5 border-none rounded-2xl p-4 font-bold text-sm text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary/20">
                </div>
                <div class="space-y-2">
                  <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Documento ID (CC / NIT)</label>
                  <input type="text" v-model="manualOrder.customer_id_document" placeholder="Documento para envío" class="w-full bg-gray-50 dark:bg-white/5 border-none rounded-2xl p-4 font-bold text-sm text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary/20">
                </div>

                <!-- Hidden Logistics (Active for PDF/Logic) -->
                <div v-show="false">
                  <input type="text" v-model="manualOrder.customer_company">
                  <input type="email" v-model="manualOrder.customer_email">
                  <input type="text" v-model="manualOrder.shipping_address">
                  <input type="text" v-model="manualOrder.shipping_city">
                  <input type="text" v-model="manualOrder.shipping_zip">
                  <input type="text" v-model="manualOrder.shipping_reference">
                </div>
            </div>

            <!-- Columna 2: Detalles del Trabajo y Costos -->
            <div class="space-y-4">
              <div class="space-y-2">
                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Tecnología y Material</label>
                <div class="flex gap-2">
                  <select v-model="manualOrder.technology" class="flex-1 bg-gray-50 dark:bg-white/5 border-none rounded-2xl p-4 font-bold text-sm text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="FDM">FDM</option>
                    <option value="SLA">SLA</option>
                  </select>
                  <select v-model="manualOrder.material_id" class="flex-1 bg-gray-50 dark:bg-white/5 border-none rounded-2xl p-4 font-bold text-sm text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="">Material...</option>
                    <option v-for="m in inventoryData.filter(i => i.category === manualOrder.technology)" :key="m.id" :value="m.id">{{ m.name }}</option>
                  </select>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-4">
                <div class="space-y-2">
                  <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Peso (g)</label>
                  <input type="number" v-model.number="manualOrder.weight_g" class="w-full bg-gray-50 dark:bg-white/5 border-none rounded-2xl p-4 font-bold text-sm text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary/20">
                </div>
                <div class="space-y-2">
                  <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Cantidad / Unidades</label>
                  <input type="number" v-model.number="manualOrder.qty" min="1" class="w-full bg-gray-50 dark:bg-white/5 border-none rounded-2xl p-4 font-bold text-sm text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary/20">
                </div>
                <div class="space-y-2">
                  <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Duración (h)</label>
                  <input type="number" v-model.number="manualOrder.duration_h" class="w-full bg-gray-50 dark:bg-white/5 border-none rounded-2xl p-4 font-bold text-sm text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary/20">
                </div>
              </div>
              <div class="space-y-2">
                <label class="text-[10px] font-black text-emerald-500 uppercase tracking-widest">Precio Final ($)</label>
                <input type="number" v-model.number="manualOrder.total_price" class="w-full bg-gray-50 dark:bg-white/5 border-none rounded-2xl p-4 font-black text-xl text-emerald-600 outline-none focus:ring-2 focus:ring-emerald-500/20">
              </div>
              <div class="pt-4">
                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-2">Comentarios / Requerimientos</label>
                <textarea v-model="manualOrder.comments" class="w-full bg-gray-50 dark:bg-white/5 border-none rounded-2xl p-4 font-bold text-xs text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary/20 min-h-[100px]"></textarea>
              </div>
            </div>
          </div>

          <div class="flex gap-4 p-8 pt-0">
            <button @click="modalState.manualOrder = false" class="btn-secondary flex-1 py-5">Descartar</button>
            <button 
                @click="handleManualOrderSubmit" 
                :disabled="submitting"
                class="btn-primary flex-1 py-5"
            >
                {{ submitting ? 'Creando...' : 'Crear Orden' }}
            </button>
          </div>
      </div>
    </div>
 
    <!-- Modal: Editar Material -->
    <div v-if="modalState.editMaterial" class="fixed inset-0 bg-gray-950/60 backdrop-blur-md z-[500] flex items-center justify-center p-4 overflow-y-auto">
      <div class="bg-white dark:bg-gray-900 rounded-[2.5rem] w-full max-w-lg shadow-2xl overflow-hidden animate-in zoom-in duration-300 my-auto">
        <div class="p-10 max-h-[90vh] overflow-y-auto no-scrollbar">
          <div class="flex justify-between items-center mb-8">
            <h3 class="text-2xl font-black text-gray-900 dark:text-white uppercase tracking-tighter">Editar Ítem</h3>
            <button @click="modalState.editMaterial = false" class="text-gray-400 hover:text-gray-600 transition-colors text-2xl">
              ✕
            </button>
          </div>
          
          <div class="space-y-6">
            <div class="space-y-2">
              <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Nombre del Material/Ítem</label>
              <input type="text" v-model="editingMaterial.name" class="w-full bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/10 rounded-2xl px-6 py-4 text-sm font-bold text-gray-900 dark:text-white focus:ring-2 focus:ring-primary outline-none transition-all">
            </div>
            
            <div class="grid grid-cols-2 gap-6">
              <div class="space-y-2">
                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Costo Unitario ($)</label>
                <input type="number" v-model.number="editingMaterial.cost_per_kg" class="w-full bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/10 rounded-2xl px-6 py-4 text-sm font-bold text-gray-900 dark:text-white focus:ring-2 focus:ring-primary outline-none transition-all">
              </div>
              <div class="space-y-2 col-span-2 sm:col-span-1">
                <label class="text-[10px] font-black text-rose-400 uppercase tracking-widest">Umbral Alerta ({{ editingMaterial.unit }})</label>
                <input type="number" v-model.number="editingMaterial.low_stock_threshold" class="w-full bg-rose-50 dark:bg-rose-500/10 border border-rose-100 dark:border-rose-500/20 rounded-2xl px-6 py-4 text-sm font-bold text-gray-900 dark:text-white focus:ring-2 focus:ring-rose-500 outline-none transition-all">
              </div>
            </div>
          </div>
          
          <div class="mt-10 flex flex-col sm:flex-row gap-4">
            <button @click="modalState.editMaterial = false" class="btn-secondary flex-1 px-8 py-4">
                Salir sin Guardar
            </button>
            <button @click="updateMaterial" :disabled="submitting" class="btn-primary flex-2 px-12 py-4">
              {{ submitting ? 'Guardando...' : 'Guardar Cambios' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal: Nuevo Material / Insumo Pulido -->
    <div v-if="modalState.newMaterial" class="fixed inset-0 bg-gray-950/60 backdrop-blur-md z-[500] flex items-center justify-center p-4 overflow-y-auto">
      <div class="bg-white dark:bg-gray-900 rounded-[3rem] w-full max-w-2xl shadow-2xl overflow-hidden animate-in zoom-in duration-300 my-auto">
        <div class="p-10 max-h-[90vh] overflow-y-auto no-scrollbar">
          <div class="flex justify-between items-center mb-10">
            <div>
              <h3 class="text-3xl font-black text-gray-900 dark:text-white uppercase tracking-tighter">Registrar Nuevo Ítem</h3>
              <p class="text-xs text-gray-400 font-bold uppercase tracking-widest mt-1">Configuración técnica de inventario</p>
            </div>
            <button @click="modalState.newMaterial = false" class="w-12 h-12 bg-gray-50 dark:bg-white/5 text-gray-400 rounded-2xl flex items-center justify-center hover:bg-gray-100 dark:hover:bg-white/10 transition-all text-xl">
              ✕
            </button>
          </div>
          
          <div class="grid grid-cols-2 gap-8 mb-10">
            <!-- ID Automático (ReadOnly) -->
            <div class="space-y-2">
                <label class="text-[10px] font-black text-primary uppercase tracking-widest flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-primary animate-pulse"></span>
                    ID Técnico (Auto)
                </label>
                <input type="text" v-model="newMaterial.id" readonly class="w-full bg-gray-100 dark:bg-white/5 border-none rounded-2xl p-5 font-black text-sm text-gray-500 outline-none opacity-80 cursor-not-allowed">
            </div>

            <!-- Nombre Comercial -->
            <div class="space-y-2">
                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Nombre Comercial / Marca</label>
                <input type="text" v-model="newMaterial.name" placeholder="Ej: PLA Pro Negro Mate" class="w-full bg-gray-50 dark:bg-white/5 border-none rounded-2xl p-5 font-bold text-sm text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary/20 transition-all">
            </div>

            <!-- Categoría -->
            <div class="space-y-2">
                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Categoría Tecnológica</label>
                <select v-model="newMaterial.category" class="w-full bg-gray-50 dark:bg-white/5 border-none rounded-2xl p-5 font-bold text-sm text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary/20 transition-all">
                  <option value="FDM">Filamento (FDM)</option>
                  <option value="SLA">Resina (SLA)</option>
                  <option value="UTIL">Otros / Adicionales</option>
                </select>
            </div>

            <!-- Tipo de Ítem -->
            <div class="space-y-2">
                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Tipo de Registro</label>
                <select v-model="newMaterial.type" class="w-full bg-gray-50 dark:bg-white/5 border-none rounded-2xl p-5 font-bold text-sm text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary/20 transition-all">
                  <option value="material">Materiales (Filamento/Resina)</option>
                  <option value="utility">Insumos (Argollas/Cajas/Bolsas)</option>
                  <option value="service">Servicios (Mano de Obra/Lavado)</option>
                </select>
            </div>

            <!-- Asistente de Paquete (Opcional) -->
            <div class="col-span-2 p-6 bg-primary/5 rounded-[2rem] border border-primary/10 space-y-4">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-[9px] font-black text-primary uppercase tracking-[0.2em]">Asistente de Costo Pro</p>
                        <p class="text-[10px] text-gray-500 font-bold mt-1">Calcula el precio unitario exacto por lotes.</p>
                    </div>
                    <div v-if="newMaterial.package_price > 0 && newMaterial.package_qty > 0" class="px-4 py-2 bg-emerald-50 rounded-xl border border-emerald-100 animate-in fade-in zoom-in duration-300">
                        <p class="text-[7px] font-black text-emerald-600 uppercase mb-1">Auditoría de Cálculo:</p>
                        <p class="text-xs font-black text-emerald-700">
                            $ {{ (newMaterial.package_price / (newMaterial.package_qty * (newMaterial.package_units || 1))).toLocaleString(undefined, {minimumFractionDigits: 2}) }} / {{ newMaterial.unit }}
                            <span v-if="newMaterial.unit === 'g' || newMaterial.unit === 'ml'" class="text-[8px] opacity-70 ml-1">
                                ($ {{ ((newMaterial.package_price / (newMaterial.package_qty * (newMaterial.package_units || 1))) * 1000).toLocaleString() }} x Kg/L)
                            </span>
                        </p>
                    </div>
                </div>
                
                <div class="grid grid-cols-3 gap-4">
                    <div class="space-y-1">
                        <label class="text-[8px] font-black text-gray-400 uppercase">Nº Paquetes</label>
                        <input type="number" v-model.number="newMaterial.package_units" placeholder="Ej: 2" class="w-full bg-white dark:bg-white/5 border-none rounded-xl p-3 font-bold text-xs outline-none focus:ring-2 focus:ring-primary/20 shadow-sm transition-all">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[8px] font-black text-gray-400 uppercase">{{ newMaterial.unit === 'g' ? 'Gramos x Paq' : newMaterial.unit === 'ml' ? 'ML x Paq' : 'Piezas x Paq' }}</label>
                        <input type="number" v-model.number="newMaterial.package_qty" placeholder="Ej: 500" class="w-full bg-white dark:bg-white/5 border-none rounded-xl p-3 font-bold text-xs outline-none focus:ring-2 focus:ring-primary/20 shadow-sm transition-all">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[8px] font-black text-gray-400 uppercase">Precio Total ($)</label>
                        <input type="number" v-model.number="newMaterial.package_price" placeholder="Ej: 120000" class="w-full bg-white dark:bg-white/5 border-none rounded-xl p-3 font-bold text-xs outline-none focus:ring-2 focus:ring-primary/20 shadow-sm transition-all">
                    </div>
                </div>
                
                <div v-if="newMaterial.package_qty > 0" class="pt-2 border-t border-primary/5 flex justify-between items-center text-[8px] font-black text-gray-400 uppercase tracking-widest">
                    <span>Cantidad total a ingresar:</span>
                    <span class="text-primary">{{ (newMaterial.package_qty * (newMaterial.package_units || 1)).toLocaleString() }} {{ newMaterial.unit }}</span>
                </div>
            </div>

            <!-- Costo por Unidad -->
            <div class="space-y-2">
                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Costo por Kilo/Litro/Unid.</label>
                <div class="relative">
                    <span class="absolute left-5 top-1/2 -translate-y-1/2 text-gray-400 font-bold">$</span>
                    <input type="number" v-model.number="newMaterial.cost_per_kg" class="w-full bg-gray-50 dark:bg-white/5 border-none rounded-2xl p-5 pl-10 font-black text-sm text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary/20 transition-all">
                </div>
            </div>

            <!-- Unidad de Medida -->
            <div class="space-y-2">
                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Unidad de Medida</label>
                <select v-model="newMaterial.unit" class="w-full bg-gray-50 dark:bg-white/5 border-none rounded-2xl p-5 font-bold text-sm text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary/20 transition-all">
                  <option value="g">Gramos (g)</option>
                  <option value="ml">Mililitros (ml)</option>
                  <option value="unid">Unidad (ud)</option>
                </select>
            </div>

            <!-- Stock Inicial -->
            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-2">
                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Stock de Apertura</label>
                    <input type="number" v-model.number="newMaterial.initial_stock" class="w-full bg-gray-50 dark:bg-white/5 border-none rounded-2xl p-5 font-black text-sm text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary/20 transition-all">
                </div>
                <div class="space-y-2">
                    <label class="text-[10px] font-black text-rose-400 uppercase tracking-widest">Stock de Alerta (Rojo)</label>
                    <input type="number" v-model.number="newMaterial.low_stock_threshold" class="w-full bg-rose-50 dark:bg-rose-500/10 border-none rounded-2xl p-5 font-black text-sm text-rose-900 dark:text-rose-100 outline-none focus:ring-2 focus:ring-rose-500/20 transition-all">
                </div>
            </div>

            <!-- Selector de Color -->
            <div class="space-y-2">
                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Identificador Visual</label>
                <div class="flex items-center gap-4">
                    <input type="color" v-model="newMaterial.color" class="w-16 h-14 bg-gray-50 border-none rounded-2xl p-1 cursor-pointer overflow-hidden transition-all hover:scale-105">
                    <span class="text-[10px] font-bold text-gray-400 uppercase italic">{{ newMaterial.color }}</span>
                </div>
            </div>
          </div>
          
          <div class="flex flex-col sm:flex-row gap-4 pt-4">
            <button @click="modalState.newMaterial = false" class="btn-secondary flex-1 py-5">
                Cancelar y Salir
            </button>
            <button 
                @click="addMaterial" 
                :disabled="submitting"
                class="btn-primary flex-1 py-5"
            >
                {{ submitting ? 'Guardando...' : 'Crear Ítem en Taller' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal: Nueva Impresora -->
    <div v-if="modalState.newPrinter" class="fixed inset-0 bg-gray-950/60 backdrop-blur-md z-[500] flex items-center justify-center p-4">
      <div class="bg-white dark:bg-gray-900 rounded-[3rem] w-full max-w-xl shadow-2xl overflow-hidden animate-in zoom-in duration-300">
        <div class="p-10">
          <div class="flex justify-between items-center mb-8">
            <h3 class="text-2xl font-black text-gray-900 dark:text-white uppercase tracking-tighter">Nueva Impresora</h3>
            <button @click="modalState.newPrinter = false" class="text-gray-400 hover:text-gray-600 transition-colors text-2xl">
              ✕
            </button>
          </div>
          <div class="space-y-4">
            <input type="text" v-model="newPrinter.name" placeholder="Nombre Identificador" class="w-full bg-gray-50 dark:bg-white/5 border-none rounded-2xl px-6 py-4 text-sm font-bold text-gray-900 dark:text-white focus:ring-2 focus:ring-primary outline-none">
            <input type="text" v-model="newPrinter.model" placeholder="Modelo (ej: Ender 3, Mars 4)" class="w-full bg-gray-50 dark:bg-white/5 border-none rounded-2xl px-6 py-4 text-sm font-bold text-gray-900 dark:text-white focus:ring-2 focus:ring-primary outline-none">
            <select v-model="newPrinter.technology" class="w-full bg-gray-50 dark:bg-white/5 border-none rounded-2xl px-6 py-4 text-sm font-bold text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary">
              <option value="FDM">Tecnología FDM</option>
              <option value="SLA">Tecnología SLA</option>
            </select>
          </div>
          <div class="mt-10 flex flex-col gap-3">
            <button 
                @click="addPrinter" 
                :disabled="submitting"
                class="w-full py-4 bg-primary text-white rounded-2xl font-black text-xs uppercase tracking-widest shadow-xl shadow-primary/20 hover:-translate-y-1 transition-all disabled:opacity-50"
            >
                {{ submitting ? 'Registrando...' : 'Registrar Máquina' }}
            </button>
            <button @click="modalState.newPrinter = false" class="w-full py-4 bg-gray-100 dark:bg-white/5 text-gray-500 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-gray-200 transition-all">Cerrar</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal: Editar Impresora -->
    <div v-if="modalState.editPrinter" class="fixed inset-0 bg-gray-950/60 backdrop-blur-md z-[500] flex items-center justify-center p-4">
      <div class="bg-white dark:bg-gray-900 rounded-[3rem] w-full max-w-xl shadow-2xl overflow-hidden animate-in zoom-in duration-300">
        <div class="p-10">
          <div class="flex justify-between items-center mb-8">
            <h3 class="text-2xl font-black text-gray-900 dark:text-white uppercase tracking-tighter">Detalles de Máquina</h3>
            <button @click="modalState.editPrinter = false" class="text-gray-400 hover:text-gray-600 transition-colors text-2xl">
              ✕
            </button>
          </div>
          
          <div class="grid grid-cols-2 gap-6 mb-8">
            <div class="space-y-2">
                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Nombre</label>
                <input type="text" v-model="editingPrinter.name" class="w-full bg-gray-50 dark:bg-white/5 border-none rounded-2xl p-4 font-bold text-sm text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary/20">
            </div>
            <div class="space-y-2">
                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Modelo</label>
                <input type="text" v-model="editingPrinter.model" class="w-full bg-gray-50 dark:bg-white/5 border-none rounded-2xl p-4 font-bold text-sm text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary/20">
            </div>
            <div class="space-y-2">
                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Horas de Uso</label>
                <input type="number" v-model.number="editingPrinter.total_hours_run" class="w-full bg-gray-50 border-none rounded-2xl p-4 font-bold text-sm outline-none focus:ring-2 focus:ring-primary/20">
            </div>
            <div class="space-y-2">
                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Próximo Mantenimiento</label>
                <input type="date" v-model="editingPrinter.next_maintenance" class="w-full bg-gray-50 border-none rounded-2xl p-4 font-bold text-sm outline-none focus:ring-2 focus:ring-primary/20">
            </div>
          </div>

          <div class="space-y-2 mb-10">
            <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Notas de Mantenimiento</label>
            <textarea v-model="editingPrinter.maintenance_notes" rows="4" class="w-full bg-gray-50 border-none rounded-2xl p-6 font-medium text-sm outline-none focus:ring-2 focus:ring-primary/20" placeholder="Historial de cambios de boquilla, limpieza, etc."></textarea>
          </div>
          
          <div class="flex gap-4">
            <button @click="modalState.editPrinter = false" class="flex-1 py-4 bg-gray-100 text-gray-500 rounded-2xl font-black text-xs uppercase tracking-widest">Cerrar</button>
            <button @click="updatePrinter" class="flex-2 py-4 bg-primary text-white rounded-2xl font-black text-xs uppercase tracking-widest shadow-xl shadow-primary/20">Guardar Cambios</button>
          </div>
        </div>
      </div>
    </div>


    <div v-if="modalState.orderDetails && selectedOrderDetails" class="fixed inset-0 bg-gray-950/60 backdrop-blur-md z-[500] flex items-center justify-center p-0 md:p-4">
      <div class="bg-white dark:bg-gray-900 rounded-none md:rounded-[4rem] w-full max-w-6xl shadow-2xl overflow-hidden animate-in zoom-in duration-300 flex flex-col md:flex-row h-full md:h-[90vh] relative">
        <!-- Floating Close Button -->
        <button @click="modalState.orderDetails = false" class="absolute top-6 right-6 w-12 h-12 bg-white/90 dark:bg-gray-800/90 backdrop-blur-md text-gray-900 dark:text-white rounded-2xl flex items-center justify-center shadow-xl hover:bg-rose-500 hover:text-white transition-all z-[600] active:scale-95 text-2xl">
            ✕
        </button>
        <!-- Columna Izquierda: Info General -->
        <div class="w-full md:w-1/3 bg-gray-50 dark:bg-gray-950 p-8 md:p-12 border-b md:border-b-0 md:border-r border-gray-100 dark:border-white/5 flex flex-col overflow-y-auto scrollbar-thin">
            <div class="mb-10 relative">
                <span class="text-[10px] font-black text-primary px-3 py-1 bg-primary/5 rounded-full uppercase tracking-widest border border-primary/10">N3XT Project System</span>
                <h3 class="text-4xl md:text-6xl font-black text-gray-900 dark:text-white dark:text-outline-gray tracking-tighter mt-6 uppercase leading-[0.8] transition-all duration-500">Orden<br><span class="text-primary not-italic">#{{ selectedOrderDetails.id }}</span></h3>
                
                <!-- Status Badge Premium -->
                <div class="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-white dark:bg-white/5 rounded-xl shadow-sm border border-gray-100 dark:border-white/10">
                    <div :class="['w-2 h-2 rounded-full animate-pulse', 
                        selectedOrderDetails.status === 'completed' ? 'bg-emerald-500' : 
                        selectedOrderDetails.status === 'printing' ? 'bg-amber-500' : 'bg-blue-500']"></div>
                    <span class="text-[9px] font-black text-gray-700 dark:text-gray-300 uppercase tracking-widest">{{ selectedOrderDetails.status }}</span>
                </div>
            </div>
            
            <div class="space-y-8 flex-1">
                <div>
                    <p class="text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-[0.3em] mb-3">Cliente Principal</p>
                    <div class="space-y-1">
                        <p class="text-2xl font-black text-gray-900 dark:text-white tracking-tight leading-none">{{ selectedOrderDetails.customer_name }}</p>
                        <p v-if="selectedOrderDetails.customer_id_document" class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Documento: {{ selectedOrderDetails.customer_id_document }}</p>
                    </div>
                </div>
                
                <div>
                    <p class="text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-[0.3em] mb-2">Especificaciones Técnicas</p>
                    <div class="bg-white dark:bg-white/5 p-5 rounded-[2rem] border border-gray-200 dark:border-white/10 shadow-sm space-y-3">
                        <p class="text-xs font-black text-gray-700 dark:text-gray-400 uppercase flex items-center justify-between">
                            <span>Tecnología:</span>
                            <span class="text-primary">{{ selectedOrderDetails.technology }}</span>
                        </p>
                        <p class="text-xs font-black text-gray-700 dark:text-gray-400 uppercase flex items-center justify-between">
                            <span>Material:</span>
                            <span class="text-gray-900 dark:text-white">{{ selectedOrderDetails.material_name || selectedOrderDetails.material_id }}</span>
                        </p>
                        <div class="pt-3 border-t border-gray-100 dark:border-white/5 flex justify-between">
                             <div class="text-center">
                                 <p class="text-[8px] font-black text-gray-400 uppercase">Masa</p>
                                 <p class="text-xs font-black text-gray-900 dark:text-white">{{ selectedOrderDetails.estimated_weight_g }}g</p>
                             </div>
                             <div class="text-center">
                                 <p class="text-[8px] font-black text-gray-400 uppercase">Tiempo</p>
                                 <p class="text-xs font-black text-gray-900 dark:text-white">{{ selectedOrderDetails.estimated_duration_h }}h</p>
                             </div>
                        </div>
                    </div>
                </div>

                <!-- Tracking Guide Section -->
                <div v-if="['completed', 'shipped'].includes(selectedOrderDetails.status)" class="pt-6 border-t border-gray-200 dark:border-white/10 space-y-4">
                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Logística de Envío</p>
                    <div class="space-y-3">
                        <select 
                            v-model="selectedOrderDetails.tracking_carrier"
                            class="w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-white/10 rounded-xl px-4 py-3 text-xs font-bold text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary appearance-none cursor-pointer"
                        >
                            <option value="" class="bg-white dark:bg-gray-800 text-gray-900 dark:text-white">Seleccionar Transportadora...</option>
                            <option value="Servientrega" class="bg-white dark:bg-gray-800 text-gray-900 dark:text-white">Servientrega</option>
                            <option value="Interrapidisimo" class="bg-white dark:bg-gray-800 text-gray-900 dark:text-white">Interrapidísimo</option>
                            <option value="Envia" class="bg-white dark:bg-gray-800 text-gray-900 dark:text-white">Envía</option>
                            <option value="Coordinadora" class="bg-white dark:bg-gray-800 text-gray-900 dark:text-white">Coordinadora</option>
                            <option value="TCC" class="bg-white dark:bg-gray-800 text-gray-900 dark:text-white">TCC</option>
                            <option value="Recoge en Taller" class="bg-white dark:bg-gray-800 text-gray-900 dark:text-white">Recoge en Taller</option>
                            <option value="Personalizado" class="bg-white dark:bg-gray-800 text-gray-900 dark:text-white">Otro / Personalizado</option>
                        </select>
                        <div class="flex gap-2">
                            <input 
                                type="text" 
                                v-model="selectedOrderDetails.tracking_guide" 
                                placeholder="Nº Guía / Rastreo" 
                                class="flex-1 bg-white dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-xl px-4 py-3 text-xs font-bold text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary"
                            >
                            <button 
                                @click="saveTrackingGuide" 
                                class="bg-primary text-white p-3 rounded-xl hover:bg-gray-900 transition-all shadow-lg shadow-primary/10"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tracking Timeline for Admin -->
            <div class="mt-8 pt-8 border-t border-gray-100">
                <div class="flex justify-between items-center mb-6">
                    <h4 class="text-[10px] font-black text-gray-900 dark:text-white uppercase tracking-[0.3em]">Línea de Tiempo de Manufactura</h4>
                    <span class="text-[9px] font-bold text-primary bg-primary/5 px-3 py-1 rounded-full uppercase">Vista de Trazabilidad Real</span>
                </div>
                
                <div class="relative py-8 px-4 bg-gray-50 dark:bg-gray-800/50 rounded-3xl border border-gray-100 dark:border-white/5">
                    <div class="absolute top-1/2 left-8 right-8 h-0.5 bg-gray-200 dark:bg-white/10 -translate-y-1/2"></div>
                    <div 
                        class="absolute top-1/2 left-8 h-0.5 bg-primary -translate-y-1/2 transition-all duration-1000"
                        :style="{ width: (getStatusIndex(selectedOrderDetails.status) / (statusSteps.length - 1) * 100) + '%' }"
                    ></div>

                    <div class="flex justify-between relative z-10">
                        <div v-for="(step, index) in statusSteps" :key="step.id" class="flex flex-col items-center group/step">
                            <div 
                                class="w-12 h-12 rounded-2xl flex items-center justify-center text-xl shadow-sm transition-all duration-500 relative"
                                :class="index <= getStatusIndex(selectedOrderDetails.status) ? 'bg-primary text-white scale-110 shadow-xl shadow-primary/40' : 'bg-white dark:bg-gray-800 text-gray-300 dark:text-gray-600 border border-gray-100 dark:border-white/5'"
                            >
                                <svg class="w-5 h-5 relative z-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" :d="step.icon" />
                                </svg>
                                <div v-if="index <= getStatusIndex(selectedOrderDetails.status)" class="absolute inset-0 bg-primary rounded-2xl animate-ping opacity-20"></div>
                            </div>
                            <div class="absolute -bottom-10 text-center w-24">
                                <p class="text-[9px] font-black uppercase tracking-widest leading-tight" :class="index <= getStatusIndex(selectedOrderDetails.status) ? 'text-primary' : 'text-gray-400 dark:text-gray-600'">{{ step.label }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <button @click="modalState.orderDetails = false" class="w-full py-5 bg-gray-900 text-white rounded-[2rem] font-black text-xs uppercase tracking-widest shadow-xl shadow-black/20 hover:bg-primary transition-all mt-16">Cerrar Monitor</button>
        </div>

        <!-- Columna Derecha: Desglose de Costos (Excel Style) -->
        <div class="flex-1 p-8 md:p-12 overflow-y-auto scrollbar-thin">
            <div class="flex justify-between items-start mb-12">
                <div>
                    <h4 class="text-2xl font-black text-gray-900 dark:text-white tracking-tighter uppercase">Análisis Económico</h4>
                    <p class="text-gray-400 font-medium text-xs">Cálculo proactivo basado en parámetros de infraestructura</p>
                </div>
                <div class="text-right">
                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Precio Venta</p>
                    <p class="text-4xl font-black text-gray-900 dark:text-white tracking-tighter">$ {{ Number(selectedOrderDetails.total_price).toLocaleString() }}</p>
                    <div class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-500 text-white rounded-full mt-2">
                        <span class="text-[10px] font-black uppercase tracking-widest">Rentabilidad:</span>
                        <span class="text-[11px] font-black">{{ Math.round((selectedOrderDetails.breakdown.margin / selectedOrderDetails.total_price) * 100) }}%</span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 md:gap-10">
                <div class="space-y-5">
                    <h5 class="text-[10px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-100 pb-2">Costos Operativos</h5>
                    
                    <div v-if="selectedOrderDetails.breakdown.material > 0" class="flex justify-between items-center group">
                        <span class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide group-hover:text-gray-800 dark:group-hover:text-white transition-colors">Material Base</span>
                        <span class="font-black text-gray-900 dark:text-white">$ {{ Math.round(selectedOrderDetails.breakdown.material).toLocaleString() }}</span>
                    </div>
                    <div v-if="selectedOrderDetails.breakdown.luz > 0" class="flex justify-between items-center group">
                        <span class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide group-hover:text-gray-800 dark:group-hover:text-white transition-colors">Energía (Luz)</span>
                        <span class="font-black text-gray-900 dark:text-white">$ {{ Math.round(selectedOrderDetails.breakdown.luz).toLocaleString() }}</span>
                    </div>
                    <div v-if="selectedOrderDetails.breakdown.labor > 0" class="flex justify-between items-center group">
                        <span class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide group-hover:text-gray-800 dark:group-hover:text-white transition-colors">Mano de Obra</span>
                        <span class="font-black text-gray-900 dark:text-white">$ {{ Math.round(selectedOrderDetails.breakdown.labor).toLocaleString() }}</span>
                    </div>
                    <div v-if="selectedOrderDetails.breakdown.depr > 0" class="flex justify-between items-center group">
                        <span class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide group-hover:text-gray-800 dark:group-hover:text-white transition-colors">Depreciación</span>
                        <span class="font-black text-gray-900 dark:text-white">$ {{ Math.round(selectedOrderDetails.breakdown.depr).toLocaleString() }}</span>
                    </div>
                    <div v-if="selectedOrderDetails.breakdown.setup > 0" class="flex justify-between items-center group">
                        <span class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide group-hover:text-gray-800 dark:group-hover:text-white transition-colors">Arranque</span>
                        <span class="font-black text-gray-900 dark:text-white">$ {{ Math.round(selectedOrderDetails.breakdown.setup).toLocaleString() }}</span>
                    </div>
                </div>

                <div class="space-y-5">
                    <h5 class="text-[10px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-100 pb-2">Insumos y Mantenimiento</h5>
                    
                    <div v-if="selectedOrderDetails.breakdown.mant > 0" class="flex justify-between items-center group">
                        <span class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide group-hover:text-gray-800 dark:group-hover:text-white transition-colors">Mantenimiento</span>
                        <span class="font-black text-gray-900 dark:text-white">$ {{ Math.round(selectedOrderDetails.breakdown.mant).toLocaleString() }}</span>
                    </div>
                    <div v-if="selectedOrderDetails.breakdown.etiquetas > 0" class="flex justify-between items-center group">
                        <span class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide group-hover:text-gray-800 dark:group-hover:text-white transition-colors">Prep. / Empaque</span>
                        <span class="font-black text-gray-900 dark:text-white">$ {{ Math.round(selectedOrderDetails.breakdown.etiquetas).toLocaleString() }}</span>
                    </div>
                    <div v-if="selectedOrderDetails.breakdown.extras > 0" class="flex justify-between items-center group">
                        <span class="text-xs font-bold text-gray-500 uppercase tracking-wide group-hover:text-gray-800 transition-colors">Extras Registrados</span>
                        <span class="font-black text-emerald-600">$ {{ Math.round(selectedOrderDetails.breakdown.extras).toLocaleString() }}</span>
                    </div>
                    
                    <div class="mt-10 pt-8 border-t border-gray-100 dark:border-white/10">
                        <div class="flex justify-between items-center mb-4">
                            <span class="text-xs font-black text-gray-400 uppercase tracking-widest">Costo Total Producción</span>
                            <span class="text-lg font-black text-gray-900 dark:text-white tracking-tighter">$ {{ Math.round(selectedOrderDetails.breakdown.total_cost).toLocaleString() }}</span>
                        </div>
                        <div class="relative overflow-hidden p-6 bg-emerald-50 dark:bg-emerald-500/10 rounded-[2.5rem] border border-emerald-100 dark:border-emerald-500/20 group">
                            <div class="relative z-10 flex justify-between items-center">
                                <div>
                                    <span class="text-[10px] font-black text-emerald-600 dark:text-emerald-400 uppercase tracking-widest block mb-1">Margen Neto Operativo</span>
                                    <span class="text-3xl font-black text-emerald-700 dark:text-emerald-400 tracking-tighter">$ {{ Math.round(selectedOrderDetails.breakdown.margin).toLocaleString() }}</span>
                                </div>
                                <div class="text-right">
                                     <svg class="w-5 h-5 text-emerald-200 group-hover:text-emerald-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                                </div>
                            </div>
                        </div>

                        <!-- Botón para Agregar Extra Dinámico -->
                        <div class="mt-6">
                            <button v-if="!showAddExtraForm" @click="showAddExtraForm = true" class="w-full py-4 bg-white dark:bg-white/5 hover:bg-gray-50 dark:hover:bg-white/10 text-gray-500 rounded-2xl font-black text-[9px] uppercase tracking-widest border-2 border-dashed border-gray-200 dark:border-white/10 transition-all flex items-center justify-center gap-2">
                                Agregar Consumible / Extra
                            </button>
                            <div v-else class="p-6 bg-gray-50 border border-gray-200 rounded-[2rem] space-y-4 animate-in fade-in slide-in-from-top-2">
                                <p class="text-[9px] font-black text-gray-500 uppercase tracking-widest">Añadir Insumo Adicional</p>
                                <div class="relative group/sel">
                                    <select v-model="newExtra.material_id" class="w-full bg-white dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-xl p-4 pr-10 text-xs font-black text-gray-900 dark:text-white appearance-none outline-none focus:ring-2 focus:ring-primary shadow-sm transition-all cursor-pointer">
                                        <option value="" class="dark:bg-gray-900">Seleccionar del Inventario...</option>
                                        <option v-for="m in inventoryData.filter(i => i.type !== 'material')" :key="m.id" :value="m.id" class="dark:bg-gray-900">{{ m.name }} ({{ m.unit }})</option>
                                    </select>
                                    <div class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-gray-400">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19 9l-7 7-7-7" /></svg>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <input type="number" v-model.number="newExtra.qty" placeholder="Cant." class="w-1/3 bg-white dark:bg-white/5 border border-gray-200 dark:border-white/10 rounded-xl p-4 text-xs font-black text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary shadow-sm">
                                    <button @click="handleAddExtra" class="flex-1 bg-gray-900 dark:bg-primary text-white rounded-xl text-[9px] font-black uppercase tracking-widest hover:bg-primary dark:hover:bg-white dark:hover:text-primary transition-all shadow-lg shadow-black/10">Vincular y Cobrar</button>
                                </div>
                                <button @click="showAddExtraForm = false" class="w-full text-[9px] font-black text-gray-300 hover:text-red-500 uppercase tracking-widest transition-colors">Cancelar</button>
                            </div>
                        </div>

                        <div class="mt-8 grid grid-cols-2 gap-4">
                            <div class="bg-gray-50 dark:bg-white/5 p-4 rounded-2xl border border-gray-100 dark:border-white/10">
                                <span class="text-[8px] font-black text-gray-400 uppercase block mb-1">Recibido</span>
                                <span class="text-[10px] font-black text-gray-800 dark:text-gray-300">{{ new Date(selectedOrderDetails.created_at).toLocaleDateString() }}</span>
                            </div>
                            <div class="bg-gray-50 dark:bg-white/5 p-4 rounded-2xl border border-gray-100 dark:border-white/10">
                                <span class="text-[8px] font-black text-gray-400 uppercase block mb-1">Actividad</span>
                                <span class="text-[10px] font-black text-gray-800 dark:text-gray-300">{{ Math.floor((new Date() - new Date(selectedOrderDetails.updated_at)) / (1000 * 60 * 60 * 24)) }}d transcurridos</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-12 p-8 bg-gray-50 rounded-[2.5rem] border border-gray-100">
                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-4 flex items-center gap-2">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
                    Notas del Operario
                </p>
                <p class="text-sm text-gray-600 font-medium italic leading-relaxed">
                    {{ selectedOrderDetails.comments || 'Sin requerimientos especiales registrados.' }}
                </p>
            </div>
        </div>

      </div>
    </div>


    <!-- Printable Invoice Template (Hidden on screen) -->
    <div class="print-only" v-if="orderToPrint">
      <div style="padding: 20px 40px; font-family: system-ui, -apple-system, sans-serif; background: white; color: black; height: 100vh; box-sizing: border-box; overflow: hidden;">
        <div style="display: flex; justify-content: space-between; border-bottom: 3px solid #111827; padding-bottom: 15px; margin-bottom: 25px; align-items: center;">
          <div style="display: flex; align-items: center; gap: 20px;">
            <img v-if="settings.company_logo" :src="settings.company_logo.startsWith('http') ? settings.company_logo : `${api.storageUrl}/${settings.company_logo}`" style="height: 60px; width: auto; object-fit: contain;">
            <div>
              <h1 style="font-size: 28px; font-weight: 900; margin: 0 0 5px 0; color: #111827; letter-spacing: -1px; text-transform: uppercase;">{{ settings.company?.name || 'N3XT 3D Technology' }}</h1>
              <p style="font-size: 11px; font-weight: 800; letter-spacing: 0.15em; color: #4b5563; text-transform: uppercase;">{{ settings.company?.nit ? `NIT: ${settings.company.nit}` : 'Industrial Systems' }}</p>
              <p style="font-size: 10px; color: #6b7280; margin: 4px 0 0; font-weight: 600;">{{ settings.company?.address || '' }}</p>
            </div>
          </div>
          <div style="text-align: right;">
            <h2 style="font-size: 26px; font-weight: 900; margin: 0 0 5px 0; color: #111827; text-transform: uppercase; letter-spacing: 1px;">Factura Comercial</h2>
            <p style="font-size: 15px; font-weight: 800; color: #4b5563;">Orden #{{ String(orderToPrint.id).padStart(4, '0') }}</p>
            <p style="font-size: 12px; font-weight: 600; color: #9ca3af; margin-top: 4px;">Generada: {{ new Date().toLocaleDateString() }}</p>
          </div>
        </div>

        <div style="display: flex; gap: 40px; margin-bottom: 25px; background: #f9fafb; border-radius: 12px; padding: 16px 24px; border: 1px solid #f3f4f6;">
          <div style="flex: 1;">
            <h3 style="font-size: 11px; font-weight: 900; color: #9ca3af; text-transform: uppercase; margin: 0 0 12px 0; letter-spacing: 1px;">Facturado a</h3>
            <p style="font-size: 18px; font-weight: 900; color: #111827; margin: 0 0 4px 0;">{{ orderToPrint.customer_name }}</p>
            <p style="font-size: 14px; color: #4b5563; font-weight: 600; margin: 0;">Tel: {{ orderToPrint.customer_phone || 'N/A' }}</p>
          </div>
          <div style="flex: 1; border-left: 2px dashed #e5e7eb; padding-left: 40px;">
            <h3 style="font-size: 11px; font-weight: 900; color: #9ca3af; text-transform: uppercase; margin: 0 0 12px 0; letter-spacing: 1px;">Detalles de Manufactura</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
              <div>
                <span style="display: block; font-size: 10px; color: #6b7280; text-transform: uppercase; font-weight: 800;">Tecnología</span>
                <span style="display: inline-block; background: #e0e7ff; color: #4338ca; padding: 4px 8px; border-radius: 6px; font-size: 12px; font-weight: 800; margin-top: 4px;">{{ orderToPrint.technology }}</span>
              </div>
              <div>
                <span style="display: block; font-size: 10px; color: #6b7280; text-transform: uppercase; font-weight: 800;">Material</span>
                <span style="display: inline-block; background: #f3f4f6; color: #374151; padding: 4px 8px; border-radius: 6px; font-size: 12px; font-weight: 800; margin-top: 4px;">{{ orderToPrint.material_name || orderToPrint.material_id }}</span>
              </div>
              <div>
                <span style="display: block; font-size: 10px; color: #6b7280; text-transform: uppercase; font-weight: 800;">Peso Estimado</span>
                <span style="font-size: 14px; color: #111827; font-weight: 800; display: block; margin-top: 4px;">{{ orderToPrint.estimated_weight_g }}g</span>
              </div>
            </div>
          </div>
        </div>

        <table style="width: 100%; border-collapse: collapse; margin-bottom: 25px;">
          <thead>
            <tr style="background: #111827; color: white;">
              <th style="padding: 14px 16px; text-align: left; font-size: 11px; font-weight: 900; letter-spacing: 0.05em; text-transform: uppercase; border-top-left-radius: 8px; border-bottom-left-radius: 8px;">Concepto de Producción</th>
              <th style="padding: 14px 16px; text-align: center; font-size: 11px; font-weight: 900; letter-spacing: 0.05em; text-transform: uppercase;">Cant.</th>
              <th style="padding: 14px 16px; text-align: right; font-size: 11px; font-weight: 900; letter-spacing: 0.05em; text-transform: uppercase; border-top-right-radius: 8px; border-bottom-right-radius: 8px;">Total (COP)</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td style="padding: 12px 16px; border-bottom: 1px solid #e5e7eb;">
                <p style="margin: 0; font-weight: 800; color: #111827; font-size: 15px;">Servicio de Manufactura Aditiva (Impresión 3D)</p>
                <p style="margin: 4px 0 0; font-size: 12px; color: #6b7280; font-weight: 500;">Tiempo estimado de producción: {{ orderToPrint.estimated_duration_h }}h</p>
              </td>
              <td style="padding: 12px 16px; border-bottom: 1px solid #e5e7eb; text-align: center; font-weight: 800; color: #111827; font-size: 15px;">{{ orderToPrint.qty || 1 }}</td>
              <td style="padding: 12px 16px; border-bottom: 1px solid #e5e7eb; text-align: right; font-weight: 900; color: #111827; font-size: 18px;">${{ Number(orderToPrint.total_price).toLocaleString() }}</td>
            </tr>
          </tbody>
        </table>

        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-top: 10px;">
          <div style="width: 50%; padding-right: 40px;">
            <h4 style="font-size: 11px; font-weight: 900; color: #9ca3af; text-transform: uppercase; margin: 0 0 8px 0; letter-spacing: 1px;">Términos y Condiciones</h4>
            <p style="font-size: 10px; color: #6b7280; line-height: 1.5; margin: 0 0 4px 0;">1. Las piezas fabricadas mediante impresión 3D FDM/SLA pueden presentar líneas de capa visibles o pequeñas marcas de soportes, inherentes al proceso.</p>
            <p style="font-size: 10px; color: #6b7280; line-height: 1.5; margin: 0 0 4px 0;">2. Garantizamos una tolerancia dimensional industrial de ±0.2mm a menos que se acuerde lo contrario.</p>
            <p style="font-size: 10px; color: #6b7280; line-height: 1.5; margin: 0;">3. No someter piezas de PLA a temperaturas superiores a 50°C para evitar deformaciones térmicas.</p>
          </div>
          <div style="width: 350px; padding: 16px 24px; background: #f9fafb; border-radius: 16px; border: 1px solid #e5e7eb; border-left: 4px solid #10b981;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 12px;">
              <span style="font-weight: 800; color: #6b7280; font-size: 14px;">Subtotal Neto</span>
              <span style="font-weight: 900; color: #111827; font-size: 14px;">${{ Number(orderToPrint.total_price).toLocaleString() }}</span>
            </div>
            <div style="display: flex; justify-content: space-between; border-top: 2px dashed #e5e7eb; padding-top: 16px;">
              <span style="font-weight: 900; color: #111827; font-size: 18px; text-transform: uppercase;">Total COP</span>
              <span style="font-weight: 900; color: #10b981; font-size: 26px; letter-spacing: -1px;">${{ Number(orderToPrint.total_price).toLocaleString() }}</span>
            </div>
          </div>
        </div>

        <div style="margin-top: 30px; text-align: center; padding-top: 20px; position: relative;">
          <div style="position: absolute; top: 0; left: 50%; transform: translateX(-50%); width: 50px; height: 4px; background: #e5e7eb; border-radius: 4px;"></div>
          <p style="font-size: 13px; font-weight: 900; color: #4b5563; margin: 0 0 4px 0;">Gracias por confiar en N3XT 3D Technology.</p>
          <p style="font-size: 11px; font-weight: 600; color: #9ca3af; margin: 0;">Este documento es un comprobante de servicio digital. Conservar para garantía.</p>
        </div>
      </div>
    </div>
    <!-- Premium Notification Toast -->
    <transition
      enter-active-class="transform ease-out duration-300 transition"
      enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
      enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
      leave-active-class="transition ease-in duration-200"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="notification.show" class="fixed top-12 right-12 z-[10000] flex w-full max-w-sm overflow-hidden bg-white dark:bg-gray-900 rounded-[2.5rem] shadow-[0_20px_50px_rgba(0,0,0,0.3)] border border-gray-100 dark:border-white/10 p-1 animate-in slide-in-from-right-10 duration-500">
        <div class="flex items-center gap-4 px-6 py-4 w-full">
          <div :class="[
            'flex-shrink-0 w-12 h-12 flex items-center justify-center rounded-2xl',
            notification.type === 'success' ? 'bg-emerald-50 text-emerald-500' : 'bg-red-50 text-red-500'
          ]">
            <span class="text-xl">{{ notification.type === 'success' ? '✓' : '✕' }}</span>
          </div>
          <div class="flex-1">
            <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Sistema N3XT</p>
            <p class="text-sm font-bold text-gray-800">{{ notification.message }}</p>
          </div>
        </div>
      </div>
    </transition>



    <!-- Premium Confirmation Dialog (Estilo Maker) -->
    <div v-if="confirmDialog.show" class="fixed inset-0 z-[1000] flex items-center justify-center p-6 bg-gray-950/80 backdrop-blur-md">
        <div 
            class="bg-white w-full max-w-sm rounded-[3rem] shadow-2xl p-12 text-center animate-in zoom-in duration-300"
            style="--bg-surface: #ffffff; --border-main: rgba(0,0,0,0.05);"
        >
            <div class="w-24 h-24 bg-gray-50 text-gray-900 rounded-full flex items-center justify-center mx-auto mb-8 text-4xl animate-bounce border border-gray-100">
                {{ confirmDialog.icon }}
            </div>
            <h3 class="text-3xl font-black text-gray-900 tracking-tighter uppercase mb-4">{{ confirmDialog.title }}</h3>
            <p class="text-gray-500 font-medium text-sm leading-relaxed mb-10 px-4">
                {{ confirmDialog.message }}
            </p>
            <div class="flex flex-col gap-4">
                <button @click="handleConfirm" class="btn-primary w-full py-5">
                    {{ confirmDialog.mode === 'confirm' ? 'Confirmar Acción' : 'Entendido' }}
                </button>
                <button v-if="confirmDialog.mode === 'confirm'" @click="confirmDialog.show = false" class="btn-secondary w-full py-5">
                    Mejor no, Volver
                </button>
            </div>
        </div>
    </div>
  </div> <!-- Cierre ROOT div (2060) -->
</template>

<style scoped>
.scrollbar-thin::-webkit-scrollbar { width: 6px; height: 6px; }
.scrollbar-thin::-webkit-scrollbar-track { background: transparent; }
.scrollbar-thin::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
.scrollbar-thin::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }

.dark .scrollbar-thin::-webkit-scrollbar-thumb { background: #1e293b; }
.dark .scrollbar-thin::-webkit-scrollbar-thumb:hover { background: #334155; }

/* Fluid Transitions */
.fade-slide-enter-active, .fade-slide-leave-active {
  transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}
.fade-slide-enter-from {
  opacity: 0;
  transform: translateY(20px);
}
.fade-slide-leave-to {
  opacity: 0;
  transform: translateY(-20px);
}

.toast-enter-active, .toast-leave-active {
  transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.toast-enter-from {
  opacity: 0;
  transform: translateY(30px) scale(0.9);
}
.toast-leave-to {
  opacity: 0;
  transform: scale(0.9);
}

.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

.fade-enter-active, .fade-leave-active { transition: opacity 0.5s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.view-fade-enter-active, .view-fade-leave-active { transition: all 0.4s var(--ease-out-expo); }
.view-fade-enter-from { opacity: 0; transform: translateX(30px); }
.view-fade-leave-to { opacity: 0; transform: translateX(-30px); }

/* Custom Range Input */
input[type=range] { -webkit-appearance: none; appearance: none; background: transparent; }
input[type=range]:focus { outline: none; }
input[type=range]::-webkit-slider-runnable-track { width: 100%; height: 8px; cursor: pointer; background: #f1f5f9; border-radius: 4px; }
input[type=range]::-webkit-slider-thumb { height: 20px; width: 20px; border-radius: 50%; background: #1e3a34; cursor: pointer; -webkit-appearance: none; appearance: none; margin-top: -6px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); border: 2px solid white; transition: all 0.2s; }
input[type=range]:hover::-webkit-slider-thumb { transform: scale(1.2); }



/* Printing Styles for Invoice PDF */
.print-only {
  display: none;
}

@media print {
  /* Ocultar TODO el layout normal (sidebar, dashboard, modales, alertas) */
  :deep(body > *:not(#app)),
  :deep(#app > div > :not(.print-only)),
  aside, main, .fixed.inset-0 {
    display: none !important;
  }
  
  /* Hacer visible solo el contenedor de impresión */
  .print-only {
    display: block !important;
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
    margin: 0;
    padding: 0;
  }
}
</style>
