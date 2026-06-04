/**
 * N3XT Printers Composable
 * API wrappers para gestionar impresoras 3D
 */
import { ref } from 'vue'
import { api } from '../services/api'

export function usePrinters() {
  const printerToDelete = ref<string | null>(null)
  const selectedPrinter = ref<any>(null)
  const editingPrinter = ref({
    id: '', name: '', model: '', technology: 'FDM' as string,
    status: 'idle', maintenance_interval_h: 200, next_maintenance: '',
    total_hours_run: 0, maintenance_notes: ''
  })

  const handleAddPrinter = () => {
    editingPrinter.value = { id: '', name: '', model: '', technology: 'FDM', status: 'idle', maintenance_interval_h: 200, next_maintenance: '', total_hours_run: 0, maintenance_notes: '' }
  }

  const apiUpdatePrinter = async (id: string, data: any) => {
    return await api.patch(`/admin/printers/${id}`, data)
  }

  const apiDeletePrinter = async (id: string) => {
    return await api.delete(`/admin/printers/${id}`)
  }

  const apiMaintenanceComplete = async (id: string) => {
    return await api.post(`/admin/printers/${id}/maintenance-complete`, {})
  }

  const apiResetPrinter = async (id: string) => {
    return await api.post(`/admin/printers/${id}/reset`, {})
  }

  return {
    printerToDelete,
    selectedPrinter,
    editingPrinter,
    handleAddPrinter,
    apiUpdatePrinter,
    apiDeletePrinter,
    apiMaintenanceComplete,
    apiResetPrinter
  }
}
