import { execFileSync } from 'node:child_process';
import { expect, Locator, Page } from '@playwright/test';

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
export async function addFromListing(page: Page, index: number, listName?: string): Promise<string> {
    await go(page, LISTING);
    const form = page.locator('form[action*="/wishlist/add/"]').nth(index);
    if (listName) {
        await chooseDestination(form, listName);
    }
    const card = form.locator('xpath=ancestor::*[.//a[contains(@href,"/en_US/products/")]][1]');
    const name = (await card.locator('a[href*="/en_US/products/"]').first().innerText()).trim();
    await form.getByRole('button').click();
    await expect(page.locator('body')).toContainText(/(added to|already in) your wishlist/);
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

/** Picks the destination wishlist in the select next to an "Add to Wishlist" button. */
export async function chooseDestination(form: Locator, listName: string) {
    // With a single list there is no choice to make: the product goes to that list anyway.
    if (0 === (await form.locator('select[name="wishlist"]').count())) {
        return;
    }
    const value = await form.locator('option', { hasText: listName }).first().getAttribute('value');
    await form.locator('select[name="wishlist"]').selectOption(value!);
}

/** Creates a wishlist through the form on the wishlist page and returns once it is open. */
export async function createList(page: Page, name: string) {
    await go(page, '/en_US/wishlist');
    await page.locator('[data-test-wishlist-create-form] input[name="name"]').fill(name);
    await page.locator('[data-test-wishlist-create]').click();
}

/** Opens a wishlist from the list of wishlists. */
export async function openList(page: Page, name: string) {
    await go(page, '/en_US/wishlist');
    await page.locator('[data-test-wishlist-list]').filter({ hasText: name }).first().click();
    await expect(page.locator('[data-test-wishlist-name]')).toHaveText(name);
}

/** Registers a customer, activates the account (the store asks for e-mail verification) and logs in. */
export async function registerAndLogin(page: Page, email: string, password = 'Passw0rd!x') {
    await go(page, '/en_US/register');
    await page.getByLabel('First name').fill('Wish');
    await page.getByLabel('Last name').fill('List');
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Phone number').fill('123456789');
    await page.getByLabel('Password', { exact: true }).fill(password);
    await page.getByLabel('Verification').fill(password);
    await page.getByRole('button', { name: /Create an account/ }).click();
    await expect(page).toHaveURL(/thank-you/);
    sql(`UPDATE sylius_shop_user SET enabled = 1, verified_at = NOW() WHERE username = '${email}'`);
    await login(page, email, password);
}

export async function login(page: Page, email: string, password = 'Passw0rd!x') {
    await go(page, '/en_US/login');
    await page.getByLabel('Username').fill(email);
    await page.getByLabel('Password').fill(password);
    await page.getByRole('button', { name: /Login/ }).click();
    await expect(page).not.toHaveURL(/login/);
}

/** Runs a console command of the store (for example the demo data command of the plugin). */
export function consoleCommand(args: string[]): void {
    const php = process.env.PHP_BIN ?? 'php';
    const appDir = process.env.APP_DIR ?? '../../..';
    execFileSync(php, ['bin/console', ...args], { cwd: appDir, stdio: 'pipe' });
}

export async function adminLogin(page: Page, username = 'sylius', password = 'sylius') {
    await go(page, '/admin/login');
    await page.getByLabel('Username').fill(username);
    await page.getByLabel('Password').fill(password);
    await page.getByRole('button', { name: /Login/ }).click();
    await expect(page).not.toHaveURL(/login/);
}
