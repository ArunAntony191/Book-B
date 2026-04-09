const { test, expect } = require('@playwright/test');

/**
 * Library Announcement Tests for BOOK-B
 * Verifies that a library user can successfully post an announcement.
 * Console output formatted to match user request.
 */

test.describe('Library Announcement Flow', () => {

    // Helper to log both to terminal and browser console
    async function log(page, message) {
        console.log(message);
        await page.evaluate((msg) => console.log(msg), message);
    }

    test.beforeEach(async ({ page }, testInfo) => {
        const testName = testInfo.title;
        await log(page, `${testName} (BOOK-B.AnnouncementTest.${testName}) ...`);
        await log(page, `Running ${testName}...`);

        // 1. Login as a Library user
        await page.goto('pages/login.php');
        await page.fill('input[name="email"]', 'library@test.com');
        await page.fill('input[name="password"]', 'admin123');
        await page.click('button[type="submit"]');
        await page.waitForURL(/dashboard_library\.php/);

        // 2. Navigate to Manage Announcements Page
        await page.goto('pages/manage_announcements.php');
        const url = page.url();
        const title = await page.title();
        await log(page, `Loaded: ${url} | Title: ${title}`);
    });

    test('test_post_announcement_successfully', async ({ page }) => {
        const timestamp = Date.now();
        const announcementTitle = `Notice: Library Event ${timestamp}`;
        const announcementMessage = `We are excited to announce a special reading session for children this Sunday at 10 AM. Join us for a fun-filled morning of stories and activities! (${timestamp})`;

        await log(page, `Action: Filling announcement form for "${announcementTitle}"...`);

        // Fill out the form
        await page.fill('#input-title', announcementTitle);
        await page.fill('#input-message', announcementMessage);

        // Optional: Set a future end date
        const futureDate = new Date();
        futureDate.setDate(futureDate.getDate() + 30);
        const dateStr = futureDate.toISOString().split('T')[0];
        await page.fill('#input-end-date', dateStr);

        await log(page, 'Action: Clicking Publish Announcement button...');
        await page.click('#submit-btn');

        // Verify Success Message
        await log(page, 'Status: Waiting for success confirmation...');

        // The page shows a success alert
        const successAlert = page.locator('.alert-success');
        await expect(successAlert).toBeVisible({ timeout: 10000 });

        const successText = await successAlert.innerText();
        await log(page, `Success: ${successText.trim()}`);

        // Verify it appears in the list of previous announcements
        const firstAnnouncementInList = page.locator('.main-content h4').first();
        await expect(firstAnnouncementInList).toHaveText(announcementTitle);
        await log(page, `Status: Verified "${announcementTitle}" is visible in the history.`);

        await log(page, 'ok');
    });

});
