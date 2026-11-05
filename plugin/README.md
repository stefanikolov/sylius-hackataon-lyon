# Sylius Wishlist Plugin

Wishlist for Sylius 2.x storefronts (PS-Summit hackathon). Works for guests and logged-in customers.

## Installation

```bash
composer require stefanikolov/sylius-wishlist-plugin
```

1. Register the bundle in `config/bundles.php` (Symfony Flex does it for you):

   ```php
   Hackathon\SyliusWishlistPlugin\HackathonSyliusWishlistPlugin::class => ['all' => true],
   ```

2. Run the plugin migrations (they are picked up automatically, no extra configuration):

   ```bash
   php bin/console doctrine:migrations:migrate
   ```

3. Clear the cache: `php bin/console cache:clear`

No route import and no template overrides are needed. Routes are declared on the plugin controller, the
doctrine mapping, migrations and Twig hooks are prepended by the bundle.

## Upgrading

Every stage is a new version of the plugin. To upgrade an existing store:

```bash
composer update stefanikolov/sylius-wishlist-plugin
php bin/console doctrine:migrations:migrate
php bin/console cache:clear
```

Existing wishlists are kept: schema changes only ever add tables or columns through new migrations.

## Stage 1 - what it does

| Feature | How |
|---|---|
| "Add to Wishlist" button on product page and product cards | Twig hooks `sylius_shop.product.show.content.info.summary` and `sylius_shop.shared.product.card` |
| Wishlist link with item counter in the header | Twig hook `sylius_shop.base.header.content` |
| Guest wishlist | Stored in the database under a random token kept in the `hackathon_wishlist_token` cookie (1 year) |
| Customer wishlist | Stored against the customer, survives logout/login |
| Guest logs in | Guest wishlist is merged into the customer's (no duplicates) and the cookie is removed |
| No duplicates | Unique constraint on (wishlist, variant) plus a check before inserting |
| Wishlist page `/{_locale}/wishlist` | Name, image, current price, remove, add to cart |
| Add one or many to cart | Sylius' own `CartItemAdder` and the `sylius` validation group, so stock rules are respected |
| Unavailable products | Stay visible with a badge, checkbox and button are disabled, and the server refuses them too |

Wishlist entries are **product variants** (the purchasable thing). On product pages and cards the first
enabled variant is saved.

## Stage 2 - multiple named wishlists

A customer or guest can own many wishlists. Each one has a name and its own products.

| Feature | How |
|---|---|
| Create, rename, delete | Forms on `/{_locale}/wishlist`. Deleting asks for confirmation on a separate page. |
| Names | Cannot be empty or only whitespace, max 100 characters, unique per owner (case-insensitive). |
| Choose the destination | When there is more than one list, a select next to "Add to Wishlist" (product page and product cards). Without a choice the first list is used and `My Favorites` is created if there is none. |
| Same product in several lists | Yes, but only once per list (unique constraint on (wishlist, variant)). |
| Move a product | "Move" on a row, to another list of the same owner. |
| Guests | All guest lists share one token in the `hackathon_wishlist_token` cookie. |
| Login | Guest lists become the customer's. A list with the same name is merged, without duplicates. |
| Privacy | Every list and item is looked up through `WishlistProvider`, so another visitor's list answers 404. |
| Cart | Add to cart works inside each list and never changes the lists. |

### Upgrading from Stage 1

Run `php bin/console doctrine:migrations:migrate`. The migration adds the list name and drops the "one list per owner" constraints. Every Stage 1 wishlist becomes a list named `My Favorites` with all its products.

## Stage 3 - read-only administration

Admin menu: **Customers > Wishlists** (`/admin/wishlists`).

| Feature | How |
|---|---|
| Listing | One row per wishlist: name, owner, e-mail, number of products, "View". Paginated (20 per page). |
| Owner | Registered customers by name and e-mail (link to the customer). Anonymous lists show **Guest**. The guest cookie token is never shown. |
| Search | Customer name, e-mail or wishlist name. The word `guest` finds anonymous lists. |
| Details | `/admin/wishlists/{id}`: name, owner, e-mail, product count, and for each product the image, name (and variant), current price and the date it was saved. Empty lists show a message. |
| Prices | Shown for the first enabled channel (its code is in the column header), because the admin has no storefront channel. |
| Security | Routes are under the admin path, so Sylius' admin firewall applies: anonymous visitors and storefront customers are redirected to the admin login. |
| Read-only | Only GET routes exist. There are no forms or edit/delete actions, and POST/PUT/PATCH/DELETE answer 405. |

No migration is needed for this stage.

### Demo data

`php bin/console hackathon:wishlist:demo-data` creates John Doe (`john@example.com`) with "My Favorites" (3 products), "Birthday" (5) and "Christmas" (2), Jane Smith with "My Favorites" (2) and an empty "Birthday", and one guest list. Use `--reset` to create them again. It is meant for development and demos.

## Stage 4 - wishlist API

A JSON REST API on the same data and the same business rules as the storefront (`WishlistManager` and
`WishlistProvider` are shared). It lives under the Sylius shop API prefix, **`/api/v2/shop/wishlists`**, so customers
sign in the way Sylius already prescribes.

### Authentication

| Caller | How to identify |
|---|---|
| Customer | `Authorization: Bearer <JWT>`. Get it with `POST /api/v2/shop/customers/token` (`{"email": "...", "password": "..."}`). The customer is never read from the request body. |
| Guest | Header `X-Wishlist-Token: <token>`. The token is generated by the server and returned in the `X-Wishlist-Token` response header when the guest creates the first list. A token made up by the client is never adopted. |

