<script setup>
import { ref, onMounted, onUnmounted, computed, watch, reactive, defineAsyncComponent, provide } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { api } from '../services/api'
import logger from '../utils/logger'
import { useSimulator } from '../composables/useSimulator'
import { usePrinters } from '../composables/usePrinters'
import { usePDF } from '../composables/usePDF'

// Componentes extraídos
import AdminSidebar from '../components/admin/AdminSidebar.vue'
import AdminHeader from '../components/admin/AdminHeader.vue'
import NotificationSystem from '../components/admin/NotificationSystem.vue'
import PriceSimulator from '../components/admin/PriceSimulator.vue'
import ManualOrderForm from '../components/admin/ManualOrderForm.vue'
import EditMaterialModal from '../components/admin/EditMaterialModal.vue'
import NewMaterialModal from '../components/admin/NewMaterialModal.vue'
import NewPrinterModal from '../components/admin/NewPrinterModal.vue'
import EditPrinterModal from '../components/admin/EditPrinterModal.vue'
import ShippingModal from '../components/admin/ShippingModal.vue'
import OrderDetailModal from '../components/admin/OrderDetailModal.vue'
import WebManager from '../components/admin/WebManager.vue'
import SettingsPanel from '../components/admin/SettingsPanel.vue'
/**
 * ADMIN DASHBOARD — N3XT 3D Industrial OS
 *
 * ═══════════════════════════════════════════════
 * ARQUITECTURA ACTUAL
 * ═══════════════════════════════════════════════
 *
 * Este archivo (278KB / 4178 líneas) es el núcleo del panel admin.
 * Se ha refactorizado parcialmente extrayendo la siguiente lógica:
 *
 * ✅ COMPOSABLES EXTRAÍDOS:
 * - useSimulator.ts → Simulador de costos de producción
 * - useUploads.ts → Subida de imágenes (proxy Cloudinary)
 * - usePrinters.ts → API wrappers para impresoras
 * - usePDF.ts → Generación de PDFs (cotizaciones, guías)
 * - useOrders.ts → Gestión de pedidos
 * - useSettings.ts → Gestión de configuración
 * - useUI.ts → Utilidades de UI (animaciones, etc.)
 *
 * 📦 SUB-COMPONENTES (carga asíncrona):
 * KanbanBoard, InventoryManager, AccountingDashboard,
 * MachineMonitor, OrderHistory, PurchaseLog,
 * ContactManager, DiscountManager
 *
 * 🔄 PENDIENTE DE EXTRAER (candidatos a composables):
 * - syncAll + fetch functions → useDataFetching.ts
 * - Order mutations → useOrderMutations.ts
 * - Catalog/web management → useWebManager.ts
 * - Modal/notification system → useModalManager.ts
 *
 * El bulk del tamaño (278KB) es el template HTML/CSS con todas
 * las vistas inline. Para reducirlo drásticamente habría que
 * crear sub-vistas separadas para cada tab.
 *
 * @module AdminDashboard
 */



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
const isSidebarOpen = ref(false)
const isDark = ref(localStorage.getItem('n3xt_theme') !== 'light')
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

