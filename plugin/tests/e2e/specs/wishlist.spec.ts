import { expect, Page, test } from '@playwright/test';
import { addFromListing, cartCount, go, LISTING, openProduct, sql, wishlistLink } from './helpers';

// One test per Gherkin scenario in docs/stages/stage-1-wishlist.feature (same order).

const rows = (page: Page) => page.locator('[data-test-wishlist-item]');

test('1. Add a product to the wishlist from PDP', async ({ page }) => {
    const name = await openProduct(page);
    await page.getByRole('button', { name: /Add to Wishlist/ }).first().click();
    await expect(page.locator('body')).toContainText('added to your wishlist');
    await go(page, '/en_US/wishlist');
    await expect(rows(page)).toHaveCount(1);
    await expect(rows(page).first()).toContainText(name);
});

test('2. Add a product to the wishlist from PLP', async ({ page }) => {
    const name = await addFromListing(page, 1);
    await go(page, '/en_US/wishlist');
    await expect(rows(page)).toHaveCount(1);
    await expect(rows(page).first()).toContainText(name);
});

test('3. Prevent duplicate wishlist entries', async ({ page }) => {
    await addFromListing(page, 0);
    await go(page, LISTING);
    await page.locator('form[action*="/wishlist/add/"]').first().getByRole('button').click();
    await expect(page.locator('body')).toContainText('already in your wishlist');
    await go(page, '/en_US/wishlist');
    await expect(rows(page)).toHaveCount(1);
});

test('4. Guest wishlist survives browsing and a refresh', async ({ page }) => {
    await addFromListing(page, 0);
    await go(page, '/en_US/');
    await expect(wishlistLink(page)).toContainText('1');
    await page.reload({ waitUntil: 'domcontentloaded' });
    await expect(wishlistLink(page)).toContainText('1');
    await go(page, '/en_US/wishlist');
    await expect(rows(page)).toHaveCount(1);
});

test('5. Authenticated customer wishlist survives logout and login', async ({ page }) => {
    const email = `wishlist-${Date.now()}@example.com`;
    const password = 'Passw0rd!x';

    await go(page, '/en_US/register');
    await page.getByLabel('First name').fill('Wish');
    await page.getByLabel('Last name').fill('List');
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Phone number').fill('123456789');
    await page.getByLabel('Password', { exact: true }).fill(password);
    await page.getByLabel('Verification').fill(password);
    await page.getByRole('button', { name: /Create an account/ }).click();
    await expect(page).toHaveURL(/thank-you/);
    // The store asks for e-mail verification; mark the new account as verified so it can log in.
    sql(`UPDATE sylius_shop_user SET enabled = 1, verified_at = NOW() WHERE username = '${email}'`);

    const login = async () => {
        await go(page, '/en_US/login');
        await page.getByLabel('Username').fill(email);
        await page.getByLabel('Password').fill(password);
        await page.getByRole('button', { name: /Login/ }).click();
    };

    await login();
    const name = await addFromListing(page, 2);
    await go(page, '/en_US/logout');
    await page.context().clearCookies();

    await login();
    await go(page, '/en_US/wishlist');
    await expect(rows(page)).toHaveCount(1);
    await expect(rows(page).first()).toContainText(name);
});

test('6. View wishlist contents (name, image, price, remove, add to cart)', async ({ page }) => {
    await addFromListing(page, 0);
    await addFromListing(page, 1);
    await go(page, '/en_US/wishlist');
    await expect(rows(page)).toHaveCount(2);
    for (const row of await rows(page).all()) {
        await expect(row.locator('a[href*="/en_US/products/"]')).toBeVisible();
        await expect(row.locator('img')).toBeVisible();
        await expect(row).toContainText(/[$€£]\s?\d|\d\s?[$€£]/);
        await expect(row.getByRole('button', { name: 'Remove' })).toBeVisible();
        await expect(row.getByRole('button', { name: 'Add to Cart' })).toBeVisible();
    }
});

test('7. Display an empty wishlist', async ({ page }) => {
    await go(page, '/en_US/wishlist');
    await expect(page.locator('body')).toContainText('Your wishlist is empty');
    await page.getByRole('link', { name: /Continue shopping/ }).click();
    await expect(page).toHaveURL(/\/en_US\/?$/);
});

test('8. Remove a product, the others stay unchanged', async ({ page }) => {
    const first = await addFromListing(page, 0);
    const second = await addFromListing(page, 1);
    await go(page, '/en_US/wishlist');
    await rows(page).filter({ hasText: first }).getByRole('button', { name: 'Remove' }).click();
    await expect(rows(page)).toHaveCount(1);
    await expect(rows(page).first()).toContainText(second);
});

test('9. Add a single wishlist product to the cart and keep it in the wishlist', async ({ page }) => {
    await addFromListing(page, 0);
    await go(page, '/en_US/wishlist');
    await rows(page).first().getByRole('button', { name: 'Add to Cart' }).click();
    await expect(page).toHaveURL(/\/cart/);
    expect(await cartCount(page)).toBe(1);
    await go(page, '/en_US/wishlist');
    await expect(rows(page)).toHaveCount(1);
});

test('10. Add only the selected wishlist products to the cart', async ({ page }) => {
    await addFromListing(page, 0);
    await addFromListing(page, 1);
    await addFromListing(page, 2);
    await go(page, '/en_US/wishlist');
    await rows(page).nth(0).locator('input[type=checkbox]').check();
    await rows(page).nth(1).locator('input[type=checkbox]').check();
    await page.getByRole('button', { name: 'Add Selected to Cart' }).click();
    expect(await cartCount(page)).toBe(2);
    await go(page, '/en_US/wishlist');
    await expect(rows(page)).toHaveCount(3);
});

test('11. A product that is no longer purchasable stays visible but cannot be added to the cart', async ({ page }) => {
    await go(page, LISTING);
    const action = await page.locator('form[action*="/wishlist/add/"]').first().getAttribute('action');
    const variantId = Number(action!.match(/add\/(\d+)/)![1]);
    await addFromListing(page, 0);

    sql(`UPDATE sylius_product_variant SET tracked = 1, on_hand = 0 WHERE id = ${variantId}`);
    try {
        await go(page, '/en_US/wishlist');
        await expect(rows(page)).toHaveCount(1);
        await expect(rows(page).first()).toContainText('unavailable');
        await expect(rows(page).first().getByRole('button', { name: 'Add to Cart' })).toBeDisabled();
        await expect(rows(page).first().locator('input[type=checkbox]')).toBeDisabled();
    } finally {
        sql(`UPDATE sylius_product_variant SET tracked = 0 WHERE id = ${variantId}`);
    }
});

test('12. The variant selected on the product page is the one saved (not the default)', async ({ page }) => {
    await openProduct(page);
    const size = page.locator('select[data-option]').first();
    await expect(size).toBeVisible();
    const options = await size.locator('option').allInnerTexts();
    const chosen = options[2].trim(); // not the default (first) option
    await size.selectOption({ label: chosen });
    await page.getByRole('button', { name: /Add to Wishlist/ }).first().click();
    await expect(page.locator('body')).toContainText('added to your wishlist');
    await go(page, '/en_US/wishlist');
    await expect(rows(page)).toHaveCount(1);
    await expect(rows(page).first().locator('.text-muted.small')).toHaveText(chosen);
});
