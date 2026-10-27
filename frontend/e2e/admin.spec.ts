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
  await expect(page.getByText('Impresora actualizada con exito')).toBeVisible()

  expect(errors).toEqual([])
})
