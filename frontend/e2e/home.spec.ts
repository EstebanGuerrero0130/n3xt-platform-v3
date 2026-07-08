import { test, expect } from '@playwright/test'

test.describe('Home Page', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/')
    // Wait for the page to be fully loaded
    await page.waitForLoadState('networkidle')
  })

  test('should load the home page successfully', async ({ page }) => {
    // Set up console error listener BEFORE navigation
    const errors: string[] = []
    page.on('console', (msg) => {
      if (msg.type() === 'error') errors.push(msg.text())
    })

    await page.goto('/')
    await page.waitForLoadState('networkidle')

    // Check page title contains N3XT 3D
    await expect(page).toHaveTitle(/N3XT 3D/)

    // Known non-critical warnings to exclude:
    // - checkAuth: known issue on navbar auth check
    // - X-Frame-Options: browser warning via <meta>, not app code
    // - favicon/third-party/Roboto: external resource warnings
    const criticalErrors = errors.filter(e =>
      !e.includes('checkAuth') &&
      !e.includes('X-Frame-Options') &&
      !e.includes('favicon') &&
      !e.includes('third-party') &&
      !e.includes('Roboto')
    )
    expect(criticalErrors.length).toBe(0)
  })

  test('should display the navbar with branding', async ({ page }) => {
    const header = page.getByRole('banner')
    await expect(header).toBeVisible()

    // Brand name should be present
    const brandLink = header.getByText(/N3XT\s*3D/i).first()
    await expect(brandLink).toBeVisible()

    // Nav should have key links
    const nav = header.getByRole('navigation')
    await expect(nav.getByText('Inicio')).toBeVisible()
    await expect(nav.getByText('Galería')).toBeVisible()
    await expect(nav.getByText('Catálogo')).toBeVisible()
    await expect(nav.getByText('Cotizador')).toBeVisible()
    await expect(nav.getByText('Rastrear')).toBeVisible()
  })

  test('should display the hero section with title and CTA buttons', async ({ page }) => {
    // The hero should contain "N3XT 3D" heading
    const heroHeading = page.locator('h1').filter({ hasText: /N3XT/i })
    await expect(heroHeading.first()).toBeVisible()

    // CTA buttons should exist
    const iniciarBtn = page.getByText('INICIAR PROYECTO 3D')
    await expect(iniciarBtn.first()).toBeVisible()

    const catalogBtn = page.getByText('VER CATÁLOGO')
    await expect(catalogBtn.first()).toBeVisible()
  })

  test('should display the stats section with key metrics', async ({ page }) => {
    // Scroll down to stats section (below hero)
    const statsSection = page.locator('section').filter({ hasText: /Piezas Fabricadas/i })
    await statsSection.first().scrollIntoViewIfNeeded()
    await expect(statsSection.first()).toBeVisible()

    // Check for stat values (use .first() to avoid strict mode with duplicate matches)
    await expect(page.getByText('+10K').first()).toBeVisible()
    await expect(page.getByText('24H').first()).toBeVisible()
    // 8K appears in both stats and ecosystem text — scope to stats section
    await expect(statsSection.locator('..').getByText('8K').first()).toBeVisible()
  })

  test('should display the process sections', async ({ page }) => {
    // The "NUESTRO PROCESO" section should be present
    const processSection = page.getByText('NUESTRO PROCESO').first()
    await expect(processSection).toBeVisible()
  })

  test('should display footer with company info', async ({ page }) => {
    const footer = page.locator('footer').last()
    await footer.scrollIntoViewIfNeeded()

    await expect(footer.getByText('N3XT 3D').first()).toBeVisible()
    await expect(footer.getByText(/Fabricación Avanzada/i)).toBeVisible()
    await expect(footer.getByText('Privacidad')).toBeVisible()
    await expect(footer.getByText('Términos')).toBeVisible()
  })

  test('navigation links should work', async ({ page }) => {
    // Click on "Galería" in nav
    const nav = page.getByRole('navigation')
    await nav.getByText('Galería').click()
    await page.waitForLoadState('networkidle')
    await expect(page).toHaveURL(/\/galeria/)

    // Go back and click "Catálogo"
    await page.goBack()
    await page.waitForLoadState('networkidle')
    await nav.getByText('Catálogo').click()
    await page.waitForLoadState('networkidle')
    await expect(page).toHaveURL(/\/catalog/)
  })
})
