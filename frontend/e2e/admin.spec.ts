import { test, expect } from '@playwright/test'
import { loginAdmin, openAdminTab, watchErrors } from './helpers'

// Recorre el panel y usa los modales de inventario y maquinaria
test('panel: cada sección abre sin errores; crear material, sumar stock y crear/editar máquina', async ({ page }) => {
  test.setTimeout(90_000)
  const errors = watchErrors(page)
  const stamp = Date.now()
  await loginAdmin(page)

  for (const tab of ['Inventario', 'Contabilidad', 'Maquinaria', 'Historial', 'Compras', 'Contactos', 'Descuentos', 'Parámetros', 'Sitio web', 'Equipo', 'Producción']) {
    await openAdminTab(page, tab)
    await expect(page.locator('header h1').first()).toBeVisible()
  }

  // Inventario: nuevo ítem y sumar stock
  await openAdminTab(page, 'Inventario')
  await page.getByRole('button', { name: 'Nuevo ítem' }).click()
  const matModal = page.getByRole('dialog', { name: 'Nuevo ítem de inventario' })
  await matModal.locator('#nm-name').fill(`PLA E2E ${stamp}`)
  await matModal.locator('#nm-cost').fill('80000')
  await matModal.locator('#nm-stock').fill('1000')
  await matModal.getByRole('button', { name: 'Crear ítem' }).click()
  await expect(matModal).toBeHidden()
  const row = page.getByRole('row').filter({ hasText: `PLA E2E ${stamp}` })
  await row.getByRole('spinbutton').fill('250')
  await row.getByRole('button', { name: 'Sumar' }).click()
  await expect(row).toContainText('1.250')

  // Maquinaria: nueva y editar
  await openAdminTab(page, 'Maquinaria')
  await page.getByRole('button', { name: 'Nueva impresora' }).click()
  const prModal = page.getByRole('dialog', { name: 'Nueva impresora' })
  await prModal.locator('#np-name').fill(`Máquina ${stamp}`)
  await prModal.locator('#np-model').fill('Ender E2E')
  await prModal.getByRole('button', { name: 'Agregar impresora' }).click()
  await expect(prModal).toBeHidden()
  const card = page.locator('article, li, div.admin-card').filter({ hasText: `Máquina ${stamp}` }).last()
  await card.getByRole('button', { name: 'Editar' }).click()
  const editModal = page.getByRole('dialog', { name: `Editar Máquina ${stamp}` })
  await editModal.getByRole('button', { name: 'Guardar cambios' }).click()
  await expect(editModal).toBeHidden()
  await expect(page.getByRole('status').or(page.getByRole('alert')).filter({ hasText: /Impresora|Error/ }).first()).toContainText('Impresora actualizada')

  expect(errors).toEqual([])
})

test('simulador por camas: precio por cama y por pieza, y el pedido lleva toda la corrida', async ({ page }) => {
  const errors = watchErrors(page)
  const job = `Llaveros ${Date.now()}`
  await loginAdmin(page)
  await page.getByRole('button', { name: 'Simulador de precios' }).click()
  const sim = page.getByRole('dialog', { name: 'Simulador de precios' })
  await sim.locator('#sim-job').fill(job)
  await sim.locator('#sim-name').fill('Cliente lote')
  await sim.locator('#sim-material').selectOption({ index: 1 })
  await sim.locator('#sim-weight').fill('100')
  await sim.locator('#sim-time').fill('2:00')
  await sim.locator('#sim-beds').fill('3')
  await sim.locator('#sim-pieces').fill('4')
  await expect(sim.getByText(/12 piezas\s+en 3 camas · 300 g · 6:00 h/)).toBeVisible()

  const money = async (label: string) => Number((await sim.locator('dt', { hasText: new RegExp(`^${label}$`) }).locator('+ dd').innerText()).replace(/\D/g, ''))
  const total = Number((await sim.locator('p.text-4xl').innerText()).replace(/\D/g, ''))
  expect(total).toBeGreaterThan(0)
  expect(Math.abs((await money('Por cama')) * 3 - total)).toBeLessThanOrEqual(3)
  expect(Math.abs((await money('Por pieza')) * 12 - total)).toBeLessThanOrEqual(12)

  await sim.getByRole('button', { name: 'Convertir en pedido' }).click()
  await expect(sim).toBeHidden()
  const list = await page.evaluate(async () => (await (await fetch('http://localhost:8000/api/admin/orders', { credentials: 'include', headers: { Accept: 'application/json' } })).json()).data)
  const order = list.find((o: any) => o.job_name === job)
  expect(order).toMatchObject({ qty: 12, estimated_weight_g: 300, estimated_duration_h: 6 })
  expect(Math.round(order.total_price)).toBe(total)
  expect(order.comments).toContain('en 3 camas de 4')
  expect(errors).toEqual([])
})
