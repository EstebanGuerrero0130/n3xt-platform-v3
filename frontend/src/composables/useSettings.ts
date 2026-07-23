/**
 * N3XT Settings Composable
 * Gestiona configuración del sistema, inventario e impresoras
 */
import { ref } from 'vue'
import { api } from '../services/api'
import logger from '../utils/logger'

interface UseSettingsOptions {
  showNotify?: (msg: string, type?: string) => void
}

export function useSettings({ showNotify }: UseSettingsOptions = {}) {
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
    discounts: [] as any[],
    company_logo: ''
  })

  const inventoryData = ref<any[]>([])
  const printers = ref<any[]>([])
  const savingSettings = ref(false)

  const fetchInventory = async () => {
    try {
      const res = await api.get('/materials')
      inventoryData.value = Array.isArray(res) ? res : (res?.data || [])
    } catch (_err) {
      inventoryData.value = []
    }
  }

  const fetchPrinters = async () => {
    try {
      const res = await api.get('/admin/printers')
      printers.value = Array.isArray(res) ? res : (res?.data || [])
    } catch (_err) {
      printers.value = []
    }
  }

  const fetchSettings = async () => {
    try {
      const data = await api.get('/settings')
      if (data && typeof data === 'object' && !Array.isArray(data)) {
        if (data.infra) settings.value.infra = { ...settings.value.infra, ...data.infra }
        if (data.prep) settings.value.prep = { ...settings.value.prep, ...data.prep }
        if (data.oper) settings.value.oper = { ...settings.value.oper, ...data.oper }
        if (data.margin) settings.value.margin = { ...settings.value.margin, ...data.margin }
        if (data.company) settings.value.company = { ...settings.value.company, ...data.company }
        if (data.web) {
          settings.value.web = { ...settings.value.web, ...data.web }
          if (!Array.isArray(settings.value.web.catalog)) settings.value.web.catalog = []
        }
        if (data.company_logo) settings.value.company_logo = data.company_logo
        if (data.discounts) settings.value.discounts = data.discounts
      }
      if (!settings.value.company) {
        settings.value.company = { name: 'N3XT 3D TECHNOLOGY', nit: '', email: '', phone: '', address: '', slogan: 'Manufactura Digital Avanzada', website: '' }
      }
    } catch (err) {
      logger.error('Error settings:', err)
    }
  }

  const saveSettings = (silent = false, askConfirm) => {
    const executeSave = async () => {
      if (savingSettings.value) return
      savingSettings.value = true
      try {
        const payload = JSON.parse(JSON.stringify(settings.value))
        const response = await api.post('/admin/settings', { settings: payload })
        if (response) {
          if (showNotify) showNotify('Ajustes publicados con exito', 'success')
          if (response.settings) settings.value = { ...settings.value, ...response.settings }
        }
      } catch (err) {
        if (showNotify) showNotify('Error al sincronizar: ' + (err.message || 'Error de red'), 'error')
      } finally {
        savingSettings.value = false
      }
    }

    if (silent) {
      executeSave()
    } else if (askConfirm) {
      askConfirm(
        'Publicar Cambios',
        '¿Estás seguro de que deseas publicar los ajustes actuales? Esto actualizará la configuración de costos, SEO y catálogo en la web pública.',
        '',
        executeSave
      )
    }
  }

  const handleUpdateStock = async (mat) => {
    try {
      if (mat.newStock === null || mat.newStock === undefined || mat.newStock === 0) return
      const currentStock = mat.inventory?.stock_available || 0
      const totalNewStock = currentStock + mat.newStock
      await api.post(`/materials/${mat.id}/stock`, { stock_available: totalNewStock })
      await fetchInventory()
      if (showNotify) showNotify(`Stock incrementado: +${mat.newStock}${mat.unit || ''} en ${mat.name}`, 'success')
      mat.newStock = null
    } catch (err) {
      if (showNotify) showNotify('Error al actualizar stock: ' + err.message, 'error')
    }
  }

  return {
    settings,
    inventoryData,
    printers,
    savingSettings,
    fetchInventory,
    fetchPrinters,
    fetchSettings,
    saveSettings,
    handleUpdateStock
  }
}
