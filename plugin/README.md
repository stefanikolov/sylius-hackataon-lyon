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