const settings = ref({
 infra: { luz_hr: 926, depr_hr: 400, mant_hr: 700, etiquetas: 500, load_factor: 0.4 },
 prep: { mano_obra_hr: 1000, prep_time_pct: 10 },
 oper: { transporte: 5, ganancia: 20, marketing: 10, fallos: 5 },
 margin: { iva: 19 },
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

// --- Composables ---
const { simulator, simulatedResult } = useSimulator({
 inventoryData, settings, showNotify
})

const { apiUpdatePrinter, apiDeletePrinter, apiMaintenanceComplete, apiResetPrinter} = usePrinters()
const { handleDownloadQuotePDF, handlePrintLabel} = usePDF({
 settings,
 inventoryData,
 showNotify
})


// --- Wrappers para template (adaptan composables al template existente) ---
const handleEditPrinterWrapper = (printer) => {
 editingPrinter.value = { ...printer }
 modalState.editPrinter = true
}

const handleUpdatePrinterStatusWrapper = async ({ id, status }) => {
 try {
 await apiUpdatePrinter(id, { status })
 await fetchPrinters()
 showNotify(`Estado actualizado: ${status}`, 'success')
 } catch (err) {
 showNotify('Error: ' + err.message, 'error')
 }
}

const handleMaintenanceCompleteWrapper = async (id) => {
 try {
 await apiMaintenanceComplete(id)
 await fetchPrinters()
 showAlert('Puesta a Punto', 'Mantenimiento registrado. Horas reiniciadas.', '')
 } catch (err) {
 showAlert('Error', 'No se pudo registrar el mantenimiento: ' + err.message, '')
 }
}

const handleResetPrinterWrapper = async (id) => {
 try {
 await apiResetPrinter(id)
 await fetchPrinters()
 showNotify('Máquina reseteada a estado Libre', 'success')
 } catch (err) {
 showNotify('Error: ' + err.message, 'error')
 }
}

const handleDeletePrinterWrapper = (id) => {
 askConfirm(
 'Eliminar Impresora',
 '¿Estás seguro de que deseas retirar esta máquina de la granja? Esta acción no se puede deshacer.',
 '',
 async () => {
 try {
 await apiDeletePrinter(id)
 await fetchPrinters()
 showNotify('Impresora eliminada', 'success')
 } catch (err) {
 showNotify('Error: ' + err.message, 'error')
 }
 }
 )
}

const handleEditPrinter = handleEditPrinterWrapper
const handleUpdatePrinterStatus = handleUpdatePrinterStatusWrapper
const handleMaintenanceComplete = handleMaintenanceCompleteWrapper
const handleResetPrinter = handleResetPrinterWrapper
const handleDeletePrinter = handleDeletePrinterWrapper




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

const editingPrinter = ref({ id: '', name: '', model: '', technology: 'FDM', status: 'idle', maintenance_interval_h: 200, next_maintenance: '', total_hours_run: 0, maintenance_notes: '' })
// Note: editingPrinter aliased as editingPrinterData from usePrinters composable


// Selections
const selectedOrderForPrinter = ref(null)
const selectedOrderDetails = ref(null)
const editingMaterial = ref({ id: '', code: '', name: '', cost_per_kg: 0, density: 1.24, color: '#cccccc', location: '' })

// Forms
const newMaterial = reactive({ id: '', code: '', name: '', category: 'FDM', type: 'material', unit: 'g', cost_per_kg: 0, density: 1.24, color: '#000000', initial_stock: 1000, 
 low_stock_threshold: 200,
 package_price: null, package_qty: null, package_units: 1 
})
const newPrinter = reactive({ name: '', model: '', technology: 'FDM' })
// simulator provided by useSimulator composable
const logoUrl = computed(() => {
 if (!settings.value?.company_logo) return '/logo.png';
 if (settings.value.company_logo.startsWith('http')) return settings.value.company_logo;
 // Soporte para rutas relativas del Motor N3XT
 return `${api.storageUrl}/${settings.value.company_logo}`;
});

const uploadingLogo = ref(false)
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
 logger.error(`Error en módulo ${task.name}:`, err)
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
 logger.error('Error fetching orders:', err)
 orders.value = []
 }
}

const fetchInventory = async () => {
 try {
 const res = await api.get('/materials', true)
 inventoryData.value = Array.isArray(res) ? res : (res?.data || [])
 } catch (_err) { // eslint-disable-line @typescript-eslint/no-unused-vars
 inventoryData.value = []
 }
}

const fetchPrinters = async () => {
 try {
 const res = await api.get('/admin/printers', true)
 printers.value = Array.isArray(res) ? res : (res?.data || [])
 } catch (_err) { // eslint-disable-line @typescript-eslint/no-unused-vars
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
 }
 if (settings.value.oper?.transporte) {
 simulator.transporte_pct = settings.value.oper.transporte
 }
 if (settings.value.oper?.marketing) {
 simulator.marketing_pct = settings.value.oper.marketing
 }
 if (settings.value.oper?.fallos) {
 simulator.fallos_pct = settings.value.oper.fallos
 }
 if (settings.value.oper?.ganancia) {
 simulator.profit_pct = settings.value.oper.ganancia
 }
 } catch (err) {
 logger.error('Error settings:', err)
 }
}

