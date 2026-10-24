import { onMounted, onBeforeUnmount, nextTick } from 'vue'
import gsap from 'gsap'
import SplitText from 'gsap/SplitText'

/**
 * Animates CTA buttons character-by-character using GSAP SplitText.
 * On hover, the animation replays for interactive feedback.
 * Usage: call `useSplitButton()` in any Vue component's <script setup>.
 * Add `class="split-btn"` to CTA buttons/links you want animated.
 * Returns `{ applySplitBtn }` to re-run after async data loads.
 * Each element is split once per run (the hover reuses the same chars);
 * re-running reverts the previous split first and everything is reverted on unmount.
 */
export function useSplitButton(selector: string = '.split-btn') {
  gsap.registerPlugin(SplitText)

  interface Entry {
    split: SplitText
    tweens: gsap.core.Tween[]
    cleanup: () => void
  }
  const instances = new Map<HTMLElement, Entry>()

  const revertEl = (el: HTMLElement) => {
    const entry = instances.get(el)
    if (!entry) return
    entry.cleanup()
    entry.tweens.forEach(t => t.kill())
    entry.split.revert()
    instances.delete(el)
  }

  const split = () => {
    const els = document.querySelectorAll<HTMLElement>(selector)
    if (!els.length) return

    els.forEach(el => {
      revertEl(el)

      // Entrance animation
      const s = new SplitText(el, { type: 'chars' })
      const entry: Entry = { split: s, tweens: [], cleanup: () => {} }
      entry.tweens.push(gsap.from(s.chars, {
        opacity: 0,
        y: 30,
        duration: 0.5,
        stagger: 0.03,
        ease: 'back.out(1.7)',
      }))

      // Hover re-trigger reuses the same split chars (no re-split)
      let hoverTween: gsap.core.Tween | null = null
      const handler = () => {
        if (hoverTween) hoverTween.kill()
        hoverTween = gsap.fromTo(
          s.chars,
          { opacity: 0.6, y: -8 },
          {
            opacity: 1,
            y: 0,
            duration: 0.35,
            stagger: 0.02,
            ease: 'back.out(2)',
          }
        )
      }
      el.addEventListener('mouseenter', handler)
      entry.cleanup = () => {
        el.removeEventListener('mouseenter', handler)
        if (hoverTween) hoverTween.kill()
      }
      instances.set(el, entry)
    })
  }

  onMounted(async () => {
    await nextTick()
    split()
  })

  onBeforeUnmount(() => {
    Array.from(instances.keys()).forEach(revertEl)
  })

  return { applySplitBtn: split }
}
