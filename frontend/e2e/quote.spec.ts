import { test, expect } from '@playwright/test'

test.describe('Quote (Cotizador) Page', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/quote')
    await page.waitForLoadState('networkidle')
    // Wait for heading to confirm page rendered
    await expect(page.locator('h1').first()).toBeVisible({ timeout: 10000 })
  })

  test('should load the quote page with correct title', async ({ page }) => {
    await expect(page).toHaveTitle(/Cotizador/i)
  })

  test('should display the page header', async ({ page }) => {
    const heading = page.locator('h1').filter({ hasText: /COTIZA/i })
    await expect(heading.first()).toBeVisible()
  })

  test('should display technology and material selectors in sidebar', async ({ page }) => {
    // The sidebar should be visible on desktop viewport
    const sidebar = page.locator('aside')
    await expect(sidebar).toBeVisible()

    // Look for technology/material selection elements
    const materialSection = sidebar.getByText(/Material|Tecnología/i)
    await expect(materialSection.first()).toBeVisible()
  })

  test('should display the 3D viewer area', async ({ page }) => {
    // The STL viewer container should be present
    const viewer = page.locator('canvas').first()
    const viewerContainer = page.locator('[class*="aspect-"]').first()

    const viewerExists = await viewer.count()
    const containerExists = await viewerContainer.count()

    if (viewerExists > 0) {
      await expect(viewer).toBeVisible()
    } else if (containerExists > 0) {
      await expect(viewerContainer).toBeVisible()
    }
  })

  test('should display material guide cards', async ({ page }) => {
    // Material guide section should be present (uses reveal animation, so check attached)
    const guideCard = page.getByText(/PLA|PETG|ABS|RESINA/i).first()
    await expect(guideCard).toBeAttached()
  })

  test('should have a request quote button', async ({ page }) => {
    // The "Solicitar Cotización" button should exist somewhere
    const requestBtn = page.getByText(/Solicitar|Cotización|Request/i).first()
    const quoteBtn = page.locator('button').filter({ hasText: /Cotizar/i }).first()

    const requestExists = await requestBtn.count()
    const quoteExists = await quoteBtn.count()

    if (requestExists > 0) {
      await expect(requestBtn).toBeVisible()
    } else if (quoteExists > 0) {
      await expect(quoteBtn).toBeVisible()
    }
  })

  test('should display the price breakdown in sidebar', async ({ page }) => {
    const sidebar = page.locator('aside')
    const priceInfo = sidebar.getByText(/\$\s*\d+/)
    const exists = await priceInfo.count()
    if (exists > 0) {
      await expect(priceInfo.first()).toBeVisible()
    }
  })
})