// Sincronización reactiva: si cambias los ajustes, el simulador se actualiza al instante
watch(() => settings.value, (newVal) => {
 if (newVal.infra?.etiquetas) {
 simulator.etiquetas = newVal.infra.etiquetas

 }
 if (newVal.oper?.transporte) {
 simulator.transporte_pct = newVal.oper.transporte

 }
 if (newVal.oper?.marketing) {
 simulator.marketing_pct = newVal.oper.marketing

 }
 if (newVal.oper?.fallos) {
 simulator.fallos_pct = newVal.oper.fallos

 }
 if (newVal.oper?.ganancia) {
 simulator.profit_pct = newVal.oper.ganancia

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
 logger.error('Error fetching contacts:', err)
 }
}


// --- Mutations ---

const orderToShip = ref(null)
const trackingGuide = ref('')
const trackingCarrier = ref('')

const handleStatusUpdate = async ({ orderId, status, tracking_guide, tracking_carrier }) => {
 logger.info(`[ACTION] Actualizando estado: Orden ${orderId} -> ${status}`);
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
 
 logger.info(`[STOCK] Deduciendo ${usedWeight}g de ${material.name}. Nuevo: ${newStock}`);
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
 logger.info(`[ACTION] Toggle Pago: Orden ${orderId}`);
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
 logger.info(`[ACTION] Solicitud de borrado: Orden ${id}`);
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


const downloadFile = async (order) => {
 if (!order.file_path && !order.id) {
 showNotify('No hay archivo digital asociado a este pedido.', 'warning')
 return
 }
 
 try {
 // Use api client with session cookies (Sanctum SPA) instead of Bearer token
 const res = await fetch(`${api.baseUrl}/admin/orders/${order.id}/download`, {
 credentials: 'include',
 headers: { 'Accept': 'application/json' }
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


const handleDownloadSimulationPDF = (formData) => {
 const materialId = formData?.material_id || simulator.material_id
 if (!materialId) {
 showNotify('Selecciona un material para generar la cotizacion.', 'warning')
 return
 }
 
 // Si viene data del PriceSimulator, la usamos; si no, del composable
 const mat = inventoryData.value.find(m => m.id === materialId)
 
 // Mapeamos los datos del simulador a una estructura que entienda el generador de PDF
 const fakeOrder = {
 id: 'SIM-' + Date.now().toString().slice(-4),
 customer_name: formData?.customer_name || simulator.customer_name || 'Cliente Prospecto',
 customer_id_document: formData?.customer_id_document || simulator.customer_id_document || '',
 customer_company: formData?.customer_company || simulator.customer_company || '',
 customer_email: formData?.customer_email || simulator.customer_email || 'Sin email',
 customer_phone: formData?.customer_phone || simulator.customer_phone || 'Sin teléfono',
 shipping_address: formData?.shipping_address || simulator.shipping_address || '',
 shipping_city: formData?.shipping_city || simulator.shipping_city || '',
 shipping_zip: formData?.shipping_zip || simulator.shipping_zip || '',
 shipping_reference: formData?.shipping_reference || simulator.shipping_reference || '',
 job_name: formData?.job_name || simulator.job_name || 'Simulación de Proyecto',
 technology: mat?.category || 'FDM',
 material_id: materialId,
 estimated_weight_g: formData?.weight_g || simulator.weight_g,
 estimated_duration_h: formData?.total_hours || simulatedResult.value.total_hours,
 total_price: formData?.total_price != null ? formData.total_price : (simulatedResult.value.total || 0),
 extras_cost: formData?.extras_cost != null ? formData.extras_cost : (simulatedResult.value.extras || 0),
 extra_items: formData?.extra_items ? JSON.parse(JSON.stringify(formData.extra_items)) : JSON.parse(JSON.stringify(simulator.extra_items))
 }
 
 handleDownloadQuotePDF(fakeOrder)
}

// handleDownloadQuotePDF provided by usePDF composable


const handleUpdateStock = async (matId, newStock) => {
 try {
 if (!newStock || newStock === 0) return
 
 const mat = inventoryData.value.find((m) => m.id === matId)
 if (!mat) return
 
 // Cálculo preventivo: Sumamos al stock actual para evitar sobrescritura accidental
 const currentStock = mat.inventory?.stock_available || 0
 const totalNewStock = currentStock + newStock
 
 await api.post(`/materials/${matId}/stock`, { stock_available: totalNewStock }, true)
 await fetchInventory()
 
 showNotify(`Stock incrementado: +${newStock}${mat.unit || ''} en ${mat.name}`, 'success')
 } catch (err) {
 showNotify('Error al actualizar stock: ' + err.message, 'error')
 }
}

// handlePrintLabel provided by usePDF composable

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

const savePrinterEdit = async () => {
 if (submitting.value) return
 submitting.value = true
 try {
 await apiUpdatePrinter(editingPrinter.value.id, editingPrinter.value)
 modalState.editPrinter = false
 await fetchPrinters()
 showNotify('Impresora actualizada con exito', 'success')
 } catch (err) {
 showNotify('Error al guardar: ' + err.message, 'error')
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

// eslint-disable-next-line @typescript-eslint/no-unused-vars
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


/* saveMaterial removed — addMaterial handles creation */
const saveSettings = (silent = false, updatedData = null) => {
 const executeSave = async () => {
 if (savingSettings.value) return
 savingSettings.value = true
 logger.info('N3XT Sync: Iniciando proceso de guardado...');
 
 try {
 try {
 if (typeof globalSeoOptimizer === 'function') {
 globalSeoOptimizer()
 }
 } catch (seoErr) {
 logger.warn('Advertencia SEO:', seoErr)
 }
 
  // Use data from SettingsPanel if provided (merge with current state to preserve discounts, logo, etc.)
  const payload = updatedData
    ? { ...JSON.parse(JSON.stringify(settings.value)), ...updatedData }
    : JSON.parse(JSON.stringify(settings.value))
 
 const response = await api.post('/admin/settings', { settings: payload }, true)
 
 if (response) {
 showNotify('Ajustes publicados con exito', 'success')
 if (response.settings) {
 settings.value = { ...settings.value, ...response.settings }
 }
 }
 } catch (err) {
 logger.error('Error crítico en guardado:', err)
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
 
 // Actualizamos el logo en el estado reactivo con la ruta relativa
 if (res && res.logo_path) {
 settings.value.company_logo = res.logo_path
 showNotify('IDENTIDAD ACTUALIZADA: Marca sincronizada con éxito', 'success')
 } else if (res && res.logo_url) {
 // Fallback: extraer la ruta relativa de la URL completa
 const urlParts = res.logo_url.split('/storage/');
 if (urlParts.length > 1) {
 const path = urlParts[1].split('?')[0];
 settings.value.company_logo = path;
 } else {
 settings.value.company_logo = res.logo_url;
 }
 showNotify('IDENTIDAD ACTUALIZADA: Marca sincronizada con éxito', 'success')
 }
 } catch (err) {
 logger.error('Error Logo Upload:', err)
 showNotify('FALLO DE IDENTIDAD: ' + err.message, 'error')
 } finally {
 uploadingLogo.value = false
 // Limpiar input para permitir subir el mismo archivo si es necesario
 if (event.target) event.target.value = ''
 }
}


const handleExportCSV = async () => {
 try {
 const response = await fetch(`${api.baseUrl}/admin/export-csv`, {
 credentials: 'include',
 headers: {
 'Accept': 'text/csv'
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
 try {
 // Integrity Logic: Use snapshot if available, fallback to current settings
 const hasSnapshot = order.cost_snapshot && order.cost_snapshot.settings
 const snap = hasSnapshot ? order.cost_snapshot : null
 
 const currentMat = inventoryData.value.find(m => m.id === order.material_id)
 const matCostPerKg = hasSnapshot ? snap.material_cost_per_kg : (currentMat ? currentMat.cost_per_kg : 0)
 
 const s = hasSnapshot ? snap.settings : settings.value
 
 const totalHours = parseFloat(order.estimated_duration_h) || 0
 const loadFactor = s.infra?.load_factor ?? 0.4
 const prepTimePct = (s.prep?.prep_time_pct ?? 10) / 100
 const luz = totalHours * loadFactor * (s.infra?.luz_hr || 0)
 const labor = (totalHours * prepTimePct) * (s.prep?.mano_obra_hr || 0)
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
 } catch (err) {
 logger.error('Error al abrir detalle de orden:', err)
 showNotify('Error al abrir detalle: ' + (err.message || 'Error desconocido'), 'error')
 }
}


// Motor de sincronización — hoisted para acceso desde onUnmounted
let syncInterval

// simulatedResult provided by useSimulator composable)
const handleConvertSimulationToOrder = async (formData) => {
 if (submitting.value) return
 submitting.value = true
 try {
 const materialId = formData?.material_id || simulator.material_id
 const material = inventoryData.value.find(m => m.id === materialId)
 const density = material?.density || 1.25
 const estimatedVolume = (simulator.weight_g / density) * 1000

 const qty = Math.max(1, formData?.pieces_per_batch || simulator.pieces_per_batch || 1)
 const timeStr = formData?.time_str || simulator.time_str || '0:00'
 const totalHours = (parseFloat(timeStr.split(':')[0]) || 0) + ((parseFloat(timeStr.split(':')[1]) || 0) / 60)
 const extrasData = formData?.extra_items || simulator.extra_items

 await api.post('/orders', {
 job_name: formData?.job_name != null ? formData.job_name : (simulator.job_name || ''),
 customer_id: formData?.customer_id != null ? formData.customer_id : (simulator.customer_id || null),
 customer_name: formData?.customer_name != null ? formData.customer_name : (simulator.customer_name || 'Cliente Simulación'),
 customer_id_document: formData?.customer_id_document != null ? formData.customer_id_document : (simulator.customer_id_document || ''),
 customer_email: formData?.customer_email != null ? formData.customer_email : (simulator.customer_email || ''),
 customer_phone: formData?.customer_phone != null ? formData.customer_phone : (simulator.customer_phone || ''),
 material_id: materialId,
 volume_mm3: estimatedVolume,
 estimated_weight_g: formData?.weight_g != null ? formData.weight_g : (simulator.weight_g || 0),
 estimated_duration_h: totalHours,
 total_price: formData?.total_price != null ? formData.total_price : (simulatedResult.value.total || 0),
 extras_cost: formData?.extras_cost != null ? formData.extras_cost : 0,
 technology: material?.category || 'FDM',
 comments: `SIMULACIÓN N3XT: ${formData?.job_name || formData?.customer_name || simulator.job_name || 'Proyecto'}. Lote: ${qty} pzs.` + ((formData?.comments || simulator.comments) ? `\n\nNOTAS INTERNAS:\n${formData?.comments || simulator.comments}` : ''),
 status: 'pending',
 qty: qty,
 extra_items: extrasData.map(function(e) { return { material_id: e.id, qty: e.qty }; }),
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
 logger.error('Error Conversion:', err)
 showNotify('Error al convertir: ' + err.message, 'error')
 } finally {
 submitting.value = false
 }
}


// --- Lifecycle & Watchers ---

onMounted(async () => {
 document.body.style.overflow = 'hidden'
 updateTheme()
 await syncAll()
 
 // Motor de Sincronización Proactiva: Cada 5 min intenta un silent sync si hay conexión
 syncInterval = setInterval(async () => {
 try {
 await syncAll(true)
 } catch (e) {
 // Fallo silencioso, no interrumpir al usuario
 logger.debug('Silent sync failed:', e)
 }
 }, 1000 * 60 * 5)
 
 // Verificar sesión activa via Sanctum SPA (session cookie)
 // No usamos localStorage token — la auth es via cookie httpOnly
 const authStatus = await api.checkAuth()
 if (!authStatus.authenticated || authStatus.role !== 'admin') {
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
 } catch (e) {
 logger.debug('Error fetching order by query:', e)
 }
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
 logger.error('Error inicial de carga:', err)
 }
 }
})

onUnmounted(() => {
 document.body.style.overflow = ''
 if (typeof syncInterval !== 'undefined') {
 clearInterval(syncInterval)
 }
})

watch(activeTab, (tab) => {
 if (tab === 'accounting') fetchAnalytics()
 // Asegurar que cerramos modales al cambiar de pestaña para evitar bloqueos
 Object.keys(modalState).forEach(k => modalState[k] = false)
})

const logout = async () => {
 try {
 await api.post('/logout') } catch (e) {
 // Silent — session may already be invalid
 logger.debug('Logout silent error:', e)
 }
 router.push('/admin/login')
}

// Eliminado duplicado de downloadFile para usar la versión híbrida del inicio del script


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
 const getAbsoluteUrl = (path) => path.startsWith('http') ? path : window.location.origin + (path.startsWith('/') ? '' : '/') + path;
 const companyLogo = settings.value.company_logo ? getAbsoluteUrl(settings.value.company_logo.startsWith('http') ? settings.value.company_logo : api.storageUrl + '/' + settings.value.company_logo) : window.location.origin + '/logo.png';
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
 .logo-img { height: 55px; max-width: 200px; width: auto; object-fit: contain; }
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
 ${companyLogo ? `<img src="${companyLogo}" class="logo-img" alt="Imagen N3XT 3D">` : ''}
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
 <div class="card-val">$${Number(reportData.summary.total_revenue).toLocaleString(undefined, {maximumFractionDigits: 0})}</div>
 <div class="card-sub">${reportData.summary.orders_count} órdenes procesadas</div>
 </div>
 <div class="card">
 <div class="card-label">Gastos de Operación</div>
 <div class="card-val" style="color: #ef4444;">$${Number(reportData.summary.total_expenses).toLocaleString(undefined, {maximumFractionDigits: 0})}</div>
 <div class="card-sub">Costo de producción base</div>
 </div>
 <div class="card emerald">
 <div class="card-label">Utilidad Neta Real</div>
 <div class="card-val">$${Number(reportData.summary.net_profit).toLocaleString(undefined, {maximumFractionDigits: 0})}</div>
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
 <div class="data-item"><span class="item-label">Costo por Fallos</span><span class="item-val neg">$${Number(reportData.summary.waste_cost).toLocaleString(undefined, {maximumFractionDigits: 0})}</span></div>
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
 
 <div class="data-item"><span class="item-label">Materia Prima e Insumos</span><span class="item-val">$${Number(reportData.summary.total_material_cost).toLocaleString(undefined, {maximumFractionDigits: 0})}</span></div>
 <div class="data-item sub-item"><span class="item-label">Consumo FDM</span><span class="item-val">$${Number(reportData.summary.breakdown.mat_fdm).toLocaleString(undefined, {maximumFractionDigits: 0})}</span></div>
 <div class="data-item sub-item"><span class="item-label">Consumo SLA</span><span class="item-val">$${Number(reportData.summary.breakdown.mat_sla).toLocaleString(undefined, {maximumFractionDigits: 0})}</span></div>
 
 <div class="data-item"><span class="item-label">Mano de Obra Especializada</span><span class="item-val">$${Number(reportData.summary.breakdown.labor).toLocaleString(undefined, {maximumFractionDigits: 0})}</span></div>
 <div class="data-item"><span class="item-label">Costos de Energía y Luz</span><span class="item-val">$${Number(reportData.summary.breakdown.luz).toLocaleString(undefined, {maximumFractionDigits: 0})}</span></div>
 <div class="data-item"><span class="item-label">Plan de Mantenimiento</span><span class="item-val">$${Number(reportData.summary.breakdown.mant).toLocaleString(undefined, {maximumFractionDigits: 0})}</span></div>
 <div class="data-item"><span class="item-label">Depreciación Activos</span><span class="item-val">$${Number(reportData.summary.breakdown.depr).toLocaleString(undefined, {maximumFractionDigits: 0})}</span></div>
 <div class="data-item"><span class="item-label">Logística y Extras</span><span class="item-val">$${Number(reportData.summary.total_extras_cost).toLocaleString(undefined, {maximumFractionDigits: 0})}</span></div>
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
 <td style="text-align: right; font-weight: 900; color: #1e3a34; font-size: 12px;">$${Number(c.total_spent).toLocaleString(undefined, {maximumFractionDigits: 0})}</td>
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
 EMISIÓN: ${new Date().toLocaleString(undefined, {maximumFractionDigits: 0})}
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
 ${companyLogo ? `<img src="${companyLogo}" class="logo-img" alt="Imagen N3XT 3D">` : ''}
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
 <td class="price-cell">$${Number(o.total_price).toLocaleString(undefined, {maximumFractionDigits: 0})}</td>
 </tr>
 `).join('')}
 <tr class="summary-row">
 <td colspan="6" style="text-align: right; font-weight: 900; text-transform: uppercase; letter-spacing: 2px; font-size: 10px;">Venta Total del Periodo</td>
 <td style="text-align: right; font-size: 18px; font-weight: 900;">$${filteredOrders.reduce((acc, o) => acc + parseFloat(o.total_price), 0).toLocaleString(undefined, {maximumFractionDigits: 0})}</td>
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
 const imgs = printWindow.document.images;
 let loaded = 0;
 const print = () => { printWindow.focus(); printWindow.print(); };
 if (imgs.length === 0) { setTimeout(print, 250); } 
 else {
 let printed = false;
 const onload = () => { if (++loaded === imgs.length && !printed) { printed = true; setTimeout(print, 250); } };
 for (let i = 0; i < imgs.length; i++) {
 if (imgs[i].complete) onload();
 else { imgs[i].onload = onload; imgs[i].onerror = onload; }
 }
 setTimeout(() => { if (!printed) { printed = true; print(); } }, 3000);
 }
 
 } catch (err) {
 logger.error('Error:', err);
 showNotify('Error: ' + err.message, 'error')
 } finally {
 loadingAnalytics.value = false;
 }
}


const handlePurgeAll = () => {
 askConfirm(
 'PELIGRO: PURGA DE PRODUCCIÓN',
 'Esta acción eliminará de forma irreversible todos los pedidos, materiales, inventarios, clientes y maquinaria del sistema. Volverá el estado a CERO. ¿Estás seguro?',
 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
 async () => {
 try {
 await api.post('/admin/purge-all', { confirm_purge: 'PURGE_ALL_CONFIRMED' });
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
 <div :class="{'dark': isDark}" class="min-h-screen bg-[#f8fafc] dark:bg-[#0a0f14] text-[#ffffff] dark:text-white flex overflow-hidden font-sans selection:bg-[#08872b]/20 transition-colors duration-500">
 
 <!-- Background Industrial Engine -->
 <div class="fixed inset-0 technical-grid opacity-20 dark:opacity-10 pointer-events-none z-0"></div>
 <div class="fixed top-[-10%] right-[-10%] w-[600px] h-[600px] bg-[#08872b]/10 rounded-[60px] blur-[180px] pointer-events-none z-0"></div>

 <!-- Backdrop for mobile sidebar -->
 <div 
 v-if="isSidebarOpen" 
 class="fixed inset-0 bg-black/60 dark:bg-black/80 backdrop-blur-xs z-40 md:hidden transition-all duration-300" 
 @click="isSidebarOpen = false"
 ></div>

 <!-- N3XT PREMIUM SIDEBAR (extraído a AdminSidebar.vue) -->
 <AdminSidebar 
 :is-open="isSidebarOpen"
 :active-tab="activeTab === 'accounting' ? 'accounting' : activeTab"
 :pending-orders-count="orders.filter(o => o.status === 'pending').length"
 @update:active-tab="(tab) => { activeTab = tab === 'analytics' ? 'accounting' : tab; isSidebarOpen = false }"
 @close="isSidebarOpen = false"
 @logout="logout"
 />

 <!-- MAIN CONTENT AREA -->
 <main class="flex-1 flex flex-col min-w-0 bg-transparent h-screen overflow-hidden relative z-10">

 <!-- PREMIUM HEADER (extraído a AdminHeader.vue) -->
 <AdminHeader
 :active-tab="activeTab"
 :loading="loading"
 :is-sidebar-open="isSidebarOpen"
 :settings="settings"
 @toggle-sidebar="isSidebarOpen = !isSidebarOpen"
 @sync="syncAll(false)"
 @logout="logout"
 />

 <!-- VIEWPORT: DINAMIC CONTENT -->
 <div class="flex-1 flex flex-col min-w-0 overflow-y-auto overflow-x-hidden relative p-2 pb-28 md:p-12 md:pb-12 bg-transparent">
 
 <div class="w-full max-w-[1600px] mx-auto">
 <transition 
 name="view-fade" 
 mode="out-in"
 enter-active-class="animate-slide-up"
 >
 <div :key="activeTab">
 
 <!-- Módulo: Producción (Kanban) -->
 <div v-if="activeTab === 'kanban'" class="p-0 md:p-8 animate-in fade-in slide-in-from-bottom-2 duration-300">
 <div class="flex flex-col lg:flex-row items-center justify-between gap-4 md:gap-8 mb-6 md:mb-12 bg-[#151a22]/40 dark:bg-[#090d0a]/40 backdrop-blur-md p-4 md:p-10 rounded-[2rem] md:rounded-[3rem] border border-white/60 dark:border-[#21262d] -gray-200/20 dark:-none relative overflow-hidden group">
 <div class="absolute -right-20 -top-20 w-64 h-64 bg-[#08872b]/5 rounded-[60px] blur-3xl group-hover:bg-[#08872b]/10 transition-all duration-1000"></div>
 <div class="relative z-10 text-center md:text-left"><h1 class="text-3xl md:text-5xl lg:text-6xl font-black text-[#ffffff] dark:text-white tracking-tighter uppercase leading-[0.85] mb-6 animate-fade-in">
                    Panel de <span class="text-[#8dd6ff]">Control</span>
                    </h1>
 <p class="text-[9px] md:text-xs text-[#c3c4c5] font-bold uppercase tracking-[0.3em] md:tracking-[0.4em] mt-2 md:mt-4 flex items-center justify-center md:justify-start gap-2 md:gap-3">
 <span class="w-2 h-2 md:w-2.5 md:h-2.5 rounded-[60px] bg-emerald-500 -[0_0_8px_#10b981]"></span>
 Workshop Status: Operational
 </p>
 </div>
 
 <div class="flex flex-wrap justify-center lg:justify-end gap-3 md:gap-4 relative z-10">
 <div class="bg-[#151a22]/80 dark:bg-[#151a22]/80 p-4 md:p-6 rounded-[1.5rem] md:rounded-[2.5rem] border border-[#21262d] dark:border-[#21262d] min-w-[120px] md:min-w-[160px] hover: transition-all group relative overflow-hidden">
 <p class="text-[9px] md:text-[10px] font-black text-[#c3c4c5] uppercase tracking-widest mb-1">Carga Activa</p>
 <div class="flex items-end gap-2">
 <h3 class="text-2xl md:text-3xl font-black text-[#ffffff] dark:text-white tracking-tighter leading-none">{{ orders.filter(o => o.status === 'printing').length }}</h3>
 <span class="text-[7px] md:text-[8px] font-black text-[#8dd6ff] uppercase tracking-widest mb-0.5 italic">Jobs</span>
 </div>
 </div>
 </div>
 </div>
 <!-- Estadísticas Rápidas de Producción -->
 <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-3 md:gap-8 mb-6 md:mb-12">
 <div class="bg-[#151a22] dark:bg-[#151a22] p-4 md:p-10 rounded-[1.5rem] md:rounded-[3rem] border border-[#21262d] dark:border-[#21262d] group relative overflow-hidden">
 <p class="text-[9px] md:text-[10px] font-black text-[#c3c4c5] uppercase tracking-widest mb-2 md:mb-3">Total Proyectos</p>
 <h3 class="text-3xl md:text-4xl font-black text-[#ffffff] dark:text-white tracking-tighter">{{ orders.length }}</h3>
 <div class="mt-4 md:mt-6">
 <button class="btn-primary w-full py-3 md:py-4 !rounded-[6px] md:!rounded-[24px] text-xs md:text-sm" @click="modalState.simulator = true">
 <svg class="w-3.5 h-3.5 md:w-4 md:h-4 text-[#8dd6ff]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
 </svg>
 <span>Análisis de Manufactura</span>
 </button>
 </div>
 </div>

 <div class="bg-[#151a22] dark:bg-[#151a22] p-4 md:p-10 rounded-[1.5rem] md:rounded-[3rem] border border-[#21262d] dark:border-[#21262d] group">
 <p class="text-[9px] md:text-[10px] font-black text-[#c3c4c5] uppercase tracking-widest mb-2 md:mb-3">Pendientes</p>
 <div class="flex items-center justify-between">
 <h3 class="text-3xl md:text-5xl font-black text-red-500 tracking-tighter">{{ orders.filter(o => o.status === 'pending').length }}</h3>
 <div class="w-10 h-10 md:w-12 md:h-12 bg-red-50 dark:bg-red-500/10 text-red-500 rounded-[6px] md:rounded-[24px] flex items-center justify-center animate-pulse">
 <svg class="w-5 h-5 md:w-6 md:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
 </div>
 </div>
 </div>

 <div class="bg-[#151a22] dark:bg-[#151a22] p-4 md:p-10 rounded-[1.5rem] md:rounded-[3rem] border border-[#21262d] dark:border-[#21262d] group">
 <p class="text-[9px] md:text-[10px] font-black text-[#c3c4c5] uppercase tracking-widest mb-2 md:mb-3">En Producción</p>
 <div class="flex items-center justify-between">
 <h3 class="text-3xl md:text-5xl font-black text-amber-500 tracking-tighter">{{ orders.filter(o => o.status === 'printing').length }}</h3>
 <div class="w-10 h-10 md:w-12 md:h-12 bg-amber-50 dark:bg-amber-500/10 text-amber-500 rounded-[6px] md:rounded-[24px] flex items-center justify-center">
 <svg class="w-5 h-5 md:w-6 md:h-6 animate-spin-slow" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
 </div>
 </div>
 </div>

 <!-- Terminados & Eficiencia -->
 <div class="bg-[#151a22] dark:bg-[#151a22] p-4 md:p-8 rounded-[1.5rem] md:rounded-[3rem] border border-[#21262d] dark:border-[#21262d] group flex flex-col justify-between overflow-hidden relative">
 <div class="flex items-center justify-between mb-3 md:mb-4">
 <div>
 <p class="text-[9px] md:text-[10px] font-black text-[#c3c4c5] uppercase tracking-widest mb-1">Terminados</p>
 <h3 class="text-3xl md:text-4xl font-black text-emerald-500 tracking-tighter">{{ orders.filter(o => o.status === 'completed').length }}</h3>
 </div>
 <div class="w-10 h-10 md:w-12 md:h-12 bg-emerald-50 dark:bg-emerald-500/10 text-emerald-500 rounded-[6px] md:rounded-[24px] flex items-center justify-center">
 <svg class="w-5 h-5 md:w-6 md:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
 </div>
 </div>

 <!-- El Cuadro Negro (Eficiencia Bruta) -->
 <div class="bg-[#090d0a] p-4 md:p-6 rounded-[1.5rem] md:rounded-[2rem] -black/40 relative overflow-hidden border border-white/5 group-hover:scale-[1.02] transition-transform duration-500">
 <div class="absolute -right-10 -top-10 w-32 h-32 bg-[#08872b]/5 rounded-[60px] blur-2xl group-hover:bg-[#08872b]/10 transition-all"></div>
 <p class="text-[7px] md:text-[8px] font-black text-[#a4aea6] uppercase tracking-[0.2em] md:tracking-[0.3em] mb-2">Eficiencia Bruta</p>
 <div class="flex items-end gap-2">
 <h3 class="text-lg md:text-2xl font-black text-white tracking-tighter leading-none">${{ Math.round(orders.reduce((acc, o) => acc + (o.status === 'completed' ? o.total_price : 0), 0)).toLocaleString(undefined, {maximumFractionDigits: 0}) }}</h3>
 <span class="text-[7px] md:text-[8px] font-black text-[#8dd6ff] uppercase tracking-widest mb-0.5 italic">COP</span>
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
 :loading-analytics="loadingAnalytics"
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

 
 <!-- Modal Simulador (extraido a PriceSimulator.vue) -->
 <PriceSimulator
 :visible="modalState.simulator"
 :settings="settings"
 :materials="inventoryData"
 :contacts="contacts.customers"
 @close="modalState.simulator = false"
 @create-order="handleConvertSimulationToOrder"
 @download-pdf="handleDownloadSimulationPDF"
 />

 <!-- Modal: Orden Manual (extraido a ManualOrderForm.vue) -->
 <ManualOrderForm
 :visible="modalState.manualOrder"
 :materials="inventoryData"
 :contacts="contacts.customers"
 :settings="settings"
 @close="modalState.manualOrder = false"
 @created="modalState.manualOrder = false; fetchOrders()"
 />

 <!-- Modal: Editar Material (extraido a EditMaterialModal.vue) -->
 <EditMaterialModal
 v-model="modalState.editMaterial"
 :editing-material="editingMaterial"
 :submitting="submitting"
 @update-material="updateMaterial"
 />

 <!-- Modal: Nuevo Material (extraido a NewMaterialModal.vue) -->
 <NewMaterialModal
 v-model="modalState.newMaterial"
 :new-material="newMaterial"
 :submitting="submitting"
 @add-material="addMaterial"
 />

 <!-- Modal: Nueva Impresora (extraido a NewPrinterModal.vue) -->
 <NewPrinterModal
 v-model="modalState.newPrinter"
 :new-printer="newPrinter"
 :submitting="submitting"
   @update:new-printer="Object.assign(newPrinter, $event)"
 @add-printer="addPrinter"
 />

 <!-- Modal: Editar Impresora (extraido a EditPrinterModal.vue) -->
 <EditPrinterModal
 v-model="modalState.editPrinter"
 :editing-printer="editingPrinter"
 :submitting="submitting"
 @update-printer="savePrinterEdit"
 @maintenance-complete="handleMaintenanceComplete"
 @reset-printer="handleResetPrinter"
 @delete-printer="handleDeletePrinter"
 />

 <!-- Modal: Envío (ShippingModal.vue) -->
 <ShippingModal
 v-model="modalState.shipping"
 :order-to-ship="orderToShip"
 :tracking-guide="trackingGuide"
 :tracking-carrier="trackingCarrier"
 @update:tracking-guide="trackingGuide = $event"
 @update:tracking-carrier="trackingCarrier = $event"
 @confirm-shipping="confirmShipping"
 />

 <!-- Modal: Detalle de Orden (OrderDetailModal.vue) -->
 <OrderDetailModal
 :order="selectedOrderDetails"
 :materials="inventoryData"
 :settings="settings"
 @close="selectedOrderDetails = null; modalState.orderDetails = false"
 @updated="fetchOrders()"
 />

 <!-- Módulo: Gestión Web (extraído a WebManager.vue) -->
 <WebManager
 v-if="activeTab === 'web'"
 :settings="settings"
 :inventory-data="inventoryData"
 :show-notify="showNotify"
 :ask-confirm="askConfirm"
 @save-settings="saveSettings"
 @seo-optimize="globalSeoOptimizer"
 />

 <!-- Módulo: Parámetros (SettingsPanel.vue) -->
 <SettingsPanel
 v-if="activeTab === 'settings'"
 :settings="settings"
 :saving-settings="savingSettings"
 :uploading-logo="uploadingLogo"
 :logo-url="logoUrl"
 :show-notify="showNotify"
 :ask-confirm="askConfirm"
 @save-settings="saveSettings"
 @logo-upload="handleLogoUpload"
 @purge-all="handlePurgeAll"
 />

 <!-- Premium Notification Toast (extraido a NotificationSystem.vue) -->
 
 </div>
 </transition>
 </div>
 </div>
 </main>

 </div>

 <NotificationSystem
 :notification="notification"
 :confirm-dialog="confirmDialog"
 :is-dark="isDark"
 @close="confirmDialog.show = false; notification.show = false"
 />

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
