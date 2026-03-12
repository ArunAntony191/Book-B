const { test, expect } = require('@playwright/test');

/**
 * Login Tests for BOOK-B
 * Console output redirected to browser console to appear in Playwright UI.
 */

test.describe('Multi-Role Login Flow', () => {

    // Helper to log both to terminal and browser console
    async function log(page, message) {
        console.log(message); // Still log to terminal
        await page.evaluate((msg) => console.log(msg), message); // Log to browser for Playwright UI
    }

    test.beforeEach(async ({ page }, testInfo) => {
        const testName = testInfo.title;
        await log(page, `${testName} (BOOK-B.LoginTest.${testName}) ...`);
        await log(page, `Running ${testName}...`);

        // Navigate to the login page
        await page.goto('pages/login.php');
        const url = page.url();
        const title = await page.title();
        await log(page, `Loaded: ${url} | Title: ${title}`);
    });

    const credentials = [
        { role: 'Admin', email: 'admin@bookb.com', password: 'admin123', dashboard: /dashboard_admin\.php|admin\/dashboard/ },
        { role: 'User', email: 'user@test.com', password: 'admin123', dashboard: /dashboard_user\.php/ },
        { role: 'Library', email: 'library@test.com', password: 'admin123', dashboard: /dashboard_library\.php/ },
        { role: 'Bookstore', email: 'store@test.com', password: 'admin123', dashboard: /dashboard_bookstore\.php/ }
    ];

    for (const cred of credentials) {
        test(`test_login_as_${cred.role.toLowerCase()}`, async ({ page }) => {
            await page.fill('input[name="email"]', cred.email);
            await page.fill('input[name="password"]', cred.password);
            await page.click('button[type="submit"]');

            await page.waitForURL(cred.dashboard, { timeout: 30000 });

            await log(page, `Login successful for ${cred.role}.`);
            await log(page, 'ok');
        });
    }

    test('test_invalid_credentials', async ({ page }) => {
        await page.fill('input[name="email"]', 'incorrect@test.com');
        await page.fill('input[name="password"]', 'wrongpass');
        await page.click('button[type="submit"]');

        await page.waitForURL(/error=invalid_credentials/);

        const errorMsg = page.locator('div[style*="background: #fee2e2"]');
        await expect(errorMsg).toBeVisible();

        await log(page, 'Invalid credentials correctly handled.');
        await log(page, 'ok');
    });

    test('test_mandatory_fields', async ({ page }) => {
        await page.click('button[type="submit"]');
        const emailInput = page.locator('input[name="email"]');
        const isEmailRequired = await emailInput.evaluate(node => node.required);

        expect(isEmailRequired).toBeTruthy();
        await log(page, 'Mandatory fields validation passed.');
        await log(page, 'ok');
    });

});
