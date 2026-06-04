/**
 * N3XT Orders Composable
 * Gestiona pedidos: fetch, status updates, pagos, etc.
 */
import { ref, type Ref } from 'vue'
import { api } from '../services/api'
import logger from '../utils/logger'

interface UseOrdersOptions {
  showNotify?: (msg: string, type?: string) => void
  askConfirm?: (title: string, message: string, icon: string, onConfirm: () => Promise<void>) => void
}

interface OrderData {
  id: number | string
  status?: string
  material_id?: string
  printer_id?: string
  is_paid?: boolean
  estimated_weight_g?: number | string
  estimated_duration_h?: number | string
  extra_items?: Array<{ material_id?: string; name?: string }>
  tracking_guide?: string
  tracking_carrier?: string
  [key: string]: any
}

export function useOrders({ showNotify, askConfirm }: UseOrdersOptions) {
  const orders = ref([])
  const selectedOrderForPrinter = ref(null)
  const selectedOrderDetails = ref(null)
  const orderToShip = ref(null)
  const trackingGuide = ref('')
  const trackingCarrier = ref('')
  const orderToPrint = ref(null)
  const showAddExtraForm = ref(false)
  const newExtra = ref({ material_id: '', qty: 1 })

  const fetchOrders = async () => {
    try {
      const res = await api.get('/admin/orders')
      orders.value = Array.isArray(res) ? res : (res?.data || [])
    } catch (err) {
      logger.error('Error fetching orders:', err)
      orders.value = []
    }
  }

  const handleStatusUpdate = async ({ orderId, status, tracking_guide, tracking_carrier }: {
    orderId: number | string
    status: string
    tracking_guide?: string
    tracking_carrier?: string
  }, { printers, inventoryData, settings }: {
    printers: Ref<Array<Record<string, any>>>
    inventoryData: Ref<Array<Record<string, any>>>
    settings: Ref<Record<string, any>>
  }) => {
    // [ACTION] Actualizando estado
    if (status === 'shipped' && !tracking_guide) {
      orderToShip.value = orders.value.find(o => o.id === orderId)
      trackingGuide.value = ''
      trackingCarrier.value = ''
      return 'await_shipping'
    }

    try {
      const order = orders.value.find(o => o.id === orderId)

      // Deducción Automática de Stock al completar
      if (status === 'completed' && order && order.material_id) {
        const material = inventoryData.value.find(m => m.id === order.material_id)
        if (material) {
          const usedWeight = parseFloat(order.estimated_weight_g || 0)
          const currentStock = parseFloat(material.stock_available || 0)
          const newStock = Math.max(0, currentStock - usedWeight)
          // Stock deducido automáticamente
          await api.post(`/materials/${material.id}/stock`, { stock_available: newStock })
        }
      }

      await api.patch(`/admin/orders/${orderId}/status`, { 
        status, 
        tracking_guide, 
        tracking_carrier 
      })

      // Liberación de impresora
      if ((status === 'completed' || status === 'cancelled') && order && order.printer_id) {
        const printer = printers.value.find(p => p.id === order.printer_id)
        const updateData = { status: 'idle' }
        if (status === 'completed' && printer) {
          updateData.total_hours_run = (parseFloat(printer.total_hours_run) || 0) + (parseFloat(order.estimated_duration_h) || 0)
        }
        await api.patch(`/admin/printers/${order.printer_id}`, updateData)
      }

      if (showNotify) showNotify('Estado actualizado: ' + status.toUpperCase(), 'success')
      return 'success'
    } catch (err) {
      if (showNotify) showNotify('Error al actualizar estado: ' + err.message, 'error')
      return 'error'
    }
  }

  const handleTogglePaid = async (orderId) => {
    // [ACTION] Toggle Pago
    try {
      const order = orders.value.find(o => o.id === orderId)
      if (!order) return
      const newState = order.is_paid ? 0 : 1
      await api.post(`/admin/orders/${orderId}/toggle-paid`, { is_paid: newState })
      if (showNotify) showNotify(newState ? 'Pedido marcado como pagado' : 'Pago pendiente', 'success')
    } catch (err) {
      if (showNotify) showNotify('Error al procesar pago: ' + err.message, 'error')
    }
  }

  const handleDeleteOrder = (id) => {
    if (!askConfirm) return
    askConfirm(
      '¿Eliminar Orden?',
      'Esta acción es permanente y borrará todos los registros de producción y finanzas asociados.',
      '',
      async () => {
        try {
          await api.delete(`/admin/orders/${id}`)
          await fetchOrders()
          if (showNotify) showNotify('Orden eliminada correctamente', 'success')
        } catch (err) {
          if (showNotify) showNotify('Error: ' + err.message, 'error')
        }
      }
    )
  }

  const confirmShipping = async () => {
    if (!trackingGuide.value || !trackingCarrier.value) {
      if (showNotify) showNotify('Por favor ingrese el numero de guia y la transportadora.', 'warning')
      return
    }
    await handleStatusUpdate({ 
      orderId: orderToShip.value.id, 
      status: 'shipped', 
      tracking_guide: trackingGuide.value,
      tracking_carrier: trackingCarrier.value
    }, { printers: ref([]), inventoryData: ref([]), settings: ref({}) })
    orderToShip.value = null
  }

  const saveTrackingGuide = async () => {
    if (!selectedOrderDetails.value) return
    try {
      await api.patch(`/admin/orders/${selectedOrderDetails.value.id}/status`, {
        tracking_guide: selectedOrderDetails.value.tracking_guide,
        tracking_carrier: selectedOrderDetails.value.tracking_carrier
      })
      if (showNotify) showNotify('Guía de seguimiento guardada con éxito', 'success')
      await fetchOrders()
    } catch (err) {
      if (showNotify) showNotify('Error al guardar guía: ' + err.message, 'error')
    }
  }

  const handleAddExtra = async () => {
    if (!newExtra.value.material_id || newExtra.value.qty <= 0) return

    const submitExtra = async () => {
      try {
        await api.post(`/admin/orders/${selectedOrderDetails.value.id}/extras`, newExtra.value)
        await fetchOrders()
        const updated = orders.value.find(o => o.id === selectedOrderDetails.value.id)
        if (updated) {
          selectedOrderDetails.value = JSON.parse(JSON.stringify(updated))
        }
        showAddExtraForm.value = false
        newExtra.value = { material_id: '', qty: 1 }
        if (showNotify) showNotify('Extra agregado e inventario actualizado', 'success')
      } catch (err) {
        if (showNotify) showNotify('Error: ' + err.message, 'error')
      }
    }

    if (selectedOrderDetails.value.extra_items) {
      const existing = selectedOrderDetails.value.extra_items.find(e => e.material_id === newExtra.value.material_id)
      if (existing && askConfirm) {
        askConfirm(
          'Ítem Duplicado',
          `El consumible "${existing.name}" ya está registrado. ¿Deseas añadirlo nuevamente?`,
          '',
          submitExtra
        )
        return
      }
    }

    await submitExtra()
  }

  return {
    orders,
    selectedOrderForPrinter,
    selectedOrderDetails,
    orderToShip,
    trackingGuide,
    trackingCarrier,
    orderToPrint,
    showAddExtraForm,
    newExtra,
    fetchOrders,
    handleStatusUpdate,
    handleTogglePaid,
    handleDeleteOrder,
    confirmShipping,
    saveTrackingGuide,
    handleAddExtra
  }
}
