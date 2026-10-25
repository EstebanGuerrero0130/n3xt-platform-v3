import { onMounted, onBeforeUnmount, nextTick } from 'vue'
import gsap from 'gsap'
import SplitText from 'gsap/SplitText'
import { prefersReducedMotion } from './useMotion'

/**
 * Entrada de titulares por líneas enmascaradas: cada línea sube desde detrás
 * de su máscara (yPercent 100 → 0) con una curva suave, en cascada corta.
 * Usage: call `useSplitTitle()` in any Vue component's <script setup>.
 * Add `class="split-title"` to the h1/h2 elements you want animated.
 * Returns `{ applySplitTitle }` to re-run after async data loads.
 *
 * - Con prefers-reduced-motion no hace nada (el texto ya es visible).
 * - Al terminar se revierte el split: el texto vuelve a ser un nodo normal
 *   (reflujo correcto al cambiar el ancho, lectores de pantalla intactos).
 * - Re-ejecutar revierte el anterior primero; todo se limpia al desmontar.
 */
export function useSplitTitle(selector: string = '.split-title') {
  gsap.registerPlugin(SplitText)

  const instances = new Map<HTMLElement, { split: SplitText; tween: gsap.core.Tween }>()

  const revertEl = (el: HTMLElement) => {
    const entry = instances.get(el)
    if (!entry) return
    entry.tween.kill()
    entry.split.revert()
    instances.delete(el)
  }

  const split = () => {
    if (prefersReducedMotion()) return
    const els = document.querySelectorAll<HTMLElement>(selector)
    if (!els.length) return
    els.forEach(el => {
      revertEl(el)
      const s = new SplitText(el, { type: 'lines', mask: 'lines', linesClass: 'split-line' })
      const tween = gsap.from(s.lines, {
        yPercent: 105,
        duration: 0.9,
        stagger: 0.08,
        ease: 'power4.out',
        onComplete: () => revertEl(el),
      })
      instances.set(el, { split: s, tween })
    })
  }

  onMounted(async () => {
    await nextTick()
    split()
  })

  onBeforeUnmount(() => {
    Array.from(instances.keys()).forEach(revertEl)
  })

  return { applySplitTitle: split }
}
