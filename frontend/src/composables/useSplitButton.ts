import { onMounted, onBeforeUnmount, nextTick } from 'vue'
import gsap from 'gsap'
import SplitText from 'gsap/SplitText'

/**
 * Animates CTA buttons character-by-character using GSAP SplitText.
 * On hover, the animation replays for interactive feedback.
 * Usage: call `useSplitButton()` in any Vue component's <script setup>.
 * Add `class="split-btn"` to CTA buttons/links you want animated.
 * Returns `{ applySplitBtn }` to re-run after async data loads.
 */
export function useSplitButton(selector: string = '.split-btn') {
  gsap.registerPlugin(SplitText)

  // Track which elements already have hover listeners
  const hoverAttached = new WeakSet<HTMLElement>()
  const hoverCleanups: (() => void)[] = []

  const split = () => {
    const els = document.querySelectorAll<HTMLElement>(selector)
    if (!els.length) return

    els.forEach(el => {
      // Entrance animation
      const s = new SplitText(el, { type: 'chars' })
      gsap.from(s.chars, {
        opacity: 0,
        y: 30,
        duration: 0.5,
        stagger: 0.03,
        ease: 'back.out(1.7)',
      })

      // Attach hover re-trigger only once per element
      if (hoverAttached.has(el)) return
      hoverAttached.add(el)

      let hoverTween: gsap.core.Tween | null = null
      const handler = () => {
        if (hoverTween) hoverTween.kill()
        const s2 = new SplitText(el, { type: 'chars' })
        hoverTween = gsap.fromTo(
          s2.chars,
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
      hoverCleanups.push(() => el.removeEventListener('mouseenter', handler))
    })
  }

  onMounted(async () => {
    await nextTick()
    split()
  })

  onBeforeUnmount(() => {
    hoverCleanups.forEach(fn => fn())
    hoverCleanups.length = 0
  })

  return { applySplitBtn: split }
}
