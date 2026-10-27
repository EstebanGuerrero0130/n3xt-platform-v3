/**
 * Avisos del panel: notificación flotante, confirmación y alerta (un solo
 * diálogo en NotificationSystem). `showNotify` y `askConfirm` se comparten con
 * los módulos hijos por provide/inject.
 */
import { reactive, provide } from 'vue'

export type ConfirmHandler = (() => void | Promise<void>) | null

export function useAdminFeedback() {
  const notification = reactive({ show: false, message: '', type: 'success' })
  const confirmDialog = reactive({
    show: false,
    title: '',
    message: '',
    icon: '',
    onConfirm: null as ConfirmHandler,
    mode: 'confirm' as 'confirm' | 'alert',
  })

  const showNotify = (msg: string, type = 'success') => {
    notification.message = msg
    notification.type = type
    notification.show = true
    setTimeout(() => { notification.show = false }, 4000)
  }

  const openDialog = (mode: 'confirm' | 'alert', title: string, message: string, icon: string, onConfirm: ConfirmHandler) => {
    Object.assign(confirmDialog, { title, message, icon: icon || '', onConfirm, mode, show: true })
  }

  const askConfirm = (title: string, message: string, icon: string, onConfirm: () => void | Promise<void>) =>
    openDialog('confirm', title, message, icon, onConfirm)

  const showAlert = (title: string, message: string, icon = '') =>
    openDialog('alert', title, message, icon, null)

  provide('showNotify', showNotify)
  provide('askConfirm', askConfirm)

  return { notification, confirmDialog, showNotify, askConfirm, showAlert }
}
