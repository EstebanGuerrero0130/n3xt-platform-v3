import { expect, type Page } from '@playwright/test'
import { readFileSync, existsSync } from 'node:fs'

export const ADMIN = {
  email: process.env.E2E_ADMIN_EMAIL || 'admin@n3xt3d.com',
  password: process.env.E2E_ADMIN_PASSWORD || 'LocalAdmin_2026',
}

/** Entra al panel por el formulario real (pasa por CSRF y sesión). */
export async function loginAdmin(page: Page) {
  await page.goto('/admin/login')
  await page.locator('#login-email').fill(ADMIN.email)
  await page.locator('#login-password').fill(ADMIN.password)
  await page.getByRole('button', { name: 'Iniciar sesión' }).click()
  await expect(page).toHaveURL(/\/admin$/)
}

/** Va a una pestaña del panel por el menú lateral. */
export async function openAdminTab(page: Page, name: string) {
  await page.getByRole('navigation', { name: 'Secciones' }).getByRole('button', { name: new RegExp(`^${name}`) }).click()
}

/**
 * Último código de verificación enviado a `email`. Con MAIL_MAILER=log los correos
 * quedan en el log de Laravel (E2E_MAIL_LOG apunta a ese archivo).
 */
export function lastMailCode(email: string): string {
  const path = process.env.E2E_MAIL_LOG || '../backend/storage/logs/laravel.log'
  if (!existsSync(path)) throw new Error(`No existe el log de correos: ${path}`)
  const log = readFileSync(path, 'utf8')
  const start = log.lastIndexOf(email)
  const code = log.slice(start).match(/letter-spacing:10px[^>]*>(\d{6})/)
  if (start < 0 || !code) throw new Error(`No hay código para ${email}`)
  return code[1]
}

/** Falla la prueba si la página registra errores de consola o respuestas 5xx. */
export function watchErrors(page: Page) {
  const errors: string[] = []
  page.on('pageerror', e => errors.push('JS: ' + e.message))
  page.on('console', m => { if (m.type() === 'error' && !/favicon/.test(m.text())) errors.push('console: ' + m.text()) })
  page.on('response', r => { if (r.status() >= 500) errors.push(`HTTP ${r.status()} ${r.url()}`) })
  return errors
}
