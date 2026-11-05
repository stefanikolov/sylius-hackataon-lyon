import { expect, test } from '@playwright/test';
import { API, ApiClient, bodyText, customerClient, GUEST_HEADER, guestClient, PASSWORD, SUNGLASSES, T_SHIRT } from './api-helpers';
import { go, login, sql } from './helpers';

// One test per Gherkin scenario in docs/stages/stage-4-wishlist-api.feature (same order).

const names = (wishlist: { items: { name: string }[] }) => wishlist.items.map((item) => item.name);

test('1. Retrieve all wishlists', async ({ request }) => {
    const { client } = await customerClient(request, 'John');
    const { client: alice } = await customerClient(request, 'Alice');
    for (const name of ['My Favorites', 'Birthday', 'Christmas']) {
        await client.createList(name);
    }
    await alice.createList('Alice private');

    const response = await client.get(API);
    expect(response.status()).toBe(200);
    const lists = await response.json();
    expect(lists.map((list: { name: string }) => list.name).sort()).toEqual(['Birthday', 'Christmas', 'My Favorites']);
    for (const list of lists) {
        expect(list.id).toBeGreaterThan(0);
    }
});

test('2. Retrieve a specific wishlist', async ({ request }) => {
    const { client } = await customerClient(request, 'John');
    const id = await client.createList('Birthday');
    await client.addProduct(id, T_SHIRT);
    await client.addProduct(id, SUNGLASSES);

    const response = await client.get(`${API}/${id}`);
    expect(response.status()).toBe(200);
    const wishlist = await response.json();
    expect(wishlist).toMatchObject({ id, name: 'Birthday', itemCount: 2 });
    expect(names(wishlist)).toEqual(expect.arrayContaining(['Celestial Harmony T-Shirt', 'Nebula Dream T-Shirt']));
});

test('3. Create a new wishlist', async ({ request }) => {
    const { client } = await customerClient(request, 'John');
    const response = await client.post(API, { name: 'Christmas' });
    expect(response.status()).toBe(201);
    const created = await response.json();
    expect(created.id).toBeGreaterThan(0);
    expect(response.headers()['location']).toBe(`${API}/${created.id}`);

    expect((await client.lists()).map((list) => list.name)).toContain('Christmas');
});

test('4. Rename an existing wishlist', async ({ request }) => {
    const { client } = await customerClient(request, 'John');
    const id = await client.createList('Birthday');
    await client.addProduct(id, T_SHIRT);
    await client.addProduct(id, SUNGLASSES);
    const before = names(await client.wishlist(id));

    const response = await client.patch(`${API}/${id}`, { name: 'Birthday Ideas' });
    expect(response.status()).toBe(200);
    expect((await response.json()).name).toBe('Birthday Ideas');

    const after = await client.wishlist(id);
    expect(after.name).toBe('Birthday Ideas');
    expect(names(after)).toEqual(before);
});

test('5. Delete a wishlist', async ({ request }) => {
    const { client } = await customerClient(request, 'John');
    const christmas = await client.createList('Christmas');
    const other = await client.createList('Birthday');
    await client.addProduct(christmas, T_SHIRT);
    await client.addProduct(christmas, SUNGLASSES);
    await client.addProduct(other, T_SHIRT);

    expect((await client.delete(`${API}/${christmas}`)).status()).toBe(204);
    expect((await client.get(`${API}/${christmas}`)).status()).toBe(404);
    expect((await client.wishlist(other)).itemCount).toBe(1); // the other list is unchanged

    // The catalogue still has the products.
    expect((await request.get(`/api/v2/shop/products/${T_SHIRT}`, { headers: { Accept: 'application/ld+json' } })).status()).toBe(200);
});

test('6. Add a product to a wishlist', async ({ request }) => {
    const { client } = await customerClient(request, 'John');
    const favorites = await client.createList('My Favorites');
    const other = await client.createList('Birthday');

    const response = await client.post(`${API}/${favorites}/items`, { productCode: T_SHIRT });
    expect(response.status()).toBe(201);
    expect(names((await response.json()).wishlist)).toContain('Celestial Harmony T-Shirt');
    expect(names(await client.wishlist(favorites))).toContain('Celestial Harmony T-Shirt');
    expect((await client.wishlist(other)).itemCount).toBe(0);
});

