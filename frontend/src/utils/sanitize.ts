/**
 * Sanitiza strings SVG eliminando event handlers, etiquetas script y javascript: URLs
 * para prevenir XSS al usar innerHTML/v-html con contenido controlado.
 */
export function sanitizeSVG(svgString: string): string {
  if (!svgString || typeof svgString !== 'string') return ''
  return svgString
    .replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '')
    .replace(/\son\w+\s*=\s*(?:"[^"]*"|'[^']*'|[^\s>]+)/gi, '')
    .replace(/href\s*=\s*(?:"javascript:[^"]*"|'javascript:[^']*')/gi, 'href="#"')
    .replace(/src\s*=\s*(?:"javascript:[^"]*"|'javascript:[^']*')/gi, 'src="#"')
}
