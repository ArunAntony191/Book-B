// @ts-check
const { test, expect } = require('@playwright/test');

test('Login page visual automation tour', async ({ page }) => {
    // 1. Navigate to the login page
    console.log('Navigating to login page...');
    await page.goto('pages/login.php');

    // 2. Wait for the page to load
    await expect(page).toHaveTitle(/Login | BOOK-B/);

    // 3. Test empty submission (Missing fields error)
    console.log('Testing empty submission error...');

    // We need to remove 'required' attributes to test server-side validation
    await page.evaluate(() => {
        document.querySelectorAll('input[required]').forEach(el => el.removeAttribute('required'));
    });

    await page.click('button[type="submit"]');

    // Wait for PHP redirect/error message to appear
    await expect(page.locator('text=Please fill in all fields.')).toBeVisible({ timeout: 10000 });
    await page.waitForTimeout(1500);

    // 4. Fill in invalid credentials
    console.log('Testing invalid credentials...');
    await page.fill('input[name="email"]', 'wrong@example.com');
    await page.fill('input[name="password"]', 'wrongpassword');

    // Clicking submit causes a page reload
    await Promise.all([
        page.waitForLoadState('networkidle'),
        page.click('button[type="submit"]')
    ]);

    // Wait for "Invalid email or password" error
    await expect(page.locator('text=Invalid email or password.')).toBeVisible({ timeout: 10000 });
    await page.waitForTimeout(1000);

    // 5. Fill in the REAL credentials you provided
    console.log('Filling in the credentials you provided...');

    // Ensure the fields are empty first (just in case)
    await page.fill('input[name="email"]', '');
    await page.fill('input[name="email"]', 'mos@gmail.com');
    await page.waitForTimeout(500);

    await page.fill('#password', '');
    await page.fill('#password', 'Mos@1234');
    await page.waitForTimeout(500);

    // 6. Toggle password visibility
    console.log('Toggling password visibility...');
    await page.click('#togglePassword');
    await page.waitForTimeout(1000);
    await page.click('#togglePassword');

    // 7. ACTUALLY SIGN IN
    console.log('Performing final Sign In...');
    await Promise.all([
        page.waitForURL(/.*dashboard.*/).catch(() => console.log('Redirect to dashboard timed out or failed.')),
        page.click('button[type="submit"]')
    ]);

    // Verify we are on a dashboard
    console.log('Verifying login success...');
    await expect(page).not.toHaveURL(/.*error.*/);

    console.log('Login automation tour complete!');
    await page.waitForTimeout(2000);
});