test('7. Prevent duplicate wishlist entries', async ({ request }) => {
    const { client } = await customerClient(request, 'John');
    const id = await client.createList('My Favorites');
    await client.addProduct(id, T_SHIRT);

    const again = await client.post(`${API}/${id}/items`, { productCode: T_SHIRT });
    expect(again.status()).toBe(200);
    const body = await again.json();
    expect(body.added).toBe(false);
    expect(body.message).toContain('already');
    expect((await client.wishlist(id)).itemCount).toBe(1);
});

test('8. Remove a product from a wishlist', async ({ request }) => {
    const { client } = await customerClient(request, 'John');
    const id = await client.createList('Birthday');
    const wishlist = await client.addProduct(id, T_SHIRT);
    await client.addProduct(id, SUNGLASSES);
    const tShirt = wishlist.items.find((item: { productCode: string }) => item.productCode === T_SHIRT);

    expect((await client.delete(`${API}/${id}/items/${tShirt.id}`)).status()).toBe(204);
    const after = await client.wishlist(id);
    expect(names(after)).toEqual(['Nebula Dream T-Shirt']);
});

test('9. Add the same product to multiple wishlists', async ({ request }) => {
    const { client } = await customerClient(request, 'John');
    const birthday = await client.createList('Birthday');
    const christmas = await client.createList('Christmas');

    for (const id of [birthday, christmas, christmas]) {
        await client.post(`${API}/${id}/items`, { productCode: T_SHIRT });
    }
    for (const id of [birthday, christmas]) {
        const wishlist = await client.wishlist(id);
        expect(wishlist.itemCount).toBe(1);
        expect(names(wishlist)).toEqual(['Celestial Harmony T-Shirt']);
    }
});

test('10. Move a product between wishlists', async ({ request }) => {
    const { client } = await customerClient(request, 'John');
    const birthday = await client.createList('Birthday');
    const christmas = await client.createList('Christmas');
    const wishlist = await client.addProduct(birthday, SUNGLASSES);

    const response = await client.post(`${API}/${birthday}/items/${wishlist.items[0].id}/move`, { targetWishlistId: christmas });
    expect(response.status()).toBe(200);
    expect((await client.wishlist(birthday)).itemCount).toBe(0);
    expect(names(await client.wishlist(christmas))).toEqual(['Nebula Dream T-Shirt']);
});

test('11. Reject a wishlist with an invalid name', async ({ request }) => {
    const { client } = await customerClient(request, 'John');
    for (const name of ['', '   ', 'x'.repeat(101)]) {
        const response = await client.post(API, { name });
        expect(response.status()).toBe(422);
        const body = await response.json();
        expect(body.error).toBe('validation_failed');
        expect(body.violations.name[0]).toMatch(/name/i);
    }
    expect((await client.post(API, {})).status()).toBe(422); // missing name
    expect(await client.lists()).toHaveLength(0);
});

test('12. Prevent unauthorized wishlist access', async ({ request }) => {
    const { client: john } = await customerClient(request, 'John');
    const { client: alice } = await customerClient(request, 'Alice');
    const id = await john.createList('Birthday secret');
    await john.addProduct(id, T_SHIRT);

    const response = await alice.get(`${API}/${id}`);
    expect(response.status()).toBe(404);
    const text = await bodyText(response);
    expect(text).not.toContain('Birthday secret');
    expect(text).not.toContain('T-Shirt');
    expect(await alice.lists()).toHaveLength(0);
});

test('13. Prevent unauthorized wishlist modification', async ({ request }) => {
    const { client: john } = await customerClient(request, 'John');
    const { client: alice } = await customerClient(request, 'Alice');
    const mine = await alice.createList('Alice list');
    const id = await john.createList('Birthday');
    const wishlist = await john.addProduct(id, T_SHIRT);
    const itemId = wishlist.items[0].id;

    const attempts = [
        await alice.patch(`${API}/${id}`, { name: 'Hacked' }),
        await alice.delete(`${API}/${id}`),
        await alice.post(`${API}/${id}/items`, { productCode: SUNGLASSES }),
        await alice.delete(`${API}/${id}/items/${itemId}`),
        await alice.post(`${API}/${id}/items/${itemId}/move`, { targetWishlistId: mine }),
    ];
    for (const attempt of attempts) {
        expect(attempt.status()).toBe(404);
    }

    const unchanged = await john.wishlist(id);
    expect(unchanged.name).toBe('Birthday');
    expect(names(unchanged)).toEqual(['Celestial Harmony T-Shirt']);
    expect((await alice.wishlist(mine)).itemCount).toBe(0);
});

