import type { App } from 'vue'
import { vReveal } from './reveal'
import { vSpotlight } from './spotlight'

export { vReveal, vSpotlight }

/** Registra las directivas de movimiento como globales (v-reveal, v-spotlight). */
export function installDirectives(app: App) {
  app.directive('reveal', vReveal)
  app.directive('spotlight', vSpotlight)
}

declare module 'vue' {
  export interface GlobalDirectives {
    vReveal: typeof vReveal
    vSpotlight: typeof vSpotlight
  }
}
