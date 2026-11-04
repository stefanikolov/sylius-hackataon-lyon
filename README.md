# Sylius Wishlist Plugin - PS-Summit Hackathon

A wishlist plugin for Sylius 2.x, built during the PS-Summit Hackathon 2026.

This repository contains two things:

| | What | Why it exists |
|---|---|---|
| [`plugin/`](plugin) | **The deliverable.** A reusable, independently installable Sylius plugin (`stefanikolov/sylius-wishlist-plugin`). | The hackathon rules require a plugin that can be installed into any existing store. Anything that only works inside a starter project does not count. |
| everything else | A standard **Sylius 2.3 application** (Sylius Standard). | A store to install the plugin into, run it and demonstrate it. It contains no wishlist code. |

The only places where the application knows about the plugin are `composer.json` (the path repository and the
requirement), `config/bundles.php` (the bundle registration) and `symfony.lock`. This is exactly what a store owner
would get after `composer require`.

> The previous contents of this README (the Sylius Standard readme) are available in the git history.

## Status

| Stage | State |
|---|---|
| 1 - Basic wishlist (MVP) | Implemented |
| 2 - 4 | Not released yet |

The Gherkin scenarios of stage 1 are in [`docs/stages/stage-1-wishlist.feature`](docs/stages/stage-1-wishlist.feature).
They are used as the checklist for the demo.

## Repository map

```
.
├── plugin/                          THE PLUGIN (see "Inside the plugin" below)
├── docs/stages/                     Stage descriptions as Gherkin scenarios
├── config/bundles.php               Registers the plugin bundle (added by Symfony Flex)
├── composer.json                    Contains the "path" repository pointing to ./plugin
├── .env.local                       Local database (not committed): sylius_hackathon
└── (src, templates, config, ...)    Untouched Sylius Standard application
```

### Inside the plugin

```
plugin/
├── composer.json                    Package name, PHP/Sylius constraints, PSR-4 autoload
├── README.md                        Install and upgrade instructions
├── config/services.php              Service wiring
├── src/
│   ├── HackathonSyliusWishlistPlugin.php
│   ├── Entity/        Wishlist, WishlistItem
│   ├── Repository/    WishlistRepository
│   ├── Provider/      WishlistProvider
│   ├── Controller/    WishlistController
│   ├── EventListener/ WishlistCookieListener
│   ├── Twig/          WishlistExtension
│   └── Migrations/    Version20261104143025
├── templates/shop/    button, header_link, index
└── translations/      messages.en.yaml, flashes.en.yaml
```

| File | What it does | Why it is built this way |
|---|---|---|
| `HackathonSyliusWishlistPlugin.php` | The bundle. Registers the Doctrine mapping, the migrations path and the Twig hooks for the storefront. | It is an `AbstractBundle`, so the plugin configures itself. The host application needs no config files, no route import and no template overrides, which keeps installation to `composer require` + migrate. |
| `Entity/Wishlist.php` | A wishlist. Belongs to a customer **or** to an anonymous visitor (a random token). | One model covers guests and customers, so cart and display code do not care which one it is. |
| `Entity/WishlistItem.php` | One saved **product variant**, with a unique constraint on (wishlist, variant). | A variant is the thing that can actually be bought and put in the cart (price, stock and options live there). The database constraint guarantees "no duplicates" even if two requests race. |
| `Repository/WishlistRepository.php` | Finds a wishlist by customer or by guest token. | Lookups stay in one place. A guest token that already belongs to a customer is ignored. |
| `Provider/WishlistProvider.php` | Decides which wishlist the current visitor has, creates it on demand and merges a guest wishlist into the customer wishlist after login. | Controllers and templates never deal with cookies or login state, they just ask for "the wishlist". |
| `EventListener/WishlistCookieListener.php` | Writes or removes the guest cookie (`hackathon_wishlist_token`, 1 year, HttpOnly). | The provider can only mark that a cookie is needed, because cookies belong on the response. The listener keeps that concern separate. |
| `Controller/WishlistController.php` | The wishlist page, add, remove, and add-to-cart (single and multiple). | One small controller, all changes are POST + CSRF. The cart is never touched directly. See "Cart integration". |
| `Twig/WishlistExtension.php` | Twig functions for the item counter and "is this variant already saved". | Templates on product cards stay free of logic and queries. |
| `templates/shop/button.html.twig` | The "Add to Wishlist" button. | Used by two hooks (product page and product card), so there is one template. |
| `templates/shop/header_link.html.twig` | Wishlist link with a counter in the shop header. | Lets customers reach the list from anywhere while browsing. |
| `templates/shop/index.html.twig` | The wishlist page: name, image, current price, remove, add to cart, empty state, unavailable badge. | Extends the shop layout, so header, footer and styling match the store. |
| `translations/` | UI texts (`messages`) and flash messages (`flashes`). | Sylius translates flash messages from the `flashes` domain, so they have to be there. |
| `Migrations/Version20261104143025.php` | Creates the two wishlist tables. | The rules require upgrades without a fresh database. Every future stage adds a **new** migration and never edits an old one. |

