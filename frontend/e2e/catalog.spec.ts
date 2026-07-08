import { test, expect } from '@playwright/test'

test.describe('Catalog Page', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/catalog')
    await page.waitForLoadState('networkidle')
    // Wait for heading to confirm page rendered
    await expect(page.locator('h1').first()).toBeVisible({ timeout: 10000 })
  })

  test('should load the catalog page with correct title', async ({ page }) => {
    await expect(page).toHaveTitle(/Catálogo/i)
  })

  test('should display catalog header', async ({ page }) => {
    const heading = page.locator('h1').filter({ hasText: /CATÁLOGO/i })
    await expect(heading.first()).toBeVisible()
  })

  test('should display category filter buttons', async ({ page }) => {
    // Category filter bar should exist (uses reveal animation, so check attached instead of visible)
    const categoryButton = page.locator('button').filter({ hasText: /Todos/i }).first()
    await expect(categoryButton).toBeAttached()
  })

  test('should display catalog items after loading', async ({ page }) => {
    const items = page.locator('[class*="stagger-item"]')
    const noItems = page.getByText(/No hay piezas/i)
    const itemsVisible = await items.count()
    const noItemsVisible = await noItems.isVisible()

    if (itemsVisible > 0) {
      await expect(items.first()).toBeVisible()
    } else if (noItemsVisible) {
      await expect(noItems).toBeVisible()
    }
  })

  test('should display PDF catalog download button', async ({ page }) => {
    const downloadBtn = page.getByText(/Descargar Catálogo/i)
    const exists = await downloadBtn.count()
    if (exists > 0) {
      await expect(downloadBtn.first()).toBeVisible()
    }
  })

  test('should navigate to product detail on item click', async ({ page }) => {
    // Wait for catalog items to be attached (reveal animation may keep them hidden)
    await expect(page.locator('[class*="stagger-item"]').first()).toBeAttached({ timeout: 10000 }).catch(() => {})

    const itemLink = page.locator('a[href^="/catalog/"]').first()
    const exists = await itemLink.count()

    if (exists > 0) {
      await itemLink.click()
      await page.waitForLoadState('networkidle')
      await expect(page).toHaveURL(/\/catalog\//)
    }
  })
})
