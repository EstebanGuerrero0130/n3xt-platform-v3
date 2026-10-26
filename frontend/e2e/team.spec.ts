import { test, expect } from '@playwright/test'
import { loginAdmin, openAdminTab, apiCall, watchErrors, signOut } from './helpers'

test('equipo: el dueño crea un operario que solo ve producción', async ({ page }) => {
  test.setTimeout(90_000)
  const errors = watchErrors(page)
  const email = `operario${Date.now()}@example.com`
  const password = 'Operario_2026'

  await loginAdmin(page)
  await openAdminTab(page, 'Equipo')
  await page.getByRole('button', { name: 'Nuevo usuario' }).click()
  await page.locator('#team-name').fill('Operario E2E')
  await page.locator('#team-email').fill(email)
  await page.locator('#team-password').fill(password)
  // Por defecto viene Producción; sumamos Maquinaria
  await page.getByRole('checkbox', { name: /Maquinaria/ }).check()
  await page.getByRole('button', { name: 'Crear usuario' }).click()
  await expect(page.getByRole('listitem').filter({ hasText: email })).toContainText('Maquinaria')

  // Entra el operario
  await signOut(page)
  await page.goto('/admin/login')
  await page.locator('#login-email').fill(email)
  await page.locator('#login-password').fill(password)
  await page.getByRole('button', { name: 'Iniciar sesión' }).click()
  await expect(page).toHaveURL(/\/admin$/)

  const nav = page.getByRole('navigation', { name: 'Secciones' })
  await expect(nav.getByRole('button')).toHaveText([/^Producción/, /^Maquinaria/])
  await expect(page.getByRole('heading', { name: /Producción/ }).first()).toBeVisible()

  // El panel no pidió nada fuera de sus secciones
  expect(errors).toEqual([])
  // Y el servidor lo bloquea aunque lo pida a mano
  expect((await apiCall(page, 'GET', '/admin/purchases')).status).toBe(403)
  expect((await apiCall(page, 'GET', '/admin/team')).status).toBe(403)
})
