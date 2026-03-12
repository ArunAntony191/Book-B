const { test, expect } = require('@playwright/test');

/**
 * Book Listing Tests for BOOK-B
 * Verifies that a normal user can successfully list a book.
 * Console output formatted to match user request.
 */

test.describe('Book Listing Flow', () => {

    // Helper to log both to terminal and browser console
    async function log(page, message) {
        console.log(message);
        await page.evaluate((msg) => console.log(msg), message);
    }

    test.beforeEach(async ({ page }, testInfo) => {
        const testName = testInfo.title;
        await log(page, `${testName} (BOOK-B.ListingTest.${testName}) ...`);
        await log(page, `Running ${testName}...`);

        // 1. Perform Login First
        await page.goto('pages/login.php');
        await page.fill('input[name="email"]', 'user@test.com');
        await page.fill('input[name="password"]', 'admin123');
        await page.click('button[type="submit"]');
        await page.waitForURL(/dashboard_user\.php/);

        // 2. Navigate to Add Listing Page
        await page.goto('pages/add_listing.php');
        const url = page.url();
        const title = await page.title();
        await log(page, `Loaded: ${url} | Title: ${title}`);
    });

    test('test_list_book_successfully', async ({ page }) => {
        const timestamp = Date.now();
        const bookTitle = `Test Book ${timestamp}`;
        const bookAuthor = 'Automated Tester';

        await log(page, `Action: Filling form for "${bookTitle}"...`);

        // Fill basic details
        await page.fill('#input_title', bookTitle);
        await page.fill('#input_author', bookAuthor);

        // Select a category
        await page.click('text=Fiction');

        await page.fill('#input_description', 'This is a test book listed by an automated Playwright script. It should be successful.');

        // Handle Location (Mock GPS values since it's a map picker)
        await log(page, 'Action: Setting manual location coordinates...');
        await page.evaluate(() => {
            document.getElementById('lat').value = '12.9716';
            document.getElementById('lng').value = '77.5946';
            document.getElementById('location_name').value = 'Bangalore, Karnataka, India';
        });
        await page.fill('input[name="landmark"]', 'Test Landmark Near Park');

        // Select Listing Type (Borrow by default)

        // Fill Credit Cost
        await page.fill('input[name="credit_cost"]', '10');

        // Fill Condition
        await page.selectOption('select[name="condition"]', 'new');

        await log(page, 'Action: Clicking Publish Book button...');

        // Submit
        await page.click('button[type="submit"]');

        // Verify Success
        console.log('Status: Waiting for success message...');
        const successBanner = page.locator('div[style*="background: rgba(16, 185, 129, 0.1)"]');
        await expect(successBanner).toBeVisible({ timeout: 15000 });

        const successMsg = await successBanner.innerText();
        await log(page, `Success: ${successMsg.split('\n')[0].trim()}`);
        await log(page, 'ok');
    });

});
