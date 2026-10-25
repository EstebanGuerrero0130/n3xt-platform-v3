import type { Directive, DirectiveBinding } from 'vue'
import { prefersReducedMotion } from '../composables/useMotion'

/**
 * v-reveal — entrada suave al hacer scroll (fade + translateY, blur leve opcional).
 *
 *   <section v-reveal>                         el elemento entra solo
 *   <div v-reveal.stagger>                     cada hijo directo entra en cascada
 *   <div v-reveal="{ delay: 120 }">            retraso inicial en ms
 *   <header v-reveal.blur>                     añade un desenfoque leve (6px)
 *
 * Seguridad: el estado oculto ([data-reveal="armed"]) solo se aplica cuando hay
 * IntersectionObserver y no se pidió reducir el movimiento. Si el JS falla o el
 * usuario reduce el movimiento, el contenido simplemente está visible.
 * Cada elemento se anima una única vez; al terminar se limpian atributos y
 * will-change, y todo se desconecta al desmontar.
 */

export interface RevealOptions {
  /** Retraso inicial en ms. */
  delay?: number
  /** Ms entre elementos que entran a la vez (modo stagger). */
  step?: number
  /** Desplazamiento vertical inicial en px (16–24). */
  y?: number
}

interface RevealState {
  delay: number
  step: number
  y: number
  blur: boolean
  timer?: ReturnType<typeof setTimeout>
  onEnd?: (e: TransitionEvent) => void
}

const STATE = new WeakMap<Element, RevealState>()
const OWNED = new WeakMap<Element, Element[]>()
const MAX_STAGGER_SLOTS = 8

let observer: IntersectionObserver | null = null

const canAnimate = (): boolean =>
  typeof window !== 'undefined' && 'IntersectionObserver' in window && !prefersReducedMotion()

const finish = (el: HTMLElement) => {
  const s = STATE.get(el)
  if (s?.timer) clearTimeout(s.timer)
  if (s?.onEnd) el.removeEventListener('transitionend', s.onEnd)
  el.removeAttribute('data-reveal')
  el.style.removeProperty('--reveal-delay')
  el.style.removeProperty('--reveal-y')
  STATE.delete(el)
}

const play = (el: HTMLElement, slot: number) => {
  const s = STATE.get(el)
  if (!s) return
  const delay = s.delay + Math.min(slot, MAX_STAGGER_SLOTS) * s.step
  el.style.setProperty('--reveal-delay', `${delay}ms`)
  s.onEnd = (e: TransitionEvent) => {
    if (e.target === el && e.propertyName === 'opacity') finish(el)
  }
  el.addEventListener('transitionend', s.onEnd)
  // Red de seguridad por si transitionend no llega (pestaña oculta, display:none…)
  s.timer = setTimeout(() => finish(el), delay + 1400)
  el.setAttribute('data-reveal', 'in')
}

const getObserver = (): IntersectionObserver => {
  if (observer) return observer
  observer = new IntersectionObserver(
    (entries) => {
      let slot = 0
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return
        const el = entry.target as HTMLElement
        observer?.unobserve(el)
        play(el, slot++)
      })
    },
    { threshold: 0, rootMargin: '0px 0px -8% 0px' },
  )
  return observer
}

const arm = (el: HTMLElement, state: RevealState) => {
  STATE.set(el, state)
  el.style.setProperty('--reveal-y', `${state.y}px`)
  el.setAttribute('data-reveal', state.blur ? 'armed-blur' : 'armed')
  getObserver().observe(el)
}

const release = (el: Element) => {
  observer?.unobserve(el)
  finish(el as HTMLElement)
}

export const vReveal: Directive<HTMLElement, RevealOptions | undefined> = {
  mounted(el, binding: DirectiveBinding<RevealOptions | undefined>) {
    if (!canAnimate()) return
    const opts = binding.value || {}
    const base = {
      delay: opts.delay ?? 0,
      step: opts.step ?? 70,
      y: opts.y ?? 20,
      blur: !!binding.modifiers.blur,
    }

    if (binding.modifiers.stagger) {
      const children = Array.from(el.children) as HTMLElement[]
      children.forEach((child) => arm(child, { ...base }))
      OWNED.set(el, children)
    } else {
      // Un elemento suelto no escalona: el step solo aplica a varios que entran juntos
      arm(el, { ...base, step: opts.step ?? 0 })
      OWNED.set(el, [el])
    }
  },
  beforeUnmount(el) {
    OWNED.get(el)?.forEach(release)
    OWNED.delete(el)
  },
}
