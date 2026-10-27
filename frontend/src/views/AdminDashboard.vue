<script setup>
import { ref, onMounted, onUnmounted, computed, watch, reactive, defineAsyncComponent } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { api } from '../services/api'
import logger from '../utils/logger'
import { useSimulator } from '../composables/useSimulator'
import { usePrinters } from '../composables/usePrinters'
import { useMaterials } from '../composables/useMaterials'
import { useSimulationActions } from '../composables/useSimulationActions'
import { useOrderActions } from '../composables/useOrderActions'
import { usePDF } from '../composables/usePDF'
import { useAdminFeedback } from '../composables/useAdminFeedback'
import { useAdminSettings } from '../composables/useAdminSettings'

// Componentes extraídos
import AdminSidebar from '../components/admin/AdminSidebar.vue'
import AdminHeader from '../components/admin/AdminHeader.vue'
import NotificationSystem from '../components/admin/NotificationSystem.vue'
import PriceSimulator from '../components/admin/PriceSimulator.vue'
import ManualOrderForm from '../components/admin/ManualOrderForm.vue'
import ProductionCalendar from '../components/admin/ProductionCalendar.vue'
import TeamManager from '../components/admin/TeamManager.vue'
import EditMaterialModal from '../components/admin/EditMaterialModal.vue'
import NewMaterialModal from '../components/admin/NewMaterialModal.vue'
import NewPrinterModal from '../components/admin/NewPrinterModal.vue'
import EditPrinterModal from '../components/admin/EditPrinterModal.vue'
import ShippingModal from '../components/admin/ShippingModal.vue'
import PrinterAssignModal from '../components/admin/PrinterAssignModal.vue'
import OrderDetailModal from '../components/admin/OrderDetailModal.vue'
import WebManager from '../components/admin/WebManager.vue'
import SettingsPanel from '../components/admin/SettingsPanel.vue'
import { buildReportHtml, printHtml } from '../services/adminReports'
/**
 * Panel del taller. Este archivo solo arma la pantalla: carga los datos que el
 * usuario puede ver (permisos del equipo), elige la sección y conecta los modales.
 * La lógica vive en composables:
 *   - useAdminFeedback   → avisos, confirmaciones y alertas (provide a los módulos)
 *   - useAdminSettings   → cargar, guardar y publicar ajustes, cupones y logo
 *   - useOrderActions    → etapas, pago, envío, máquina, descargas y detalle de pedidos
 *   - usePrinters        → Maquinaria
 *   - useMaterials       → Inventario
 *   - useSimulator / useSimulationActions → simulador de precios, PDF y pedido
 *   - usePDF             → cotizaciones y etiquetas en PDF
 *   - services/adminReports → reportes imprimibles de Contabilidad
 * Cada sección es un componente en components/admin (carga asíncrona).
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
const route = useRoute()

// --- State ---
const orders = ref([])
const inventoryData = ref([])
const printers = ref([])
const analyticsData = ref(null)
const loading = ref(true)
const loadingAnalytics = ref(false)
const activeTab = ref('kanban')
const productionView = ref('board') // 'board' | 'calendar'

// Permisos del equipo: secciones que ve este usuario (el dueño, todas)
const sections = ref([])
// Un sync en curso al salir del panel no debe redirigir la página nueva
let unmounted = false
const can = (...ids) => ids.some(id => sections.value.includes(id))
const applyAuth = (user) => {
  sections.value = Array.isArray(user?.sections) ? user.sections : []
  // Sin ninguna sección no se muestra Producción (sus acciones darían 403)
  if (!can(activeTab.value)) activeTab.value = sections.value[0] || 'none'
}
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
const contacts = reactive({ customers: [], suppliers: [] })
// --- Avisos y confirmaciones (compartidos con los módulos por provide) ---
const { notification, confirmDialog, showNotify, askConfirm, showAlert } = useAdminFeedback()

// --- Ajustes: costos, empresa, sitio web, cupones y logo ---
const {
  settings, couponUses, savingSettings, logoUrl, uploadingLogo,
  fetchSettings, saveSettings, saveCoupons, handleLogoUpload, globalSeoOptimizer,
} = useAdminSettings({ showNotify, askConfirm })

// --- Composables ---
const { simulator, simulatedResult } = useSimulator({
  inventoryData, settings, showNotify
})

const { handleDownloadQuotePDF, handlePrintLabel} = usePDF({
  settings,
  inventoryData,
  showNotify
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

// Maquinaria: handlers y formularios (fetchPrinters se declara más abajo)
const {
  editingPrinter, newPrinter, handleEditPrinter, handleUpdatePrinterStatus, handleMaintenanceComplete,
  handleResetPrinter, handleDeletePrinter, savePrinterEdit, addPrinter,
} = usePrinters({ fetchPrinters: () => fetchPrinters(), showNotify, showAlert, askConfirm, modalState, submitting })

// Inventario: handlers y formularios (fetchInventory se declara más abajo)
const {
  editingMaterial, newMaterial, handleEditMaterial, handleUpdateStock, handleDeleteMaterial, updateMaterial, addMaterial,
} = useMaterials({ inventoryData, fetchInventory: () => fetchInventory(), showNotify, askConfirm, modalState, submitting })

// Simulador → PDF o pedido (fetchOrders se declara más abajo)
const { handleDownloadSimulationPDF, handleConvertSimulationToOrder } = useSimulationActions({
  simulator, simulatedResult, inventoryData, settings, submitting, modalState,
  fetchOrders: () => fetchOrders(), showNotify, handleDownloadQuotePDF,
})

// Pedidos: etapas, pago, envío, máquina, descargas (syncAll y fetchOrders se declaran más abajo)
const {
  orderToShip, trackingGuide, trackingCarrier, selectedOrderForPrinter, selectedOrderDetails, assigning,
  handleStatusUpdate, confirmShipping, handleTogglePaid, handleDeleteOrder, downloadFile,
  handleAssignment, confirmAssignment, cancelAssignment, openOrderDetails, refreshOrderDetails,
} = useOrderActions({
  orders, printers, modalState, showNotify, askConfirm,
  syncAll: (silent) => syncAll(silent), fetchOrders: () => fetchOrders(),
})

// --- Data Fetching ---

const syncAll = async (silent = false) => {
  // Si silent es un evento de Vue (objeto), lo tratamos como false para mostrar carga
  const isSilent = typeof silent === 'boolean' ? silent : false
  if (!isSilent) loading.value = true
  
  // Ejecución paralela pero resiliente. El sync silencioso (cada 5 min y tras cada
  // acción) no recarga Ajustes: pisaba lo que el admin estaba editando sin publicar.
  // Solo lo que este usuario puede ver (el servidor también lo exige)
  const tasks = [
    ...(can('kanban', 'history', 'accounting') ? [{ name: 'Pedidos', fn: fetchOrders }] : []),
    { name: 'Inventario', fn: fetchInventory },
    ...(can('machines', 'kanban') ? [{ name: 'Maquinaria', fn: fetchPrinters }] : []),
    ...(isSilent ? [] : [{ name: 'Ajustes', fn: fetchSettings }]),
    ...(can('accounting') ? [{ name: 'Analíticas', fn: fetchAnalytics }] : []),
    ...(can('contacts', 'kanban', 'purchases') ? [{ name: 'Contactos', fn: fetchContacts }] : []),
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
    // Cada fetch atrapa sus errores: la sesión expirada se detecta aquí
    const auth = await api.checkAuth(true)
    if (unmounted) return
    if (!auth.authenticated || auth.role !== 'admin') {
      showNotify('Sesión expirada. Por favor ingresa de nuevo.', 'warning')
      logout()
    } else {
      applyAuth(auth.user)
    }
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
  } catch {
    inventoryData.value = []
  }
}

const fetchPrinters = async () => {
  try {
    const res = await api.get('/admin/printers', true)
    printers.value = Array.isArray(res) ? res : (res?.data || [])
  } catch {
    printers.value = []
  }
}

// Simulador: sus valores por defecto siguen a Parámetros
watch(() => settings.value, (val) => {
  if (val.infra?.etiquetas) simulator.etiquetas = val.infra.etiquetas
  if (val.oper?.transporte) simulator.transporte_pct = val.oper.transporte
  if (val.oper?.marketing) simulator.marketing_pct = val.oper.marketing
  if (val.oper?.fallos) simulator.fallos_pct = val.oper.fallos
  if (val.oper?.ganancia) simulator.profit_pct = val.oper.ganancia
}, { deep: true })

const fetchAnalytics = async () => {
  loadingAnalytics.value = true
  try {
    analyticsData.value = await api.get('/admin/analytics', true)
  } finally {
    loadingAnalytics.value = false
  }
}

// Clientes: Contactos y Producción (pedido manual). Proveedores: Contactos y Compras.
const fetchContacts = async () => {
  try {
    const [cData, sData] = await Promise.all([
      can('contacts', 'kanban') ? api.get('/admin/contacts/customers', true) : Promise.resolve([]),
      can('contacts', 'purchases') ? api.get('/admin/contacts/suppliers', true) : Promise.resolve([]),
    ])
    contacts.customers = cData
    contacts.suppliers = sData
  } catch (err) {
    logger.error('Error fetching contacts:', err)
  }
}


// --- Contactos y exportación ---

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

// --- Computed & Logic ---

// Resumen de Producción: solo cifras que sirven para decidir el día
const kanbanStats = computed(() => {
  const by = (st) => orders.value.filter(o => o.status === st).length
  return {
    pending: by('pending'),
    printing: by('printing'),
    finishing: by('post-processing'),
    ready: by('completed'),
    unpaid: orders.value
      .filter(o => !o.is_paid && o.status !== 'cancelled')
      .reduce((sum, o) => sum + (Number(o.total_price) || 0), 0),
  }
})
const formatMoney = (n) => '$' + Math.round(Number(n) || 0).toLocaleString('es-CO')

// Motor de sincronización — hoisted para acceso desde onUnmounted
let syncInterval

// --- Lifecycle & Watchers ---

onMounted(async () => {
  document.body.style.overflow = 'hidden'
  updateTheme()
  // Primero la sesión: define qué secciones cargar
  const authStatus = await api.checkAuth(true)
  if (unmounted) return
  if (!authStatus.authenticated || authStatus.role !== 'admin') {
    router.push('/admin/login')
    return
  }
  applyAuth(authStatus.user)
  await syncAll()
  if (unmounted) return

  // Motor de Sincronización Proactiva: Cada 5 min intenta un silent sync si hay conexión
  syncInterval = setInterval(async () => {
    try {
      await syncAll(true)
    } catch (e) {
      // Fallo silencioso, no interrumpir al usuario
      logger.debug('Silent sync failed:', e)
    }
  }, 1000 * 60 * 5)
  

  // --- Auto-Open Order from URL (Admin Bridge) ---
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
})

onUnmounted(() => {
  unmounted = true
  document.body.style.overflow = ''
  if (typeof syncInterval !== 'undefined') {
    clearInterval(syncInterval)
  }
})

watch(activeTab, (tab) => {
  if (tab === 'accounting') fetchAnalytics()
  // Asegurar que cerramos modales al cambiar de pestaña para evitar bloqueos
  Object.keys(modalState).forEach(k => modalState[k] = false)
  selectedOrderDetails.value = null
})

const logout = async () => {
  try {
    await api.post('/logout')
  } catch (e) {
    // Silent — session may already be invalid
    logger.debug('Logout silent error:', e)
  }
  router.push('/admin/login')
}



const handleExportReport = async ({ type, start, end, filteredOrders }) => {
  loadingAnalytics.value = true
  try {
    // El libro de pedidos sale de los pedidos filtrados; solo el balance pide Contabilidad
    const reportData = type === 'metrics' ? await api.get(`/admin/analytics?start_date=${start}&end_date=${end}`, true) : null
    printHtml(buildReportHtml({ type, start, end, filteredOrders, reportData, settings: settings.value, storageUrl: api.storageUrl }))
  } catch (err) {
    logger.error('Error:', err)
    showNotify('Error: ' + err.message, 'error')
  } finally {
    loadingAnalytics.value = false
  }
}


const handlePurgeAll = () => {
  askConfirm(
    'Borrar todos los datos',
    'Se eliminarán para siempre todos los pedidos, materiales, inventario, clientes y máquinas. ¿Seguro que quieres borrar todo?',
    'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
    async () => {
      try {
        await api.post('/admin/purge-all', { confirm_purge: 'PURGE_ALL_CONFIRMED' });
        showNotify('Se borraron todos los datos.', 'success');
        orders.value = [];
        inventoryData.value = [];
        printers.value = [];
        contacts.customers = [];
        contacts.suppliers = [];
        syncAll(true);
      } catch (err) {
        showNotify('No se pudieron borrar los datos: ' + err.message, 'error');
      }
    }
  );
}
</script>

<template>
  <div class="min-h-screen bg-canvas text-ink flex overflow-hidden">
    <!-- Fondo del menú en móvil -->
    <div
      v-if="isSidebarOpen"
      class="fixed inset-0 bg-black/50 z-40 md:hidden"
      aria-hidden="true"
      @click="isSidebarOpen = false"
    ></div>

    <!-- N3XT PREMIUM SIDEBAR (extraído a AdminSidebar.vue) -->
    <AdminSidebar 
      :is-open="isSidebarOpen"
      :active-tab="activeTab === 'accounting' ? 'accounting' : activeTab"
      :pending-orders-count="orders.filter(o => o.status === 'pending').length"
      :sections="sections"
      @update:active-tab="(tab) => { activeTab = tab === 'analytics' ? 'accounting' : tab; isSidebarOpen = false }"
      @close="isSidebarOpen = false"
      @logout="logout"
    />

    <!-- MAIN CONTENT AREA -->
    <main id="main" class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden">

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
        <div class="flex-1 flex flex-col min-w-0 overflow-y-auto overflow-x-hidden px-4 py-6 md:px-8 md:py-8 pb-24 md:pb-10">
            <div class="w-full max-w-[1600px] mx-auto">
                <transition 
                    name="view-fade" 
                    mode="out-in"
                    enter-active-class="animate-slide-up"
                >
                    <div :key="activeTab">
        
        <div v-if="activeTab === 'none'" class="admin-card py-16 text-center">
          <h1 class="text-2xl font-extrabold">Aún no tienes secciones asignadas</h1>
          <p class="mt-2 text-muted">Pídele al dueño del taller que te dé acceso desde Equipo.</p>
        </div>

        <!-- Módulo: Producción (Kanban) -->
        <div v-if="activeTab === 'kanban'">
          <header class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-6 mb-8">
            <div>
              <h1 class="admin-title">Producción <span class="text-brand">del taller</span></h1>
              <p class="admin-lead">Arrastra cada pedido a su etapa. Al pasar a impresión eliges la máquina.</p>
            </div>
            <div class="flex flex-wrap gap-3">
              <button type="button" class="btn-outlined btn-lg" @click="modalState.simulator = true">Simulador de precios</button>
              <button type="button" class="btn-primary btn-lg" @click="modalState.manualOrder = true">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14m-7-7h14"/></svg>
                Nuevo pedido
              </button>
            </div>
          </header>

          <dl class="grid grid-cols-2 lg:grid-cols-5 gap-3 md:gap-4 mb-8">
            <div class="admin-card">
              <dt class="admin-label">Por revisar</dt>
              <dd class="mt-2 admin-kpi" :class="kanbanStats.pending ? 'text-warning' : ''">{{ kanbanStats.pending }}</dd>
            </div>
            <div class="admin-card">
              <dt class="admin-label">En impresión</dt>
              <dd class="mt-2 admin-kpi">{{ kanbanStats.printing }}</dd>
            </div>
            <div class="admin-card">
              <dt class="admin-label">En acabado</dt>
              <dd class="mt-2 admin-kpi">{{ kanbanStats.finishing }}</dd>
            </div>
            <div class="admin-card">
              <dt class="admin-label">Listos para entregar</dt>
              <dd class="mt-2 admin-kpi text-success">{{ kanbanStats.ready }}</dd>
            </div>
            <div class="admin-card col-span-2 lg:col-span-1">
              <dt class="admin-label">Por cobrar</dt>
              <dd class="mt-2 admin-kpi">{{ formatMoney(kanbanStats.unpaid) }}</dd>
            </div>
          </dl>

          <div class="mb-6 flex gap-2" role="group" aria-label="Vista de producción">
            <button type="button" class="chip" :aria-pressed="productionView === 'board'" @click="productionView = 'board'">Tablero</button>
            <button type="button" class="chip" :aria-pressed="productionView === 'calendar'" @click="productionView = 'calendar'">Calendario</button>
          </div>

          <ProductionCalendar
            v-if="productionView === 'calendar'"
            :printers="printers"
            :orders="orders"
            @view="openOrderDetails($event)"
          />

          <KanbanBoard 
            v-else
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
        <div v-if="activeTab === 'accounting'">
          <AccountingDashboard 
            :analytics="analyticsData"
            :loading="loadingAnalytics"
            @export="handleExportCSV"
            @toggle-paid="handleTogglePaid"
          />
        </div>

        <!-- Módulo: Inventario (Inventory Manager) -->
        <div v-if="activeTab === 'inventory'">
          <InventoryManager 
            :inventory="inventoryData" 
            @add-item="modalState.newMaterial = true"
            @edit-item="handleEditMaterial"
            @delete-item="handleDeleteMaterial"
            @update-stock="handleUpdateStock"
          />
        </div>

        <!-- Módulo: Registro de Compras (Purchase Log) -->
        <div v-if="activeTab === 'purchases'">
          <PurchaseLog 
            :inventory="inventoryData"
            :suppliers="contacts.suppliers"
            :settings="settings"
            @refresh-inventory="fetchInventory"
          />
        </div>

        <!-- Módulo: Historial de Pedidos -->
        <div v-if="activeTab === 'history'">
          <OrderHistory 
            :orders="orders"
            :loading-analytics="loadingAnalytics"
            :can-see-balance="can('accounting')"
            @view-details="openOrderDetails"
            @download-pdf="handleDownloadQuotePDF"
            @export-report="handleExportReport"
            @delete="handleDeleteOrder"
            @print-label="handlePrintLabel"
          />
        </div>

        <!-- Módulo: Códigos y Descuentos -->
        <div v-if="activeTab === 'discounts'">
          <DiscountManager
            :coupons="settings.coupons"
            :uses="couponUses"
            :welcome-pct="settings.welcome_coupon_pct"
            :saving="savingSettings"
            @save="saveCoupons"
          />
        </div>

        <!-- Módulo: Estado de la Granja (Machine Monitor) -->
        <div v-if="activeTab === 'machines'">
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
        <div v-if="activeTab === 'contacts'">
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

    <!-- Modal: Asignar impresora -->
    <PrinterAssignModal
      :show="modalState.printerStatus"
      :order="selectedOrderForPrinter"
      :printers="printers"
      :busy="assigning"
      @close="cancelAssignment"
      @confirm="confirmAssignment"
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
      :can-edit="can('kanban')"
      @close="selectedOrderDetails = null; modalState.orderDetails = false"
      @updated="refreshOrderDetails"
      @download="(idx) => downloadFile(selectedOrderDetails, idx)"
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

    <!-- Módulo: Equipo (solo el dueño) -->
    <TeamManager
      v-if="activeTab === 'team'"
      :show-notify="showNotify"
      :ask-confirm="askConfirm"
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

    <NotificationSystem
      :notification="notification"
      :confirm-dialog="confirmDialog"
      :is-dark="isDark"
      @close="confirmDialog.show = false; notification.show = false"
    />
  </div>
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
