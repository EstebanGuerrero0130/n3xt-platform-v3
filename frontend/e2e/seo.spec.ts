import { test, expect, type Page } from '@playwright/test'
import { loginAdmin, apiCall, signOut, watchErrors } from './helpers'

const jsonLd = (page: Page) => page.locator('script[type="application/ld+json"]').evaluateAll(els => els.map(e => JSON.parse(e.textContent || '{}')))

test('noticias con página propia, datos estructurados y sitemap', async ({ page, request }) => {
  const stamp = Date.now()
  const title = `Feria 3D en Bogotá ${stamp}`
  const slug = `feria-3d-en-bogota-${stamp}`

  await loginAdmin(page)
  const current = await apiCall(page, 'GET', '/settings')
  const web = {
    ...(current.json?.web || {}),
    catalog: [{ id: 9201, name: 'Lámpara E2E', price: 45000, status: 'active', category: 'Pruebas', description: 'Lámpara impresa en PLA.' }],
    news: [
      { t: title, st: 'Estuvimos en la feria con piezas en resina.', body: 'Primer párrafo de la nota.\n\nSegundo párrafo.', date: '2026-10-20', status: 'published', category: 'Eventos' },
      { t: `Borrador ${stamp}`, status: 'draft' },
    ],
  }
  expect((await apiCall(page, 'POST', '/admin/settings', { settings: { web } })).status).toBe(200)
  await signOut(page)
  const errors = watchErrors(page)

  // La Home enlaza la nota a su página
  await page.goto('/')
  await page.locator('#news').getByRole('link', { name: 'Leer nota' }).first().click()
  await expect(page).toHaveURL(new RegExp(`/noticias/${slug}$`))
  await expect(page.getByRole('heading', { level: 1, name: title })).toBeVisible()
  await expect(page.getByText('Segundo párrafo.')).toBeVisible()
  await expect(page).toHaveTitle(`${title} | Noticias N3XT 3D`)
  expect(await jsonLd(page)).toContainEqual(expect.objectContaining({ '@type': 'NewsArticle', headline: title, datePublished: '2026-10-20' }))

  // Lista: solo las publicadas
  await page.goto('/noticias')
  await expect(page.getByRole('heading', { name: title })).toBeVisible()
  await expect(page.getByText(`Borrador ${stamp}`)).toHaveCount(0)

  // Producto con precio en COP
  await page.goto('/catalog/9201')
  await expect(page.getByRole('heading', { level: 1, name: /Lámpara E2E/ })).toBeVisible()
  await expect.poll(async () => (await jsonLd(page)).find(d => d['@type'] === 'Product')?.offers?.price).toBe(45000)

  // Sitemap del backend con la nota y el producto
  const sitemap = await (await request.get('http://localhost:8000/api/sitemap')).text()
  expect(sitemap).toContain(`/noticias/${slug}</loc>`)
  expect(sitemap).toContain('/catalog/9201</loc>')
  expect(errors).toEqual([])
})
