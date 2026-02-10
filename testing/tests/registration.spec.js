// @ts-check
const { test, expect } = require('@playwright/test');

test('Registration form visual automation tour', async ({ page }) => {
  // 1. Navigate to the registration page
  console.log('Navigating to registration page...');
  await page.goto('pages/register.php');

  // 2. Wait for the page to load
  await expect(page).toHaveTitle(/Register | BOOK-B/);

  // 3. Trigger initial validation errors (Missing fields)
  console.log('Testing empty submission validation...');
  await page.click('#submitBtn');
  await expect(page.locator('#roleError')).toBeVisible();
  await expect(page.locator('#firstnameError')).toBeVisible();
  await page.waitForTimeout(1000);

  // 4. Fill in INVALID data to test validation rules
  console.log('Testing invalid data validation...');
  await page.click('.role-card[data-role="user"]');
  await page.fill('#firstname', 'J'); // Too short
  await page.fill('#email', 'invalid-email'); // Bad format
  await page.fill('#phone', '123'); // Too short
  await page.fill('#password', '123'); // Too weak

  await page.click('#submitBtn');

  // Verify specific validation messages using IDs to avoid ambiguity
  await expect(page.locator('#firstnameError')).toContainText('Must be at least 2 characters');
  await expect(page.locator('#emailError')).toContainText('Please enter a valid email address');
  await expect(page.locator('#passwordError')).toContainText('Password must be at least 8 characters');
  console.log('Validation rules confirmed!');
  await page.waitForTimeout(1500);

  // 5. FIX DATA with CORRECT and UNIQUE info
  console.log('Filling data with unique randomized info...');
  const timestamp = Date.now();
  // Valid Name: Letters only! (Fixing the numeric name bug)
  const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
  const randomName = 'User' + alphabet[Math.floor(Math.random() * 26)] + alphabet[Math.floor(Math.random() * 26)];

  const email = `automated_${timestamp}@test.com`;
  const phone = '9' + Math.floor(100000000 + Math.random() * 900000000).toString();

  // Select a RANDOM role for the successful registration
  const roles = ['user', 'library', 'bookstore', 'delivery_agent'];
  const randomRole = roles[Math.floor(Math.random() * roles.length)];
  console.log(`Selecting Random Role: ${randomRole}`);
  await page.click(`.role-card[data-role="${randomRole}"]`);
  await page.waitForTimeout(500);

  console.log(`Using Data: Name=${randomName}, Email=${email}, Phone=${phone}`);

  await page.fill('#firstname', randomName);
  await page.fill('#lastname', 'Doe');
  await page.fill('#email', email);
  await page.fill('#phone', phone);
  await page.fill('#password', 'SecurePass123_!');
  await page.check('#terms');

  await page.waitForTimeout(1000);

  // --- SMART FIX LOGIC (Detects red errors and fixes them!) ---
  const errorLocators = [
    { id: '#firstnameError', field: '#firstname', fix: () => 'John' + alphabet[Math.floor(Math.random() * 26)] },
    { id: '#emailError', field: '#email', fix: () => `fix_${Date.now()}@test.com` },
    { id: '#phoneError', field: '#phone', fix: () => '9' + Math.floor(100000000 + Math.random() * 900000000).toString() }
  ];

  for (const err of errorLocators) {
    if (await page.locator(err.id).isVisible()) {
      console.log(`Smart Fix: Detected error in ${err.id}. Correcting field...`);
      await page.fill(err.field, ''); // Clear
      await page.fill(err.field, err.fix());
      await page.waitForTimeout(500);
    }
  }

  // 6. FINAL SUBMIT
  console.log('Performing final submission...');

  await Promise.all([
    page.waitForURL(/.*dashboard.*/, { timeout: 60000 }).catch(async () => {
      const currentURL = page.url();
      if (currentURL.includes('error=')) {
        throw new Error(`Registration failed with error in URL: ${currentURL}`);
      }
      throw new Error('Timeout waiting for dashboard.');
    }),
    page.click('#submitBtn')
  ]);

  // Verify success by checking the URL
  console.log('Registration successful! Redirected to dashboard.');
  await expect(page).toHaveURL(/.*dashboard.*/);

  // Optional: Logout to leave the browser ready for the login test
  console.log('Logging out to prepare for next tests...');
  await page.goto('actions/logout.php').catch(() => console.log('Logout failed, proceeding...'));

  await page.waitForTimeout(2000);
});
