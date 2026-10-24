import { onMounted, onBeforeUnmount, nextTick } from 'vue'
import gsap from 'gsap'
import SplitText from 'gsap/SplitText'

/**
 * Animates all matching headings character-by-character using GSAP SplitText.
 * Usage: call `useSplitTitle()` in any Vue component's <script setup>.
 * Add `class="split-title"` to the h1/h2 elements you want animated.
 * Returns `{ applySplitTitle }` to re-run after async data loads.
 * Re-running reverts the previous split first (no nested wrappers) and
 * everything is reverted on unmount.
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
    const els = document.querySelectorAll<HTMLElement>(selector)
    if (!els.length) return
    els.forEach(el => {
      revertEl(el)
      const s = new SplitText(el, { type: 'words,chars' }) // words: evita cortes de línea a mitad de palabra
      const tween = gsap.from(s.chars, {
        opacity: 0,
        y: 30,
        duration: 0.5,
        stagger: 0.03,
        ease: 'back.out(1.7)',
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
