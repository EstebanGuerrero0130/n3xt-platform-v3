/**
 * N3XT Image Upload Composable
 * Maneja la subida de im\u00e1genes a Cloudinary v\u00eda proxy del backend
 */
import { type Ref } from 'vue'
import { api } from '../services/api'

interface UseUploadsOptions {
  settings: Ref<Record<string, any>>
  showNotify?: (msg: string, type?: string) => void
}

export function useUploads({ settings, showNotify }: UseUploadsOptions) {
  const triggerCardUpload = (idx: number) => {
    const input = document.getElementById(`card-upload-${idx}`)
    if (input) (input as HTMLInputElement).click()
  }

  const triggerProductUpload = (idx: number) => {
    const input = document.getElementById(`product-upload-${idx}`)
    if (input) (input as HTMLInputElement).click()
  }

  const triggerNewsUpload = (idx: number) => {
    const input = document.getElementById(`news-upload-${idx}`)
    if (input) (input as HTMLInputElement).click()
  }

  const triggerPostUpload = (idx: number) => {
    const input = document.getElementById(`post-upload-${idx}`)
    if (input) (input as HTMLInputElement).click()
  }

  const handleImageUploadSEO = async (event: any, targetObj: Record<string, any>, nameField: string) => {
    const file = event.target.files[0]
    if (!file) return

    if (showNotify) showNotify('SISTEMA N3XT: Optimizando SEO y subiendo imagen...', 'success')

    const sanitizedName = (targetObj[nameField] || 'n3xt_asset')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9]/g, '_')
      .substring(0, 50)

    const publicId = `n3xt_${sanitizedName}_${Date.now().toString().slice(-4)}`

    const formData = new FormData()
    formData.append('file', file)
    formData.append('public_id', publicId)
    formData.append('cloud_name', settings.value.web?.cloudinary_name || 'dplcy7vbm')
    formData.append('upload_preset', 'ml_default')

    try {
      const data = await api.post('/admin/upload-image', formData)

      if (data && data.secure_url) {
        targetObj.image = data.secure_url
        if (!targetObj.alt) targetObj.alt = `Imagen de ${targetObj[nameField]} - N3XT 3D Systems`
        if (showNotify) showNotify('MANUFACTURA DIGITAL: Imagen con SEO optimizado', 'success')
      } else {
        throw new Error(data?.error || 'Error en subida')
      }
    } catch (_err) {
      if (showNotify) showNotify('ERROR SEO: Revisa tu conexi\u00f3n con el servidor.', 'error')
    }
  }

  return {
    triggerCardUpload,
    triggerProductUpload,
    triggerNewsUpload,
    triggerPostUpload,
    handleImageUploadSEO
  }
}
