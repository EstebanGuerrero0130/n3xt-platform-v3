/**
 * Simulador de precios del panel → cotización en PDF o pedido real.
 * Toma los datos del formulario del simulador (PriceSimulator) o, si faltan,
 * los del estado compartido `simulator`.
 */
import type { Ref } from 'vue'
import { api } from '../services/api'
import logger from '../utils/logger'

interface SimulationDeps {
  simulator: Record<string, any>
  simulatedResult: Ref<Record<string, any>>
  inventoryData: Ref<any[]>
  settings: Ref<Record<string, any>>
  submitting: Ref<boolean>
  modalState: { simulator: boolean }
  fetchOrders: () => Promise<void>
  showNotify: (msg: string, type?: string) => void
  handleDownloadQuotePDF: (order: Record<string, any>) => void
}

export function useSimulationActions({
  simulator, simulatedResult, inventoryData, settings, submitting, modalState, fetchOrders, showNotify, handleDownloadQuotePDF,
}: SimulationDeps) {
  const handleDownloadSimulationPDF = (formData?: any) => {
    const materialId = formData?.material_id || simulator.material_id
    if (!materialId) {
      showNotify('Selecciona un material para generar la cotizacion.', 'warning')
      return
    }
  
    // Si viene data del PriceSimulator, la usamos; si no, del composable
    const mat = inventoryData.value.find((m: any) => m.id === materialId)
  
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

  const handleConvertSimulationToOrder = async (formData?: any) => {
    if (submitting.value) return
    submitting.value = true
    try {
      const materialId = formData?.material_id || simulator.material_id
      const material = inventoryData.value.find((m: any) => m.id === materialId)
      const density = material?.density || 1.25
      const weightG = formData?.weight_g != null ? formData.weight_g : (simulator.weight_g || 0)
      const estimatedVolume = (weightG / density) * 1000

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
        extra_items: extrasData.map((e: any) => ({ material_id: e.id, qty: e.qty })),
        cost_snapshot: {
          settings: JSON.parse(JSON.stringify(settings.value)),
          material_cost_per_kg: material?.cost_per_kg || 0,
          material_density: density
        }
      })
    
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
      showNotify('Error al convertir: ' + (err instanceof Error ? err.message : String(err)), 'error')
    } finally {
      submitting.value = false
    }
  }

  return { handleDownloadSimulationPDF, handleConvertSimulationToOrder }
}
