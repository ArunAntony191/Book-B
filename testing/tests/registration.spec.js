// @ts-check
const { test, expect } = require('@playwright/test');

const roles = ['user', 'library', 'bookstore', 'delivery_agent'];

test.describe.serial('Multi-Role Registration and Login Verification', () => {
  for (const role of roles) {
    test(`Register and Verify Login for ${role}`, async ({ page }) => {
      test.setTimeout(90000); // Increase timeout for each role flow
      console.log(`--- Starting Flow for Role: ${role} ---`);

      // 1. Navigate to the registration page
      console.log('Navigating to registration page...');
      await page.goto('pages/register.php');
      await expect(page).toHaveTitle(/Register | BOOK-B/);

      // 2. Generate unique data
      const timestamp = Date.now();
      const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
      const randomString = alphabet[Math.floor(Math.random() * 26)] + alphabet[Math.floor(Math.random() * 26)];
      const firstname = `${role.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join('')}${randomString}`;
      const lastname = 'Tester';
      const email = `${role}_${timestamp}@bookb-test.com`;
      const phone = '9' + Math.floor(100000000 + Math.random() * 900000000).toString();
      const password = 'SecurePass123_!';

      console.log(`Registering: ${firstname} (${email}) with role ${role}`);

      // 3. Fill registration form
      await page.click(`.role-card[data-role="${role}"]`);
      await page.fill('#firstname', firstname);
      await page.fill('#lastname', lastname);
      await page.fill('#email', email);
      await page.fill('#phone', phone);
      await page.fill('#password', password);
      await page.check('#terms');

      // 4. Submit Registration
      console.log('Submitting registration...');
      await Promise.all([
        page.waitForURL(/.*dashboard.*/, { timeout: 60000 }).catch(async (e) => {
          const currentUrl = page.url();
          console.error(`Registration timeout/failure for ${role}. Current URL: ${currentUrl}`);
          if (currentUrl.includes('register.php')) {
            const errorMsg = await page.locator('div[style*="background: #fee2e2"]').innerText().catch(() => 'No error message found on page');
            console.error(`Error message from page: ${errorMsg}`);
          }
          throw e;
        }),
        page.click('#submitBtn')
      ]);
      console.log('Registration successful! Redirected to dashboard.');

      // 5. Logout
      console.log('Logging out...');
      await page.goto('actions/logout.php');
      await expect(page).toHaveURL(/.*login.*/);

      // 6. Verify Login with new credentials
      console.log(`Verifying login for ${email}...`);
      await page.fill('input[name="email"]', email);
      await page.fill('input[name="password"]', password);

      await Promise.all([
        page.waitForURL(/.*dashboard.*/, { timeout: 60000 }).catch(async (e) => {
          console.error(`Login timeout/failure for ${role}. Current URL: ${page.url()}`);
          throw e;
        }),
        page.click('button[type="submit"]')
      ]);

      console.log(`Login verified for ${role}!`);

      // 7. Final Logout for this role
      await page.goto('actions/logout.php');
      console.log(`--- Completed Flow for Role: ${role} ---`);
    });
  }
});
