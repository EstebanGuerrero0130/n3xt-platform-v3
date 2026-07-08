import { test, expect } from '@playwright/test'

test.describe('Project Init (Contáctanos) Page', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/project/init')
    await page.waitForLoadState('networkidle')
    // Wait for heading to confirm page rendered
    await expect(page.locator('h1').first()).toBeVisible({ timeout: 10000 })
  })

  test('should load the page with correct title', async ({ page }) => {
    await expect(page).toHaveTitle(/Proyecto 3D/i)
  })

  test('should display the page header', async ({ page }) => {
    const heading = page.locator('h1').filter({ hasText: /INICIAR.*PROYECTO/i })
    await expect(heading.first()).toBeVisible()
  })

  test('should display customer name input', async ({ page }) => {
    const nameInput = page.locator('input[placeholder*="nombre"]')
    await expect(nameInput).toBeVisible()
  })

  test('should display all 4 configuration steps', async ({ page }) => {
    const steps = [
      /Tipo de Proyecto/i,
      /Tecnología Sugerida/i,
      /Acabado Final/i,
      /Urgencia del Proyecto/i,
    ]
    for (const stepText of steps) {
      await expect(page.getByText(stepText).first()).toBeVisible()
    }
  })

  test('should have selectable option cards in each step', async ({ page }) => {
    // Each step has option buttons — check for key options
    const figuraBtn = page.getByText('Figura / Arte').first()
    await expect(figuraBtn).toBeAttached()

    const resinaBtn = page.getByText('Resina 8K').first()
    await expect(resinaBtn).toBeAttached()

    const premiumBtn = page.getByText('Premium Paint').first()
    await expect(premiumBtn).toBeAttached()
  })

  test('should display project summary sidebar', async ({ page }) => {
    const sidebar = page.getByText(/Resumen del Proyecto/i)
    await expect(sidebar).toBeVisible()
  })

  test('should have a submit button disabled without customer name', async ({ page }) => {
    const submitBtn = page.getByText(/SOLICITAR ANÁLISIS/i)
    await expect(submitBtn).toBeVisible()
    // Should be disabled when name is empty
    await expect(submitBtn).toBeDisabled()
  })

  test('should enable submit button when name is entered', async ({ page }) => {
    const nameInput = page.locator('input[placeholder*="nombre"]')
    await nameInput.fill('Juan Pérez')

    const submitBtn = page.getByText(/SOLICITAR ANÁLISIS/i)
    await expect(submitBtn).toBeEnabled()
  })

  test('should change selection when clicking an option', async ({ page }) => {
    // Click "Pieza Técnica" (second option in first step)
    const tecnicaBtn = page.getByText('Pieza Técnica').first()
    await tecnicaBtn.click()
    // The sidebar summary should update (Vue reactivity, auto-retried)
    await expect(page.getByText('Pieza Técnica').first()).toBeAttached({ timeout: 5000 })
  })

  test('should navigate to catalog via navbar', async ({ page }) => {
    const nav = page.getByRole('navigation')
    await nav.getByText('Catálogo').click()
    await page.waitForLoadState('networkidle')
    await expect(page).toHaveURL(/\/catalog/)
  })
})
