/**
 * Inventario del panel: crear, editar, sumar stock y borrar materiales.
 * Recibe del panel la lista actual, cómo recargarla y cómo avisar.
 */
import { ref, reactive, type Ref } from 'vue'
import { api } from '../services/api'

interface MaterialDeps {
  inventoryData: Ref<any[]>
  /** Recarga materiales e inventario */
  fetchInventory: () => Promise<void>
  showNotify: (msg: string, type?: string) => void
  askConfirm: (title: string, message: string, icon: string, onConfirm: () => void | Promise<void>) => void
  /** Modales del panel (newMaterial, editMaterial) */
  modalState: { newMaterial: boolean; editMaterial: boolean }
  /** Bloqueo compartido de envíos del panel */
  submitting: Ref<boolean>
}

const errorMessage = (err: unknown) => (err instanceof Error ? err.message : String(err))

export function useMaterials({ inventoryData, fetchInventory, showNotify, askConfirm, modalState, submitting }: MaterialDeps) {
  const editingMaterial = ref<Record<string, any>>({ id: '', name: '', cost_per_kg: 0, density: 1.24, color: '#cccccc', location: '' })
  const newMaterial = reactive({
    id: '', name: '', category: 'FDM', type: 'material', unit: 'g', cost_per_kg: 0, density: 1.24, color: '#000000',
    initial_stock: 1000, low_stock_threshold: 200, package_price: null, package_qty: null, package_units: 1,
  })

  const handleEditMaterial = (mat: any) => {
    editingMaterial.value = { ...mat, ...mat.inventory, id: mat.id }
    modalState.editMaterial = true
  }

  /** Suma al stock actual (no lo reemplaza) para no pisar descuentos recientes. */
  const handleUpdateStock = async (matId: string, added: number) => {
    if (!added) return
    const mat = inventoryData.value.find(m => m.id === matId)
    if (!mat) return
    try {
      const current = mat.inventory?.stock_available || 0
      await api.post(`/materials/${matId}/stock`, { stock_available: current + added })
      await fetchInventory()
      showNotify(`Stock incrementado: +${added}${mat.unit || ''} en ${mat.name}`, 'success')
    } catch (err) {
      showNotify('Error al actualizar stock: ' + errorMessage(err), 'error')
    }
  }

  const handleDeleteMaterial = (id: string) => {
    askConfirm('¿Eliminar Material?', 'Se eliminará el insumo y su existencia en inventario. No se puede deshacer.', '', async () => {
      try {
        await api.delete(`/materials/${id}`)
        await fetchInventory()
        showNotify('Inventario actualizado: Material eliminado', 'success')
      } catch (err) {
        showNotify('Error: ' + errorMessage(err), 'error')
      }
    })
  }

  const submit = async (action: () => Promise<unknown>, modal: 'newMaterial' | 'editMaterial', okMsg: string, errPrefix: string) => {
    if (submitting.value) return
    submitting.value = true
    try {
      await action()
      modalState[modal] = false
      await fetchInventory()
      showNotify(okMsg, 'success')
    } catch (err) {
      showNotify(errPrefix + errorMessage(err), 'error')
    } finally {
      submitting.value = false
    }
  }

  const updateMaterial = (payload?: Record<string, any>) => {
    const data = payload || editingMaterial.value
    return submit(() => api.patch(`/materials/${data.id}`, data), 'editMaterial', 'Material actualizado con éxito', 'Error al guardar: ')
  }

  const addMaterial = (payload?: Record<string, any>) =>
    submit(() => api.post('/materials', payload || newMaterial), 'newMaterial', 'Material creado correctamente', 'Error al crear material: ')

  return { editingMaterial, newMaterial, handleEditMaterial, handleUpdateStock, handleDeleteMaterial, updateMaterial, addMaterial }
}
