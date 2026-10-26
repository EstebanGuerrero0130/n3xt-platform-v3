<script setup lang="ts">
import { localDateISO } from '../../utils/format'
import { ref, computed, onMounted, onUnmounted, watch, type PropType } from 'vue'
import { api } from '../../services/api'
import { inject } from 'vue'
import logger from '../../utils/logger'
import { writePrintDocument } from '../../services/pdfPrint'
import AdminModal from './AdminModal.vue'

const showNotify: any = inject('showNotify')

const props = defineProps({
  inventory: { type: Array as PropType<any[]>, required: true },
  suppliers: { type: Array as PropType<any[]>, default: () => [] },
  settings: { type: Object, default: () => ({}) }
})

const emit = defineEmits(['refresh-inventory'])

const purchases = ref<any[]>([])
const loading = ref(false)
const showNewPurchaseModal = ref(false)
const isNewMaterial = ref(false)
const _timers: any[] = []

// Filtros de fecha
const dateFilter = ref({
  start: localDateISO(new Date(new Date().getFullYear(), new Date().getMonth(), 1)),
  end: localDateISO()
})

const newPurchase = ref({
  item_name: '', category: 'maintenance', material_id: '',
  units: 1, unit_amount: 1000, total_cost: 0,
  supplier: '', notes: '', purchase_date: localDateISO(),
  is_new_item: false,
  material_data: { id: '', name: '', category: 'FDM', type: 'material', unit: 'g', cost_per_kg: 0, color: '#000000' }
})

// Auto-generador de ID para nuevo material
watch([() => newPurchase.value.material_data.name, () => newPurchase.value.material_data.category], ([name, cat]) => {
  if (isNewMaterial.value && name && name.length >= 2) {
    const prefix = cat === 'FDM' ? 'FDM' : cat === 'SLA' ? 'SLA' : 'EXT'
    const cleanName = name.toUpperCase().replace(/\s+/g, '').substring(0, 3)
    const random = Math.floor(Math.random() * 900) + 100
    newPurchase.value.material_data.id = `${prefix}-${cleanName}-${random}`
  }
})

// Limpieza de estado al cambiar categoría
watch(() => newPurchase.value.category, (newCat) => {
  if (newCat !== 'inventory_restock') {
    isNewMaterial.value = false
    newPurchase.value.material_id = ''
  }
})

const handleSelectSupplier = (e: any) => {
  const name = e.target.value
  if (name) {
    newPurchase.value.supplier = name
  }
}

const fetchPurchases = async () => {
  loading.value = true
  try {
    const data = await api.get('/admin/purchases')
    purchases.value = data
  } catch (err: any) {
    logger.error('Error fetching purchases:', err)
  } finally {
    loading.value = false
  }
}

const filteredPurchases = computed(() => {
  if (!dateFilter.value.start || !dateFilter.value.end) return purchases.value
  
  return purchases.value.filter((p: any) => {
    // Tomamos solo los primeros 10 caracteres (YYYY-MM-DD) para la comparación
    const d = p.purchase_date.substring(0, 10)
    return d >= dateFilter.value.start && d <= dateFilter.value.end
  })
})

