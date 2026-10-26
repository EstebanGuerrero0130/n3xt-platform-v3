/**
 * Analítica de visitas (opcional). Se activa con una variable de entorno:
 *   VITE_PLAUSIBLE_DOMAIN=n3xt3d.com   → Plausible (sin cookies, recomendado)
 *   VITE_GA_ID=G-XXXXXXX               → Google Analytics 4
 * Sin ninguna, no se carga nada. El panel y la cuenta del cliente no se cuentan.
 */
import type { Router } from 'vue-router'

const PLAUSIBLE_DOMAIN = import.meta.env.VITE_PLAUSIBLE_DOMAIN as string | undefined
const PLAUSIBLE_SRC = (import.meta.env.VITE_PLAUSIBLE_SRC as string | undefined) || 'https://plausible.io/js/script.manual.js'
const GA_ID = import.meta.env.VITE_GA_ID as string | undefined

const PRIVATE_PATHS = /^\/(admin|customer)(\/|$)/

type Tracker = (path: string) => void

declare global {
  interface Window {
    plausible?: ((event: string, options?: Record<string, unknown>) => void) & { q?: unknown[] }
    dataLayer?: unknown[]
    gtag?: (...args: unknown[]) => void
  }
}

function loadScript(src: string, attrs: Record<string, string> = {}) {
  const s = document.createElement('script')
  s.async = true
  s.src = src
  Object.entries(attrs).forEach(([k, v]) => s.setAttribute(k, v))
  document.head.appendChild(s)
}

function plausible(): Tracker {
  // Cola hasta que cargue el script (patrón oficial de Plausible)
  window.plausible = window.plausible || Object.assign((...args: unknown[]) => { (window.plausible!.q = window.plausible!.q || []).push(args) }, {})
  loadScript(PLAUSIBLE_SRC, { 'data-domain': PLAUSIBLE_DOMAIN! })
  return path => window.plausible?.('pageview', { u: location.origin + path })
}

function googleAnalytics(): Tracker {
  window.dataLayer = window.dataLayer || []
  window.gtag = function gtag() { window.dataLayer!.push(arguments) } // eslint-disable-line prefer-rest-params
  window.gtag('js', new Date())
  // Las páginas vistas se mandan a mano (SPA) y sin datos de anuncios
  window.gtag('config', GA_ID, { send_page_view: false, anonymize_ip: true, allow_google_signals: false })
  loadScript(`https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(GA_ID!)}`)
  return path => window.gtag?.('event', 'page_view', { page_path: path, page_location: location.origin + path, page_title: document.title })
}

export function installAnalytics(router: Router) {
  const track = PLAUSIBLE_DOMAIN ? plausible() : GA_ID ? googleAnalytics() : null
  if (!track) return
  router.afterEach((to, from, failure) => {
    if (failure || PRIVATE_PATHS.test(to.path) || (from.matched.length && to.path === from.path)) return
    // Tras el cambio de título (usePageMeta) de la página nueva
    setTimeout(() => track(to.path), 0)
  })
}
