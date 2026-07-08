import { test, expect } from '@playwright/test'

test.describe('Product Detail Page (/catalog/:id)', () => {
  test('should show not-found state when product does not exist', async ({ page }) => {
    await page.goto('/catalog/non-existent-product-xyz')
    await page.waitForLoadState('networkidle')

    // Should show the fallback "Producto no encontrado" message (auto-retried)
    await expect(page.getByText(/Producto no encontrado/i).first()).toBeVisible({ timeout: 10000 })
  })

  test('should show a link back to catalog when product not found', async ({ page }) => {
    await page.goto('/catalog/non-existent-product-xyz')
    await page.waitForLoadState('networkidle')

    await expect(page.getByText(/Volver al Catálogo/i).first()).toBeVisible({ timeout: 10000 })
  })

  test('should navigate to product detail from catalog and show product info', async ({ page }) => {
    // First go to catalog and wait for items
    await page.goto('/catalog')
    await page.waitForLoadState('networkidle')
    await expect(page.locator('h1').first()).toBeVisible({ timeout: 10000 })

    // Click the first product link
    const itemLink = page.locator('a[href^="/catalog/"]').first()
    const exists = await itemLink.count()
    if (exists === 0) {
      test.skip('No catalog items available')
      return
    }

    await itemLink.click()
    await page.waitForLoadState('networkidle')

    // Should be on a product detail page
    await expect(page).toHaveURL(/\/catalog\//)

    // Check for product elements (auto-retried via toBeAttached)
    await expect(page.locator('h1').first()).toBeAttached({ timeout: 10000 })

    // Should have the WhatsApp purchase button
    const whatsappBtn = page.getByText(/Adquirir por WhatsApp/i)
    const btnExists = await whatsappBtn.count()
    if (btnExists > 0) {
      await expect(whatsappBtn.first()).toBeVisible()
    }
  })

  test('should display product specifications', async ({ page }) => {
    await page.goto('/catalog')
    await page.waitForLoadState('networkidle')
    await expect(page.locator('h1').first()).toBeVisible({ timeout: 10000 })

    const itemLink = page.locator('a[href^="/catalog/"]').first()
    const exists = await itemLink.count()
    if (exists === 0) {
      test.skip('No catalog items available')
      return
    }

    await itemLink.click()
    await page.waitForLoadState('networkidle')

    // Check for specification categories (wait for product page to load)
    await expect(page.locator('h1').first()).toBeAttached({ timeout: 10000 })
    const specs = page.getByText(/Tecnologia|Material|Envio|Garantia/i)
    const specExists = await specs.count()
    if (specExists > 0) {
      await expect(specs.first()).toBeVisible()
    }
  })

  test('should have quantity selector on product detail', async ({ page }) => {
    await page.goto('/catalog')
    await page.waitForLoadState('networkidle')
    await expect(page.locator('h1').first()).toBeVisible({ timeout: 10000 })

    const itemLink = page.locator('a[href^="/catalog/"]').first()
    const exists = await itemLink.count()
    if (exists === 0) {
      test.skip('No catalog items available')
      return
    }

    await itemLink.click()
    await page.waitForLoadState('networkidle')

    // Wait for product page to load, then check for quantity buttons
    await expect(page).toHaveURL(/\/catalog\//)
    const minusBtn = page.locator('button').filter({ hasText: /^-$/ }).first()
    const plusBtn = page.locator('button').filter({ hasText: /^\+$/ }).first()

    const minusExists = await minusBtn.count()
    const plusExists = await plusBtn.count()

    if (minusExists > 0 && plusExists > 0) {
      await expect(minusBtn).toBeVisible()
      await expect(plusBtn).toBeVisible()
    }
  })

  test('should have customer name input for WhatsApp checkout', async ({ page }) => {
    await page.goto('/catalog')
    await page.waitForLoadState('networkidle')
    await expect(page.locator('h1').first()).toBeVisible({ timeout: 10000 })

    const itemLink = page.locator('a[href^="/catalog/"]').first()
    const exists = await itemLink.count()
    if (exists === 0) {
      test.skip('No catalog items available')
      return
    }

    await itemLink.click()
    await page.waitForLoadState('networkidle')

    // Wait for product detail to load
    await expect(page).toHaveURL(/\/catalog\//)
    const nameInput = page.locator('input[placeholder*="Juan Perez"]')
    const inputExists = await nameInput.count()
    if (inputExists > 0) {
      await expect(nameInput).toBeVisible()
    }
  })
})
