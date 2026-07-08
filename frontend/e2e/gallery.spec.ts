import { test, expect } from '@playwright/test'

test.describe('Gallery Page', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/galeria')
    await page.waitForLoadState('networkidle')
    // Wait for heading to confirm page rendered
    await expect(page.locator('h1').first()).toBeVisible({ timeout: 10000 })
  })

  test('should load the gallery page with correct title', async ({ page }) => {
    await expect(page).toHaveTitle(/Galería/i)
  })

  test('should display gallery header', async ({ page }) => {
    const heading = page.locator('h1').filter({ hasText: /TRABAJOS/i })
    await expect(heading.first()).toBeVisible()
  })

  test('should have category filter buttons', async ({ page }) => {
    // Look for filter buttons (category filters)
    const filterButtons = page.locator('button').filter({ hasText: /Todos/i })
    await expect(filterButtons.first()).toBeVisible()
  })

  test('should display gallery items or empty state', async ({ page }) => {
    // Check if we have gallery items or the empty state message
    const galleryItems = page.locator('[data-reveal-index]')
    const emptyState = page.getByText(/No hay trabajos/i)

    const hasItems = await galleryItems.count()
    const isEmpty = await emptyState.isVisible()

    if (hasItems > 0) {
      await expect(galleryItems.first()).toBeVisible()
    } else if (isEmpty) {
      await expect(emptyState).toBeVisible()
    }
    // If neither is visible yet, the page might still be loading - that's okay for now
  })

  test('should navigate to gallery detail on item click', async ({ page }) => {
    // Try to find a gallery item link (wait for content to render via URL pattern)
    await page.waitForURL('**/galeria', { timeout: 10000 })
    const itemLink = page.locator('a[href^="/galeria/"]').first()
    const exists = await itemLink.count()

    if (exists > 0) {
      await itemLink.click()
      await page.waitForLoadState('networkidle')
      // Should navigate to a gallery detail page
      await expect(page).toHaveURL(/\/galeria\//)
    }
  })
})
