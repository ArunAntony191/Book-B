// @ts-check
const { test, expect } = require('@playwright/test');

const testUsers = [
    { role: 'Admin', email: 'admin@bookb.com', password: 'admin123' },
    { role: 'User', email: 'user@test.com', password: 'admin123' },
    { role: 'Library', email: 'library@test.com', password: 'admin123' },
    { role: 'Bookstore', email: 'store@test.com', password: 'admin123' }
];

test.describe.serial('Login Verification for Different Roles', () => {
    for (const user of testUsers) {
        test(`Verify login for ${user.role}`, async ({ page }) => {
            console.log(`--- Testing login for ${user.role} (${user.email}) ---`);

            await page.goto('pages/login.php');
            await page.fill('input[name="email"]', user.email);
            await page.fill('input[name="password"]', user.password);

            await Promise.all([
                page.waitForURL(/.*dashboard.*/),
                page.click('button[type="submit"]')
            ]);

            console.log(`Successfully reached dashboard for ${user.role}`);
            await expect(page).not.toHaveURL(/.*error.*/);

            // Small pause for visual confirmation
            await page.waitForTimeout(2000);

            // Logout to prepare for next user
            console.log(`Logging out ${user.role}...`);
            await page.goto('actions/logout.php');
            await expect(page).toHaveURL(/.*login.*/);
        });
    }
});
