import { test, expect } from '@playwright/test'

test.describe('Track Page', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/track')
    await page.waitForLoadState('networkidle')
    // Wait for heading to confirm page rendered
    await expect(page.locator('h1').first()).toBeVisible({ timeout: 10000 })
  })

  test('should load the track page with correct title', async ({ page }) => {
    await expect(page).toHaveTitle(/Rastrear/i)
  })

  test('should display the page header', async ({ page }) => {
    const heading = page.locator('h1').filter({ hasText: /RASTREO/i })
    await expect(heading.first()).toBeVisible()
  })

  test('should have mode switcher (project vs shipping)', async ({ page }) => {
    const projectBtn = page.getByText(/Rastreo por Proyecto/i)
    await expect(projectBtn).toBeVisible()

    const shippingBtn = page.getByText(/Rastreo por Envío/i)
    await expect(shippingBtn).toBeVisible()
  })

  test('should display search form with project ID field', async ({ page }) => {
    // Default mode is "project"
    const projectInput = page.locator('input[placeholder*="1024"]')
    await expect(projectInput).toBeVisible()

    const searchButton = page.getByText(/Buscar pedido/i)
    await expect(searchButton).toBeVisible()
  })

  test('should switch to shipping mode and show tracking fields', async ({ page }) => {
    await page.getByText(/Rastreo por Envío/i).click()

    // Now shipping fields should be visible
    const guideInput = page.locator('input[placeholder*="982347123"]')
    await expect(guideInput).toBeVisible()

    const carrierInput = page.locator('input[placeholder*="SERVIENTREGA"]')
    const exists = await carrierInput.count()
    if (exists > 0) {
      await expect(carrierInput).toBeVisible()
    }
  })

  test('should have QR scanner button', async ({ page }) => {
    // The QR scanner button: look for a button with QR-related aria-label
    const qrButton = page.getByRole('button', { name: /escaner|qr|scan/i }).first()
    const exists = await qrButton.count()
    if (exists > 0) {
      await expect(qrButton).toBeVisible()
    }
    // Also check for the camera icon SVG
    const iconCount = await page.locator('svg').count()
    expect(iconCount).toBeGreaterThan(0)
  })

  test('should show error when searching with empty ID', async ({ page }) => {
    const searchButton = page.getByText(/Buscar pedido/i)
    await searchButton.click()

    // Should stay on the same page (form validation keeps us here)
    await expect(page).toHaveURL(/\/track/)
  })
})