## How it fits into Sylius

### Twig hooks instead of template overrides

The buttons are added with Twig hooks, which is how Sylius 2.x wants to be extended:

| Hook | Used for |
|---|---|
| `sylius_shop.product.show.content.info.summary` | Button on the product detail page |
| `sylius_shop.shared.product.card` | Button on every product card in listings |
| `sylius_shop.base.header.content` | Wishlist link and counter in the header |

*Why:* overriding Sylius templates breaks when Sylius changes them and conflicts with other plugins. A hook adds a
block next to the existing ones and leaves everything else alone.

### Guests and customers

- **Guest:** the wishlist lives in the database under a random token. The token is stored in a cookie.
  *Why not browser storage only?* The wishlist page needs current prices and availability from the server anyway, and
  a database record can be merged into a customer account after login. Browser-only storage cannot do either.
- **Customer:** the wishlist is linked to the customer, so it survives logout and login.
- **Login:** when a visitor with a guest wishlist logs in, the guest products are moved into the customer wishlist
  without duplicates and the cookie is removed.

### Cart integration

"Add to Cart" uses the same pieces Sylius' own product page uses:

1. A cart item is built with Sylius' cart item factory and quantity modifier.
2. It is validated with Sylius' `sylius` validation group (variant enabled, stock available).
3. It is added with `sylius.adder.cart_item`.

*Why:* the plugin does not reimplement a cart or any stock rules. If Sylius changes its rules, the wishlist follows.
The wishlist is never modified by cart actions, and the cart is never modified by wishlist actions, only by the
explicit "Add to Cart" buttons. Unavailable products stay on the wishlist, are marked, and are refused by the
server as well, not only by the disabled button.

### Security

- Add, remove and add-to-cart are POST requests protected by a CSRF token.
- A visitor can only remove items from their own wishlist.
- The redirect back after adding only goes to the same site.

## Running it locally

Requirements: PHP 8.3, MySQL 8, Node 22 (only to build assets), Composer.

```bash
composer install
# .env.local (not committed) points to a separate database:
# DATABASE_URL="mysql://root@127.0.0.1:3306/sylius_hackathon?serverVersion=8.4&charset=utf8mb4"
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate --no-interaction   # includes the plugin migration
php bin/console sylius:fixtures:load default --no-interaction  # demo products
yarn install && yarn build                                     # needs Node >= 22.13
php -S 127.0.0.1:8000 -t public
```

- Shop: <http://127.0.0.1:8000/en_US/>
- Wishlist page: <http://127.0.0.1:8000/en_US/wishlist>
- Admin: <http://127.0.0.1:8000/admin/login> (fixtures user: `sylius@example.com`)
- Demo customer: `shop@example.com` / `sylius`. The fixtures leave customers unverified, so mark the account as verified
  in the database (`sylius_shop_user.verified_at`) before logging in.

## Installing the plugin in another store

```bash
composer require stefanikolov/sylius-wishlist-plugin
php bin/console doctrine:migrations:migrate
php bin/console cache:clear
```

Upgrade steps and a feature table are in [`plugin/README.md`](plugin/README.md).

## Demo script (stage 1)

1. Open a product list, click **Add to Wishlist** on a product: confirmation message, counter in the header.
2. Click it again: "already in your wishlist", still one entry.
3. Open a product page and add another product from there.
4. Open **Wishlist**: name, image, price, remove and add-to-cart buttons. Refresh the page: still there.
5. Select two products and click **Add Selected to Cart**: they are in the cart, the wishlist is unchanged.
6. Make a product unavailable (stock 0): it stays visible with a badge and cannot be added to the cart.
7. Log in as the demo customer: the guest products are merged. Log out and in again: they are still there.
8. Remove a product, then remove all: empty-state message with a "Continue shopping" link.

## Design decisions and limits

- **Variants, not products.** The wishlist stores variants. On product pages and cards the first enabled variant is saved.
  Products with several variants do not yet offer a variant choice.
- **Plain forms, no JavaScript.** Pages reload after each action, which the rules allow. Dynamic updates can come later.
- **English only.** Texts are in `plugin/translations/*.en.yaml`.
- **No automated tests yet.** Stage 1 was verified against the running store following the scenarios in
  `docs/stages/stage-1-wishlist.feature`.
- **Upgrades.** Future stages add new migrations only, so existing wishlist data is preserved.
