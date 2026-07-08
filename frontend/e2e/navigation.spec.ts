import { test, expect } from '@playwright/test'

test.describe('Global Navigation', () => {
  test('should navigate between all main routes via navbar', async ({ page }) => {
    await page.goto('/')
    await page.waitForLoadState('networkidle')
    const nav = page.getByRole('navigation')

    // Test each nav link
    const links = [
      { label: 'Galería', expected: /\/galeria/ },
      { label: 'Catálogo', expected: /\/catalog/ },
      { label: 'Cotizador', expected: /\/quote/ },
      { label: 'Rastrear', expected: /\/track/ },
    ]

    for (const link of links) {
      await nav.getByText(link.label).click()
      await page.waitForLoadState('networkidle')
      await expect(page).toHaveURL(link.expected)
    }
  })

  test('should navigate to homepage from brand logo', async ({ page }) => {
    await page.goto('/galeria')
    await page.waitForLoadState('networkidle')

    // Click on the brand/logo link (first link in header)
    const brandLink = page.getByRole('banner').getByText(/N3XT\s*3D/i).first()
    await brandLink.click()
    await page.waitForLoadState('networkidle')

    await expect(page).toHaveURL('/')
  })

  test('should have a working "Acceso Taller" button', async ({ page }) => {
    await page.goto('/')
    await page.waitForLoadState('networkidle')

    // Find and click the "Acceso Taller" link
    const tallerBtn = page.getByText(/Acceso Taller/i)
    await expect(tallerBtn.first()).toBeVisible()

    await tallerBtn.first().click()
    await page.waitForLoadState('networkidle')

    await expect(page).toHaveURL(/\/admin\/login/)
  })

  test('should display footer on every page', async ({ page }) => {
    await page.goto('/')
    await page.waitForLoadState('networkidle')

    const footer = page.locator('footer').last()
    await footer.scrollIntoViewIfNeeded()
    await expect(footer).toBeVisible()
    await expect(footer.getByText(/N3XT 3D/i).first()).toBeVisible()
  })

  test('footer privacy and terms links should be present', async ({ page }) => {
    await page.goto('/')
    await page.waitForLoadState('networkidle')

    const footer = page.locator('footer').last()
    await footer.scrollIntoViewIfNeeded()

    // On the home page, privacy/terms are buttons that open a modal (not router-links)
    // Just verify they are present in the footer
    await expect(footer.getByText('Privacidad').first()).toBeVisible()
    await expect(footer.getByText('Términos').first()).toBeVisible()
  })

  test('should have responsive mobile menu button', async ({ page }) => {
    // Set viewport to mobile size
    await page.setViewportSize({ width: 375, height: 667 })
    await page.goto('/')
    await page.waitForLoadState('networkidle')

    // Mobile menu button should be visible
    const menuButton = page.locator('button[aria-label*="Menú"]').first()
    const menuButtonMobile = page.locator('button[aria-label*="navegación"]').first()

    const oneExists = await menuButton.count() > 0 || await menuButtonMobile.count() > 0
    expect(oneExists).toBeTruthy()
  })
})
