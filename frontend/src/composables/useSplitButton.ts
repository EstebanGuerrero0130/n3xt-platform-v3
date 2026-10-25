/**
 * Compatibilidad: antes partía el texto de los CTA (.split-btn) letra por letra
 * y lo hacía rebotar al hover. Ese efecto chocaba con el sistema de botones
 * premium (brillo diagonal, sombra, iconos con desplazamiento) y no respetaba
 * prefers-reduced-motion, así que ahora el feedback vive solo en CSS
 * (src/style.css, .btn-primary / .btn-secondary).
 *
 * Se conserva la misma API para no tocar las vistas que lo usan.
 */
export function useSplitButton(_selector: string = '.split-btn') {
  const applySplitBtn = () => {}
  return { applySplitBtn }
}
