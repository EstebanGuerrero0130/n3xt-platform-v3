import { test, expect } from '@playwright/test'
import { lastMailCode, solveMath, watchErrors } from './helpers'

const email = `e2e${Date.now() % 1_000_000}@example.com`

test.describe.serial('cuenta de cliente', () => {
  test('registro y verificación por correo', async ({ page }) => {
    const errors = watchErrors(page)
    await page.goto('/admin/login?registro=1')
    await page.locator('#reg-name').fill('Cliente E2E')
    await page.locator('#reg-email').fill(email)
    await page.locator('#reg-password').fill('Primera_2026')
    await page.locator('#reg-password2').fill('Primera_2026')
    await solveMath(page, await page.locator('label[for=reg-captcha] + p').innerText(), '#reg-captcha')
    await page.getByRole('button', { name: 'Crear mi cuenta' }).click()
    await expect(page).toHaveURL(/customer\/dashboard/)

    await page.locator('#verify-code').fill(lastMailCode(email))
    await page.getByRole('button', { name: 'Confirmar' }).click()
    await expect(page.getByText(/BIENVENIDA[A-Z0-9]{6}/)).toBeVisible()
    expect(errors).toEqual([])
  })

  test('recuperar la contraseña con el código del correo', async ({ page }) => {
    await page.goto('/admin/login')
    await page.getByRole('button', { name: '¿Olvidaste tu contraseña?' }).click()
    await page.locator('#forgot-email').fill(email)
    await page.getByRole('button', { name: 'Enviar código' }).click()
    await expect(page.locator('#reset-code')).toBeVisible()

    await page.locator('#reset-code').fill(lastMailCode(email))
    await page.locator('#reset-password').fill('Segunda_2026')
    await page.locator('#reset-password2').fill('Segunda_2026')
    await page.getByRole('button', { name: 'Cambiar contraseña' }).click()
    await expect(page).toHaveURL(/customer\/dashboard/)

    // La contraseña nueva sirve para entrar
    await page.getByRole('button', { name: 'Cerrar sesión' }).click()
    await page.goto('/admin/login')
    await page.locator('#login-email').fill(email)
    await page.locator('#login-password').fill('Segunda_2026')
    await page.getByRole('button', { name: 'Iniciar sesión' }).click()
    await expect(page).toHaveURL(/customer\/dashboard/)
  })
})
