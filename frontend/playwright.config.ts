import { defineConfig, devices } from '@playwright/test'

/**
 * Pruebas de punta a punta contra el sitio corriendo (frontend :5173 + backend :8000).
 * Local: CHROME_PATH=/usr/bin/brave npx playwright test
 * CI: .github/workflows/ci.yml levanta ambos servidores antes de correrlas.
 */
export default defineConfig({
  testDir: './e2e',
  timeout: 45_000,
  fullyParallel: false,
  workers: 1,
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI ? [['github'], ['list']] : 'list',
  use: {
    baseURL: process.env.E2E_BASE_URL || 'http://localhost:5173',
    trace: 'retain-on-failure',
    locale: 'es-CO',
    launchOptions: process.env.CHROME_PATH ? { executablePath: process.env.CHROME_PATH } : {},
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
})
