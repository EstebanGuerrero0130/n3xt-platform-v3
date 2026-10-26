import { test, expect } from '@playwright/test'
import { apiCall, loginAdmin, openAdminTab, registerVerifiedCustomer, seedCatalog, signOut } from './helpers'

test('reseña: el cliente califica, el admin publica y se ve en el sitio', async ({ page }) => {
  test.setTimeout(120_000)
  const comment = `El busto quedó increíble, muy buen detalle (${Date.now()}).`
  await seedCatalog(page, [{ id: 9101, name: 'Busto E2E', price: 80000, status: 'active', category: 'Pruebas' }])

  // Cliente compra
  const email = await registerVerifiedCustomer(page, 'Resena_2026')
  const order = await apiCall(page, 'POST', '/customer/catalog-orders', { items: [{ product: '9101', qty: 1 }] })
  expect(order.status).toBe(201)
  const orderId = order.json.data.order_id

  // El taller lo despacha
  await signOut(page)
  await loginAdmin(page)
  expect((await apiCall(page, 'PATCH', `/admin/orders/${orderId}/status`, { status: 'shipped', tracking_carrier: 'Recoge en el taller', tracking_guide: 'Recoge en el taller' })).status).toBe(200)

  // El cliente califica
  await signOut(page)
  await page.goto('/admin/login')
  await page.locator('#login-email').fill(email)
  await page.locator('#login-password').fill('Resena_2026')
  await page.getByRole('button', { name: 'Iniciar sesión' }).click()
  await expect(page).toHaveURL(/customer\/dashboard/)
  await page.getByRole('button', { name: /Busto E2E/ }).click()
  await page.getByRole('button', { name: 'Calificar este pedido' }).click()
  await page.getByText('★').nth(3).click() // 4 estrellas
  await page.getByLabel('Tu comentario').fill(comment)
  await page.getByRole('button', { name: 'Enviar reseña' }).click()
  await expect(page.getByText('· en revisión')).toBeVisible()

  // Sin aprobar no se publica
  await page.goto('/catalog/9101')
  await expect(page.getByText(comment)).toHaveCount(0)

  // El admin la publica
  await signOut(page)
  await loginAdmin(page)
  await openAdminTab(page, 'Sitio web')
  await page.getByRole('navigation', { name: 'Secciones del sitio' }).getByRole('button', { name: 'Reseñas' }).click()
  await page.getByRole('article').filter({ hasText: comment }).getByRole('button', { name: 'Publicar', exact: true }).click()
  await expect(page.getByText('Reseña publicada')).toBeVisible()

  // Se ve en la página del producto y en la Home
  await page.goto('/catalog/9101')
  await expect(page.getByText(comment)).toBeVisible()
  await page.goto('/')
  await expect(page.getByRole('heading', { name: /nuestros clientes/i })).toBeVisible()
})