const handleAddPurchase = async () => {
  // Validaciones
  if (isNewMaterial.value) {
    if (!newPurchase.value.material_data.name) {
      showNotify('Indica el nombre del material', 'warning')
      return
    }
  } else {
    if (newPurchase.value.category === 'inventory_restock' && !newPurchase.value.material_id) {
      showNotify('Selecciona un material existente', 'warning')
      return
    }
    if (newPurchase.value.category !== 'inventory_restock' && !newPurchase.value.item_name) {
      showNotify('Describe la compra realizada', 'warning')
      return
    }
  }

  if (newPurchase.value.total_cost <= 0) {
    showNotify('El costo debe ser mayor a 0', 'warning')
    return
  }

  try {
    // Preparar item_name si es restock
    if (!isNewMaterial.value && newPurchase.value.category === 'inventory_restock') {
        const mat = props.inventory.find((m: any) => m.id === newPurchase.value.material_id)
        if (mat) newPurchase.value.item_name = mat.name
    } else if (isNewMaterial.value) {
        // Cálculo de costo automático para el nuevo material
        const totalAmount = newPurchase.value.units * newPurchase.value.unit_amount
        if (totalAmount > 0) {
            // Si es g/ml, calculamos el costo por Kg/L (multiplicando por 1000)
            const isLiquidOrWeight = ['g', 'ml'].includes(newPurchase.value.material_data.unit)
            const unitCost = newPurchase.value.total_cost / totalAmount
            newPurchase.value.material_data.cost_per_kg = isLiquidOrWeight ? (unitCost * 1000) : unitCost
        }
        
        newPurchase.value.item_name = newPurchase.value.material_data.name
        newPurchase.value.is_new_item = true
    }

    await api.post('/admin/purchases', newPurchase.value)
    
    showNewPurchaseModal.value = false
    await fetchPurchases()
    emit('refresh-inventory')
    
    // Reset form
    isNewMaterial.value = false
    newPurchase.value = {
      item_name: '', category: 'maintenance', material_id: '',
      units: 1, unit_amount: 1000, total_cost: 0,
      supplier: '', notes: '', purchase_date: localDateISO(),
      is_new_item: false,
      material_data: { id: '', name: '', category: 'FDM', type: 'material', unit: 'g', cost_per_kg: 0, color: '#000000' }
    }
    showNotify('Compra registrada correctamente', 'success')
  } catch (err: any) {
    logger.error('Error al registrar compra:', err)
    const errorMsg = err.response?.data?.message || err.message || 'Error desconocido'
    showNotify('Error al registrar: ' + errorMsg, 'error')
  }
}

const confirmingDeleteId = ref<any>(null)

