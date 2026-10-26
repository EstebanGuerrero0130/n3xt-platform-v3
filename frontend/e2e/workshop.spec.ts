import { test, expect } from '@playwright/test'
import { loginAdmin, apiCall, watchErrors } from './helpers'

test('pedido manual con archivo 3D', async ({ page }) => {
  const errors = watchErrors(page)
  await loginAdmin(page)
  const job = `Soporte manual ${Date.now()}`

  await page.getByRole('button', { name: 'Nuevo pedido' }).first().click()
  const modal = page.getByRole('dialog', { name: 'Nuevo pedido manual' })
  await modal.locator('#mo-name').fill('Cliente taller')
  await modal.locator('#mo-jobname').fill(job)
  await modal.locator('#mo-material').selectOption({ index: 1 })
  await modal.locator('#mo-weight').fill('20')
  await modal.locator('#mo-price').fill('35000')
  await modal.locator('#mo-file').setInputFiles('e2e/fixtures/cubo-e2e.stl')
  await modal.getByRole('button', { name: 'Crear pedido' }).click()
  await expect(modal).toBeHidden()

  const orders = await apiCall(page, 'GET', '/admin/orders')
  const list = Array.isArray(orders.json) ? orders.json : (orders.json?.data?.data || orders.json?.data || [])
  const order = list.find((o: any) => o.job_name === job)
  expect(order?.original_filename).toBe('cubo-e2e.stl')
  expect(Number(order?.total_price)).toBe(35000)

  const download = await page.evaluate(async id => (await fetch(`http://localhost:8000/api/admin/orders/${id}/download`, { credentials: 'include' })).status, order.id)
  expect(download).toBe(200)
  expect(errors).toEqual([])
})
