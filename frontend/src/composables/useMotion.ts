/**
 * Preferencias de movimiento compartidas por directivas y composables.
 * Regla del sistema: el contenido es visible por defecto y una animación solo
 * se "arma" (estado inicial oculto) cuando el entorno la permite.
 */

/** true si el usuario pidió reducir el movimiento (o si no hay ventana). */
export const prefersReducedMotion = (): boolean => {
  if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') return true
  return window.matchMedia('(prefers-reduced-motion: reduce)').matches
}

/** true en dispositivos con puntero fino y hover real (ratón/trackpad). */
export const hasFinePointer = (): boolean => {
  if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') return false
  return window.matchMedia('(hover: hover) and (pointer: fine)').matches
}

/** Curva "premium" común (equivale a --ease-premium en style.css). */
export const EASE_PREMIUM = 'cubic-bezier(0.22, 1, 0.36, 1)'