const handleDeletePurchase = async (id: any) => {
  if (!id) {
    showNotify('ID de registro no valido', 'warning')
    return
  }

  if (confirmingDeleteId.value !== id) {
    confirmingDeleteId.value = id
    // Auto-reset after 3 seconds if not confirmed
    const timer = setTimeout(() => { confirmingDeleteId.value = null }, 3000)
    _timers.push(timer)
    return
  }    // Eliminando compra
  try {
    await api.delete(`/admin/purchases/${id}`)
    await fetchPurchases()
    emit('refresh-inventory')
    showNotify('Registro eliminado correctamente', 'success')
  } catch (err: any) {
    logger.error('Error al eliminar:', err)
    showNotify('Error al eliminar: ' + (err.response?.data?.message || err.message), 'error')
  } finally {
    confirmingDeleteId.value = null
  }
}

  onUnmounted(() => {
    _timers.forEach(t => { clearTimeout(t); clearInterval(t) })
  })

  const generatePDFReport = () => {
  const total = filteredPurchases.value.reduce((acc: any, p: any) => acc + parseFloat(p.total_cost), 0)
  const totalInv = filteredPurchases.value.filter((p: any) => p.category === 'inventory_restock').reduce((acc: any, p: any) => acc + parseFloat(p.total_cost), 0)
  
  let rowsHtml = '';
  filteredPurchases.value.forEach((p: any) => {
    rowsHtml += `
      <tr>
        <td class="date-cell">${new Date(p.purchase_date).toLocaleDateString()}</td>
        <td>
          <div class="item-name">${p.item_name}</div>
          <div class="supplier-info">PROVEEDOR: ${p.supplier || 'SIN ESPECIFICAR'}</div>
        </td>
        <td><span class="category-badge ${p.category === 'inventory_restock' ? 'inv' : 'exp'}">${p.category === 'inventory_restock' ? 'STOCK' : 'GASTO'}</span></td>
        <td class="qty-cell">${parseFloat(p.units).toLocaleString()} x ${parseFloat(p.unit_amount).toLocaleString()}${p.category === 'inventory_restock' ? 'g/ml' : ' ud'}</td>
        <td class="price-cell">$ ${parseFloat(p.total_cost).toLocaleString()}</td>
      </tr>
    `;
  });

  const content = `
    <html>
      <head>
        <title>N3XT 3D - Registro de Compras</title>
        <style>
          @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;900&display=swap');
          @page { size: A4; margin: 15mm; }
          body { 
            font-family: 'Outfit', sans-serif; 
            padding: 30px; 
            margin: 0;
            color: #0f172a; 
            background: #fff; 
            line-height: 1.4; 
            font-size: 11px; 
          }
          
          .header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            border-bottom: 3px solid #0f172a; 
            padding-bottom: 25px; 
            margin-bottom: 35px; 
          }
          .logo-container { display: flex; align-items: center; gap: 18px; }
          .logo-text { font-size: 26px; font-weight: 900; letter-spacing: -1.5px; text-transform: uppercase; color: #0f172a; }
          
          .report-meta { text-align: right; }
          .report-label { font-size: 9px; font-weight: 900; color: #94a3b8; text-transform: uppercase; letter-spacing: 4px; margin-bottom: 6px; }
          .report-range { font-size: 14px; font-weight: 900; color: #0f172a; background: #f1f5f9; padding: 6px 12px; border-radius: 8px; }

          .summary-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 35px; }
          .summary-card { padding: 25px; border-radius: 24px; background: #f8fafc; border: 1px solid #e2e8f0; position: relative; overflow: hidden; }
          .summary-card.dark { background: #0f172a; color: white; border: none; }
          .card-label { font-size: 8px; font-weight: 900; text-transform: uppercase; letter-spacing: 2px; color: #64748b; margin-bottom: 8px; display: block; }
          .summary-card.dark .card-label { color: #94a3b8; }
          .card-val { font-size: 28px; font-weight: 900; letter-spacing: -1px; }

          table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 10px; }
          th { 
            text-align: left; 
            background: #0f172a; 
            padding: 14px 12px; 
            font-size: 8px; 
            font-weight: 900; 
            text-transform: uppercase; 
            color: #fff; 
            letter-spacing: 2px; 
          }
          th:first-child { border-radius: 12px 0 0 0; }
          th:last-child { border-radius: 0 12px 0 0; text-align: right; }
          
          td { padding: 14px 12px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
          
          .date-cell { font-weight: 700; color: #64748b; font-size: 10px; }
          .item-name { font-size: 11px; font-weight: 900; color: #0f172a; text-transform: uppercase; letter-spacing: -0.2px; }
          .supplier-info { font-size: 8px; font-weight: 700; color: #94a3b8; margin-top: 3px; text-transform: uppercase; }
          
          .category-badge { font-size: 7px; font-weight: 900; padding: 4px 10px; border-radius: 6px; text-transform: uppercase; border: 1px solid transparent; }
          .category-badge.inv { background: #ecfdf5; color: #059669; border-color: #10b98133; }
          .category-badge.exp { background: #fff1f2; color: #be123c; border-color: #f43f5e33; }
          
          .qty-cell { font-weight: 700; color: #64748b; font-size: 10px; }
          .price-cell { font-size: 13px; font-weight: 900; color: #0f172a; text-align: right; }

          .footer { 
            margin-top: 50px; 
            padding-top: 30px;
            border-top: 1px solid #f1f5f9;
            display: flex; 
            justify-content: space-between; 
            align-items: flex-end; 
            font-size: 8px; 
            font-weight: 700; 
            color: #94a3b8; 
          }
          .signature-box { width: 220px; border-top: 2px solid #0f172a; padding-top: 10px; text-align: center; color: #0f172a; margin-top: 40px; font-weight: 900; text-transform: uppercase; letter-spacing: 1px; }

          @media print {
            body { padding: 0; }
            .summary-card { -webkit-print-color-adjust: exact; }
            th { -webkit-print-color-adjust: exact; }
          }
        </style>
      </head>
      <body>

        <div class="header">
          <div class="logo-container">
            <div class="logo-text">
               ${props.settings.company?.name || 'N3XT 3D'}<br>
               <span style="font-size: 10px; font-weight: 600; color: #64748b; letter-spacing: 0;">
                 NIT: ${props.settings.company?.nit || 'N/A'} | 
                 DIR: ${props.settings.company?.address || 'N/A'}<br>
                 TEL: ${props.settings.company?.phone || 'N/A'} | 
                 EMAIL: ${props.settings.company?.email || 'N/A'}
               </span>
            </div>
          </div>
          <div class="report-meta">
            <div class="report-label">Registro de Compras y Suministros</div>
            <div class="report-range">${new Date(dateFilter.value.start).toLocaleDateString() || 'INICIO'} — ${new Date(dateFilter.value.end).toLocaleDateString() || 'HOY'}</div>
          </div>
        </div>

        <div class="summary-grid">
          <div class="summary-card dark">
            <span class="card-label">Total Inversión Periodo</span>
            <div class="card-val">$ ${total.toLocaleString()}</div>
          </div>
          <div class="summary-card">
            <span class="card-label">Restock de Inventario</span>
            <div class="card-val" style="color: #10b981;">$ ${totalInv.toLocaleString()}</div>
          </div>
        </div>

        <table>
          <thead>
            <tr>
              <th style="width: 90px;">Fecha</th>
              <th>Descripción del Recurso</th>
              <th style="width: 80px;">Clase</th>
              <th style="width: 140px;">Carga / Detalle</th>
              <th style="text-align:right; width: 110px;">Costo Total</th>
            </tr>
          </thead>
          <tbody>
            ${rowsHtml}
          </tbody>
        </table>

        <div class="footer">
          <div>
            <div style="text-transform: uppercase; margin-bottom: 5px; font-weight: 900; color: #64748b;">Documento de Auditoría Interna</div>
            <div>Fecha Emisión: ${new Date().toLocaleString()} | ID: ${Math.random().toString(36).substr(2, 6).toUpperCase()}</div>
            <div class="signature-box">Firma Responsable de Finanzas</div>
          </div>
          <div style="text-align: right;">
            <p style="font-weight: 900; color: #0f172a; margin-bottom: 4px;">N3XT CORE V3.0 SYSTEM</p>
            <p>Industrial Additive Manufacturing Ledger</p>
            <p style="margin-top: 10px; color: #cbd5e1;">© 2026 N3XT 3D TECHNOLOGY</p>
          </div>
        </div>
      </body>
    </html>
  `;

  // Técnica de Iframe para Android (window.open suele fallar en Capacitor)
  const iframe = document.createElement('iframe');
  iframe.style.display = 'none';
  document.body.appendChild(iframe);
  
  const doc = iframe.contentWindow?.document;
  if (!doc) { document.body.removeChild(iframe); return; }
  writePrintDocument(doc, content);

  const timer1 = setTimeout(() => {
    const imgs = iframe.contentWindow?.document?.images || [];
    let loaded = 0;
    const print = () => { 
        iframe.contentWindow?.print(); 
        const t = setTimeout(() => document.body.removeChild(iframe), 1000);
        _timers.push(t)
    };
    
    if (imgs.length === 0) { _timers.push(setTimeout(print, 250)); } 
    else {
        let printed = false;
        const onload = () => { if (++loaded === imgs.length && !printed) { printed = true; _timers.push(setTimeout(print, 250)); } };
        for (let i = 0; i < imgs.length; i++) {
            if (imgs[i].complete) onload();
            else { imgs[i].onload = onload; imgs[i].onerror = onload; }
        }
        _timers.push(setTimeout(() => { if (!printed) { printed = true; print(); } }, 3000));
    }
  }, 50);
  _timers.push(timer1)
}

