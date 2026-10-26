import { test, expect } from '@playwright/test'
import { registerVerifiedCustomer, solveMath, watchErrors } from './helpers'

test('guardar una cotización y reabrirla en el cotizador', async ({ page }) => {
  await registerVerifiedCustomer(page)
  const errors = watchErrors(page)

  await page.goto('/quote')
  await solveMath(page, await page.locator('label[for=stl-captcha]').innerText(), '#stl-captcha')
  await page.getByRole('button', { name: 'Continuar' }).click()
  await page.locator('input[type=file][accept=".stl,.obj"]').setInputFiles('e2e/fixtures/cubo-e2e.stl')
  await expect(page.getByRole('button', { name: /^cubo-e2e\.stl/ })).toBeVisible({ timeout: 20_000 })

  await page.getByRole('button', { name: 'Guardar esta cotización para después' }).click()
  await page.locator('#save-quote-name').fill('Cubo de prueba')
  await page.getByRole('button', { name: 'Guardar', exact: true }).click()
  await expect(page.getByText(/Guardamos la cotización/)).toBeVisible()

  await page.goto('/customer/dashboard')
  await expect(page.getByText('Cubo de prueba')).toBeVisible()
  await page.getByRole('link', { name: 'Abrir en el cotizador' }).click()
  await expect(page.getByText(/Abrimos «Cubo de prueba»/)).toBeVisible({ timeout: 20_000 })
  await expect(page.getByRole('button', { name: /^cubo-e2e\.stl/ })).toBeVisible()
  expect(errors).toEqual([])
})
