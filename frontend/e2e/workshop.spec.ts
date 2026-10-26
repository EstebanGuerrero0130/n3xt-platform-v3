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

test('calendario de producción: impresión y cola por máquina', async ({ page }) => {
  const errors = watchErrors(page)
  await loginAdmin(page)
  const stamp = Date.now()
  const printer = await apiCall(page, 'POST', '/admin/printers', { name: `Máquina ${stamp}`, model: 'E2E', technology: 'FDM' })
  expect(printer.status).toBe(201)

  const materials = await apiCall(page, 'GET', '/materials')
  const list = Array.isArray(materials.json) ? materials.json : materials.json?.data || []
  const material = list.find((m: any) => m.category === 'FDM' && (m.type ?? 'material') === 'material')
  const newOrder = async (job: string) => (await apiCall(page, 'POST', '/orders', {
    customer_name: 'Taller', technology: 'FDM', material_id: material.id, job_name: job,
    volume_mm3: 1000, estimated_weight_g: 10, total_price: 20000, estimated_duration_h: 3,
  })).json.data.order_id
  const printingId = await newOrder(`Imprimiendo ${stamp}`)
  await newOrder(`En cola ${stamp}`)
  const moved = await apiCall(page, 'PATCH', `/admin/orders/${printingId}/status`, { status: 'printing', printer_id: printer.json.data.id })
  expect(moved.status).toBe(200)

  await page.reload()
  await page.getByRole('button', { name: 'Calendario' }).click()
  const jobs = page.getByRole('list', { name: `Trabajos de Máquina ${stamp}` })
  await expect(jobs.getByRole('listitem').filter({ hasText: `Imprimiendo ${stamp}` })).toContainText('Imprimiendo')
  // La cola puede caer en otra máquina FDM libre: basta con que esté agendada
  await expect(page.getByRole('listitem').filter({ hasText: `En cola ${stamp}` })).toContainText('En cola')
  expect(errors).toEqual([])
})