const CATEGORIES: Record<string, { label: string; cls: string }> = {
  inventory_restock: { label: 'Stock', cls: 'bg-success/10 text-success' },
  maintenance: { label: 'Mantenimiento', cls: 'bg-warning/10 text-warning' },
  tools: { label: 'Herramientas', cls: 'bg-[#3b82f6]/15 text-[#2563eb] dark:text-[#93c5fd]' },
  office: { label: 'Oficina', cls: 'bg-surface-2 text-ink' },
}
const categoryOf = (c: string) => CATEGORIES[c] ?? { label: c || 'Otro', cls: 'bg-surface-2 text-ink' }
const money = (n: any) => '$' + Math.round(Number(n) || 0).toLocaleString('es-CO')
const MATERIAL_TYPES = [
  { type: 'material', label: 'Material', category: 'FDM', unit: 'g' },
  { type: 'product', label: 'Producto', category: 'PROD', unit: 'unid' },
  { type: 'utility', label: 'Insumo', category: 'UTIL', unit: 'unid' },
]
const selectedUnit = computed(() => isNewMaterial.value
  ? newPurchase.value.material_data.unit
  : (props.inventory.find((m: any) => m.id === newPurchase.value.material_id)?.unit || 'ud'))
const totalAmount = computed(() => (Number(newPurchase.value.units) || 0) * (Number(newPurchase.value.unit_amount) || 0))
const unitCost = computed(() => totalAmount.value > 0 ? Number(newPurchase.value.total_cost) / totalAmount.value : 0)

