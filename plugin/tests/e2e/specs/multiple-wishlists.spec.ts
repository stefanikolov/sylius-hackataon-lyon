import { expect, Page, test } from '@playwright/test';
import {
    addFromListing, cartCount, chooseDestination, createList, go, LISTING, login, openList, openProduct, registerAndLogin,
} from './helpers';

// One test per Gherkin scenario in docs/stages/stage-2-multiple-wishlists.feature (same order).

const rows = (page: Page) => page.locator('[data-test-wishlist-item]');
const lists = (page: Page) => page.locator('[data-test-wishlist-list]');

test('1. Create a named wishlist', async ({ page }) => {
    await createList(page, 'Birthday');
    await expect(page.locator('[data-test-wishlist-name]')).toHaveText('Birthday');
    await expect(lists(page).filter({ hasText: 'Birthday' })).toHaveCount(1);
    await expect(rows(page)).toHaveCount(0);
    await expect(page.locator('[data-test-wishlist-empty]')).toBeVisible();
});

test('2. View all my wishlists', async ({ page }) => {
    for (const name of ['My Favorites', 'Birthday', 'Christmas']) {
        await createList(page, name);
    }
    await go(page, '/en_US/wishlist');
    await expect(lists(page)).toHaveCount(3);
    for (const name of ['My Favorites', 'Birthday', 'Christmas']) {
        await openList(page, name);
    }
});

test('3. View products in a selected wishlist', async ({ page }) => {
    await createList(page, 'Birthday');
    await createList(page, 'Christmas');
    const first = await addFromListing(page, 0, 'Birthday');
    const second = await addFromListing(page, 1, 'Birthday');
    const christmasOnly = await addFromListing(page, 2, 'Christmas');

    await openList(page, 'Birthday');
    await expect(rows(page)).toHaveCount(2);
    await expect(page.locator('[data-test-wishlist-item-name]').filter({ hasText: first })).toHaveCount(1);
    await expect(page.locator('[data-test-wishlist-item-name]').filter({ hasText: second })).toHaveCount(1);
    await expect(page.locator('[data-test-wishlist-item-name]').filter({ hasText: christmasOnly })).toHaveCount(0);
});

test('4. Add a product to a selected wishlist (from the product page)', async ({ page }) => {
    await createList(page, 'My Favorites');
    await createList(page, 'Birthday');

    const name = await openProduct(page);
    await chooseDestination(page.locator('form[action*="/wishlist/add/"]').first(), 'Birthday');
    await page.getByRole('button', { name: /Add to Wishlist/ }).first().click();
    await expect(page.locator('body')).toContainText('added to your wishlist "Birthday"');

    await openList(page, 'Birthday');
    await expect(rows(page)).toHaveCount(1);
    await expect(rows(page).first()).toContainText(name);
    await openList(page, 'My Favorites');
    await expect(rows(page)).toHaveCount(0);
});

test('5. Add the same product to different wishlists (no duplicates)', async ({ page }) => {
    await createList(page, 'Birthday');
    await createList(page, 'Christmas');
    const name = await addFromListing(page, 0, 'Birthday');
    await addFromListing(page, 0, 'Christmas');
    await addFromListing(page, 0, 'Christmas'); // the same product again

    for (const list of ['Birthday', 'Christmas']) {
        await openList(page, list);
        await expect(rows(page)).toHaveCount(1);
        await expect(rows(page).first()).toContainText(name);
    }
});

test('6. Rename a wishlist', async ({ page }) => {
    await createList(page, 'Birthday');
    await addFromListing(page, 0, 'Birthday');
    await addFromListing(page, 1, 'Birthday');

    await openList(page, 'Birthday');
    await page.locator('[data-test-wishlist-rename-form] input[name="name"]').fill('Birthday Ideas');
    await page.locator('[data-test-wishlist-rename]').click();

    await expect(page.locator('[data-test-wishlist-name]')).toHaveText('Birthday Ideas');
    await expect(rows(page)).toHaveCount(2);
    await expect(lists(page).filter({ hasText: /^\s*Birthday\s*\d*\s*$/ })).toHaveCount(0);
});

test('7. Delete a wishlist (with confirmation)', async ({ page }) => {
    await createList(page, 'Birthday');
    await createList(page, 'Christmas');
    await addFromListing(page, 0, 'Birthday');
    await addFromListing(page, 0, 'Christmas');
    const productName = await addFromListing(page, 1, 'Christmas');
    await addFromListing(page, 2, 'Christmas');

    await openList(page, 'Christmas');
    await page.locator('[data-test-wishlist-delete]').click();
    await expect(page.locator('[data-test-wishlist-delete-page]')).toBeVisible(); // nothing is deleted before confirming
    await page.locator('[data-test-wishlist-confirm-delete]').click();

    await go(page, '/en_US/wishlist');
    await expect(lists(page).filter({ hasText: 'Christmas' })).toHaveCount(0);
    await expect(lists(page).filter({ hasText: 'Birthday' })).toHaveCount(1);
    await openList(page, 'Birthday');
    await expect(rows(page)).toHaveCount(1);

    // The catalogue is untouched.
    await go(page, LISTING);
    await expect(page.locator('body')).toContainText(productName);
});

