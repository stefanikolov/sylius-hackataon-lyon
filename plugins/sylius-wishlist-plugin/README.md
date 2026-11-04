# MarkApp Sylius Wishlist Plugin

A wishlist for Sylius 2.x. Guests and customers can save products (and the variant they chose),
customers can keep several named lists, and every list can be shared with a link.

## Features

- **Add from the product page and from product listings.** The product page remembers the variant that is selected in the
  Sylius add-to-cart form (size, colour, ...); a listing remembers the default variant.
- **Guests and customers.** A guest gets one list, kept in a cookie. When the guest logs in or registers, that list moves
  into the account (or its products are merged into the default list, without duplicates).
- **Several named lists for customers** (at most 20): create, rename, delete. Names are unique per customer.
- **Private or public per list.** A public list has a share link with a random token, anyone with the link can view it
  (read-only) and put its products in their own cart. A private list, or an unknown token, answers `404`.
- **Copy link** button on the list page, and a counter in the header.
- **Cart integration.** Add one product, or the selected products, to the regular Sylius cart. Products stay on the list.
  Unavailable products (disabled, not in the channel, out of stock) stay visible but cannot be added.
- **Guest restore link.** A guest sees a secret link that restores the list on another device. It is a different token
  than the public share link: sharing a list never gives write access.
- **Bot protection:** CSRF token, rate limiting, honeypot and time trap for guests, `noindex` on all wishlist pages,
  a cleanup command for abandoned guest lists.

## Requirements

- Sylius 2.0 or newer, PHP 8.2 or newer
- `symfony/rate-limiter` (installed as a dependency of this plugin)

## Installation

```bash
composer require mark-app/sylius-wishlist-plugin
```

1. Register the bundle in `config/bundles.php` (Symfony Flex does this for you):

   ```php
   MarkApp\SyliusWishlistPlugin\MarkAppSyliusWishlistPlugin::class => ['all' => true],
   ```

2. Import the plugin configuration, in `config/packages/mark_app_sylius_wishlist.yaml`:

   ```yaml
   imports:
       - { resource: "@MarkAppSyliusWishlistPlugin/config/config.yaml" }
   ```

3. Import the routes, in `config/routes/mark_app_sylius_wishlist.yaml`:

   ```yaml
   mark_app_sylius_wishlist_shop:
       resource: "@MarkAppSyliusWishlistPlugin/config/routes/shop.yaml"
       prefix: /{_locale}
       requirements:
           _locale: ^[A-Za-z]{2,4}(_([A-Za-z]{4}|[0-9]{3}))?(_([A-Za-z]{2}|[0-9]{3}))?$
   ```

4. Create the tables and clear the cache (a new translation file is only picked up after the cache is rebuilt):

   ```bash
   bin/console doctrine:migrations:migrate
   bin/console cache:clear
   ```

The plugin needs no JavaScript build: the copy button and the variant selection are small inline scripts.

## Upgrading

Every version is a new package version with its own Doctrine migration, so a store keeps its wishlists:

```bash
composer update mark-app/sylius-wishlist-plugin
bin/console doctrine:migrations:migrate
bin/console cache:clear
```

| Migration | Adds |
|---|---|
| `Version20261104145200` | the wishlist and wishlist item tables |
| `Version20261104150833` | the chosen variant on every item (existing items get the product's first variant) |

Migrations run in their own namespace, `MarkApp\SyliusWishlistPlugin\Migrations`, and are also reversible.

## Routes

| Route | Path (after `/{_locale}`) | Who |
|---|---|---|
| `mark_app_wishlist_shop_index` | `GET /wishlist` | guest list; customers are sent to their account |
| `mark_app_wishlist_shop_add` | `POST /wishlist/add/{code}` | everyone |
| `mark_app_wishlist_shop_remove` | `POST /wishlist/remove/{id}` | owner |
| `mark_app_wishlist_shop_cart` | `POST /wishlist/cart` | owner |
| `mark_app_wishlist_shop_visibility` | `POST /wishlist/visibility` | owner |
| `mark_app_wishlist_shop_restore` | `GET /wishlist/restore/{token}` | holder of the secret token |
| `mark_app_wishlist_shop_share` | `GET /wishlist/share/{token}` | anyone, public lists only |
| `mark_app_wishlist_shop_share_cart` | `POST /wishlist/share/{token}/cart` | anyone |
| `mark_app_wishlist_shop_account_*` | `/account/wishlists...` | logged-in customers |

## Configuration and limits

| Limit | Value | Where |
|---|---|---|
| Products per list | 100 | `WishlistManager::MAX_ITEMS` |
| Lists per customer | 20 | `WishlistManager::MAX_LISTS` |
| New guest lists per IP | 10 per hour | rate limiter `mark_app_wishlist_guest_create` |
| Write requests per IP | 60 per minute | rate limiter `mark_app_wishlist_write` |
| Unknown share/restore tokens per IP | 20 per minute | rate limiter `mark_app_wishlist_token_miss` |

The rate limiters are added with `prependExtensionConfig`, so your application can override them in `config/packages`:

```yaml
framework:
    rate_limiter:
        mark_app_wishlist_guest_create:
            policy: sliding_window
            limit: 30
            interval: '1 hour'
```

Limits are per client IP. Behind a reverse proxy or load balancer, configure `framework.trusted_proxies`; otherwise every
visitor shares the proxy's IP and the limits hit everyone at once.

### Housekeeping

Remove empty guest lists (older than 24 hours) and guest lists untouched for 90 days, for example from a daily cron job:

```bash
bin/console mark-app:wishlist:cleanup-guests
bin/console mark-app:wishlist:cleanup-guests --empty-after-hours=12 --inactive-after-days=30
```

## Security notes

- Every write needs a CSRF token. Guests also need to pass a honeypot field and a minimum form age.
- A public share link only shows the list name and the owner's first name; no e-mail address or other lists.
- Making a list private again makes its old link answer `404`; making it public again revives the same link.
- Tokens are 192 bits from `random_bytes`. The guest access token and the share token are different values.
- `_redirect` values are only accepted as local paths.

## Translations

`translations/messages.en_US.yaml` and `flashes.en_US.yaml` are the source. Dutch (`nl_NL`) is included. A translation
file must match the locale your shop uses, for example `messages.fr_FR.yaml` for a French shop.

## Things to know

- The product page replaces the Sylius `submit` hookable (`sylius_shop.product.show.content.info.summary.add_to_cart`) to put
  the wishlist button on the same row as "Add to cart". It repeats a few lines of the Sylius template, so check
  `templates/shop/product/show/add_to_cart/submit.html.twig` after a Sylius upgrade. Remove the hook entry in
  `config/twig_hooks/shop.yaml` if you prefer the button on its own row below the description.
- The variant of a product page is read from the Sylius add-to-cart form by a small script when the button is clicked.
  Without JavaScript the default variant is remembered.
- The Sylius icon set does not contain a heart, so the header icon is an inline SVG.
