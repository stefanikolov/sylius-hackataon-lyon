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

## Routes

| Route | Method | Path |
|---|---|---|
| `hackathon_wishlist_index` | GET | `/{_locale}/wishlist` |
| `hackathon_wishlist_add` | POST | `/{_locale}/wishlist/add/{variantId}` |
| `hackathon_wishlist_remove` | POST | `/{_locale}/wishlist/remove/{itemId}` |
| `hackathon_wishlist_add_to_cart` | POST | `/{_locale}/wishlist/add-to-cart` (`items[]`) |

All state changes are POST requests protected by a CSRF token.
