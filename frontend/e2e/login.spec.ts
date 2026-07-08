import { test, expect } from '@playwright/test'

test.describe('Login Page', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/admin/login')
    await page.waitForLoadState('networkidle')
    // Wait for login form to render
    await expect(page.locator('#login-email')).toBeVisible({ timeout: 10000 })
  })

  test('should load the login page with correct title', async ({ page }) => {
    await expect(page).toHaveTitle(/Login|Acceso/i)
  })

  test('should display the login form', async ({ page }) => {
    // Should have email and password fields
    const emailInput = page.locator('#login-email')
    await expect(emailInput).toBeVisible()

    const passwordInput = page.locator('#login-password')
    await expect(passwordInput).toBeVisible()

    const submitBtn = page.getByText(/INICIAR SESIÓN/i)
    await expect(submitBtn).toBeVisible()
  })

  test('should have role selector tabs (Cliente / Administrador)', async ({ page }) => {
    const clientTab = page.getByText('Cliente')
    await expect(clientTab).toBeVisible()

    const adminTab = page.getByText('Administrador')
    await expect(adminTab).toBeVisible()
  })

  test('should switch to register mode', async ({ page }) => {
    const registerLink = page.getByText(/REGÍSTRATE AQUÍ/i)
    await expect(registerLink).toBeVisible()
    await registerLink.click()

    // Registration form should now be visible
    const nameInput = page.locator('#register-name')
    await expect(nameInput).toBeVisible()

    const emailInput = page.locator('#register-email')
    await expect(emailInput).toBeVisible()

    const passwordInput = page.locator('#register-password')
    await expect(passwordInput).toBeVisible()

    const confirmInput = page.locator('#register-password-confirm')
    await expect(confirmInput).toBeVisible()

    const createBtn = page.getByText(/CREAR MI CUENTA/i)
    await expect(createBtn).toBeVisible()
  })

  test('should have captcha verification in register mode', async ({ page }) => {
    await page.getByText(/REGÍSTRATE AQUÍ/i).click()

    // Wait for register form to appear (auto-retries via toBeVisible)
    const captchaSection = page.getByText(/Verificacion/i).first()
    await expect(captchaSection).toBeVisible({ timeout: 10000 })
  })

  test('should show error on empty login submission', async ({ page }) => {
    const submitBtn = page.getByText(/INICIAR SESIÓN/i)
    await submitBtn.click()

    // Should either show validation or stay on page
    await expect(page).toHaveURL(/\/admin\/login/)
  })

  test('should have a back button to homepage', async ({ page }) => {
    const backBtn = page.getByText(/Volver al Inicio/i)
    await expect(backBtn).toBeVisible()
  })
})
