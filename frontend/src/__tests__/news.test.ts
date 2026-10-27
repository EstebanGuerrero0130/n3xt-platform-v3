import { describe, it, expect } from 'vitest'
import { publishedNews, slugify, paragraphs } from '../utils/news'

describe('noticias', () => {
  it('slugify quita tildes y símbolos', () => {
    expect(slugify('¡Impresión 3D en Bogotá!')).toBe('impresion-3d-en-bogota')
  })

  it('solo publica las publicadas y con título, con slug único', () => {
    const list = publishedNews([
      { t: 'Feria 3D', status: 'published', body: 'Texto' },
      { t: 'Borrador', status: 'draft' },
      { t: '', status: 'published' },
      { t: 'Feria 3D', status: 'published', st: 'Otra' },
      { t: 'Con slug', slug: 'mi-nota', image: 'x.jpg', category: 'Eventos' },
    ])
    expect(list.map(n => n.slug)).toEqual(['feria-3d', 'feria-3d-2', 'mi-nota'])
    expect(list[0].summary).toBe('Texto')
    expect(list[2]).toMatchObject({ image: 'x.jpg', category: 'Eventos' })
  })

  it('coincide con el sitemap en ligaduras, títulos vacíos y enlaces', async () => {
    const { newsLink } = await import('../utils/news')
    expect(slugify('Straße & Æon Ø')).toBe('strasse-aeon-o')
    expect(publishedNews([{ t: '🎉' }, { t: '🎉🎉' }]).map(n => n.slug)).toEqual(['noticia', 'noticia-2'])
    expect(newsLink({ slug: 'a', body: '', url: 'instagram.com/p/x' })).toBe('/noticias/a')
    expect(newsLink({ slug: 'a', body: '', url: 'https://instagram.com/p/x' })).toBe('https://instagram.com/p/x')
  })

  it('separa párrafos por líneas en blanco', () => {
    expect(paragraphs('Uno\nsigue\n\n  Dos  \n\n\n')).toEqual(['Uno\nsigue', 'Dos'])
  })
})
