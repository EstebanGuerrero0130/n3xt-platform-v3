interface Notification {
  show: boolean
  message: string
  type: string
}

interface ConfirmDialog {
  show: boolean
  title: string
  message: string
  icon: string
  onConfirm: (() => Promise<void>) | null
  mode: 'confirm' | 'alert'
}

interface ModalState {
  manualOrder: boolean
  newMaterial: boolean
  newPrinter: boolean
  printerStatus: boolean
  orderDetails: boolean
  simulator: boolean
  editMaterial: boolean
  editPrinter: boolean
  shipping: boolean
  shipping_details: boolean
}

/**
 * N3XT UI Composable
 * Maneja estados de UI: notificaciones, diálogos de confirmación, modales
 */
export function useUI() {
  // --- Premium UI Feedback ---
  const notification: Notification = reactive({ show: false, message: '', type: 'success' })
  
  const confirmDialog: ConfirmDialog = reactive({ 
    show: false, 
    title: '', 
    message: '', 
    icon: '', 
    onConfirm: null, 
    mode: 'confirm' 
  })

  // --- Modals State ---
  const modalState: ModalState = reactive({
    manualOrder: false,
    newMaterial: false,
    newPrinter: false,
    printerStatus: false,
    orderDetails: false,
    simulator: false,
    editMaterial: false,
    editPrinter: false,
    shipping: false,
    shipping_details: false
  })

  const showNotify = (msg: string, type: string = 'success'): void => {
    notification.message = msg
    notification.type = type
    notification.show = true
    setTimeout(() => { notification.show = false }, 4000)
  }

  const askConfirm = (title: string, message: string, icon: string, onConfirm: (() => Promise<void>) | null): void => {
    confirmDialog.title = title
    confirmDialog.message = message
    confirmDialog.icon = icon || ''
    confirmDialog.onConfirm = onConfirm
    confirmDialog.mode = 'confirm'
    confirmDialog.show = true
  }

  const showAlert = (title: string, message: string, icon: string = ''): void => {
    confirmDialog.title = title
    confirmDialog.message = message
    confirmDialog.icon = icon || ''
    confirmDialog.onConfirm = null
    confirmDialog.mode = 'alert'
    confirmDialog.show = true
  }

  const handleConfirm = async () => {
    if (confirmDialog.onConfirm) {
      await confirmDialog.onConfirm()
    }
    confirmDialog.show = false
  }

  const closeAllModals = () => {
    Object.keys(modalState).forEach(k => modalState[k] = false)
  }

  return {
    notification,
    confirmDialog,
    modalState,
    showNotify,
    askConfirm,
    showAlert,
    handleConfirm,
    closeAllModals
  }
}
