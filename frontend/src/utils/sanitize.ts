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

const BLOCKED_TAGS = 'script, iframe, frame, object, embed, base, form, noscript, template'
const URL_ATTRS = ['href', 'src', 'action', 'formaction', 'xlink:href', 'srcdoc']

/**
 * Limpia un documento HTML completo (rótulos, cotizaciones, reportes) antes de
 * escribirlo en una ventana o iframe del mismo origen. Los datos del cliente
 * (nombre, dirección…) van interpolados en el HTML: sin esto, un nombre como
 * `<img onerror=…>` ejecutaría código con la sesión del admin.
 *
 * DOMParser crea un documento inerte (no ejecuta nada); se quitan etiquetas
 * activas, atributos on* y URLs javascript:, y se añade una CSP sin scripts.
 */
export function sanitizePrintDocument(html: string): string {
  const doc = new DOMParser().parseFromString(html || '', 'text/html')

  doc.querySelectorAll(BLOCKED_TAGS).forEach(el => el.remove())
  doc.querySelectorAll('meta[http-equiv]').forEach(el => el.remove())

  doc.querySelectorAll('*').forEach(el => {
    for (const attr of [...el.attributes]) {
      const name = attr.name.toLowerCase()
      if (name.startsWith('on')) {
        el.removeAttribute(attr.name)
      // Los caracteres de control se quitan a propósito: los navegadores los ignoran en "java\x00script:"
      // eslint-disable-next-line no-control-regex
      } else if (URL_ATTRS.includes(name) && /^\s*(javascript|vbscript|data:text\/html)/i.test(attr.value.replace(/[\s\u0000-\u001f]/g, ''))) {
        el.removeAttribute(attr.name)
      }
    }
  })

  const csp = doc.createElement('meta')
  csp.setAttribute('http-equiv', 'Content-Security-Policy')
  csp.setAttribute('content', "script-src 'none'; object-src 'none'; base-uri 'none'")
  doc.head.prepend(csp)

  return '<!DOCTYPE html>\n' + doc.documentElement.outerHTML
}
