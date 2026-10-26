import { test, expect } from '@playwright/test'
import { seedCatalog, watchErrors } from './helpers'

test('carrito: agregar varios productos y pedir por WhatsApp', async ({ page, context }) => {
  await seedCatalog(page, [
    { id: 9001, name: 'Figura E2E', price: '50000', status: 'active', category: 'Pruebas', image: '' },
    { id: 9002, name: 'Llavero E2E', price: 12000, status: 'active', category: 'Pruebas', image: '' },
  ])
  const errors = watchErrors(page)

  await page.goto('/catalog/9001')
  await page.getByRole('button', { name: 'Agregar al carrito' }).click()
  await page.goto('/catalog/9002')
  await page.getByRole('button', { name: 'Aumentar cantidad' }).click()
  await page.getByRole('button', { name: 'Agregar al carrito' }).click()
  await expect(page.getByRole('link', { name: /Carrito: 3 productos/ })).toBeVisible()

  await page.goto('/carrito')
  await expect(page.getByText(/\$\s?74\.000/).first()).toBeVisible()
  await page.getByRole('button', { name: 'Más Figura E2E' }).click()
  await expect(page.getByText(/\$\s?124\.000/).first()).toBeVisible()

  const popup = context.waitForEvent('page')
  await page.getByRole('button', { name: 'Pedir por WhatsApp' }).click()
  const wa = await popup
  await wa.waitForURL(/wa\.me|whatsapp/, { timeout: 15_000 }).catch(() => {})
  const text = new URL(wa.url()).searchParams.get('text') || ''
  expect(text).toContain('2 × *Figura E2E*')
  expect(text).toContain('2 × *Llavero E2E*')
  // Tras pedir, el carrito queda vacío
  await expect(page.getByText('Tu carrito está vacío')).toBeVisible()
  expect(errors).toEqual([])
})
