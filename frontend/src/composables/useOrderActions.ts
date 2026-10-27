/**
 * Pedidos en el panel: cambiar de etapa (y pedir la guía al enviar), confirmar
 * el pago, pasar a impresión en una máquina, descargar archivos, ver y borrar.
 * El stock y las horas de máquina los maneja el servidor.
 */
import { ref, type Ref } from 'vue'
import { api } from '../services/api'
import logger from '../utils/logger'

type Order = Record<string, any>

interface OrderDeps {
  orders: Ref<Order[]>
  printers: Ref<Order[]>
  modalState: { shipping: boolean; printerStatus: boolean; orderDetails: boolean }
  /** Recarga todo el panel en silencio */
  syncAll: (silent?: boolean) => Promise<void>
  fetchOrders: () => Promise<void>
  showNotify: (msg: string, type?: string) => void
  askConfirm: (title: string, message: string, icon: string, onConfirm: () => void | Promise<void>) => void
}

export const STATUS_NAMES: Record<string, string> = {
  pending: 'por revisar', printing: 'en impresión', 'post-processing': 'en acabado',
  completed: 'listo', shipped: 'enviado', cancelled: 'cancelado',
}

const errorMessage = (err: unknown) => (err instanceof Error ? err.message : String(err))

export function useOrderActions({ orders, printers, modalState, syncAll, fetchOrders, showNotify, askConfirm }: OrderDeps) {
  const orderToShip = ref<Order | null>(null)
  const trackingGuide = ref('')
  const trackingCarrier = ref('')
  const selectedOrderForPrinter = ref<Order | null>(null)
  const selectedOrderDetails = ref<Order | null>(null)
  const assigning = ref(false)

  const findOrder = (id: unknown) => orders.value.find(o => o.id === id)

  const handleStatusUpdate = async ({ orderId, status, tracking_guide, tracking_carrier }: { orderId: number; status: string; tracking_guide?: string; tracking_carrier?: string }) => {
    // Enviar sin guía: primero se pide la guía
    if (status === 'shipped' && !tracking_guide) {
      orderToShip.value = findOrder(orderId) ?? null
      trackingGuide.value = ''
      trackingCarrier.value = ''
      modalState.shipping = true
      return
    }
    try {
      const order = findOrder(orderId)
      await api.patch(`/admin/orders/${orderId}/status`, { status, tracking_guide, tracking_carrier })
      // Liberar la máquina (las horas ya las suma el servidor)
      if ((status === 'completed' || status === 'cancelled') && order?.printer_id) {
        await api.patch(`/admin/printers/${order.printer_id}`, { status: 'idle' })
      }
      showNotify(`Pedido #${orderId}: ${STATUS_NAMES[status] || status}`, 'success')
      syncAll(true)
    } catch (err) {
      showNotify('Error al actualizar estado: ' + errorMessage(err), 'error')
    }
  }

  const confirmShipping = async () => {
    if (!trackingGuide.value || !trackingCarrier.value) {
      showNotify('Elige la transportadora y escribe el número de guía.', 'warning')
      return
    }
    if (!orderToShip.value) return
    await handleStatusUpdate({
      orderId: orderToShip.value.id,
      status: 'shipped',
      tracking_guide: trackingGuide.value,
      tracking_carrier: trackingCarrier.value,
    })
    modalState.shipping = false
  }

  /** El pago confirmado no se revierte: el servidor ya descontó el inventario. */
  const handleTogglePaid = async (orderId: number) => {
    const order = findOrder(orderId)
    if (!order) return
    if (order.is_paid) {
      showNotify('El pago ya está confirmado y no puede revertirse.', 'warning')
      return
    }
    try {
      await api.post(`/admin/orders/${orderId}/toggle-paid`)
      showNotify('Pedido marcado como pagado', 'success')
      syncAll(true)
    } catch (err) {
      showNotify('Error al procesar pago: ' + errorMessage(err), 'error')
    }
  }

  const handleDeleteOrder = (id: number) => {
    askConfirm('¿Eliminar Orden?', 'Esta acción es permanente y borrará todos los registros de producción y finanzas asociados.', '', async () => {
      try {
        await api.delete(`/admin/orders/${id}`)
        await fetchOrders()
        showNotify('Orden eliminada correctamente', 'success')
      } catch (err) {
        showNotify('Error: ' + errorMessage(err), 'error')
      }
    })
  }

  /** Descarga la pieza `index` del pedido con la sesión del panel. */
  const downloadFile = async (order: Order, index = 0) => {
    if (!order.file_path && !order.id) {
      showNotify('No hay archivo digital asociado a este pedido.', 'warning')
      return
    }
    try {
      const res = await fetch(`${api.baseUrl}/admin/orders/${order.id}/download?model=${index}`, {
        credentials: 'include',
        headers: { Accept: 'application/json' },
      })
      if (!res.ok) {
        showNotify(res.status === 404 ? 'Este pedido no tiene archivo 3D guardado.' : 'No se pudo descargar el archivo.', 'warning')
        return
      }
      const url = window.URL.createObjectURL(await res.blob())
      const a = document.createElement('a')
      a.href = url
      a.download = order.models?.[index]?.original_filename || order.original_filename || `pedido_${order.id}.stl`
      a.click()
      showNotify('Iniciando descarga del archivo...', 'success')
    } catch (err) {
      showNotify('Error en la descarga: ' + errorMessage(err), 'error')
    }
  }

  const handleAssignment = (order: Order) => {
    selectedOrderForPrinter.value = order
    modalState.printerStatus = true
  }

  /** Pasa el pedido a impresión; el servidor marca la máquina como ocupada. */
  const confirmAssignment = async (printerId: number) => {
    const order = selectedOrderForPrinter.value
    if (assigning.value || !order) return
    assigning.value = true
    try {
      await api.patch(`/admin/orders/${order.id}/status`, { status: 'printing', printer_id: printerId })
      modalState.printerStatus = false
      await syncAll(true)
      const printer = printers.value.find(p => p.id === printerId)
      showNotify(`Pedido #${order.id} en impresión${printer ? ' en ' + printer.name : ''}`, 'success')
    } catch (err) {
      showNotify('No se pudo pasar a impresión: ' + errorMessage(err), 'error')
    } finally {
      assigning.value = false
    }
  }

  // Cerrar sin asignar: el tablero vuelve a mostrar el pedido donde estaba
  const cancelAssignment = () => {
    modalState.printerStatus = false
    fetchOrders()
  }

  // El desglose de costos lo calcula OrderDetailModal (calcOrderDetailBreakdown)
  const openOrderDetails = (order: Order) => {
    selectedOrderDetails.value = order
    modalState.orderDetails = true
  }

  // Tras agregar un insumo o guardar la guía: recarga y vuelve a abrir el pedido con datos frescos
  const refreshOrderDetails = async () => {
    const id = selectedOrderDetails.value?.id
    await fetchOrders()
    const fresh = findOrder(id)
    if (fresh) openOrderDetails(fresh)
    else logger.debug('Pedido no encontrado al refrescar', id)
  }

  return {
    orderToShip, trackingGuide, trackingCarrier, selectedOrderForPrinter, selectedOrderDetails, assigning,
    handleStatusUpdate, confirmShipping, handleTogglePaid, handleDeleteOrder, downloadFile,
    handleAssignment, confirmAssignment, cancelAssignment, openOrderDetails, refreshOrderDetails,
  }
}
