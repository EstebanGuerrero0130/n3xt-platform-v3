import { test, expect } from '@playwright/test'
import { loginAdmin, watchErrors } from './helpers'

test('la API rechaza POST sin token CSRF', async ({ request }) => {
  const res = await request.post('http://localhost:8000/api/auth-master-industrial-access', {
    headers: { Accept: 'application/json' },
    data: { email: 'x@example.com', password: 'nada' },
  })
  expect(res.status()).toBe(419)
})

test('el admin entra y usa el panel con CSRF activo', async ({ page }) => {
  const errors = watchErrors(page)
  await loginAdmin(page)
  await expect(page.getByRole('heading', { name: /Producción/ })).toBeVisible()
  expect(errors).toEqual([])
})
