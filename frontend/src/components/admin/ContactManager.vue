<script setup lang="ts">
import { ref, onMounted, computed, inject } from 'vue'
import { api } from '../../services/api'
import { printHtml, openInNewWindow, getCompanyInfo } from '../../services/pdfPrint'
import logger from '../../utils/logger'
import AdminModal from './AdminModal.vue'

const props = defineProps({
    customers: { type: Array, default: () => [] },
    suppliers: { type: Array, default: () => [] }
})

const emit = defineEmits(['refresh', 'delete-contact'])
const showNotify: any = inject('showNotify')

const settings = ref({})
const loading = ref(false)
const activeType = ref('customers') // customers, suppliers
const searchQuery = ref('')

const newCustomer = ref({ 
    name: '', company: '', customer_id_document: '', email: '', phone: '',
    location: '', address_full: '', city_dept_country: '', zip_code: '', 
    location_reference: '', notes: '' 
})
const newSupplier = ref({ name: '', contact_person: '', phone: '', specialty: '' })
const showAddModal = ref(false)
const isEditing = ref(false)
const editingId = ref<any>(null)

// La confirmación se delega al padre (AdminDashboard) para consistencia global
const handleDelete = (id: any) => {
    emit('delete-contact', activeType.value === 'customers' ? 'customers' : 'suppliers', id)
}

const fetchData = async () => {
    loading.value = true
    try {
        const results = await Promise.allSettled([
            api.get('/settings')
        ]);
        
        if (results[0].status === 'fulfilled') {
            const rawSettings = results[0].value;
            if (rawSettings && typeof rawSettings === 'object' && !Array.isArray(rawSettings)) {
                settings.value = { ...settings.value, ...rawSettings }
            } else if (Array.isArray(rawSettings)) {
                const mapped = rawSettings.reduce((acc, s) => ({ ...acc, [s.key]: s.value }), {})
                settings.value = { ...settings.value, ...mapped }
            }
        }
    } catch (err: any) {
        logger.error('Error fetching settings in ContactManager:', err)
    } finally {
        loading.value = false
    }
}

const filteredItems = computed(() => {
    const baseList: any[] = (activeType.value === 'customers' ? props.customers : props.suppliers) as any[];
    if (!searchQuery.value) return baseList;
    
    const query = searchQuery.value.toLowerCase();
    return baseList.filter(item => 
        item.name?.toLowerCase().includes(query) || 
        item.company?.toLowerCase().includes(query) ||
        item.email?.toLowerCase().includes(query)
    );
});

const openAddModal = () => {
    isEditing.value = false
    editingId.value = null
    newCustomer.value = { 
        name: '', company: '', customer_id_document: '', email: '', phone: '',
        location: '', address_full: '', city_dept_country: '', zip_code: '', 
        location_reference: '', notes: '' 
    }
    newSupplier.value = { name: '', contact_person: '', phone: '', specialty: '' }
    showAddModal.value = true
}

const openEditModal = (item: any) => {
    isEditing.value = true
    editingId.value = item.id
    if (activeType.value === 'customers') {
        newCustomer.value = { ...item }
    } else {
        newSupplier.value = { ...item }
    }
    showAddModal.value = true
}

const handleAdd = async () => {
    const data = activeType.value === 'customers' ? newCustomer.value : newSupplier.value
    
    if (!data.name) {
        showNotify('El nombre es obligatorio para registrar un contacto.', 'error')
        return
    }

    try {
        const type = activeType.value === 'customers' ? 'customers' : 'suppliers'
        const endpoint = `/admin/contacts/${type}`
        
        if (isEditing.value) {
            await api.patch(`${endpoint}/${editingId.value}`, data)
        } else {
            await api.post(endpoint, data)
        }
        
        showAddModal.value = false
        emit('refresh')
        
        // Reset forms after success
        newCustomer.value = { 
            name: '', company: '', customer_id_document: '', email: '', phone: '',
            location: '', address_full: '', city_dept_country: '', zip_code: '', 
            location_reference: '', notes: '' 
        }
        newSupplier.value = { name: '', contact_person: '', phone: '', specialty: '' }
        
        emit('refresh')
    } catch (err: any) {
        showNotify('No pudimos guardar los cambios: ' + err.message, 'error')
    }
}

// Eliminado handleDelete interno para usar el emitido al padre

onMounted(fetchData)

