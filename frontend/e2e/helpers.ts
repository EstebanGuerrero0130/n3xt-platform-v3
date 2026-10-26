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

/** Llama a la API desde la página (usa la sesión y el token CSRF del navegador). */
export async function apiCall(page: Page, method: string, path: string, body?: unknown) {
  return page.evaluate(async ({ method, path, body }) => {
    await fetch('http://localhost:8000/sanctum/csrf-cookie', { credentials: 'include' })
    const xsrf = decodeURIComponent((document.cookie.split('; ').find(c => c.startsWith('XSRF-TOKEN=')) || '').slice(11))
    const res = await fetch('http://localhost:8000/api' + path, {
      method,
      credentials: 'include',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrf },
      body: body === undefined ? undefined : JSON.stringify(body),
    })
    return { status: res.status, json: await res.json().catch(() => null) }
  }, { method, path, body })
}

/**
 * Cierra la sesión del navegador. Primero sale de la página: una petición del
 * panel aún en vuelo volvería a dejar la cookie de sesión después de borrarla.
 */
export async function signOut(page: Page) {
  await page.goto('about:blank')
  await page.context().clearCookies()
}

/** Publica productos de prueba en el catálogo (como admin). */
export async function seedCatalog(page: Page, catalog: unknown[]) {
  await loginAdmin(page)
  const current = await apiCall(page, 'GET', '/settings')
  const web = { ...(current.json?.web || {}), catalog }
  const res = await apiCall(page, 'POST', '/admin/settings', { settings: { web } })
  expect(res.status).toBe(200)
  await signOut(page)
}

/** Resuelve una operación de captcha "¿Cuánto es A op B?" en un campo. */
export async function solveMath(page: Page, labelText: string, inputSelector: string) {
  const [, a, op, b] = labelText.match(/(\d+)\s*([+\-−×x*])\s*(\d+)/)!
  const n = { '+': +a + +b, '-': +a - +b, '−': +a - +b, '×': +a * +b, x: +a * +b, '*': +a * +b }[op]!
  await page.locator(inputSelector).fill(String(n))
}

/** Crea un cliente por el formulario real y verifica su correo. Devuelve el correo. */
export async function registerVerifiedCustomer(page: Page, password = 'Primera_2026') {
  const email = `e2e${Date.now() % 10_000_000}@example.com`
  await page.goto('/admin/login?registro=1')
  await page.locator('#reg-name').fill('Cliente E2E')
  await page.locator('#reg-email').fill(email)
  await page.locator('#reg-password').fill(password)
  await page.locator('#reg-password2').fill(password)
  await solveMath(page, await page.locator('label[for=reg-captcha] + p').innerText(), '#reg-captcha')
  await page.getByRole('button', { name: 'Crear mi cuenta' }).click()
  await expect(page).toHaveURL(/customer\/dashboard/)
  await page.locator('#verify-code').fill(lastMailCode(email))
  await page.getByRole('button', { name: 'Confirmar' }).click()
  await expect(page.getByText(/BIENVENIDA[A-Z0-9]{6}/)).toBeVisible()
  return email
}
