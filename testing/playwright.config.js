// @ts-check
const { defineConfig, devices } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './tests',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,
  timeout: 60000,

  /* Reporter to use. See https://playwright.dev/docs/test-reporters */
  reporter: [
    ['html'],
    ['list'] // Ensures standard output is visible in terminal/CI
  ],

  use: {
    baseURL: 'http://localhost/BOOK-B project/', // Added trailing slash
    trace: 'on', // Changed from 'on-first-retry' to 'on' for visibility
    screenshot: 'on', // Changed from 'only-on-failure' to 'on'
    video: 'on-first-retry',
    headless: false,
  },

  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
