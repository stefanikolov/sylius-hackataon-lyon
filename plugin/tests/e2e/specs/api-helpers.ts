import { APIRequestContext, APIResponse } from '@playwright/test';
import { sql } from './helpers';

export const API = '/api/v2/shop/wishlists';
export const PASSWORD = 'Passw0rd!x';
export const GUEST_HEADER = 'X-Wishlist-Token';

/** Two products of the example catalogue. */
export const T_SHIRT = 'Celestial_Harmony_T_Shirt';
export const SUNGLASSES = 'Nebula_Dream_T_Shirt';

/** A small HTTP client for the wishlist API: one instance is one caller (a customer or a guest). */
export class ApiClient {
    constructor(
        private readonly request: APIRequestContext,
        readonly headers: Record<string, string> = {},
    ) {}

    private options(data?: unknown) {
        const headers = { Accept: 'application/json', ...this.headers };
        return data === undefined ? { headers } : { headers: { ...headers, 'Content-Type': 'application/json' }, data };
    }

    get(path: string) {
        return this.request.get(path, this.options());
    }

    post(path: string, data: unknown = {}) {
        return this.request.post(path, this.options(data));
    }

    patch(path: string, data: unknown = {}) {
        return this.request.patch(path, this.options(data));
    }

    delete(path: string) {
        return this.request.delete(path, this.options());
    }

    /** Creates a wishlist and returns its id. */
    async createList(name: string): Promise<number> {
        const response = await this.post(API, { name });
        if (response.status() !== 201) {
            throw new Error(`Could not create "${name}": ${response.status()} ${await response.text()}`);
        }
        return (await response.json()).id;
    }

    /** Adds a product (by code) to a list and returns the wishlist as the API shows it afterwards. */
    async addProduct(listId: number, productCode: string) {
        const response = await this.post(`${API}/${listId}/items`, { productCode });
        return (await response.json()).wishlist;
    }

    async wishlist(listId: number) {
        return (await this.get(`${API}/${listId}`)).json();
    }

    async lists(): Promise<{ id: number; name: string; itemCount: number }[]> {
        return (await this.get(API)).json();
    }
}

/**
 * Registers a customer through the shop API and activates the account (the store asks for e-mail verification),
 * then signs in with the JWT endpoint of Sylius. Returns a client that sends the bearer token.
 */
export async function customerClient(request: APIRequestContext, name: string): Promise<{ client: ApiClient; email: string }> {
    const email = `${name.toLowerCase()}-${Date.now()}-${Math.floor(Math.random() * 1e6)}@example.com`;

    const registration = await request.post('/api/v2/shop/customers', {
        headers: { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' },
        data: { email, firstName: name, lastName: 'Tester', password: PASSWORD, subscribedToNewsletter: false },
    });
    if (registration.status() >= 300) {
        throw new Error(`Registration failed: ${registration.status()} ${await registration.text()}`);
    }
    sql(`UPDATE sylius_shop_user SET enabled = 1, verified_at = NOW() WHERE username = '${email}'`);

    const login = await request.post('/api/v2/shop/customers/token', { data: { email, password: PASSWORD } });
    if (!login.ok()) {
        throw new Error(`Login failed: ${login.status()}`);
    }
    const { token } = await login.json();

    return { client: new ApiClient(request, { Authorization: `Bearer ${token}` }), email };
}

/** A guest has no headers until the first list is created; then the server's token identifies them. */
export function guestClient(request: APIRequestContext, token?: string): ApiClient {
    return new ApiClient(request, token ? { [GUEST_HEADER]: token } : {});
}

export async function bodyText(response: APIResponse): Promise<string> {
    return response.text();
}
