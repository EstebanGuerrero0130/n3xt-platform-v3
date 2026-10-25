/**
 * srcset para las imágenes locales de 1024 px que tienen variante de 640 px
 * (public/assets/n3xt_*_<timestamp>.webp y public/assets/img/*.webp).
 * El navegador decodifica la de 640 cuando basta: ~2.5× menos memoria por imagen.
 * Para URLs externas (Cloudinary, panel) devuelve undefined y se usa solo `src`.
 */
const LOCAL_1024 = /^\/assets\/(n3xt_[a-z0-9_]+_\d+|img\/[a-z0-9_]+)\.webp$/

export function srcsetFor(url: string | undefined): string | undefined {
  if (!url || !LOCAL_1024.test(url)) return undefined
  return `${url.replace(/\.webp$/, '-640.webp')} 640w, ${url} 1024w`
}
