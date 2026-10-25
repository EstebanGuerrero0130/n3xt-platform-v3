import Lenis from 'lenis'
import type { Router } from 'vue-router'
import { hasFinePointer, prefersReducedMotion } from './useMotion'

/**
 * Scroll suave con Lenis, solo donde aporta: ratón/trackpad y sin
 * prefers-reduced-motion. En táctil se deja el scroll nativo del sistema.
 *
 * - Usa el scroll nativo de la ventana (no transforma el contenido), así que
 *   position: sticky, IntersectionObserver y el scrollBehavior del router
 *   siguen funcionando igual.
 * - Se detiene mientras hay un modal/menú que bloquea el body
 *   (clase overflow-hidden o style.overflow = hidden) y se reanuda después.
 * - Canvas (visor 3D), diálogos y elementos con scroll propio reciben la rueda
 *   de forma nativa.
 */

let lenis: Lenis | null = null

export const getLenis = () => lenis

const isBodyLocked = () => {
  const b = document.body
  return b.classList.contains('overflow-hidden') || b.style.overflow === 'hidden'
}

const create = () => {
  if (lenis) return
  lenis = new Lenis({
    autoRaf: true,
    lerp: 0.11,
    wheelMultiplier: 1,
    smoothWheel: true,
    syncTouch: false,
    allowNestedScroll: true,
    stopInertiaOnNavigate: true,
    prevent: (node: HTMLElement) =>
      node.tagName === 'CANVAS' ||
      node.tagName === 'TEXTAREA' ||
      node.getAttribute?.('role') === 'dialog' ||
      node.getAttribute?.('aria-modal') === 'true',
  })
  if (isBodyLocked()) lenis.stop()
}

const destroy = () => {
  lenis?.destroy()
  lenis = null
}

export function initSmoothScroll(router: Router) {
  if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') return

  const update = () => {
    if (!prefersReducedMotion() && hasFinePointer()) create()
    else destroy()
  }
  update()
  window.matchMedia('(prefers-reduced-motion: reduce)').addEventListener?.('change', update)
  window.matchMedia('(hover: hover) and (pointer: fine)').addEventListener?.('change', update)

  // Pausa durante bloqueos de scroll (menú móvil, lightbox, modales)
  new MutationObserver(() => {
    if (!lenis) return
    if (isBodyLocked()) lenis.stop()
    else lenis.start()
  }).observe(document.body, { attributes: true, attributeFilter: ['class', 'style'] })

  // Corta la inercia al navegar para que el scrollBehavior del router mande
  router.beforeEach(() => {
    // stop/start corta la inercia sin usar el reset() privado de Lenis
    lenis?.stop()
    lenis?.start()
  })
}
