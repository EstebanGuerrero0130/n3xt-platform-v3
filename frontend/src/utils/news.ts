/**
 * Noticias del sitio. El panel (WebManager) las guarda en `web.news` como
 * { t, st, body, image, category, date, status, slug, url }; aquí se normalizan
 * para la Home y para /noticias. Los borradores no se publican.
 */

export interface NewsItem {
  slug: string
  title: string
  summary: string
  body: string
  image: string
  category: string
  date: string
  /** Enlace externo (nota en otro sitio), si no hay texto propio */
  url: string
}

export const slugify = (text: string) =>
  text.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '')
    .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '')

export function publishedNews(raw: unknown): NewsItem[] {
  const list = Array.isArray(raw) ? raw : []
  const seen = new Set<string>()
  return list
    .filter((n: any) => n && n.t && (!n.status || n.status === 'published'))
    .map((n: any) => {
      // Slug único aunque dos títulos coincidan
      let slug = slugify(String(n.slug || n.t)) || 'noticia'
      for (let i = 2; seen.has(slug); i++) slug = `${slugify(String(n.slug || n.t))}-${i}`
      seen.add(slug)
      const body = String(n.body || '').trim()
      return {
        slug,
        title: String(n.t),
        summary: String(n.d || n.st || body.slice(0, 200)),
        body,
        image: String(n.i || n.image || ''),
        category: String(n.tag || n.category || ''),
        date: String(n.date || ''),
        url: String(n.url || ''),
      }
    })
}

/** Párrafos del texto completo (líneas en blanco separan párrafos). */
export const paragraphs = (body: string) => body.split(/\n\s*\n/).map(p => p.trim()).filter(Boolean)
