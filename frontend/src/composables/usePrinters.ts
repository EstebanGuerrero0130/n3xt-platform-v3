/**
 * Maquinaria del panel: crear, editar, cambiar estado, mantenimiento y borrar
 * impresoras. Recibe del panel cómo recargar la lista y cómo avisar.
 */
import { ref, reactive, type Ref } from 'vue'
import { api } from '../services/api'

interface PrinterDeps {
  /** Recarga la lista de máquinas del panel */
  fetchPrinters: () => Promise<void>
  showNotify: (msg: string, type?: string) => void
  showAlert: (title: string, message: string, icon?: string) => void
  askConfirm: (title: string, message: string, icon: string, onConfirm: () => void | Promise<void>) => void
  /** Modales del panel (newPrinter, editPrinter) */
  modalState: { newPrinter: boolean; editPrinter: boolean }
  /** Bloqueo compartido de envíos del panel */
  submitting: Ref<boolean>
}

const emptyPrinter = () => ({
  id: '' as string | number, name: '', model: '', technology: 'FDM', status: 'idle',
  maintenance_interval_h: 200, next_maintenance: '', total_hours_run: 0, maintenance_notes: '',
})

const errorMessage = (err: unknown) => (err instanceof Error ? err.message : String(err))

export function usePrinters({ fetchPrinters, showNotify, showAlert, askConfirm, modalState, submitting }: PrinterDeps) {
  const editingPrinter = ref(emptyPrinter())
  const newPrinter = reactive({ name: '', model: '', technology: 'FDM' })

  /** Ejecuta una acción sobre una máquina y recarga la lista. */
  const run = async (action: () => Promise<unknown>, done: () => void, fail: (msg: string) => void) => {
    try {
      await action()
      await fetchPrinters()
      done()
    } catch (err) {
      fail(errorMessage(err))
    }
  }

  const handleEditPrinter = (printer: ReturnType<typeof emptyPrinter>) => {
    editingPrinter.value = { ...printer }
    modalState.editPrinter = true
  }

  const handleUpdatePrinterStatus = ({ id, status }: { id: string | number; status: string }) =>
    run(() => api.patch(`/admin/printers/${id}`, { status }),
      () => showNotify(`Estado actualizado: ${status}`, 'success'),
      msg => showNotify('Error: ' + msg, 'error'))

  const handleMaintenanceComplete = (id: string | number) =>
    run(() => api.post(`/admin/printers/${id}/maintenance-complete`, {}),
      () => showAlert('Puesta a Punto', 'Mantenimiento registrado. Horas reiniciadas.', ''),
      msg => showAlert('Error', 'No se pudo registrar el mantenimiento: ' + msg, ''))

  const handleResetPrinter = (id: string | number) =>
    run(() => api.post(`/admin/printers/${id}/reset`, {}),
      () => showNotify('Máquina reseteada a estado Libre', 'success'),
      msg => showNotify('Error: ' + msg, 'error'))

  const handleDeletePrinter = (id: string | number) => {
    askConfirm(
      'Eliminar Impresora',
      '¿Estás seguro de que deseas retirar esta máquina de la granja? Esta acción no se puede deshacer.',
      '',
      () => run(() => api.delete(`/admin/printers/${id}`),
        () => showNotify('Impresora eliminada', 'success'),
        msg => showNotify('Error: ' + msg, 'error')),
    )
  }

  /** Crear y editar usan el bloqueo de envíos del panel y cierran su modal. */
  const submit = async (action: () => Promise<unknown>, modal: 'newPrinter' | 'editPrinter', okMsg: string, errPrefix: string) => {
    if (submitting.value) return
    submitting.value = true
    try {
      await action()
      modalState[modal] = false
      await fetchPrinters()
      showNotify(okMsg, 'success')
    } catch (err) {
      showNotify(errPrefix + errorMessage(err), 'error')
    } finally {
      submitting.value = false
    }
  }

  const savePrinterEdit = (payload?: ReturnType<typeof emptyPrinter>) => {
    const data = payload || editingPrinter.value
    return submit(() => api.patch(`/admin/printers/${data.id}`, data), 'editPrinter', 'Impresora actualizada con exito', 'Error al guardar: ')
  }

  const addPrinter = (payload?: typeof newPrinter) =>
    submit(() => api.post('/admin/printers', payload || newPrinter), 'newPrinter', 'Impresora añadida', 'Error: ')

  return {
    editingPrinter,
    newPrinter,
    handleEditPrinter,
    handleUpdatePrinterStatus,
    handleMaintenanceComplete,
    handleResetPrinter,
    handleDeletePrinter,
    savePrinterEdit,
    addPrinter,
  }
}
