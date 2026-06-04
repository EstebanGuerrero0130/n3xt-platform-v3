/**
 * N3XT useRevealAnim Composable
 * Reusable IntersectionObserver for scroll-triggered reveal animations.
 */
import { onMounted, onUnmounted } from 'vue'

interface RevealAnimOptions {
  selector?: string
  observerOptions?: IntersectionObserverInit
  delay?: number
}

export function useRevealAnim({ 
  selector = '.reveal',
  observerOptions = { threshold: 0.1, rootMargin: '0px 0px -40px 0px' },
  delay = 100
}: RevealAnimOptions = {}) {
  let observer: IntersectionObserver | null = null
  let _timer: ReturnType<typeof setTimeout> | null = null

  const observe = (): void => {
    _timer = setTimeout(() => {
      document.querySelectorAll(selector).forEach(el => {
        if (observer) observer.observe(el)
      })
    }, delay)
  }

  onMounted(() => {
    observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('revealed')
          if (observer) observer.unobserve(entry.target)
        }
      })
    }, observerOptions)
    observe()
  })

  onUnmounted(() => {
    if (_timer) clearTimeout(_timer)
    if (observer) observer.disconnect()
  })

  return { observe }
}
