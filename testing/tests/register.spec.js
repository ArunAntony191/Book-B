const { test, expect } = require('@playwright/test');

/**
 * Registration Tests for BOOK-B
 * This test suite covers user registration for different roles
 * and enforces strict name validation (no hyphens/special characters).
 */

test.describe('Registration Flow', () => {

    test.beforeEach(async ({ page }) => {
        test.setTimeout(90000);
        // Navigate to the registration page
        console.log('Navigating to registration page...');
        await page.goto('pages/register.php');
        await expect(page).toHaveTitle(/Register | BOOK-B/);
    });

    const roles = ['user', 'library', 'bookstore', 'delivery_agent'];

    for (const role of roles) {
        test(`successful registration as ${role}`, async ({ page }) => {
            console.log(`Starting registration for role: ${role}`);

            // Select role
            await page.click(`.role-card[data-role="${role}"]`);
            console.log(`Selected role: ${role}`);

            // Fill details
            const timestamp = Date.now();
            const email = `test_${role}_${timestamp}@example.com`;

            await page.fill('#firstname', 'Test');
            await page.fill('#lastname', (role.charAt(0).toUpperCase() + role.slice(1)).replace(/_/g, ' '));
            await page.fill('#email', email);
            await page.fill('#phone', '1234567890');
            await page.fill('#password', 'Password123');
            await page.check('#terms');

            console.log(`Filling form for ${email}...`);

            // Submit
            await page.click('#submitBtn');

            // Verify redirection (Dashboard path depends on role)
            console.log('Waiting for navigation after submission...');
            await page.waitForURL(/dashboard/, { timeout: 60000 });

            const currentURL = page.url();
            console.log(`Successfully registered and redirected to: ${currentURL}`);

            expect(currentURL).toContain('dashboard');
        });
    }

    test('name field validation - reject hyphens and special characters', async ({ page }) => {
        console.log('Testing name field validation constraints...');

        // First Name with hyphen
        await page.fill('#firstname', 'Jean-Luc');
        await page.locator('#firstname').blur(); // Trigger validation

        const firstNameError = page.locator('#firstnameError');
        await expect(firstNameError).toBeVisible();
        await expect(firstNameError).toHaveText('Only letters and spaces allowed.');
        console.log('Hyphen in First Name correctly rejected.');

        // Last Name with special character
        await page.fill('#lastname', 'O\'Connor');
        await page.locator('#lastname').blur();

        const lastNameError = page.locator('#lastnameError');
        await expect(lastNameError).toBeVisible();
        await expect(lastNameError).toHaveText('Only letters and spaces allowed.');
        console.log('Apostrophe in Last Name correctly rejected.');

        // Valid name
        await page.fill('#firstname', 'John');
        await page.locator('#firstname').blur();
        await expect(firstNameError).not.toBeVisible();
        console.log('Valid name accepted.');
    });

    test('mandatory field validation', async ({ page }) => {
        console.log('Testing mandatory field validation...');

        // Attempt to submit empty form
        await page.click('#submitBtn');

        // Check for error messages
        await expect(page.locator('#roleError')).toBeVisible();
        await expect(page.locator('#firstnameError')).toBeVisible();
        await expect(page.locator('#lastnameError')).toBeVisible();
        await expect(page.locator('#emailError')).toBeVisible();
        await expect(page.locator('#phoneError')).toBeVisible();
        await expect(page.locator('#passwordError')).toBeVisible();
        await expect(page.locator('#termsError')).toBeVisible();

        console.log('All mandatory field errors are visible.');
    });
});