test('14. Manage a guest wishlist through the API', async ({ request }) => {
    const guest = guestClient(request);
    const created = await guest.post(API, { name: 'My Favorites' });
    expect(created.status()).toBe(201);
    const token = created.headers()[GUEST_HEADER.toLowerCase()];
    expect(token).toMatch(/^[0-9a-f]{48}$/);
    const id = (await created.json()).id;

    const same = guestClient(request, token);
    await same.addProduct(id, T_SHIRT);
    const lists = await same.lists();
    expect(lists.map((list) => list.name)).toEqual(['My Favorites']);
    expect(names(await same.wishlist(id))).toEqual(['Celestial Harmony T-Shirt']);

    // Another guest (no token, or a token that was made up) sees nothing.
    for (const other of [guestClient(request), guestClient(request, 'a'.repeat(48))]) {
        expect(await other.lists()).toHaveLength(0);
        expect((await other.get(`${API}/${id}`)).status()).toBe(404);
    }

    // A token chosen by the client is never adopted: the server issues its own.
    const chosen = 'b'.repeat(48);
    const fresh = await guestClient(request, chosen).post(API, { name: 'Mine' });
    expect(fresh.headers()[GUEST_HEADER.toLowerCase()]).not.toBe(chosen);
    expect((await guestClient(request, chosen).lists())).toHaveLength(0);
});

test('15. Synchronize API and storefront changes', async ({ request, page }) => {
    const { client, email } = await customerClient(request, 'John');
    const id = await client.createList('Birthday');
    await client.addProduct(id, T_SHIRT);

    // The same customer in the storefront sees what the API saved.
    await login(page, email, PASSWORD);
    await go(page, `/en_US/wishlist/${id}`);
    const rows = page.locator('[data-test-wishlist-item]');
    await expect(rows).toHaveCount(1);
    await expect(rows.first()).toContainText('Celestial Harmony T-Shirt');

    // Removing it in the storefront shows in the API.
    await rows.first().locator('[data-test-wishlist-remove]').click();
    await expect(page.locator('[data-test-wishlist-empty]')).toBeVisible();
    expect((await client.wishlist(id)).itemCount).toBe(0);

    // And the other way round: a list made in the API is listed in the storefront.
    await client.createList('From the API');
    await go(page, '/en_US/wishlist');
    await expect(page.locator('[data-test-wishlist-list]').filter({ hasText: 'From the API' })).toHaveCount(1);
});

test('16. Handle an unavailable product', async ({ request }) => {
    const { client } = await customerClient(request, 'John');
    const id = await client.createList('Birthday');
    const wishlist = await client.addProduct(id, T_SHIRT);
    const variantId = wishlist.items[0].variantId;
    expect(wishlist.items[0].available).toBe(true);

    sql(`UPDATE sylius_product_variant SET tracked = 1, on_hand = 0 WHERE id = ${variantId}`);
    try {
        const after = await client.wishlist(id);
        expect(after.itemCount).toBe(1);
        expect(after.items[0].available).toBe(false);
    } finally {
        sql(`UPDATE sylius_product_variant SET tracked = 0 WHERE id = ${variantId}`);
    }
});

test('17. Handle a non-existent wishlist', async ({ request }) => {
    const { client } = await customerClient(request, 'John');
    const response = await client.get(`${API}/99999999`);
    expect(response.status()).toBe(404);
    const text = await bodyText(response);
    expect(JSON.parse(text)).toEqual({ error: 'not_found', message: 'Wishlist not found.' });
    for (const leak of ['Exception', 'Doctrine', 'vendor', 'Stack trace', '.php']) {
        expect(text).not.toContain(leak);
    }
    expect((await client.get(`${API}/not-a-number`)).status()).toBe(404);
});

test('Authentication: a bad token is rejected', async ({ request }) => {
    const response = await new ApiClient(request, { Authorization: 'Bearer not-a-real-token' }).get(API);
    expect(response.status()).toBe(401);
});
