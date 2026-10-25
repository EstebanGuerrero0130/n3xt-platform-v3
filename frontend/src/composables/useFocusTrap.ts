/**
 * Foco accesible para modales, lightbox y menús:
 * - al abrir guarda el elemento enfocado y mueve el foco dentro del contenedor
 * - Tab / Shift+Tab quedan atrapados dentro
 * - Escape llama a `onEscape`
 * - opcionalmente bloquea el scroll del body
 * - al cerrar devuelve el foco a donde estaba
 */
import { nextTick, onBeforeUnmount, watch, type Ref } from 'vue'

interface FocusTrapOptions {
  onEscape?: () => void
  /** Bloquear el scroll de la página mientras está abierto (por defecto sí). */
  lockScroll?: boolean
}

const FOCUSABLE = [
  'a[href]', 'button:not([disabled])', 'input:not([disabled]):not([type="hidden"])',
  'select:not([disabled])', 'textarea:not([disabled])', '[tabindex]:not([tabindex="-1"])',
].join(',')

export function useFocusTrap(container: Ref<HTMLElement | null>, active: Ref<boolean>, options: FocusTrapOptions = {}) {
  const { onEscape, lockScroll = true } = options
  let previous: HTMLElement | null = null

  const focusables = () =>
    [...(container.value?.querySelectorAll<HTMLElement>(FOCUSABLE) ?? [])].filter(el => el.offsetParent !== null || el === document.activeElement)

  const onKeydown = (e: KeyboardEvent) => {
    if (e.key === 'Escape' && onEscape) {
      e.stopPropagation()
      onEscape()
      return
    }
    if (e.key !== 'Tab' || !container.value) return
    const items = focusables()
    if (!items.length) {
      e.preventDefault()
      container.value.focus()
      return
    }
    const first = items[0]
    const last = items[items.length - 1]
    const current = document.activeElement as HTMLElement | null
    if (e.shiftKey && (current === first || !container.value.contains(current))) {
      e.preventDefault()
      last.focus()
    } else if (!e.shiftKey && (current === last || !container.value.contains(current))) {
      e.preventDefault()
      first.focus()
    }
  }

  const activate = async () => {
    previous = document.activeElement as HTMLElement | null
    document.addEventListener('keydown', onKeydown, true)
    if (lockScroll) document.body.style.overflow = 'hidden'
    await nextTick()
    const el = container.value
    if (!el) return
    const target = el.querySelector<HTMLElement>('[data-autofocus]') ?? focusables()[0] ?? el
    if (target === el && !el.hasAttribute('tabindex')) el.setAttribute('tabindex', '-1')
    target.focus({ preventScroll: true })
  }

  const deactivate = () => {
    document.removeEventListener('keydown', onKeydown, true)
    if (lockScroll) document.body.style.overflow = ''
    if (previous && document.contains(previous)) previous.focus({ preventScroll: true })
    previous = null
  }

  watch(active, (on) => (on ? activate() : deactivate()), { immediate: active.value })
  onBeforeUnmount(() => { if (active.value) deactivate() })
}