// Helper compartido para generar el HTML del rótulo de envío
const buildShippingLabelHtml = (customer: any, companyInfo: any) => `
    <html>
        <head>
            <title>Rótulo de Envío - ${customer.name}</title>
            <style>
                @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@400;700;900&display=swap');
                @page { size: 100mm 150mm; margin: 0; }
                body { font-family: 'Outfit', sans-serif; padding: 15px; color: #000; margin: 0; background: white; }
                .label-box { border: 5px solid #000; padding: 20px; height: 135mm; display: flex; flex-direction: column; position: relative; box-sizing: border-box; }
                .header { border-bottom: 3px solid #000; padding-bottom: 5px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; }
                .title { font-size: 20px; font-weight: 900; text-transform: uppercase; letter-spacing: -1px; }
                .priority { font-size: 9px; font-weight: 900; background: #000; color: #fff; padding: 2px 8px; border-radius: 4px; }
                .remitente { font-size: 8px; font-weight: 800; text-transform: uppercase; margin-bottom: 10px; color: #444; border-left: 3px solid #ccc; padding-left: 12px; line-height: 1.1; }
                .destinatario-label { font-size: 11px; font-weight: 900; text-transform: uppercase; background: #000; color: #fff; padding: 3px 10px; display: inline-block; margin-bottom: 10px; }
                .dest-info { margin-bottom: 10px; }
                .dest-name { font-size: 24px; font-weight: 900; line-height: 0.9; margin-bottom: 4px; text-transform: uppercase; }
                .dest-company { font-size: 12px; font-weight: 700; text-transform: uppercase; margin-bottom: 8px; color: #333; }
                .dest-addr { font-size: 18px; font-weight: 800; line-height: 1.1; margin-bottom: 6px; border-top: 2px solid #eee; padding-top: 8px; }
                .dest-city { font-size: 20px; font-weight: 900; text-transform: uppercase; letter-spacing: -0.5px; }
                .dest-cc { font-size: 14px; font-weight: 800; margin-top: 5px; color: #333; }
                .dest-zip { font-size: 14px; font-family: monospace; font-weight: 700; margin-top: 4px; background: #f0f0f0; padding: 2px 6px; display: inline-block; }
                .ref-box { margin-top: 15px; padding: 10px; border: 2px dashed #000; font-size: 12px; font-weight: 800; line-height: 1.2; }
                .footer-info { margin-top: auto; border-top: 2px solid #000; padding-top: 12px; display: flex; justify-content: space-between; align-items: center; }
                .contact-data { display: flex; flex-direction: column; gap: 2px; }
                .phone-box { font-size: 16px; font-weight: 900; }
                .email-box { font-size: 10px; font-weight: 700; color: #444; text-transform: lowercase; }
                @media print { body { -webkit-print-color-adjust: exact; } }
            </style>
        </head>
        <body>
            <div class="label-box">
                <div class="header">
                    <div class="title">${companyInfo.name}</div>
                    <div class="priority">ENVÍO PRIORITARIO</div>
                </div>
                <div class="remitente">
                    <strong>REMITENTE:</strong><br>
                    ${companyInfo.name}<br>
                    NIT: ${companyInfo.nit}<br>
                    DIR: ${companyInfo.address}<br>
                    TEL: ${companyInfo.phone}<br>
                    EMAIL: ${companyInfo.email}
                </div>
                <div class="destinatario-label">DESTINATARIO</div>
                <div class="dest-info">
                    <div class="dest-name">${customer.name}</div>
                    ${customer.customer_id_document ? `<div class="dest-cc">CC/NIT: ${customer.customer_id_document}</div>` : ''}
                    ${customer.company ? `<div class="dest-company">${customer.company}</div>` : ''}
                </div>
                <div class="dest-addr">${customer.address_full || customer.location || 'DIRECCIÓN PENDIENTE'}</div>
                <div class="dest-city">${customer.city_dept_country || 'CIUDAD / MUNICIPIO'}</div>
                <div class="dest-zip">C.P. ${customer.zip_code || 'S/N'}</div>
                ${customer.location_reference ? `
                    <div class="ref-box">
                        <strong>REFERENCIAS:</strong><br>
                        ${customer.location_reference}
                    </div>
                ` : ''}
                <div class="footer-info">
                    <div class="contact-data">
                        <div class="phone-box">TEL: ${customer.phone}</div>
                        ${customer.email ? `<div class="email-box">${customer.email}</div>` : ''}
                    </div>
                    <div style="font-size: 9px; font-weight: 900; text-transform: uppercase;">N3XT 3D</div>
                </div>
            </div>
        </body>
    </html>`

