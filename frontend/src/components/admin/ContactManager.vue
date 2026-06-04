<script setup lang="ts">
import { ref, onMounted, computed, inject } from 'vue'
import { api } from '../../services/api'
import { printHtml, openInNewWindow, getCompanyInfo } from '../../services/pdfPrint'
import logger from '../../utils/logger'

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
  <div class="animate-fade-in">
    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-12 lg:mb-16 px-6 lg:px-10 gap-8">
        <div>
            <h2 class="text-3xl md:text-5xl font-black text-gray-900 dark:text-white tracking-tighter uppercase mb-2">Iniciar<br/>Proyecto 3D<span class="text-primary">.</span></h2>
            <div class="flex items-center gap-3">
                <div class="w-2 h-2 rounded-full bg-primary animate-pulse"></div>
                <p class="text-gray-400 dark:text-gray-300 font-bold uppercase tracking-widest text-[10px] md:text-[10px]">Identificación del Cliente</p>
            </div>
        </div>

        <div class="flex items-center gap-2 md:gap-4 bg-white dark:bg-[#151a22] p-2 rounded-2xl md:rounded-[24px] border border-gray-100 dark:border-[#21262d] shadow-sm w-full lg:w-auto overflow-x-auto no-scrollbar">
            <!-- Buscador -->
            <div class="relative flex-1 lg:w-64">
                <input 
                    v-model="searchQuery"
                    type="text" 
                    placeholder="Buscar..."
                    class="w-full pl-10 pr-4 py-3 bg-gray-50 dark:bg-white/5 border-none rounded-xl md:rounded-2xl text-[10px] font-bold text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-primary/20"
                >
                <svg class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-600 w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
            
            <button 
                :class="activeType === 'customers' ? 'bg-gray-900 dark:bg-primary text-white shadow-xl' : 'text-gray-400 dark:text-gray-300 hover:text-gray-600 dark:hover:text-gray-100'" 
                class="flex-1 lg:flex-none px-4 md:px-8 py-3 rounded-xl md:rounded-2xl text-[10px] md:text-[10px] font-black uppercase tracking-widest whitespace-nowrap"
                @click="activeType = 'customers'"
            >
                Clientes
            </button>
            <button 
                :class="activeType === 'suppliers' ? 'bg-gray-900 dark:bg-primary text-white shadow-xl' : 'text-gray-400 dark:text-gray-300 hover:text-gray-600 dark:hover:text-gray-100'" 
                class="flex-1 lg:flex-none px-4 md:px-8 py-3 rounded-xl md:rounded-2xl text-[10px] md:text-[10px] font-black uppercase tracking-widest whitespace-nowrap"
                @click="activeType = 'suppliers'"
            >
                Proveedores
            </button>
            <div class="w-px h-6 bg-gray-100 dark:bg-white/5 mx-1 md:mx-2 shrink-0"></div>
            <button 
                class="w-10 h-10 md:w-12 md:h-12 bg-primary text-white rounded-xl md:rounded-2xl flex items-center justify-center hover:rotate-90 transition-all duration-300 shadow-lg shadow-primary/20 shrink-0"
                @click="openAddModal"
            >
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            </button>
        </div>
    </div>

    <!-- Lista de Contactos -->
    <div class="px-6 lg:px-10">
        <!-- Skeleton: cards placeholder mientras carga -->
        <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8 lg:gap-10 animate-pulse">
            <div v-for="i in 6" :key="'skel-'+i" class="bg-gray-100 dark:bg-[#1a1f2e] p-8 lg:p-10 rounded-[24px] border border-gray-200 dark:border-[#252b3a]">
                <div class="flex items-center gap-4 mb-6">
                    <div class="w-14 h-14 rounded-2xl bg-gray-200 dark:bg-[#2a3040]"></div>
                    <div>
                        <div class="h-4 bg-gray-200 dark:bg-[#2a3040] rounded-full w-28 mb-2"></div>
                        <div class="h-2 bg-gray-200 dark:bg-[#2a3040] rounded-full w-20"></div>
                    </div>
                </div>
                <div class="space-y-3">
                    <div class="h-3 bg-gray-200 dark:bg-[#2a3040] rounded-full w-40"></div>
                    <div class="h-3 bg-gray-200 dark:bg-[#2a3040] rounded-full w-32"></div>
                    <div class="h-3 bg-gray-200 dark:bg-[#2a3040] rounded-full w-44"></div>
                </div>
                <div class="flex gap-2 mt-6 pt-4 border-t border-gray-200 dark:border-[#252b3a]">
                    <div class="flex-1 h-10 bg-gray-200 dark:bg-[#2a3040] rounded-xl"></div>
                    <div class="flex-1 h-10 bg-gray-200 dark:bg-[#2a3040] rounded-xl"></div>
                </div>
            </div>
        </div>
        
        <div v-else :key="activeType" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8 lg:gap-10">
            <div 
                v-for="item in filteredItems" 
                :key="item.id"
                class="bg-white dark:bg-[#151a22] p-8 lg:p-10 rounded-[24px] border border-gray-100 dark:border-[#21262d] shadow-sm dark:shadow-none group relative overflow-hidden transition-all hover:shadow-xl"
            >
                <div class="absolute top-0 right-0 p-4 opacity-100 md:opacity-0 group-hover:opacity-100 transition-all flex gap-2">
                    <button class="icon-btn icon-btn-edit" @click="openEditModal(item)">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </button>
                    <button class="icon-btn icon-btn-danger" @click="handleDelete(item.id)">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                </div>

                <div class="flex items-center gap-4 mb-6">
                    <div class="w-14 h-14 rounded-2xl bg-gray-50 dark:bg-white/5 flex items-center justify-center text-2xl group-hover:scale-110 transition-transform duration-300">
                        {{ activeType === 'customers' ? 'C' : 'P' }}
                    </div>
                    <div>
                        <h4 class="font-black text-gray-900 dark:text-white uppercase tracking-tight text-lg">{{ item.name }}</h4>
                        <div class="flex flex-col">
                            <p v-if="item.customer_id_document" class="text-[10px] font-bold text-gray-500 dark:text-gray-300 uppercase tracking-widest">ID: {{ item.customer_id_document }}</p>
                            <p v-if="item.company" class="text-[10px] font-black text-gray-400 dark:text-gray-600 uppercase tracking-widest mb-1">{{ item.company }}</p>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <div v-if="activeType === 'customers'" class="space-y-3">
                        <a :href="'mailto:' + item.email" class="flex items-center gap-3 text-sm text-gray-500 dark:text-gray-300 font-bold hover:text-primary">
                            <svg class="w-4 h-4 inline text-gray-300 dark:text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg> {{ item.email || 'N/A' }}
                        </a>
                        <a :href="'tel:' + item.phone" class="flex items-center gap-3 text-sm text-gray-500 dark:text-gray-300 font-bold hover:text-primary">
                            <svg class="w-4 h-4 inline text-gray-300 dark:text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg> {{ item.phone }}
                        </a>
                        <div class="flex items-center gap-3 text-sm text-gray-500 dark:text-gray-300 font-bold">
                            <svg class="w-4 h-4 inline text-gray-300 dark:text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg> {{ item.address_full || item.location || 'N/A' }}
                        </div>
                        <div v-if="item.city_dept_country" class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-7">
                            {{ item.city_dept_country }} {{ item.zip_code ? `[${item.zip_code}]` : '' }}
                        </div>

                        <div class="pt-4 mt-2 border-t border-gray-50 dark:border-[#21262d] flex gap-2">
                            <button class="btn-pdf dark:bg-white/5 dark:border-white/10 dark:text-white dark:hover:bg-primary dark:hover:border-primary flex-1 py-3" @click="handleDownloadShippingLabel(item)">
                                <svg class="w-3 h-3 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg> Imprimir
                            </button>
                            <button class="btn-secondary dark:bg-white/5 dark:border-white/10 dark:text-white dark:hover:bg-gray-700 flex-1 py-3" @click="handleViewShippingLabel(item)">
                                <svg class="w-3 h-3 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg> Ver PDF
                            </button>
                        </div>
                    </div>
                    <div v-else class="space-y-3">
                        <div class="flex items-center gap-3 text-sm text-gray-500 font-bold">
                            <span class="text-gray-300 font-black">C</span> {{ item.contact_person || 'N/A' }}
                        </div>
                        <div class="flex items-center gap-3 text-sm text-gray-500 font-bold">
                            <svg class="w-4 h-4 inline text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg> {{ item.phone || 'N/A' }}
                        </div>
                        <div v-if="item.specialty" class="pt-4 mt-2 border-t border-gray-50 dark:border-[#21262d]">
                            <p class="text-[10px] font-black text-primary uppercase tracking-widest mb-1">Especialidad / Insumos</p>
                            <p class="text-sm font-bold text-gray-700">{{ item.specialty }}</p>
                        </div>
                    </div>
                </div>

                <div v-if="item.notes" class="mt-6 pt-6 border-t border-gray-50 dark:border-[#21262d]">
                    <p class="text-[10px] font-black text-gray-400 dark:text-gray-600 uppercase mb-2">Observaciones</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 italic">{{ item.notes }}</p>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="(activeType === 'customers' ? props.customers.length : props.suppliers.length) === 0" class="col-span-full bg-gray-50/50 dark:bg-[#151a22]/50 border-2 border-dashed border-gray-100 dark:border-[#21262d] rounded-[24px] p-20 text-center">
                <svg class="w-12 h-12 text-gray-300 opacity-20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                <h3 class="text-xl font-black text-gray-400 uppercase tracking-tighter">No hay {{ activeType === 'customers' ? 'clientes' : 'proveedores' }} registrados</h3>
                <p class="text-sm text-gray-300 font-bold uppercase tracking-widest mt-2">Usa el botón "+" para crear el primero</p>
            </div>
        </div>
    </div>

    <!-- Modal para Agregar/Editar -->
    <div v-if="showAddModal" class="fixed inset-0 z-[100] flex items-center justify-center p-0 md:p-4">
        <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" @click="showAddModal = false"></div>
        <div class="bg-white dark:bg-[#0d1117] md:rounded-[24px] w-full max-w-lg relative z-10 overflow-hidden shadow-2xl animate-in zoom-in duration-300 h-full md:h-auto">
            <div class="bg-gray-900 dark:bg-black p-8 md:p-10 text-white flex justify-between items-center">
                <div>
                    <h3 class="text-2xl md:text-3xl font-black uppercase tracking-tighter mb-1 md:mb-2">
                        {{ isEditing ? 'Editar' : 'Nuevo' }} {{ activeType === 'customers' ? 'Cliente' : 'Proveedor' }}
                    </h3>
                    <p class="text-gray-400 text-[8px] md:text-[10px] font-bold uppercase tracking-widest">Actualiza la ficha del contacto</p>
                </div>
                <button class="text-white text-2xl hover:rotate-90 transition-transform px-4 outline-none" @click="showAddModal = false">✕</button>
            </div>
            
            <div class="p-6 md:p-10 space-y-6 overflow-y-auto h-[calc(100%-120px)] md:h-auto md:max-h-[70vh] no-scrollbar">
                <!-- Cliente Form -->
                <div v-if="activeType === 'customers'" class="grid grid-cols-1 gap-4">
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-4">Nombre Completo</label>
                        <input v-model="newCustomer.name" type="text" class="w-full px-6 py-4 bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/10 rounded-2xl outline-none focus:ring-4 focus:ring-primary/10 transition-all font-bold text-gray-900 dark:text-white" placeholder="Ej. Juan Perez">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-4">Empresa</label>
                            <input v-model="newCustomer.company" type="text" class="w-full px-6 py-4 bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/10 rounded-2xl outline-none focus:ring-4 focus:ring-primary/10 transition-all font-bold text-gray-900 dark:text-white" placeholder="Opcional">
                        </div>
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-4">CC / NIT</label>
                            <input v-model="newCustomer.customer_id_document" type="text" class="w-full px-6 py-4 bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/10 rounded-2xl outline-none focus:ring-4 focus:ring-primary/10 transition-all font-bold text-gray-900 dark:text-white" placeholder="Identificación">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-4">Email</label>
                            <input v-model="newCustomer.email" type="email" class="w-full px-6 py-4 bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/10 rounded-2xl outline-none focus:ring-4 focus:ring-primary/10 transition-all font-bold text-gray-900 dark:text-white" placeholder="correo@ejemplo.com">
                        </div>
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-4">Teléfono</label>
                            <input v-model="newCustomer.phone" type="text" class="w-full px-6 py-4 bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/10 rounded-2xl outline-none focus:ring-4 focus:ring-primary/10 transition-all font-bold text-gray-900 dark:text-white" placeholder="+57 ...">
                        </div>
                    </div>
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-4">Ciudad / Dept / País</label>
                        <input v-model="newCustomer.city_dept_country" type="text" class="w-full px-6 py-4 bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/10 rounded-2xl outline-none focus:ring-4 focus:ring-primary/10 transition-all font-bold text-gray-900 dark:text-white" placeholder="Bogotá, CO">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-4">Dirección</label>
                            <input v-model="newCustomer.address_full" type="text" class="w-full px-6 py-4 bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/10 rounded-2xl outline-none focus:ring-4 focus:ring-primary/10 transition-all font-bold text-gray-900 dark:text-white" placeholder="Calle ...">
                        </div>
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-4">C. Postal</label>
                            <input v-model="newCustomer.zip_code" type="text" class="w-full px-6 py-4 bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/10 rounded-2xl outline-none focus:ring-4 focus:ring-primary/10 transition-all font-bold text-gray-900 dark:text-white" placeholder="110xxx">
                        </div>
                    </div>
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-4">Referencia de Ubicación</label>
                        <input v-model="newCustomer.location_reference" type="text" class="w-full px-6 py-4 bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/10 rounded-2xl outline-none focus:ring-4 focus:ring-primary/10 transition-all font-bold text-gray-900 dark:text-white" placeholder="Ej. Casa verde esquina">
                    </div>
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-4">Notas</label>
                        <textarea v-model="newCustomer.notes" class="w-full px-6 py-4 bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/10 rounded-2xl outline-none focus:ring-4 focus:ring-primary/10 transition-all font-bold min-h-[80px] text-gray-900 dark:text-white"></textarea>
                    </div>
                </div>

                <!-- Proveedor Form -->
                <div v-else class="grid grid-cols-1 gap-6">
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-4">Empresa</label>
                        <input v-model="newSupplier.name" type="text" class="w-full px-6 py-4 bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/10 rounded-2xl outline-none focus:ring-4 focus:ring-primary/10 transition-all font-bold text-gray-900 dark:text-white" placeholder="Nombre comercial">
                    </div>
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-4">Contacto</label>
                        <input v-model="newSupplier.contact_person" type="text" class="w-full px-6 py-4 bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/10 rounded-2xl outline-none focus:ring-4 focus:ring-primary/10 transition-all font-bold text-gray-900 dark:text-white" placeholder="Persona de contacto">
                    </div>
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-4">Teléfono</label>
                        <input v-model="newSupplier.phone" type="text" class="w-full px-6 py-4 bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/10 rounded-2xl outline-none focus:ring-4 focus:ring-primary/10 transition-all font-bold text-gray-900 dark:text-white" placeholder="+57 ...">
                    </div>
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest ml-4">Especialidad</label>
                        <input v-model="newSupplier.specialty" type="text" class="w-full px-6 py-4 bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/10 rounded-2xl outline-none focus:ring-4 focus:ring-primary/10 transition-all font-bold text-gray-900 dark:text-white" placeholder="Insumos, resinas, filamentos...">
                    </div>
                </div>

                <div class="flex gap-4 pt-4">
                    <button class="btn-secondary flex-1 py-4" @click="showAddModal = false">Descartar</button>
                    <button class="btn-primary flex-2 py-4" @click="handleAdd">
                        {{ isEditing ? 'Actualizar Ficha' : 'Crear Registro' }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Diálogo interno eliminado para unificación global -->
  </div>
</template>
