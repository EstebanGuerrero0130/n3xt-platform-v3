/**
 * Ajustes del panel (costos, empresa, sitio web, cupones y logo): carga,
 * guarda y publica el objeto `settings` compartido por todos los módulos.
 */
import { ref, computed } from 'vue'
import { api } from '../services/api'
import logger from '../utils/logger'

interface SettingsDeps {
  showNotify: (msg: string, type?: string) => void
  askConfirm: (title: string, message: string, icon: string, onConfirm: () => void | Promise<void>) => void
}

const errorMessage = (err: unknown) => (err instanceof Error ? err.message : String(err))

export function useAdminSettings({ showNotify, askConfirm }: SettingsDeps) {
  const savingSettings = ref(false)
  const settings = ref<Record<string, any>>({
    infra: { luz_hr: 926, depr_hr: 400, mant_hr: 700, etiquetas: 500, load_factor: 0.4 },
    prep: { mano_obra_hr: 1000, prep_time_pct: 10 },
    oper: { transporte: 5, ganancia: 20, marketing: 10, fallos: 5 },
    margin: { iva: 19 },
    // Reglas comerciales: en 0 / vacías no cambian el precio
    rules: { min_order_total: 0, volume_tiers: [], round_to: 0, labor_per_piece_min: 0, rush_pct: 0 },
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
    coupons: [],
    welcome_coupon_pct: 10
  })
  // Usos por cupón: solo lo escribe el servidor, nunca se reenvía al guardar
  const couponUses = ref<Record<string, number>>({})

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
      settings.value.web.catalog.forEach((item: any) => {
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
      settings.value.web.news.forEach((item: any) => {
        if (!item.alt) item.alt = `Noticia: ${item.t} | Manufactura Digital N3XT`
      })
    }
  }

  const fetchSettings = async () => {
    try {
      // Siempre fresco: «Publicar» manda el objeto entero y no debe pisar cambios de otro
      const data = await api.get('/settings', true)
      if (data && typeof data === 'object' && !Array.isArray(data)) {
        // Merge inteligente por categorías para no perder campos nuevos
        if (data.infra) settings.value.infra = { ...settings.value.infra, ...data.infra }
        if (data.prep) settings.value.prep = { ...settings.value.prep, ...data.prep }
        if (data.oper) settings.value.oper = { ...settings.value.oper, ...data.oper }
        if (data.margin) settings.value.margin = { ...settings.value.margin, ...data.margin }
        if (data.rules && typeof data.rules === 'object') {
          settings.value.rules = { ...settings.value.rules, ...data.rules }
          if (!Array.isArray(settings.value.rules.volume_tiers)) settings.value.rules.volume_tiers = []
        }
        if (data.company) settings.value.company = { ...settings.value.company, ...data.company }
        if (data.web) {
          settings.value.web = { ...settings.value.web, ...data.web }
          // Garantía N3XT: Asegurar que el catálogo siempre sea un array para evitar crash de UI
          if (!Array.isArray(settings.value.web.catalog)) {
              settings.value.web.catalog = []
          }
        }
        if (data.company_logo) settings.value.company_logo = data.company_logo
        if (Array.isArray(data.coupons)) settings.value.coupons = data.coupons
        couponUses.value = data.coupon_uses && typeof data.coupon_uses === 'object' ? data.coupon_uses : {}
        if (data.welcome_coupon_pct != null) settings.value.welcome_coupon_pct = Number(data.welcome_coupon_pct)
      }
    
      // Ensure company info exists
      if (!settings.value.company) {
        settings.value.company = { name: 'N3XT 3D TECHNOLOGY', nit: '', email: '', phone: '', address: '', slogan: 'Manufactura Digital Avanzada', website: '' }
      }
    } catch (err) {
      logger.error('Error settings:', err)
    }
  }

  const saveCoupons = async ({ coupons, welcomePct }: { coupons: any[]; welcomePct: number }) => {
    if (savingSettings.value) return
    savingSettings.value = true
    try {
      await api.post('/admin/settings', { settings: { coupons, welcome_coupon_pct: welcomePct } })
      settings.value.coupons = coupons
      settings.value.welcome_coupon_pct = welcomePct
      showNotify('Cupones guardados', 'success')
    } catch (err) {
      showNotify('No se pudieron guardar los cupones: ' + errorMessage(err), 'error')
    } finally {
      savingSettings.value = false
    }
  }

  const saveSettings = (silent = false, updatedData: Record<string, any> | null = null) => {
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
      
        // Use data from SettingsPanel if provided, otherwise from local state
        const payload = updatedData || JSON.parse(JSON.stringify(settings.value))
      
        const response = await api.post('/admin/settings', { settings: payload })
      
        if (response) {
          showNotify('Ajustes publicados con exito', 'success')
          if (response.settings) {
             settings.value = { ...settings.value, ...response.settings }
          }
        }
      } catch (err) {
        logger.error('Error crítico en guardado:', err)
        showNotify('Error al sincronizar: ' + (errorMessage(err) || 'Error de red'), 'error')
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

  const handleLogoUpload = async (event: Event & { target: HTMLInputElement | null }) => {
    const file = event.target?.files?.[0]
    if (!file) return

    const formData = new FormData()
    formData.append('logo', file)

    uploadingLogo.value = true
    showNotify('Subiendo el logo…', 'success')

    try {
      const res = await api.post('/admin/settings/logo', formData)
    
      // Actualizamos el logo en el estado reactivo con la ruta relativa
      if (res && res.logo_path) {
        settings.value.company_logo = res.logo_path
        showNotify('Logo actualizado', 'success')
      } else if (res && res.logo_url) {
        // Fallback: extraer la ruta relativa de la URL completa
        const urlParts = res.logo_url.split('/storage/');
        if (urlParts.length > 1) {
          const path = urlParts[1].split('?')[0];
          settings.value.company_logo = path;
        } else {
          settings.value.company_logo = res.logo_url;
        }
        showNotify('Logo actualizado', 'success')
      }
    } catch (err) {
      logger.error('Error Logo Upload:', err)
      showNotify('No se pudo subir el logo: ' + errorMessage(err), 'error')
    } finally {
      uploadingLogo.value = false
      // Limpiar input para permitir subir el mismo archivo si es necesario
      if (event.target) event.target.value = ''
    }
  }

  return {
    settings, couponUses, savingSettings, logoUrl, uploadingLogo,
    fetchSettings, saveSettings, saveCoupons, handleLogoUpload, globalSeoOptimizer,
  }
}
