/**
 * N3XT useTurnstile Composable
 * Lazy-loads Cloudflare Turnstile and manages a single explicit widget.
 *
 * If VITE_TURNSTILE_SITE_KEY is not set, the widget is skipped (`enabled === false`).
 */

import { ref, onBeforeUnmount } from 'vue'

const SCRIPT_SRC = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit'

let scriptPromise: Promise<void> | null = null

function loadTurnstileScript(): Promise<void> {
  if (window.turnstile) return Promise.resolve()
  if (scriptPromise) return scriptPromise

  scriptPromise = new Promise<void>((resolve, reject) => {
    const onLoad = () => (window.turnstile ? resolve() : reject(new Error('Turnstile no disponible')))
    const onError = () => {
      scriptPromise = null
      reject(new Error('No se pudo cargar Turnstile'))
    }

    // Reuse an existing Turnstile script tag (e.g. one declared in index.html)
    const existing = document.querySelector<HTMLScriptElement>('script[src^="https://challenges.cloudflare.com/turnstile/"]')
    const script = existing || document.createElement('script')
    script.addEventListener('load', onLoad, { once: true })
    script.addEventListener('error', onError, { once: true })
    if (!existing) {
      script.src = SCRIPT_SRC
      script.async = true
      script.defer = true
      document.head.appendChild(script)
    }
  })
  return scriptPromise
}

export function useTurnstile() {
  const siteKey = import.meta.env.VITE_TURNSTILE_SITE_KEY
  const enabled = !!siteKey
  const token = ref('')
  const error = ref('')
  let widgetId: string | null = null

  async function render(el: HTMLElement | null): Promise<void> {
    if (!enabled || !el || widgetId) return
    error.value = ''
    try {
      await loadTurnstileScript()
      if (!window.turnstile || widgetId) return
      widgetId = window.turnstile.render(el, {
        sitekey: siteKey,
        callback: (t: string) => { token.value = t },
        'expired-callback': () => { token.value = '' },
        'error-callback': () => {
          token.value = ''
          error.value = 'Error en la verificación anti-bot. Intenta de nuevo.'
        },
      })
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'No se pudo cargar la verificación anti-bot.'
    }
  }

  function reset(): void {
    token.value = ''
    if (widgetId && window.turnstile) window.turnstile.reset(widgetId)
  }

  function remove(): void {
    token.value = ''
    if (widgetId && window.turnstile) window.turnstile.remove(widgetId)
    widgetId = null
  }

  onBeforeUnmount(remove)

  return { enabled, token, error, render, reset, remove }
}
