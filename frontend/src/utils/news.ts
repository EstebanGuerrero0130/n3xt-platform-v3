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

/** Letras que NFD no descompone, transliteradas como Str::ascii del backend (sitemap) */
const LIGATURES: Record<string, string> = { '\u00df': 'ss', '\u00e6': 'ae', '\u00f8': 'o', '\u0153': 'oe', '\u00f0': 'd', '\u00fe': 'th', '\u0142': 'l', '\u0111': 'd' }

export const slugify = (text: string) =>
  text.toLowerCase().replace(/[\u00df\u00e6\u00f8\u0153\u00f0\u00fe\u0142\u0111]/g, c => LIGATURES[c])
    .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '')

export function publishedNews(raw: unknown): NewsItem[] {
  const list = Array.isArray(raw) ? raw : []
  const seen = new Set<string>()
  return list
    .filter((n: any) => n && n.t && (!n.status || n.status === 'published'))
    .map((n: any) => {
      // Slug único aunque dos títulos coincidan
      // Igual que SitemapController: base vacía → "noticia", duplicados → base-2, base-3…
      const base = slugify(String(n.slug || n.t)) || 'noticia'
      let slug = base
      for (let i = 2; seen.has(slug); i++) slug = `${base}-${i}`
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

/** Enlace externo real (con http/https); lo demás se lee en /noticias/:slug. */
export const isExternalUrl = (url?: string) => !!url && /^https?:\/\//i.test(url.trim())

/** Con texto propio se lee aquí; sin texto y con enlace externo, la nota va a ese sitio. */
export const newsLink = (n: Pick<NewsItem, 'slug' | 'body' | 'url'>) => (n.body || !isExternalUrl(n.url) ? `/noticias/${n.slug}` : n.url.trim())
