import { execFileSync } from 'node:child_process';
import { expect, Page } from '@playwright/test';

export const LISTING = '/en_US/taxons/t-shirts/men';

/** Runs SQL through the store's own console, so the tests work with whatever database the store uses. */
export function sql(statement: string): void {
    const php = process.env.PHP_BIN ?? 'php';
    const appDir = process.env.APP_DIR ?? '../../..';
    execFileSync(php, ['bin/console', 'dbal:run-sql', statement], { cwd: appDir, stdio: 'pipe' });
}

export const wishlistLink = (page: Page) => page.getByRole('link', { name: /Wishlist/ }).first();

/** Opens the product page of the n-th product on the listing and returns its name. */
export async function openProduct(page: Page, index = 0): Promise<string> {
    await go(page, LISTING);
    const link = page.locator('a[href*="/en_US/products/"]').nth(index);
    const name = (await link.innerText()).trim();
    await link.click();
    await expect(page.getByRole('button', { name: /Add to Wishlist|In your wishlist/ }).first()).toBeVisible();
    return name;
}

/** Clicks "Add to Wishlist" on the n-th product card of the listing and returns the product name. */
export async function addFromListing(page: Page, index: number): Promise<string> {
    await go(page, LISTING);
    const form = page.locator('form[action*="/wishlist/add/"]').nth(index);
    const card = form.locator('xpath=ancestor::*[.//a[contains(@href,"/en_US/products/")]][1]');
    const name = (await card.locator('a[href*="/en_US/products/"]').first().innerText()).trim();
    await form.getByRole('button').click();
    await expect(page.locator('body')).toContainText('added to your wishlist');
    return name;
}

export async function cartCount(page: Page): Promise<number> {
    await go(page, '/en_US/cart/');
    return page.locator('[data-test-cart-item-product]').count();
}

/**
 * Navigates without waiting for every asset: the PHP built-in server used in development is single-threaded,
 * so waiting for the full "load" event is slow and flaky.
 */
export async function go(page: Page, url: string) {
    await page.goto(url, { waitUntil: 'domcontentloaded' });
}