The storefront cookie is deliberately ignored by the API. A list that belongs to somebody else answers `404`, exactly like
a list that does not exist. JWT keys must exist (`php bin/console lexik:jwt:generate-keypair`), as for any Sylius shop API.

### Endpoints

| Method | Path | Body | Success |
|---|---|---|---|
| GET | `/api/v2/shop/wishlists` | | `200`, array of `{id, name, itemCount, createdAt}` |
| GET | `/api/v2/shop/wishlists/{id}` | | `200`, the list with its `items` |
| POST | `/api/v2/shop/wishlists` | `{"name": "Birthday"}` | `201` + `Location`, the new list |
| PATCH | `/api/v2/shop/wishlists/{id}` | `{"name": "Birthday Ideas"}` | `200`, the list |
| DELETE | `/api/v2/shop/wishlists/{id}` | | `204` |
| POST | `/api/v2/shop/wishlists/{id}/items` | `{"productCode": "..."}` (or `productId`, `variantId`, `variantCode`; optional `options` to pick a variant) | `201` when added, `200` (`"added": false`) when it was already there |
| DELETE | `/api/v2/shop/wishlists/{id}/items/{itemId}` | | `204` (`itemId` is the `id` of the entry in `items`) |
| POST | `/api/v2/shop/wishlists/{id}/items/{itemId}/move` | `{"targetWishlistId": 12}` | `200`, `{source, target}` |

Bodies must be JSON with `Content-Type: application/json` (otherwise `415`).

An item looks like this. Unavailable products stay in the list and have `"available": false`:

```json
{"id": 156, "productId": 3, "productCode": "Celestial_Harmony_T_Shirt", "name": "Celestial Harmony T-Shirt",
 "variantId": 11, "variantCode": "Celestial_Harmony_T_Shirt-variant-0", "variantName": "S",
 "available": true, "addedAt": "2026-11-04T16:52:18+00:00"}
```

### Errors

| Status | When | Body |
|---|---|---|
| `401` | missing/invalid JWT on a protected call | Sylius' JWT response |
| `404` | list or entry not found, or not yours | `{"error": "not_found", "message": "Wishlist not found."}` |
| `415` | body is not JSON | `{"error": "unsupported_media_type", ...}` |
| `400` | body is not a JSON object | `{"error": "invalid_json", ...}` |
| `422` | invalid data | `{"error": "validation_failed", "message": "...", "violations": {"name": ["A wishlist name is required."]}}` |

Names are trimmed, must not be empty, at most 100 characters, and unique per owner (case-insensitive).

### Examples (cURL)

```bash
BASE=https://sylius-hackataon-lyon.gs

# Customer
TOKEN=$(curl -sk -X POST $BASE/api/v2/shop/customers/token -H 'Content-Type: application/json' \
  -d '{"email":"test@example.com","password":"Test1234!"}' | sed 's/.*"token":"\([^"]*\)".*/\1/')
AUTH="Authorization: Bearer $TOKEN"

curl -sk -X POST $BASE/api/v2/shop/wishlists -H "$AUTH" -H 'Content-Type: application/json' -d '{"name":"Birthday"}'
curl -sk -X POST $BASE/api/v2/shop/wishlists/ID/items -H "$AUTH" -H 'Content-Type: application/json' -d '{"productCode":"Celestial_Harmony_T_Shirt"}'
curl -sk $BASE/api/v2/shop/wishlists/ID -H "$AUTH"
curl -sk -X PATCH $BASE/api/v2/shop/wishlists/ID -H "$AUTH" -H 'Content-Type: application/json' -d '{"name":"Birthday Ideas"}'
curl -sk -X DELETE $BASE/api/v2/shop/wishlists/ID/items/ITEM_ID -H "$AUTH"
curl -sk -X DELETE $BASE/api/v2/shop/wishlists/ID -H "$AUTH"

# Guest: the token is in the response headers of the first POST
curl -sk -i -X POST $BASE/api/v2/shop/wishlists -H 'Content-Type: application/json' -d '{"name":"My Favorites"}'
curl -sk $BASE/api/v2/shop/wishlists -H 'X-Wishlist-Token: <token from the previous response>'
```

A guest who later signs in sends both the bearer token and the guest token once: the guest lists are merged into the
customer's (lists with the same name are combined, without duplicates), the same rule as in the storefront.

## Routes

| Route | Method | Path |
|---|---|---|
| `hackathon_wishlist_index` | GET | `/{_locale}/wishlist` (the default list) |
| `hackathon_wishlist_show` | GET | `/{_locale}/wishlist/{id}` |
| `hackathon_wishlist_create` | POST | `/{_locale}/wishlist/create` |
| `hackathon_wishlist_rename` | POST | `/{_locale}/wishlist/{id}/rename` |
| `hackathon_wishlist_delete` | GET, POST | `/{_locale}/wishlist/{id}/delete` (confirmation, then delete) |
| `hackathon_wishlist_move` | POST | `/{_locale}/wishlist/move/{itemId}` |
| `hackathon_wishlist_add` | POST | `/{_locale}/wishlist/add/{variantId}` |
| `hackathon_wishlist_remove` | POST | `/{_locale}/wishlist/remove/{itemId}` |
| `hackathon_wishlist_add_to_cart` | POST | `/{_locale}/wishlist/add-to-cart` (`items[]`) |

All state changes are POST requests protected by a CSRF token.
