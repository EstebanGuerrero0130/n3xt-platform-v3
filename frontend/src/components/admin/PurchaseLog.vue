<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { api } from '../../services/api'
import { inject } from 'vue'

const showNotify = inject('showNotify')

const props = defineProps({
  inventory: { type: Array, required: true },
  suppliers: { type: Array, default: () => [] },
  settings: { type: Object, default: () => ({}) }
})

const emit = defineEmits(['refresh-inventory'])

const purchases = ref([])
const loading = ref(false)
const showNewPurchaseModal = ref(false)
const isNewMaterial = ref(false)

// Filtros de fecha
const dateFilter = ref({
  start: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().substr(0, 10),
  end: new Date().toISOString().substr(0, 10)
})

const newPurchase = ref({
  item_name: '', category: 'maintenance', material_id: '',
  units: 1, unit_amount: 1000, total_cost: 0,
  supplier: '', notes: '', purchase_date: new Date().toISOString().substr(0, 10),
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

const handleSelectSupplier = (e) => {
  const name = e.target.value
  if (name) {
    newPurchase.value.supplier = name
  }
}

const fetchPurchases = async () => {
  loading.value = true
  try {
    const data = await api.get('/admin/purchases', true)
    purchases.value = data
  } catch (err) {
    console.error('Error fetching purchases:', err)
  } finally {
    loading.value = false
  }
}

const filteredPurchases = computed(() => {
  if (!dateFilter.value.start || !dateFilter.value.end) return purchases.value
  
  return purchases.value.filter(p => {
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
        const mat = props.inventory.find(m => m.id === newPurchase.value.material_id)
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

    const response = await api.post('/admin/purchases', newPurchase.value, true)
    
    showNewPurchaseModal.value = false
    await fetchPurchases()
    emit('refresh-inventory')
    
    // Reset form
    isNewMaterial.value = false
    newPurchase.value = {
      item_name: '', category: 'maintenance', material_id: '',
      units: 1, unit_amount: 1, total_cost: 0,
      supplier: '', notes: '', purchase_date: new Date().toISOString().substr(0, 10),
      is_new_item: false,
      material_data: { id: '', name: '', category: 'FDM', type: 'material', unit: 'g', cost_per_kg: 0, color: '#000000' }
    }
    showNotify('Compra registrada correctamente', 'success')
  } catch (err) {
    console.error('Error al registrar compra:', err)
    const errorMsg = err.response?.data?.message || err.message || 'Error desconocido'
    showNotify('Error al registrar: ' + errorMsg, 'error')
  }
}

const confirmingDeleteId = ref(null)

const handleDeletePurchase = async (id) => {
  if (!id) {
    showNotify('ID de registro no valido', 'warning')
    return
  }

  if (confirmingDeleteId.value !== id) {
    confirmingDeleteId.value = id
    // Auto-reset after 3 seconds if not confirmed
    setTimeout(() => { confirmingDeleteId.value = null }, 3000)
    return
  }

  console.log('Eliminando compra ID:', id)
  try {
    const res = await api.delete(`/admin/purchases/${id}`, true)
    console.log('Respuesta eliminación:', res)
    await fetchPurchases()
    emit('refresh-inventory')
    showNotify('Registro eliminado correctamente', 'success')
  } catch (err) {
    console.error('Error al eliminar:', err)
    showNotify('Error al eliminar: ' + (err.response?.data?.message || err.message), 'error')
  } finally {
    confirmingDeleteId.value = null
  }
}

const generatePDFReport = () => {
  const total = filteredPurchases.value.reduce((acc, p) => acc + parseFloat(p.total_cost), 0)
  const totalInv = filteredPurchases.value.filter(p => p.category === 'inventory_restock').reduce((acc, p) => acc + parseFloat(p.total_cost), 0)
  
  let rowsHtml = '';
  filteredPurchases.value.forEach(p => {
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

          .summary-grid { display: grid; grid-template-cols: 1fr 1fr; gap: 20px; margin-bottom: 35px; }
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
            ${props.settings.company_logo ? `<img src="${api.storageUrl}/${props.settings.company_logo}" style="height: 60px; width: auto; object-fit: contain;" onerror="this.src='/logo.png'">` : `<img src="/logo.png" style="height: 60px; width: auto; object-fit: contain;">`}
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
  
  const doc = iframe.contentWindow.document;
  doc.open();
  doc.write(content);
  doc.close();

  setTimeout(() => {
    iframe.contentWindow.print();
    setTimeout(() => {
        document.body.removeChild(iframe);
    }, 1000);
  }, 500);
}

const stats = computed(() => {
  return {
    total: filteredPurchases.value.reduce((acc, p) => acc + parseFloat(p.total_cost), 0),
    inventory: filteredPurchases.value.filter(p => p.category === 'inventory_restock').reduce((acc, p) => acc + parseFloat(p.total_cost), 0),
    maintenance: filteredPurchases.value.filter(p => p.category === 'maintenance' || p.category === 'tools').reduce((acc, p) => acc + parseFloat(p.total_cost), 0),
    count: filteredPurchases.value.length
  }
})

onMounted(() => {
  fetchPurchases()
})
</script>

<template>
  <div class="animate-in fade-in slide-in-from-bottom-6 duration-700 p-4">
    <!-- Header -->
    <div class="flex flex-col xl:flex-row justify-between items-start xl:items-end gap-6 mb-8 px-4">
      <div>
        <h2 class="text-3xl md:text-5xl font-black text-gray-900 dark:text-white tracking-tighter uppercase mb-2">Registro de Compras</h2>
        <div class="flex items-center gap-3">
          <div class="w-2 h-2 rounded-full bg-primary animate-pulse"></div>
          <p class="text-gray-400 dark:text-gray-500 font-bold uppercase tracking-widest text-[9px] md:text-[10px]">Trazabilidad financiera y operativa</p>
        </div>
      </div>
      
      <!-- Filtros y Botones -->
      <div class="flex flex-col sm:flex-row flex-wrap items-center gap-4 bg-white dark:bg-gray-900 p-3 md:p-4 rounded-2xl md:rounded-[2rem] border border-gray-100 dark:border-white/5 shadow-sm w-full xl:w-auto">
        <div class="flex items-center gap-2 px-4 border-b sm:border-b-0 sm:border-r border-gray-100 dark:border-white/5 w-full sm:w-auto pb-2 sm:pb-0">
            <label class="text-[9px] font-black text-gray-400 dark:text-gray-600 uppercase">Desde</label>
            <input type="date" v-model="dateFilter.start" class="bg-transparent border-none text-xs font-bold text-gray-900 dark:text-white outline-none flex-1">
        </div>
        <div class="flex items-center gap-2 px-4 border-b sm:border-b-0 sm:border-r border-gray-100 dark:border-white/5 w-full sm:w-auto pb-2 sm:pb-0">
            <label class="text-[9px] font-black text-gray-400 dark:text-gray-600 uppercase">Hasta</label>
            <input type="date" v-model="dateFilter.end" class="bg-transparent border-none text-xs font-bold text-gray-900 dark:text-white outline-none flex-1">
        </div>
        <div class="flex gap-2 w-full sm:w-auto">
            <button @click="generatePDFReport" class="flex-1 sm:flex-none p-4 bg-gray-50 dark:bg-white/5 text-gray-600 dark:text-gray-400 rounded-xl md:rounded-2xl hover:bg-gray-100 dark:hover:bg-white/10 transition-all flex items-center justify-center gap-2">
                <span class="text-[10px] font-black uppercase tracking-widest">PDF</span>
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </button>
            <button @click="showNewPurchaseModal = true" class="flex-[2] sm:flex-none px-6 md:px-8 py-4 bg-gray-900 dark:bg-primary text-white rounded-xl md:rounded-2xl shadow-xl shadow-black/10 hover:bg-primary dark:hover:bg-white dark:hover:text-primary transition-all active:scale-95 flex items-center justify-center gap-3 font-black text-[10px] uppercase tracking-widest">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg> Nueva Compra
            </button>
        </div>
      </div>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-8 md:mb-12 px-4">
      <div class="bg-white dark:bg-gray-900 p-6 md:p-8 rounded-2xl md:rounded-[2.5rem] border border-gray-100 dark:border-white/5 shadow-sm dark:shadow-none flex flex-col justify-between group hover:shadow-xl transition-all">
        <p class="text-[9px] md:text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-3 text-center">Gasto Periodo</p>
        <h3 class="text-2xl md:text-4xl font-black text-gray-900 dark:text-white tracking-tighter text-center">$ {{ stats.total.toLocaleString() }}</h3>
      </div>
      <div class="bg-white dark:bg-gray-900 p-6 md:p-8 rounded-2xl md:rounded-[2.5rem] border border-gray-100 dark:border-white/5 shadow-sm dark:shadow-none flex flex-col justify-between group hover:shadow-xl transition-all">
        <p class="text-[9px] md:text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-3 text-center">Stock</p>
        <h3 class="text-2xl md:text-4xl font-black text-primary tracking-tighter text-center">$ {{ stats.inventory.toLocaleString() }}</h3>
      </div>
      <div class="bg-white dark:bg-gray-900 p-6 md:p-8 rounded-2xl md:rounded-[2.5rem] border border-gray-100 dark:border-white/5 shadow-sm dark:shadow-none flex flex-col justify-between group hover:shadow-xl transition-all">
        <p class="text-[9px] md:text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-3 text-center">Mant.</p>
        <h3 class="text-2xl md:text-4xl font-black text-gray-900 dark:text-white tracking-tighter text-center">$ {{ stats.maintenance.toLocaleString() }}</h3>
      </div>
      <div class="bg-white dark:bg-gray-900 p-6 md:p-8 rounded-2xl md:rounded-[2.5rem] border border-gray-100 dark:border-white/5 shadow-sm dark:shadow-none flex flex-col justify-between group hover:shadow-xl transition-all">
        <p class="text-[9px] md:text-[10px] font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-3 text-center">Registros</p>
        <h3 class="text-2xl md:text-4xl font-black text-gray-900 dark:text-white tracking-tighter text-center">{{ stats.count }}</h3>
      </div>
    </div>

    <!-- Historial Estilo Cards Premium -->
    <div class="px-4 pb-20">
      <div v-if="filteredPurchases.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div v-for="p in filteredPurchases" :key="p.id" 
             class="group relative bg-white dark:bg-gray-900 rounded-[2.5rem] border border-gray-100 dark:border-white/5 p-8 shadow-sm dark:shadow-none hover:shadow-2xl hover:shadow-gray-200/50 dark:hover:border-primary/20 transition-all duration-500 hover:-translate-y-1 overflow-hidden">
          
          <!-- Decoración de Fondo -->
          <div class="absolute -right-4 -top-4 w-24 h-24 bg-gray-50 dark:bg-white/5 rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-700"></div>
          
          <!-- Badge de Fecha y Categoría -->
          <div class="flex justify-between items-start mb-6 relative z-10">
            <div class="flex flex-col gap-1">
              <span class="text-[9px] font-black text-gray-400 dark:text-gray-600 uppercase tracking-[0.2em]">{{ new Date(p.purchase_date).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) }}</span>
              <div :class="[
                'px-3 py-1 rounded-full text-[8px] font-black uppercase tracking-widest border inline-block',
                p.category === 'inventory_restock' ? 'bg-emerald-50 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 border-emerald-100 dark:border-emerald-500/20' : 
                p.category === 'maintenance' ? 'bg-amber-50 dark:bg-amber-500/20 text-amber-600 dark:text-amber-400 border-amber-100 dark:border-amber-500/20' : 'bg-blue-50 dark:bg-blue-500/20 text-blue-600 dark:text-blue-400 border-blue-100 dark:border-blue-500/20'
              ]">
                {{ p.category === 'inventory_restock' ? 'Stock' : p.category === 'maintenance' ? 'Mant.' : 'Herram.' }}
              </div>
            </div>
            <button 
                @click="handleDeletePurchase(p.id)" 
                :class="[
                  'w-10 h-10 rounded-2xl transition-all flex items-center justify-center shadow-sm',
                  confirmingDeleteId === p.id ? 'bg-rose-600 text-white animate-pulse scale-110' : 'bg-gray-50 text-gray-300 hover:bg-rose-50 hover:text-rose-500'
                ]"
            >
                <svg v-if="confirmingDeleteId !== p.id" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                <span v-else class="text-[8px] font-black">OK?</span>
            </button>
          </div>

          <!-- Info Principal -->
          <div class="space-y-4 mb-8 relative z-10">
            <h4 class="text-lg font-black text-gray-900 dark:text-white leading-tight uppercase tracking-tighter group-hover:text-primary transition-colors">{{ p.item_name }}</h4>
            <div class="flex items-center gap-2">
              <div class="w-5 h-5 rounded-lg bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center text-[10px] font-black text-indigo-500">P</div>
              <span class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest">{{ p.supplier || 'Proveedor Eventual' }}</span>
            </div>
          </div>

          <!-- Detalles de Cantidad -->
          <div class="bg-gray-50/50 dark:bg-white/5 rounded-2xl p-4 flex justify-between items-center mb-6 border border-gray-100/50 dark:border-white/5">
            <div class="flex flex-col">
              <span class="text-[8px] font-black text-gray-400 dark:text-gray-600 uppercase tracking-widest">Carga Detalle</span>
              <span class="text-xs font-black text-gray-800 dark:text-gray-200">
                {{ parseFloat(p.units).toLocaleString() }} 
                <span class="text-[10px] text-gray-400 dark:text-gray-600 font-bold">{{ p.category === 'inventory_restock' ? 'uds' : 'piezas' }}</span>
              </span>
            </div>
            <div class="text-right">
              <span class="text-[8px] font-black text-gray-400 dark:text-gray-600 uppercase tracking-widest">Contenido</span>
              <p class="text-xs font-black text-primary">
                {{ parseFloat(p.unit_amount).toLocaleString() }} 
                <span class="text-[10px] opacity-70">{{ p.material ? p.material.unit : (p.category === 'inventory_restock' ? 'ud' : 'ud') }}</span>
              </p>
            </div>
          </div>

          <!-- Footer de Tarjeta: Precio -->
          <div class="flex justify-between items-end">
            <div class="flex flex-col">
              <span class="text-[8px] font-black text-gray-400 dark:text-gray-600 uppercase tracking-widest mb-1">Inversión Total</span>
              <span class="text-2xl font-black text-gray-900 dark:text-white tracking-tighter">$ {{ parseFloat(p.total_cost).toLocaleString() }}</span>
            </div>
            <div class="w-12 h-12 bg-gray-50 rounded-2xl flex items-center justify-center opacity-0 group-hover:opacity-100 group-hover:translate-x-0 translate-x-4 transition-all duration-500">
               <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="py-32 flex flex-col items-center justify-center bg-gray-50/50 rounded-[3rem] border border-dashed border-gray-200">
        <svg class="w-16 h-16 mb-6 text-gray-300 opacity-20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/></svg>
        <p class="text-gray-400 font-black uppercase tracking-[0.3em] text-xs">Sin registros en este periodo</p>
      </div>
    </div>

    <div v-if="showNewPurchaseModal" class="fixed inset-0 bg-gray-950/60 backdrop-blur-md z-[100] flex items-center justify-center p-0 md:p-4 overflow-y-auto">
      <div class="bg-white md:rounded-[3rem] w-full max-w-4xl shadow-2xl overflow-hidden animate-in zoom-in duration-500 my-auto border border-white/20">
        <div class="p-6 md:p-12 max-h-[95vh] overflow-y-auto no-scrollbar">
          
          <!-- Header Premium -->
          <div class="flex justify-between items-center mb-10 md:mb-12">
            <div class="flex items-center gap-6">
                <div class="w-16 h-16 bg-gray-900 rounded-[1.5rem] flex items-center justify-center shadow-2xl shadow-black/20 transform -rotate-6"><svg class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg></div>
                <div>
                  <h3 class="text-3xl md:text-4xl font-black text-gray-900 uppercase tracking-tighter leading-none">Registrar Compra</h3>
                  <p class="text-[10px] md:text-xs text-gray-400 font-bold uppercase tracking-widest mt-2 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
                    Auditoría de Insumos y Suministros
                  </p>
                </div>
            </div>
            <button @click="showNewPurchaseModal = false" class="w-12 h-12 bg-gray-50 text-gray-400 rounded-2xl flex items-center justify-center hover:bg-gray-900 hover:text-white transition-all duration-500 shadow-inner">
              <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>

          <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 md:gap-12">
            <!-- Columna Izquierda: Origen y Tipo -->
            <div class="lg:col-span-5 space-y-8">
                <div class="bg-gray-50/50 p-8 rounded-[2.5rem] border border-gray-100 space-y-6">
                    <div class="space-y-3">
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest flex items-center gap-2">
                            Categoria de Gasto
                        </label>
                        <select v-model="newPurchase.category" class="w-full bg-white border border-gray-100 rounded-2xl p-5 font-black text-xs text-gray-900 outline-none focus:ring-4 focus:ring-primary/10 transition-all shadow-sm">
                            <option value="inventory_restock">Reabastecer Stock</option>
                            <option value="maintenance">Mantenimiento / Repuestos</option>
                            <option value="tools">Herramientas / Maquinaria</option>
                            <option value="office">Administrativo / Oficina</option>
                        </select>
                    </div>

                    <div v-if="newPurchase.category === 'inventory_restock'" class="pt-4 border-t border-gray-100 space-y-6">
                        <div class="flex items-center justify-between">
                            <label class="text-[10px] font-black text-primary uppercase tracking-widest">Vincular Item</label>
                            <button @click="isNewMaterial = !isNewMaterial" class="text-[8px] font-black uppercase tracking-widest px-4 py-2 bg-white border border-primary/20 rounded-full text-primary hover:bg-primary hover:text-white transition-all shadow-md">
                                {{ isNewMaterial ? 'Seleccionar Existente' : 'Nuevo Material' }}
                            </button>
                        </div>

                        <div v-if="!isNewMaterial" class="animate-in fade-in slide-in-from-left-4">
                            <select v-model="newPurchase.material_id" class="w-full bg-white border border-gray-100 rounded-2xl p-5 font-black text-xs text-gray-900 outline-none shadow-sm focus:ring-4 focus:ring-primary/10">
                                <option value="">Selecciona del Inventario...</option>
                                <option v-for="m in inventory" :key="m.id" :value="m.id">{{ m.name }} ({{ m.category }})</option>
                            </select>
                        </div>

                        <div v-else class="space-y-6 animate-in zoom-in duration-500">
                            <!-- Tipo de Registro (Cerebro del formulario) -->
                            <div class="space-y-2">
                                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest flex items-center gap-2">
                                    Tipo de Registro
                                </label>
                                <div class="grid grid-cols-3 gap-2">
                                    <button 
                                        type="button"
                                        @click="() => {
                                            newPurchase.material_data.type = 'material';
                                            newPurchase.material_data.category = 'FDM';
                                            newPurchase.material_data.unit = 'g';
                                        }"
                                        :class="[
                                            'py-3 rounded-xl text-[8px] font-black uppercase transition-all border',
                                            newPurchase.material_data.type === 'material' ? 'bg-primary text-white border-primary shadow-lg' : 'bg-white text-gray-400 border-gray-100 hover:bg-gray-50'
                                        ]"
                                    >Material</button>
                                    <button 
                                        type="button"
                                        @click="() => {
                                            newPurchase.material_data.type = 'product';
                                            newPurchase.material_data.category = 'PROD';
                                            newPurchase.material_data.unit = 'unid';
                                        }"
                                        :class="[
                                            'py-3 rounded-xl text-[8px] font-black uppercase transition-all border',
                                            newPurchase.material_data.type === 'product' ? 'bg-indigo-600 text-white border-indigo-600 shadow-lg' : 'bg-white text-gray-400 border-gray-100 hover:bg-gray-50'
                                        ]"
                                    >Producto</button>
                                    <button 
                                        type="button"
                                        @click="() => {
                                            newPurchase.material_data.type = 'utility';
                                            newPurchase.material_data.category = 'UTIL';
                                            newPurchase.material_data.unit = 'unid';
                                        }"
                                        :class="[
                                            'py-3 rounded-xl text-[8px] font-black uppercase transition-all border',
                                            newPurchase.material_data.type === 'utility' ? 'bg-amber-500 text-white border-amber-500 shadow-lg' : 'bg-white text-gray-400 border-gray-100 hover:bg-gray-50'
                                        ]"
                                    >Insumo</button>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div v-if="newPurchase.material_data.type === 'material'" class="space-y-2">
                                    <label class="text-[9px] font-black text-gray-400 uppercase ml-2">Tecnología</label>
                                    <select v-model="newPurchase.material_data.category" @change="newPurchase.material_data.unit = newPurchase.material_data.category === 'SLA' ? 'ml' : 'g'" class="w-full bg-white border border-gray-100 rounded-2xl p-4 font-black text-[10px] text-gray-900 outline-none shadow-sm uppercase">
                                        <option value="FDM">Filamento (FDM)</option>
                                        <option value="SLA">Resina (SLA)</option>
                                    </select>
                                </div>
                                <div :class="newPurchase.material_data.type === 'material' ? 'col-span-1' : 'col-span-2'" class="space-y-2">
                                    <label class="text-[9px] font-black text-gray-400 uppercase ml-2">Unidad de Medida</label>
                                    <select v-model="newPurchase.material_data.unit" class="w-full bg-white border border-gray-100 rounded-2xl p-4 font-black text-[10px] text-gray-900 outline-none shadow-sm uppercase">
                                        <option value="g">Gramos (g)</option>
                                        <option value="ml">Mililitros (ml)</option>
                                        <option value="unid">Unidades (ud)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label class="text-[9px] font-black text-gray-400 uppercase ml-2">Nombre Identificador</label>
                                <input type="text" v-model="newPurchase.material_data.name" placeholder="Ej: PLA Pro Negro" class="w-full bg-white border border-gray-100 rounded-2xl p-5 font-black text-xs text-gray-900 outline-none shadow-sm focus:ring-4 focus:ring-primary/10">
                            </div>

                            <div class="flex items-center gap-4 bg-white/50 p-4 rounded-2xl border border-dashed border-gray-200">
                                <div class="flex items-center gap-2 flex-1">
                                    <input type="color" v-model="newPurchase.material_data.color" class="w-8 h-8 rounded-lg border-none bg-transparent cursor-pointer">
                                    <span class="text-[9px] font-black text-gray-400 uppercase">Visual</span>
                                </div>
                                <div class="text-right">
                                    <span class="text-[8px] font-black text-gray-300 uppercase block">ID Sugerido</span>
                                    <span class="text-[10px] font-black text-gray-400 italic font-mono">{{ newPurchase.material_data.id || '---' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="space-y-3 animate-in fade-in">
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest flex items-center gap-2">
                            Descripcion General
                        </label>
                        <input type="text" v-model="newPurchase.item_name" placeholder="Ej: Correa GT2 6mm" class="w-full bg-white border border-gray-100 rounded-2xl p-5 font-bold text-sm text-gray-900 outline-none shadow-sm focus:ring-4 focus:ring-primary/10">
                    </div>
                </div>

                <!-- Proveedor Card -->
                <div class="bg-indigo-50/30 p-8 rounded-[2.5rem] border border-indigo-100/50 space-y-6">
                    <div class="space-y-3">
                        <label class="text-[10px] font-black text-indigo-400 uppercase tracking-widest flex items-center gap-2">
                            Proveedor
                        </label>
                        <select @change="handleSelectSupplier" class="w-full bg-white border border-indigo-100 rounded-2xl p-4 font-black text-xs text-gray-900 outline-none shadow-sm">
                            <option value="">-- Nuevo Proveedor --</option>
                            <option v-for="s in suppliers" :key="s.id" :value="s.name">{{ s.name }}</option>
                        </select>
                        <input type="text" v-model="newPurchase.supplier" placeholder="Nombre Comercial" class="w-full bg-white border border-indigo-100 rounded-2xl p-4 font-bold text-xs text-gray-900 outline-none shadow-sm">
                    </div>
                </div>
            </div>

            <!-- Columna Derecha: Cantidades y Análisis Económico -->
            <div class="lg:col-span-7 space-y-8">
                <div class="grid grid-cols-2 gap-6 bg-gray-900 p-8 rounded-[3rem] shadow-2xl shadow-black/20 relative overflow-hidden">
                    <!-- Cantidades -->
                    <div class="space-y-3 relative z-10">
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Nº Unidades</label>
                        <input type="number" v-model.number="newPurchase.units" class="w-full bg-white/10 border border-white/10 rounded-2xl p-5 font-black text-2xl text-white outline-none focus:ring-4 focus:ring-primary/30">
                    </div>
                    <div class="space-y-3 relative z-10">
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Contenido Unit.</label>
                        <input type="number" v-model.number="newPurchase.unit_amount" class="w-full bg-white/10 border border-white/10 rounded-2xl p-5 font-black text-2xl text-white outline-none focus:ring-4 focus:ring-primary/30">
                    </div>

                    <div class="col-span-2 pt-6 border-t border-white/10 relative z-10">
                        <div class="flex justify-between items-center mb-6">
                            <span class="text-[10px] font-black text-primary uppercase tracking-[0.3em]">Carga Total de Stock</span>
                            <span class="text-3xl font-black text-white tracking-tighter">
                                {{ (newPurchase.units * newPurchase.unit_amount).toLocaleString() }} 
                                <span class="text-xs text-gray-500 uppercase">{{ isNewMaterial ? newPurchase.material_data.unit : (inventory.find(m => m.id === newPurchase.material_id)?.unit || 'g') }}</span>
                            </span>
                        </div>
                        
                        <div class="space-y-3">
                            <label class="text-[10px] font-black text-emerald-400 uppercase tracking-widest">Inversión Total ($)</label>
                            <input type="number" v-model.number="newPurchase.total_cost" class="w-full bg-emerald-500/10 border border-emerald-500/20 rounded-2xl p-6 font-black text-4xl text-emerald-400 outline-none focus:ring-4 focus:ring-emerald-500/20">
                        </div>
                    </div>

                    <!-- Decorative logo in background -->
                    <div class="absolute -right-8 -bottom-8 w-40 h-40 opacity-10 text-white pointer-events-none">
                        <svg fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.47 2 2 6.47 2 12s4.47 10 10 10 10-4.47 10-10S17.53 2 12 2zm1 14h-2v-2h2v2zm0-4h-2V7h2v5z"/></svg>
                    </div>
                </div>

                <!-- Gadget de Análisis de Costo -->
                <div v-if="newPurchase.total_cost > 0 && (newPurchase.units * newPurchase.unit_amount) > 0" class="bg-emerald-50 p-8 rounded-[2.5rem] border border-emerald-100 flex flex-col md:flex-row justify-between items-center gap-6 animate-in zoom-in duration-500 shadow-xl shadow-emerald-500/5">
                    <div class="text-center md:text-left">
                        <p class="text-[10px] font-black text-emerald-600 uppercase tracking-widest mb-1">Precio Unitario Calculado</p>
                        <p class="text-3xl font-black text-emerald-900 tracking-tighter">
                            $ {{ (newPurchase.total_cost / (newPurchase.units * newPurchase.unit_amount)).toLocaleString(undefined, {minimumFractionDigits: 2}) }}
                            <span class="text-xs text-emerald-500 font-bold uppercase ml-1">/ {{ isNewMaterial ? newPurchase.material_data.unit : (inventory.find(m => m.id === newPurchase.material_id)?.unit || 'ud') }}</span>
                        </p>
                    </div>
                    <div v-if="isNewMaterial && (newPurchase.material_data.unit === 'g' || newPurchase.material_data.unit === 'ml')" class="bg-white/50 px-6 py-4 rounded-2xl border border-emerald-100/50">
                        <p class="text-[8px] font-black text-emerald-400 uppercase tracking-widest mb-1">Costo Proyectado Kg/L</p>
                        <p class="text-xl font-black text-emerald-600">$ {{ ((newPurchase.total_cost / (newPurchase.units * newPurchase.unit_amount)) * 1000).toLocaleString() }}</p>
                    </div>
                </div>

                <div class="space-y-4">
                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest flex items-center gap-2">
                        Fecha de Operacion
                    </label>
                    <input type="date" v-model="newPurchase.purchase_date" class="w-full bg-gray-50 border border-gray-100 rounded-2xl p-5 font-black text-sm text-gray-900 outline-none focus:ring-4 focus:ring-primary/10">
                </div>
            </div>
          </div>

          <!-- Acciones Finales -->
          <div class="mt-12 md:mt-16 flex flex-col md:flex-row gap-6">
            <button @click="showNewPurchaseModal = false" class="flex-1 py-6 bg-gray-100 text-gray-400 rounded-[2rem] font-black text-xs uppercase tracking-widest hover:bg-gray-200 hover:text-gray-600 transition-all duration-500 flex items-center justify-center gap-3">
                SALIR SIN REGISTRAR
            </button>
            <button @click="handleAddPurchase" class="flex-[2] py-6 bg-gradient-to-r from-gray-900 to-gray-800 text-white rounded-[2rem] font-black text-xs uppercase tracking-[0.2em] shadow-2xl shadow-black/30 hover:scale-[1.02] hover:shadow-primary/20 active:scale-95 transition-all duration-500 flex items-center justify-center gap-4">
                REGISTRAR COMPRA COMPLETA
            </button>
          </div>

        </div>
      </div>
    </div>
  </div>
</template>
