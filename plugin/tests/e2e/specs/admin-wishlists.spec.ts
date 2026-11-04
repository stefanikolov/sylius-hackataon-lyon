import { expect, Page, test } from '@playwright/test';
import { adminLogin, consoleCommand, go, registerAndLogin } from './helpers';

// One test per Gherkin scenario in docs/stages/stage-3-admin-wishlists.feature (same order).
// The example data (John Doe, Jane Smith and a guest) comes from the demo data command of the plugin.

test.beforeAll(() => {
    consoleCommand(['hackathon:wishlist:demo-data', '--reset']);
});

const rows = (page: Page) => page.locator('[data-test-wishlist-admin-row]');
const listOf = (page: Page, owner: string, name: string) => rows(page).filter({ hasText: owner }).filter({ hasText: name });

async function openAdminList(page: Page) {
    await adminLogin(page);
    await go(page, '/admin/wishlists');
    await expect(rows(page).first()).toBeVisible();
}

async function openDetails(page: Page, owner: string, name: string) {
    await openAdminList(page);
    await listOf(page, owner, name).first().locator('[data-test-wishlist-admin-view]').click();
    await expect(page.locator('[data-test-wishlist-admin-name]').first()).toHaveText(name);
}

test('1. View wishlists in the administration panel', async ({ page }) => {
    await openAdminList(page);
    await expect(page.locator('a[href$="/admin/wishlists"]').first()).toBeAttached(); // menu entry
    const first = rows(page).first();
    await expect(first.locator('[data-test-wishlist-admin-name]')).not.toBeEmpty();
    await expect(first.locator('[data-test-wishlist-admin-owner]')).not.toBeEmpty();
    await expect(first.locator('[data-test-wishlist-admin-count]')).toHaveText(/^\d+$/);
    await expect(first.locator('[data-test-wishlist-admin-view]')).toBeVisible();
});

test('2. View multiple wishlists belonging to one customer', async ({ page }) => {
    await openAdminList(page);
    for (const [name, count] of [['My Favorites', '3'], ['Birthday', '5'], ['Christmas', '2']]) {
        const row = listOf(page, 'john@example.com', name);
        await expect(row).toHaveCount(1);
        await expect(row).toContainText('John Doe');
        await expect(row.locator('[data-test-wishlist-admin-count]')).toHaveText(count);
    }
});

test('3. View wishlist details', async ({ page }) => {
    await openDetails(page, 'john@example.com', 'Birthday');
    await expect(page.locator('[data-test-wishlist-admin-owner]')).toContainText('John Doe');
    await expect(page.locator('[data-test-wishlist-admin-product]')).toHaveCount(5);
    await expect(page.locator('[data-test-wishlist-admin-count]')).toHaveText('5');
});

test('4. View wishlist product information', async ({ page }) => {
    await openDetails(page, 'john@example.com', 'Birthday');
    for (const product of await page.locator('[data-test-wishlist-admin-product]').all()) {
        await expect(product.locator('[data-test-wishlist-admin-product-name]')).not.toBeEmpty();
        await expect(product.locator('img')).toBeVisible();
        await expect(product.locator('[data-test-wishlist-admin-price]')).toContainText(/[$€£]\s?\d|\d\s?[$€£]/);
    }
});

test('5. Identify an authenticated customer', async ({ page }) => {
    await openDetails(page, 'john@example.com', 'My Favorites');
    await expect(page.locator('[data-test-wishlist-admin-owner]')).toContainText('John Doe');
    await expect(page.locator('[data-test-wishlist-admin-email]')).toHaveText('john@example.com');
});

test('6. Identify a guest wishlist', async ({ page }) => {
    await openAdminList(page);
    const guest = rows(page).filter({ has: page.locator('[data-test-wishlist-admin-owner]', { hasText: 'Guest' }) }).first();
    await expect(guest).toBeVisible();
    await guest.locator('[data-test-wishlist-admin-view]').click();
    await expect(page.locator('[data-test-wishlist-admin-owner]')).toContainText('Guest');
});

test('7. View an empty wishlist', async ({ page }) => {
    await openDetails(page, 'jane@example.com', 'Birthday');
    await expect(page.locator('[data-test-wishlist-admin-owner]')).toContainText('Jane Smith');
    await expect(page.locator('[data-test-wishlist-admin-empty]')).toBeVisible();
    await expect(page.locator('[data-test-wishlist-admin-count]')).toHaveText('0');
});

test('8. Search wishlists by customer', async ({ page }) => {
    await adminLogin(page);
    await go(page, '/admin/wishlists?q=john%40example.com');
    await expect(rows(page)).toHaveCount(3);
    for (const row of await rows(page).all()) {
        await expect(row).toContainText('john@example.com');
    }
    await go(page, '/admin/wishlists?q=Jane+Smith');
    await expect(rows(page)).toHaveCount(2);
    await expect(rows(page).filter({ hasText: 'john@example.com' })).toHaveCount(0);
});

test('9. Restrict wishlist administration to administrators', async ({ page, browser }) => {
    // Anonymous visitor.
    const anonymous = await browser.newContext({ ignoreHTTPSErrors: true });
    const anonymousPage = await anonymous.newPage();
    await go(anonymousPage, '/admin/wishlists');
    await expect(anonymousPage).toHaveURL(/\/admin\/login/);
    await anonymous.close();

    // Logged-in storefront customer.
    await registerAndLogin(page, `not-admin-${Date.now()}@example.com`);
    await go(page, '/admin/wishlists');
    await expect(page).toHaveURL(/\/admin\/login/);
    await expect(page.locator('[data-test-wishlist-admin-row]')).toHaveCount(0);
});

test('10. The administration interface is read-only', async ({ page }) => {
    await openDetails(page, 'john@example.com', 'Birthday');
    const url = page.url();

    // Nothing to click: no forms, inputs, buttons or edit/delete links in the page content.
    await expect(page.locator('.page-body form, .page-body input, .page-body button')).toHaveCount(0);
    await expect(page.locator('.page-body a[href*="delete"], .page-body a[href*="edit"]')).toHaveCount(0);

    // And the server has no way to change it either.
    for (const method of ['post', 'put', 'patch', 'delete'] as const) {
        const response = await page.request[method](url);
        expect(response.status(), method).toBe(405);
    }
    await go(page, url);
    await expect(page.locator('[data-test-wishlist-admin-product]')).toHaveCount(5);
});