const stats = computed(() => {
  return {
    total: filteredPurchases.value.reduce((acc: any, p: any) => acc + parseFloat(p.total_cost), 0),
    inventory: filteredPurchases.value.filter((p: any) => p.category === 'inventory_restock').reduce((acc: any, p: any) => acc + parseFloat(p.total_cost), 0),
    maintenance: filteredPurchases.value.filter((p: any) => p.category === 'maintenance' || p.category === 'tools').reduce((acc: any, p: any) => acc + parseFloat(p.total_cost), 0),
    count: filteredPurchases.value.length
  }
})

onMounted(() => {
  fetchPurchases()
})
</script>

<template>
  <div class="space-y-8">
    <header class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-6">
      <div>
        <h1 class="admin-title">Compras <span class="text-brand">y gastos</span></h1>
        <p class="admin-lead">Material, repuestos y gastos del taller. Una compra de stock suma existencias al inventario.</p>
      </div>
      <div class="flex flex-wrap gap-3">
        <button type="button" class="btn-outlined btn-lg" :disabled="!filteredPurchases.length" @click="generatePDFReport">Reporte PDF</button>
        <button type="button" class="btn-primary btn-lg" @click="showNewPurchaseModal = true">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14m-7-7h14"/></svg>
          Registrar compra
        </button>
      </div>
    </header>

    <div class="admin-card grid gap-4 sm:grid-cols-2 lg:max-w-xl">
      <div>
        <label for="purchases-from" class="admin-label mb-2">Desde</label>
        <input id="purchases-from" v-model="dateFilter.start" type="date" class="field">
      </div>
      <div>
        <label for="purchases-to" class="admin-label mb-2">Hasta</label>
        <input id="purchases-to" v-model="dateFilter.end" type="date" class="field">
      </div>
    </div>

    <dl class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4">
      <div class="admin-card"><dt class="admin-label">Gasto del periodo</dt><dd class="mt-2 admin-kpi">{{ money(stats.total) }}</dd></div>
      <div class="admin-card"><dt class="admin-label">En stock</dt><dd class="mt-2 admin-kpi">{{ money(stats.inventory) }}</dd></div>
      <div class="admin-card"><dt class="admin-label">Mantenimiento y herramientas</dt><dd class="mt-2 admin-kpi">{{ money(stats.maintenance) }}</dd></div>
      <div class="admin-card"><dt class="admin-label">Registros</dt><dd class="mt-2 admin-kpi">{{ stats.count }}</dd></div>
    </dl>

    <div v-if="loading" class="admin-card space-y-3" aria-hidden="true">
      <div v-for="i in 4" :key="i" class="h-12 rounded-[var(--radius-control)] bg-surface-2 animate-pulse"></div>
    </div>

    <section v-else class="admin-card" aria-label="Compras">
      <p v-if="!filteredPurchases.length" class="py-12 text-center text-muted">No hay compras registradas en estas fechas.</p>
      <div v-else class="overflow-x-auto -mx-1 px-1">
        <table class="admin-table min-w-[46rem]">
          <thead><tr><th>Fecha</th><th>Compra</th><th>Tipo</th><th>Cantidad</th><th>Total</th><th class="sr-only">Acciones</th></tr></thead>
          <tbody>
            <tr v-for="p in filteredPurchases" :key="p.id">
              <td class="tabular-nums whitespace-nowrap">{{ new Date(p.purchase_date.substring(0, 10) + 'T12:00:00').toLocaleDateString('es-CO', { day: 'numeric', month: 'short', year: 'numeric' }) }}</td>
              <td>
                <span class="block font-medium truncate max-w-[18rem]">{{ p.item_name }}</span>
                <span class="block text-sm text-muted truncate max-w-[18rem]">{{ p.supplier || 'Sin proveedor' }}</span>
              </td>
              <td><span :class="['badge', categoryOf(p.category).cls]">{{ categoryOf(p.category).label }}</span></td>
              <td class="tabular-nums whitespace-nowrap text-sm">{{ parseFloat(p.units).toLocaleString('es-CO') }} × {{ parseFloat(p.unit_amount).toLocaleString('es-CO') }} {{ p.material ? p.material.unit : 'ud' }}</td>
              <td class="tabular-nums font-semibold whitespace-nowrap">{{ money(p.total_cost) }}</td>
              <td class="text-right">
                <button
                  type="button"
                  :class="['btn-sm rounded-full px-3 font-semibold transition-colors', confirmingDeleteId === p.id ? 'bg-danger text-white' : 'text-muted hover:text-danger hover:bg-danger/10']"
                  :aria-label="confirmingDeleteId === p.id ? `Confirmar: eliminar ${p.item_name}` : `Eliminar ${p.item_name}`"
                  @click="handleDeletePurchase(p.id)"
                >{{ confirmingDeleteId === p.id ? '¿Eliminar?' : 'Eliminar' }}</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Registrar compra -->
    <AdminModal :show="showNewPurchaseModal" title="Registrar compra" description="Si es material del taller, súmalo al inventario como stock." size="lg" @close="showNewPurchaseModal = false">
      <form id="purchase-form" class="grid grid-cols-1 gap-6 md:grid-cols-2" novalidate @submit.prevent="handleAddPurchase">
        <div class="space-y-5">
          <div>
            <label for="purchase-category" class="admin-label mb-2">Tipo de compra</label>
            <select id="purchase-category" v-model="newPurchase.category" class="field">
              <option value="inventory_restock">Stock (material o insumo)</option>
              <option value="maintenance">Mantenimiento y repuestos</option>
              <option value="tools">Herramientas y maquinaria</option>
              <option value="office">Oficina y administrativo</option>
            </select>
          </div>

          <template v-if="newPurchase.category === 'inventory_restock'">
            <div class="flex flex-wrap gap-2" role="group" aria-label="Ítem del inventario">
              <button type="button" class="chip !min-h-9 !px-3 text-sm" :aria-pressed="!isNewMaterial" @click="isNewMaterial = false">Ítem existente</button>
              <button type="button" class="chip !min-h-9 !px-3 text-sm" :aria-pressed="isNewMaterial" @click="isNewMaterial = true">Ítem nuevo</button>
            </div>

            <div v-if="!isNewMaterial">
              <label for="purchase-material" class="admin-label mb-2">Ítem</label>
              <select id="purchase-material" v-model="newPurchase.material_id" class="field">
                <option value="">Elige del inventario…</option>
                <option v-for="m in inventory" :key="m.id" :value="m.id">{{ m.name }} ({{ m.category }})</option>
              </select>
            </div>

            <template v-else>
              <fieldset>
                <legend class="admin-label mb-2">Clase de ítem</legend>
                <div class="flex flex-wrap gap-2">
                  <button
                    v-for="t in MATERIAL_TYPES" :key="t.type" type="button" class="chip !min-h-9 !px-3 text-sm"
                    :aria-pressed="newPurchase.material_data.type === t.type"
                    @click="newPurchase.material_data.type = t.type; newPurchase.material_data.category = t.category; newPurchase.material_data.unit = t.unit"
                  >{{ t.label }}</button>
                </div>
              </fieldset>
              <div class="grid grid-cols-2 gap-3">
                <div v-if="newPurchase.material_data.type === 'material'">
                  <label for="purchase-tech" class="admin-label mb-2">Tecnología</label>
                  <select id="purchase-tech" v-model="newPurchase.material_data.category" class="field" @change="newPurchase.material_data.unit = newPurchase.material_data.category === 'SLA' ? 'ml' : 'g'">
                    <option value="FDM">Filamento (FDM)</option>
                    <option value="SLA">Resina (SLA)</option>
                  </select>
                </div>
                <div>
                  <label for="purchase-unit" class="admin-label mb-2">Unidad</label>
                  <select id="purchase-unit" v-model="newPurchase.material_data.unit" class="field">
                    <option value="g">Gramos (g)</option>
                    <option value="ml">Mililitros (ml)</option>
                    <option value="unid">Unidades</option>
                  </select>
                </div>
              </div>
              <div>
                <label for="purchase-name" class="admin-label mb-2">Nombre</label>
                <input id="purchase-name" v-model="newPurchase.material_data.name" type="text" placeholder="Ej. PLA Pro Negro" class="field">
              </div>
              <div class="flex items-center gap-3">
                <label for="purchase-color" class="admin-label">Color</label>
                <input id="purchase-color" v-model="newPurchase.material_data.color" type="color" class="h-10 w-14 rounded-[var(--radius-control)] border border-line bg-canvas p-1">
                <span class="ml-auto text-sm text-muted">Código: <span class="font-mono text-ink">{{ newPurchase.material_data.id || '—' }}</span></span>
              </div>
            </template>
          </template>

          <div v-else>
            <label for="purchase-desc" class="admin-label mb-2">Qué compraste</label>
            <input id="purchase-desc" v-model="newPurchase.item_name" type="text" placeholder="Ej. Correa GT2 6 mm" class="field">
          </div>

          <div>
            <label for="purchase-supplier" class="admin-label mb-2">Proveedor</label>
            <select v-if="suppliers.length" class="field mb-2" aria-label="Elegir un proveedor guardado" @change="handleSelectSupplier">
              <option value="">Proveedor guardado…</option>
              <option v-for="sp in suppliers" :key="sp.id" :value="sp.name">{{ sp.name }}</option>
            </select>
            <input id="purchase-supplier" v-model="newPurchase.supplier" type="text" placeholder="Nombre del proveedor" class="field">
          </div>
        </div>

        <div class="space-y-5">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label for="purchase-units" class="admin-label mb-2">Unidades</label>
              <input id="purchase-units" v-model.number="newPurchase.units" type="number" min="1" class="field tabular-nums">
            </div>
            <div>
              <label for="purchase-amount" class="admin-label mb-2">Contenido por unidad ({{ selectedUnit }})</label>
              <input id="purchase-amount" v-model.number="newPurchase.unit_amount" type="number" min="0" class="field tabular-nums">
            </div>
          </div>
          <p class="text-sm text-muted">Total que entra al stock: <span class="font-semibold text-ink tabular-nums">{{ totalAmount.toLocaleString('es-CO') }} {{ selectedUnit }}</span></p>
          <div>
            <label for="purchase-cost" class="admin-label mb-2">Valor pagado ($)</label>
            <input id="purchase-cost" v-model.number="newPurchase.total_cost" type="number" min="0" class="field tabular-nums">
          </div>
          <div v-if="unitCost > 0" class="admin-card !p-4 bg-surface">
            <p class="text-sm text-muted">Costo por {{ selectedUnit }}</p>
            <p class="text-lg font-bold tabular-nums">${{ unitCost.toLocaleString('es-CO', { maximumFractionDigits: 2 }) }}</p>
            <p v-if="isNewMaterial && (newPurchase.material_data.unit === 'g' || newPurchase.material_data.unit === 'ml')" class="mt-1 text-sm text-muted">Equivale a {{ money(unitCost * 1000) }} por {{ newPurchase.material_data.unit === 'g' ? 'kg' : 'litro' }}.</p>
          </div>
          <div>
            <label for="purchase-date" class="admin-label mb-2">Fecha</label>
            <input id="purchase-date" v-model="newPurchase.purchase_date" type="date" class="field">
          </div>
        </div>
      </form>
      <template #footer>
        <button type="button" class="btn-outlined" @click="showNewPurchaseModal = false">Cancelar</button>
        <button type="submit" form="purchase-form" class="btn-primary">Registrar compra</button>
      </template>
    </AdminModal>
  </div>
</template>
