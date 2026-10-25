import type { Directive } from 'vue'
import { hasFinePointer, prefersReducedMotion } from '../composables/useMotion'

/**
 * v-spotlight — actualiza las variables CSS --mx / --my (px relativos a la
 * tarjeta) con la posición del cursor. El brillo radial lo pinta
 * .surface-card-interactive en style.css; aquí solo se mueven dos variables,
 * como mucho una vez por frame, y únicamente con ratón/trackpad.
 */

interface SpotState {
  move: (e: PointerEvent) => void
  leave: () => void
  raf: number
}

const STATE = new WeakMap<HTMLElement, SpotState>()

export const vSpotlight: Directive<HTMLElement> = {
  mounted(el) {
    if (!hasFinePointer() || prefersReducedMotion()) return
    let x = 0
    let y = 0
    const state: SpotState = {
      raf: 0,
      move: (e: PointerEvent) => {
        if (e.pointerType !== 'mouse' && e.pointerType !== 'pen') return
        x = e.clientX
        y = e.clientY
        if (state.raf) return
        state.raf = requestAnimationFrame(() => {
          state.raf = 0
          const r = el.getBoundingClientRect()
          el.style.setProperty('--mx', `${Math.round(x - r.left)}px`)
          el.style.setProperty('--my', `${Math.round(y - r.top)}px`)
        })
      },
      leave: () => {
        if (state.raf) cancelAnimationFrame(state.raf)
        state.raf = 0
      },
    }
    el.addEventListener('pointermove', state.move, { passive: true })
    el.addEventListener('pointerleave', state.leave, { passive: true })
    STATE.set(el, state)
  },
  beforeUnmount(el) {
    const state = STATE.get(el)
    if (!state) return
    if (state.raf) cancelAnimationFrame(state.raf)
    el.removeEventListener('pointermove', state.move)
    el.removeEventListener('pointerleave', state.leave)
    STATE.delete(el)
  },
}
