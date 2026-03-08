// @ts-check
const { test, expect } = require('@playwright/test');

test.describe.serial('Random Book Listing Flow', () => {
    const testUser = { email: 'user@test.com', password: 'admin123' };
    const timestamp = Date.now();
    const bookTitle = `Random Book ${timestamp}`;
    const author = 'Test Author';
    const description = 'This is a randomly generated book description for automated testing purposes. It contains at least ten characters.';

    test('Login, List a Book, and Verify on Explore Page', async ({ page }) => {
        // 1. Login
        console.log(`Logging in as ${testUser.email}...`);
        await page.goto('pages/login.php');
        await page.fill('input[name="email"]', testUser.email);
        await page.fill('input[name="password"]', testUser.password);
        await page.click('button[type="submit"]');
        await expect(page).toHaveURL(/.*dashboard.*/);

        // 2. Navigate to Add Listing
        console.log('Navigating to Add Listing page...');
        await page.goto('pages/add_listing.php');

        // 3. Fill out the form
        console.log(`Filling form for: ${bookTitle}`);
        await page.fill('#input_title', bookTitle);
        await page.fill('#input_author', author);
        await page.click('.cat-pill:has-text("Fiction")');
        await page.fill('#input_description', description);

        // Set Location using the Home button (as requested by user)
        console.log('Clicking "Use my registered Home address" button...');
        await page.click('button[title="Use my registered Home address"]');

        // Wait for the address and coordinates to be populated
        await page.waitForTimeout(1500);
        const locationName = await page.inputValue('#location_name');
        console.log(`Location populated: ${locationName}`);

        await page.fill('input[name="landmark"]', 'Near Terminal 1');

        // Select Type
        console.log('Selecting "Sell" type...');
        await page.click('.type-card:has-text("Sell")');
        await page.fill('#input_price', '499');
        await page.fill('input[name="quantity"]', '2');
        await page.fill('input[name="credit_cost"]', '15');

        // 4. Submit
        console.log('Submitting listing...');
        await page.click('button[type="submit"]');

        // 5. Verify Success
        console.log('Verifying success message...');
        await expect(page.locator('text=Successfully listed your book!')).toBeVisible({ timeout: 15000 });
        console.log('Book listed successfully!');

        // 6. Verify on Explore Page
        console.log(`Searching for "${bookTitle}" on Explore page...`);
        // Using query parameter to bypass map bounds filtering on initial load
        await page.goto(`pages/explore.php?query=${encodeURIComponent(bookTitle)}`, { waitUntil: 'networkidle' });

        // Ensure we are on the right page
        console.log(`Current URL: ${page.url()}`);

        // Immediately turn OFF Live Sync to prevent map movement from clearing results
        console.log('Turning OFF Live Sync for reliable verification...');
        await page.evaluate(() => {
            const toggle = document.getElementById('live-search-toggle');
            if (toggle && toggle['checked']) {
                toggle['checked'] = false;
                toggle.dispatchEvent(new Event('change'));
            }
        });

        // Wait for results to load
        console.log('Waiting for book card to appear...');
        const bookLocator = page.locator('.result-card-premium').filter({ hasText: bookTitle });
        await expect(bookLocator).toBeVisible({ timeout: 20000 });

        // Also verify searching via the UI search bar
        console.log('Testing search bar interaction...');
        const searchInput = page.locator('#book-search-input');
        await searchInput.clear();
        await searchInput.fill(bookTitle);

        // Turn it back ON and wait for the search to trigger
        console.log('Turning ON Live Sync to test search bar interaction...');
        await page.evaluate(() => {
            const toggle = document.getElementById('live-search-toggle');
            if (toggle && !toggle['checked']) {
                toggle['checked'] = true;
                toggle.dispatchEvent(new Event('change'));
            }
        });
        await page.waitForTimeout(2000);

        await expect(bookLocator).toBeVisible({ timeout: 15000 });
        console.log('Book found and search bar interaction verified!');
    });
});