const handleDownloadShippingLabel = (customer: any) => {
    const html = buildShippingLabelHtml(customer, getCompanyInfo(settings.value))
    printHtml(html)
}

const handleViewShippingLabel = (customer: any) => {
    const html = buildShippingLabelHtml(customer, getCompanyInfo(settings.value))
    openInNewWindow(html, `Rótulo - ${customer.name}`)
}
</script>

<template>
  <div class="space-y-8">
    <header class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-6">
      <div>
        <h1 class="admin-title">Contactos <span class="text-brand">del taller</span></h1>
        <p class="admin-lead">Clientes con sus datos de envío y proveedores de material. Los clientes que se registran en la web aparecen aquí.</p>
      </div>
      <button type="button" class="btn-primary btn-lg" @click="openAddModal">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14m-7-7h14"/></svg>
        {{ activeType === 'customers' ? 'Nuevo cliente' : 'Nuevo proveedor' }}
      </button>
    </header>

    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div class="flex flex-wrap gap-2" role="group" aria-label="Tipo de contacto">
        <button type="button" class="chip" :aria-pressed="activeType === 'customers'" @click="activeType = 'customers'">Clientes <span class="tabular-nums opacity-80">{{ customers.length }}</span></button>
        <button type="button" class="chip" :aria-pressed="activeType === 'suppliers'" @click="activeType = 'suppliers'">Proveedores <span class="tabular-nums opacity-80">{{ suppliers.length }}</span></button>
      </div>
      <div class="md:w-80">
        <label for="contacts-search" class="sr-only">Buscar contactos</label>
        <input id="contacts-search" v-model="searchQuery" type="search" placeholder="Buscar por nombre, empresa o correo" class="field">
      </div>
    </div>

    <div v-if="loading" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3" aria-hidden="true">
      <div v-for="i in 3" :key="i" class="h-48 admin-card animate-pulse"></div>
    </div>

    <div v-else-if="!filteredItems.length" class="admin-card py-14 text-center">
      <p class="font-semibold">{{ searchQuery ? 'Ningún contacto coincide con la búsqueda.' : (activeType === 'customers' ? 'Aún no hay clientes.' : 'Aún no hay proveedores.') }}</p>
      <p v-if="!searchQuery" class="mt-1 text-muted">Crea el primero con el botón de arriba.</p>
    </div>

    <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
      <article v-for="item in (filteredItems as any[])" :key="item.id" class="admin-card flex flex-col" :aria-label="item.name">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <h2 class="font-semibold text-lg leading-snug truncate">{{ item.name }}</h2>
            <p v-if="item.company || item.customer_id_document" class="text-sm text-muted truncate">{{ [item.company, item.customer_id_document && `CC/NIT ${item.customer_id_document}`].filter(Boolean).join(' · ') }}</p>
          </div>
          <div class="flex items-center gap-1 shrink-0 -mr-2 -mt-1">
            <span v-if="activeType === 'customers' && item.email_verified_at" class="badge bg-success/10 text-success mr-1" title="Se registró en la web y verificó su correo">Cuenta web</span>
          <button type="button" class="icon-btn text-muted hover:text-ink hover:bg-surface-2" :aria-label="`Editar ${item.name}`" title="Editar" @click="openEditModal(item)">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
            </button>
            <button type="button" class="icon-btn text-muted hover:text-danger hover:bg-danger/10" :aria-label="`Eliminar ${item.name}`" title="Eliminar" @click="handleDelete(item.id)">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </button>
          </div>
        </div>

        <dl v-if="activeType === 'customers'" class="mt-4 space-y-1.5 text-sm">
          <div v-if="item.email" class="flex gap-2"><dt class="sr-only">Correo</dt><dd class="min-w-0"><a :href="'mailto:' + item.email" class="text-brand hover:underline break-all">{{ item.email }}</a></dd></div>
          <div v-if="item.phone" class="flex gap-2"><dt class="sr-only">Teléfono</dt><dd><a :href="'tel:' + item.phone" class="hover:underline tabular-nums">{{ item.phone }}</a></dd></div>
          <div v-if="item.address_full || item.city_dept_country" class="text-muted"><dt class="sr-only">Dirección</dt><dd>{{ [item.address_full, item.city_dept_country, item.zip_code].filter(Boolean).join(', ') }}</dd></div>
        </dl>
        <dl v-else class="mt-4 space-y-1.5 text-sm">
          <div v-if="item.contact_person"><dt class="sr-only">Contacto</dt><dd>{{ item.contact_person }}</dd></div>
          <div v-if="item.phone"><dt class="sr-only">Teléfono</dt><dd><a :href="'tel:' + item.phone" class="hover:underline tabular-nums">{{ item.phone }}</a></dd></div>
          <div v-if="item.specialty" class="text-muted"><dt class="sr-only">Especialidad</dt><dd>{{ item.specialty }}</dd></div>
        </dl>

        <p v-if="item.notes" class="mt-3 text-sm text-muted line-clamp-2">{{ item.notes }}</p>

        <div v-if="activeType === 'customers'" class="mt-auto pt-4 flex flex-wrap items-center gap-2">
          <button type="button" class="btn-outlined btn-sm" @click="handleDownloadShippingLabel(item)">Imprimir rótulo</button>
          <button type="button" class="btn-outlined btn-sm" @click="handleViewShippingLabel(item)">Ver rótulo</button>
        </div>
      </article>
    </div>

    <AdminModal
      :show="showAddModal"
      :title="`${isEditing ? 'Editar' : 'Nuevo'} ${activeType === 'customers' ? 'cliente' : 'proveedor'}`"
      size="lg"
      @close="showAddModal = false"
    >
      <form id="contact-form" class="grid gap-4 sm:grid-cols-2" novalidate @submit.prevent="handleAdd">
        <template v-if="activeType === 'customers'">
          <div class="sm:col-span-2">
            <label for="c-name" class="admin-label mb-2">Nombre completo</label>
            <input id="c-name" v-model="newCustomer.name" type="text" required class="field" data-autofocus>
          </div>
          <div><label for="c-company" class="admin-label mb-2">Empresa <span class="opacity-70">(opcional)</span></label><input id="c-company" v-model="newCustomer.company" type="text" class="field"></div>
          <div><label for="c-doc" class="admin-label mb-2">CC / NIT</label><input id="c-doc" v-model="newCustomer.customer_id_document" type="text" class="field"></div>
          <div><label for="c-email" class="admin-label mb-2">Correo</label><input id="c-email" v-model="newCustomer.email" type="email" class="field"></div>
          <div><label for="c-phone" class="admin-label mb-2">Teléfono</label><input id="c-phone" v-model="newCustomer.phone" type="tel" class="field"></div>
          <div class="sm:col-span-2"><label for="c-city" class="admin-label mb-2">Ciudad, departamento</label><input id="c-city" v-model="newCustomer.city_dept_country" type="text" placeholder="Bogotá, Cundinamarca" class="field"></div>
          <div><label for="c-address" class="admin-label mb-2">Dirección</label><input id="c-address" v-model="newCustomer.address_full" type="text" class="field"></div>
          <div><label for="c-zip" class="admin-label mb-2">Código postal</label><input id="c-zip" v-model="newCustomer.zip_code" type="text" class="field"></div>
          <div class="sm:col-span-2"><label for="c-ref" class="admin-label mb-2">Referencia para el envío</label><input id="c-ref" v-model="newCustomer.location_reference" type="text" placeholder="Ej. Casa verde, esquina" class="field"></div>
          <div class="sm:col-span-2"><label for="c-notes" class="admin-label mb-2">Notas</label><textarea id="c-notes" v-model="newCustomer.notes" rows="3" class="field !h-auto py-3"></textarea></div>
        </template>
        <template v-else>
          <div class="sm:col-span-2"><label for="s-name" class="admin-label mb-2">Empresa</label><input id="s-name" v-model="newSupplier.name" type="text" required class="field" data-autofocus></div>
          <div><label for="s-contact" class="admin-label mb-2">Persona de contacto</label><input id="s-contact" v-model="newSupplier.contact_person" type="text" class="field"></div>
          <div><label for="s-phone" class="admin-label mb-2">Teléfono</label><input id="s-phone" v-model="newSupplier.phone" type="tel" class="field"></div>
          <div class="sm:col-span-2"><label for="s-spec" class="admin-label mb-2">Qué vende</label><input id="s-spec" v-model="newSupplier.specialty" type="text" placeholder="Filamento, resinas, repuestos…" class="field"></div>
        </template>
      </form>
      <template #footer>
        <button type="button" class="btn-outlined" @click="showAddModal = false">Cancelar</button>
        <button type="submit" form="contact-form" class="btn-primary">{{ isEditing ? 'Guardar cambios' : 'Crear contacto' }}</button>
      </template>
    </AdminModal>
  </div>
</template>
