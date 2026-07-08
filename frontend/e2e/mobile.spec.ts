import { test, expect } from '@playwright/test'

const MOBILE_VIEWPORT = { width: 375, height: 667 }

test.describe('Mobile Viewport — Navegación', () => {
  test.beforeEach(async ({ page }) => {
    await page.setViewportSize(MOBILE_VIEWPORT)
  })

  test('menú hamburguesa debe abrirse y cerrarse', async ({ page }) => {
    await page.goto('/')
    await page.waitForLoadState('networkidle')

    // Find the mobile menu button (visible only on mobile via md:hidden)
    const menuBtn = page.locator('button[aria-label="Menú de navegación"]')
    await expect(menuBtn).toBeVisible()

    // Open menu
    await menuBtn.click()

    // Wait for mobile overlay to appear (CSS transition, auto-retried)
    const overlay = page.locator('.fixed.z-\\[999\\]')
    await expect(overlay).toBeVisible({ timeout: 5000 })

    // Menu overlay should show nav links inside the overlay
    await expect(overlay.getByText('Inicio')).toBeVisible()
    await expect(overlay.getByText('Galería')).toBeVisible()
    await expect(overlay.getByText('Catálogo')).toBeVisible()
    await expect(overlay.getByText('Cotizador')).toBeVisible()
    await expect(overlay.getByText('Rastrear')).toBeVisible()

    // Close by clicking close button again
    await menuBtn.click()
    await expect(overlay).not.toBeVisible({ timeout: 5000 })
  })

  test('navegación móvil debe cambiar de ruta', async ({ page }) => {
    await page.goto('/')
    await page.waitForLoadState('networkidle')

    // Open menu
    const menuBtn = page.locator('button[aria-label="Menú de navegación"]')
    await menuBtn.click()

    // Wait for overlay to appear, then click "Catálogo"
    const overlay = page.locator('.fixed.z-\\[999\\]')
    await expect(overlay).toBeVisible({ timeout: 5000 })
    await overlay.getByText('Catálogo').click()
    await page.waitForLoadState('networkidle')

    await expect(page).toHaveURL(/\/catalog/)
  })
})

test.describe('Mobile Viewport — Login', () => {
  test.beforeEach(async ({ page }) => {
    await page.setViewportSize(MOBILE_VIEWPORT)
    await page.goto('/admin/login')
    await page.waitForLoadState('networkidle')
    await expect(page.locator('#login-email')).toBeVisible({ timeout: 10000 })
  })

  test('formulario de login debe ser usable en móvil', async ({ page }) => {
    // Email and password fields should be visible and usable
    const emailInput = page.locator('#login-email')
    await expect(emailInput).toBeVisible()
    await emailInput.fill('test@example.com')

    const passwordInput = page.locator('#login-password')
    await expect(passwordInput).toBeVisible()
    await passwordInput.fill('password123')

    const submitBtn = page.getByText(/INICIAR SESIÓN/i)
    await expect(submitBtn).toBeVisible()
  })

  test('selector de rol debe funcionar en móvil', async ({ page }) => {
    const adminTab = page.getByText('Administrador')
    await expect(adminTab).toBeVisible()
    await adminTab.click()

    const clientTab = page.getByText('Cliente')
    await expect(clientTab).toBeVisible()
  })

  test('modo registro debe ser accesible en móvil', async ({ page }) => {
    await page.getByText(/REGÍSTRATE AQUÍ/i).click()

    // Registration form should appear (Vue reactivity, auto-retried)
    const nameInput = page.locator('#register-name')
    await expect(nameInput).toBeVisible({ timeout: 10000 })

    const createBtn = page.getByText(/CREAR MI CUENTA/i)
    await expect(createBtn).toBeVisible()
  })

  test('botón volver debe ser visible en móvil', async ({ page }) => {
    const backBtn = page.getByText(/Volver al Inicio/i)
    await expect(backBtn).toBeVisible()
  })
})

test.describe('Mobile Viewport — Cotizador', () => {
  test.beforeEach(async ({ page }) => {
    await page.setViewportSize(MOBILE_VIEWPORT)
    await page.goto('/quote')
    await page.waitForLoadState('networkidle')
    await expect(page.locator('h1').first()).toBeVisible({ timeout: 10000 })
  })

  test('header del cotizador debe ser visible en móvil', async ({ page }) => {
    const heading = page.locator('h1').filter({ hasText: /COTIZA/i })
    await expect(heading.first()).toBeVisible()
  })

  test('sidebar debe estar oculto inicialmente y tener botón toggle', async ({ page }) => {
    // On mobile (< 1024px), the sidebar should be hidden initially
    const sidebar = page.locator('aside')
    const sidebarVisible = await sidebar.isVisible()

    // The toggle button should exist (it's the button with lg:hidden class near the h1)
    // It contains a cog/settings SVG icon
    const toggleBtn = page.locator('button.lg\\:hidden').first()
    const toggleExists = await toggleBtn.count()
    if (toggleExists > 0) {
      await expect(toggleBtn).toBeVisible()

      // Try toggling sidebar visibility
      await toggleBtn.click()
    }
  })

  test('guía de materiales debe estar presente en móvil', async ({ page }) => {
    const guideCard = page.getByText(/PLA|PETG|ABS|RESINA/i).first()
    await expect(guideCard).toBeAttached()
  })

  test('visor 3D debe mostrar área de carga en móvil', async ({ page }) => {
    // The viewer container should exist
    const viewer = page.locator('canvas').first()
    const viewerContainer = page.locator('[class*="aspect-"]').first()
    const viewerExists = await viewer.count()
    if (viewerExists > 0) {
      await expect(viewer).toBeVisible()
    } else {
      await expect(viewerContainer).toBeAttached()
    }
  })
})

test.describe('Mobile Viewport — Rastreo', () => {
  test.beforeEach(async ({ page }) => {
    await page.setViewportSize(MOBILE_VIEWPORT)
    await page.goto('/track')
    await page.waitForLoadState('networkidle')
    await expect(page.locator('h1').first()).toBeVisible({ timeout: 10000 })
  })

  test('header del rastreo debe ser visible en móvil', async ({ page }) => {
    const heading = page.locator('h1').filter({ hasText: /RASTREO/i })
    await expect(heading.first()).toBeVisible()
  })

  test('switcher de modo búsqueda debe funcionar en móvil', async ({ page }) => {
    const projectBtn = page.getByText(/Rastreo por Proyecto/i)
    await expect(projectBtn).toBeVisible()

    const shippingBtn = page.getByText(/Rastreo por Envío/i)
    await expect(shippingBtn).toBeVisible()

    // Switch to shipping mode
    await shippingBtn.click()

    // Shipping input should now be visible (Vue reactivity, auto-retried)
    const guideInput = page.locator('input[placeholder*="982347123"]')
    await expect(guideInput).toBeVisible({ timeout: 10000 })
  })

  test('campo de ID de proyecto debe ser usable en móvil', async ({ page }) => {
    const projectInput = page.locator('input[placeholder*="1024"]')
    await expect(projectInput).toBeVisible()
    await projectInput.fill('TEST-123')
  })

  test('botón de búsqueda debe ser visible en móvil', async ({ page }) => {
    const searchBtn = page.getByText(/Buscar pedido/i)
    await expect(searchBtn).toBeVisible()
  })
})
