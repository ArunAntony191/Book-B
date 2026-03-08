// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * Buy Workflow Test: Self Pickup with Mutual Confirmation
 * Roles:
 * - Seller: user@test.com (Private User)
 * - Buyer: library@test.com (Library User)
 * 
 * Flow:
 * 1. Seller lists a book for sale.
 * 2. Buyer finds the book and submits a purchase request (Self Pickup + COD).
 * 3. Seller confirms handover in track_deliveries.php.
 * 4. Buyer confirms receipt in track_deliveries.php.
 */

const SELLER = { email: 'user@test.com', password: 'admin123', name: 'User Tester' };
const BUYER = { email: 'library@test.com', password: 'admin123', name: 'Library Tester' };
const BOOK_TITLE = `Buy Test ${Date.now()}`;

test.describe.serial('Purchase Workflow - Self Pickup', () => {

    test('Seller: List a book for sale', async ({ page }) => {
        console.log('--- Step 1: Seller Listing Book ---');
        await page.goto('pages/login.php');
        await page.fill('input[name="email"]', SELLER.email);
        await page.fill('input[name="password"]', SELLER.password);
        await page.click('button[type="submit"]');
        await page.waitForURL(/.*dashboard.*/);

        await page.goto('pages/add_listing.php');
        await page.fill('#input_title', BOOK_TITLE);
        await page.fill('#input_author', 'Test Author');

        // Select Sell Type
        await page.click('.type-card:has-text("Sell")');
        await page.fill('#input_price', '150');
        await page.fill('input[name="quantity"]', '5');
        await page.fill('#input_description', 'A test book for the purchase workflow.');

        // Select a category
        await page.click('.cat-pill:has-text("Fiction")');

        // Use registered address for location
        console.log('Using registered home address...');
        await page.click('button[title="Use my registered Home address"]');
        await page.waitForTimeout(2000); // Wait for geocoding and population

        await page.click('button[type="submit"]');

        // 4. Verify Success Message (No automatic redirect)
        console.log('Verifying success message...');
        await expect(page.locator('text=Successfully listed your book!')).toBeVisible({ timeout: 15000 });
        console.log(`Book listed successfully: ${BOOK_TITLE}`);

        // 5. Navigate to Explore Page
        console.log(`Navigating to Explore page to find "${BOOK_TITLE}"...`);
        await page.goto(`pages/explore.php?query=${encodeURIComponent(BOOK_TITLE)}`);
    });

    test('Buyer: Purchase the book (Self Pickup)', async ({ page }) => {
        console.log('--- Step 2: Buyer Requesting Purchase ---');

        // Debugging logs from page
        page.on('console', msg => console.log('PAGE LOG:', msg.text()));
        page.on('pageerror', err => console.log('PAGE ERROR:', err.message));

        await page.goto('pages/login.php');
        await page.fill('input[name="email"]', BUYER.email);
        await page.fill('input[name="password"]', BUYER.password);
        await page.click('button[type="submit"]');
        await page.waitForURL(/.*dashboard.*/);

        // Find the book on Explore page
        await page.goto(`pages/explore.php?query=${encodeURIComponent(BOOK_TITLE)}`);

        // Disable Live Sync to prevent map movement issues during test
        await page.evaluate(() => {
            const toggle = document.getElementById('live-search-toggle');
            if (toggle && toggle instanceof HTMLInputElement) {
                toggle.checked = false;
                toggle.dispatchEvent(new Event('change'));
            }
        });

        const bookCard = page.locator('.result-card-premium').filter({ hasText: BOOK_TITLE });
        await expect(bookCard).toBeVisible({ timeout: 15000 });
        await bookCard.click();

        await expect(page).toHaveURL(/.*book_details\.php.*/);

        // Debug: Dump the script tag content to see syntax errors
        const scriptContent = await page.evaluate(() => {
            const scripts = Array.from(document.querySelectorAll('script'));
            const mainScript = scripts.find(s => s.innerText.includes('const userId'));
            return mainScript ? mainScript.innerText.substring(0, 1000) : 'Script not found';
        });
        console.log('--- RENDERED SCRIPT START ---');
        console.log(scriptContent);
        console.log('--- RENDERED SCRIPT END ---');

        // Open Request Modal
        console.log('Clicking "Buy Now" button...');
        const buyBtn = page.locator('button:has-text("Buy Now")');
        await buyBtn.scrollIntoViewIfNeeded();
        // Wait for page to fully load and animations to complete
        await page.waitForTimeout(1000);
        await buyBtn.click({ force: true });

        console.log('Waiting for #req-modal to be visible...');
        await page.waitForSelector('#req-modal', { state: 'visible', timeout: 15000 });
        await expect(page.locator('#req-modal')).toBeVisible();

        // Configure Purchase: Self Pickup + COD
        // Delivery is un-checked by default, but let's ensure it.
        const deliveryCheckbox = page.locator('#want-delivery');

        // Ensure the element is ready before interacting
        await deliveryCheckbox.waitFor({ state: 'attached' });

        // Wait another moment for everything in the modal to load properly
        await page.waitForTimeout(500);

        const isChecked = await deliveryCheckbox.isChecked();
        if (isChecked) {
            await deliveryCheckbox.click({ force: true });
        }

        // Select COD (Cash Payment)
        console.log('Selecting Cash Payment...');
        await page.click('.choice-card:has-text("Cash Payment")', { force: true });
        await page.waitForTimeout(500);

        console.log('Submitting purchase request...');
        await Promise.all([
            page.waitForResponse(resp => resp.url().includes('request_action.php') && resp.status() === 200),
            page.click('#btn-submit-request')
        ]);

        console.log('Purchase request submitted (Auto-approved for small qty).');

        // Wait for redirect or modal closure and toast
        await page.waitForTimeout(3000);
    });

    test('Seller: Confirm Handover', async ({ page }) => {
        console.log('--- Step 3: Seller Confirming Handover ---');
        await page.goto('pages/login.php');
        await page.fill('input[name="email"]', SELLER.email);
        await page.fill('input[name="password"]', SELLER.password);
        await page.click('button[type="submit"]');
        await page.waitForURL(/.*dashboard.*/);

        await page.goto('pages/track_deliveries.php');
        await page.click('button:has-text("Pickups")');

        // Target the specific tab content to avoid strict mode violations
        const orderCard = page.locator('#pickups-list .delivery-card').filter({ hasText: BOOK_TITLE });
        await expect(orderCard).toBeVisible();

        // Confirm Handover
        await orderCard.locator('button:has-text("Confirm Handover")').click();

        // Interaction with SweetAlert/Popup confirmation
        await page.click('button:has-text("Yes, Proceed")');

        console.log('Seller confirmed handover.');
        await page.waitForTimeout(2000);
    });

    test('Buyer: Confirm Receive', async ({ page }) => {
        console.log('--- Step 4: Buyer Confirming Receive ---');
        await page.goto('pages/login.php');
        await page.fill('input[name="email"]', BUYER.email);
        await page.fill('input[name="password"]', BUYER.password);
        await page.click('button[type="submit"]');
        await page.waitForURL(/.*dashboard.*/);

        await page.goto('pages/track_deliveries.php');
        await page.click('button:has-text("Pickups")');

        // Target the specific tab content to avoid strict mode violations
        const orderCard = page.locator('#pickups-list .delivery-card').filter({ hasText: BOOK_TITLE });
        await expect(orderCard).toBeVisible();

        // Confirm Receive
        await orderCard.locator('button:has-text("Confirm Receive")').click();

        // Interaction with SweetAlert/Popup confirmation
        await page.click('button:has-text("Yes, Proceed")');

        console.log('Buyer confirmed receive.');

        // Verify final status
        await expect(orderCard.locator('.status-badge')).toHaveText('Delivered & Verified');
        console.log('Transaction Completed: Delivered & Verified');
    });

});

