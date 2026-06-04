import { onMounted, nextTick } from 'vue'
import gsap from 'gsap'
import SplitText from 'gsap/SplitText'

/**
 * Animates all matching headings character-by-character using GSAP SplitText.
 * Usage: call `useSplitTitle()` in any Vue component's <script setup>.
 * Add `class="split-title"` to the h1/h2 elements you want animated.
 * Returns `{ applySplitTitle }` to re-run after async data loads.
 */
export function useSplitTitle(selector: string = '.split-title') {
  gsap.registerPlugin(SplitText)

  const split = () => {
    const els = document.querySelectorAll<HTMLElement>(selector)
    if (!els.length) return
    els.forEach(el => {
      const s = new SplitText(el, { type: 'chars' })
      gsap.from(s.chars, {
        opacity: 0,
        y: 30,
        duration: 0.5,
        stagger: 0.03,
        ease: 'back.out(1.7)',
      })
    })
  }

  onMounted(async () => {
    await nextTick()
    split()
  })

  return { applySplitTitle: split }
}