test('8. Remove a product from a specific wishlist', async ({ page }) => {
    await createList(page, 'Birthday');
    await createList(page, 'Christmas');
    const name = await addFromListing(page, 0, 'Birthday');
    await addFromListing(page, 0, 'Christmas');

    await openList(page, 'Birthday');
    await rows(page).first().locator('[data-test-wishlist-remove]').click();
    await expect(rows(page)).toHaveCount(0);

    await openList(page, 'Christmas');
    await expect(rows(page)).toHaveCount(1);
    await expect(rows(page).first()).toContainText(name);
});

test('9. Move a product between wishlists', async ({ page }) => {
    await createList(page, 'Birthday');
    await createList(page, 'Christmas');
    const name = await addFromListing(page, 0, 'Birthday');

    await openList(page, 'Birthday');
    await rows(page).first().locator('[data-test-wishlist-move-form] select').selectOption({ label: 'Christmas' });
    await rows(page).first().locator('[data-test-wishlist-move]').click();
    await expect(rows(page)).toHaveCount(0);

    await openList(page, 'Christmas');
    await expect(rows(page)).toHaveCount(1);
    await expect(rows(page).first()).toContainText(name);
});

test('10. Prevent creating a wishlist without a name', async ({ page }) => {
    await createList(page, 'Birthday');
    for (const empty of ['', '   ']) {
        await go(page, '/en_US/wishlist');
        await page.locator('[data-test-wishlist-create-form] input[name="name"]').fill(empty);
        await page.locator('[data-test-wishlist-create]').click();
        await expect(page.locator('body')).toContainText('A wishlist name is required');
    }
    await expect(lists(page)).toHaveCount(1);
});

test('11. Persist authenticated customer wishlists', async ({ page }) => {
    const email = `wishlists-${Date.now()}@example.com`;
    await registerAndLogin(page, email);
    await createList(page, 'Birthday');
    const name = await addFromListing(page, 0, 'Birthday');

    await go(page, '/en_US/logout');
    await page.context().clearCookies();
    await login(page, email);

    await openList(page, 'Birthday');
    await expect(rows(page)).toHaveCount(1);
    await expect(rows(page).first()).toContainText(name);
});

test('12. Manage multiple wishlists as a guest', async ({ page }) => {
    await createList(page, 'Birthday');
    await createList(page, 'Christmas');
    const name = await addFromListing(page, 0, 'Birthday');

    await page.reload({ waitUntil: 'domcontentloaded' });
    await go(page, '/en_US/wishlist');
    await expect(lists(page)).toHaveCount(2);
    await openList(page, 'Birthday');
    await expect(rows(page).first()).toContainText(name);
});

test("13. Another visitor cannot access or change someone else's wishlist", async ({ page, browser }) => {
    await createList(page, 'Birthday');
    await addFromListing(page, 0, 'Birthday');
    await openList(page, 'Birthday');
    const listUrl = page.url();
    const itemId = Number((await rows(page).first().locator('[data-test-wishlist-remove]').evaluate((button) => (button.closest('form') as HTMLFormElement).action)).match(/remove\/(\d+)/)![1]);

    const stranger = await browser.newContext({ ignoreHTTPSErrors: true });
    try {
        const view = await stranger.request.get(listUrl);
        expect(view.status()).toBe(404);
        expect((await stranger.request.post(`${listUrl}/rename`, { form: { name: 'Hacked' } })).status()).toBe(404);
        expect((await stranger.request.post(`${listUrl}/delete`, { form: {} })).status()).toBe(404);

        // The owner's list is unchanged.
        await go(page, listUrl);
        await expect(page.locator('[data-test-wishlist-name]')).toHaveText('Birthday');
        await expect(rows(page)).toHaveCount(1);
        expect(itemId).toBeGreaterThan(0);
    } finally {
        await stranger.close();
    }
});

test('14. Add products from a selected wishlist to the cart', async ({ page }) => {
    await createList(page, 'Christmas');
    await createList(page, 'Birthday');
    await addFromListing(page, 0, 'Christmas');
    await addFromListing(page, 1, 'Christmas');
    await addFromListing(page, 2, 'Christmas');
    await addFromListing(page, 3, 'Birthday');

    await openList(page, 'Christmas');
    await rows(page).nth(0).locator('input[type=checkbox]').check();
    await rows(page).nth(1).locator('input[type=checkbox]').check();
    await page.locator('[data-test-wishlist-add-selected-to-cart]').click();
    await page.waitForURL(/cart/); // let the redirect to the cart finish before navigating away
    expect(await cartCount(page)).toBe(2);

    await openList(page, 'Christmas');
    await expect(rows(page)).toHaveCount(3);
    await openList(page, 'Birthday');
    await expect(rows(page)).toHaveCount(1);
});
