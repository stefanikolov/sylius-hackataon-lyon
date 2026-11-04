# MarkApp Sylius Wishlist Plugin

A wishlist for Sylius 2.x. Guests and customers can save products (and the variant they chose),
customers can keep several named lists, and every list can be shared with a link.

## Features

- **Several named lists, for customers and for guests.** Create, rename and delete lists (customers: up to 20, guests: up
  to 10). Names are unique per owner, case-insensitive, and cannot be empty or only spaces. Deleting asks for a
  confirmation and never touches the catalogue or the owner's other lists.
- **Choose the list when you add.** On the product page next to the button, on product listings from a dropdown where one
  click picks the list. Without a choice the oldest list is used; the first product creates the list "My Favorites".
- **A product occurs once per list**, with the variant that was chosen last (size, colour, ...). The same product may be
  on several lists. The product page reads the variant from the Sylius add-to-cart form; a listing uses the default variant.
- **Move a product to another list** in one step. If the target already has the product, it is only removed from the source.
- **Guests keep their lists in a cookie.** When a guest logs in or registers, the lists move into the account: a list
  whose name the customer already has is merged into it (without duplicates), the others become new lists.
- **Private or public per list.** A public list has a share link with a random token; anyone with the link can view it
  (read-only) and put its products in their own cart. A private list, or an unknown token, answers `404`.
- **Copy link** button on the list page, and a counter in the header.
- **Cart integration.** Add one product, or the selected products, of any list to the regular Sylius cart. Products stay
  on the list. Unavailable products (disabled, not in the channel, out of stock) stay visible but cannot be added.
- **Guest restore link.** A guest sees a secret link that restores all their lists on another device. It is a different
  token than the public share link: sharing a list never gives write access.
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

| Version | Migration | Adds |
|---|---|---|
| 1.0.0 (Stage 1) | `Version20261104145200` | the wishlist and wishlist item tables |
| 1.0.0 (Stage 1) | `Version20261104150833` | the chosen variant on every item (existing items get the product's first variant) |
| 2.0.0 (Stage 2) | `Version20261104170000` | several lists per guest; one entry per product per list (a list that held several variants of one product keeps the newest entry) |

Migrations run in their own namespace, `MarkApp\SyliusWishlistPlugin\Migrations`, and are also reversible.

Upgrading from 1.0.0 keeps all lists and products. The pages of customers stay in the account area; guests get their own
pages under `/wishlist`.

## Routes

| Route | Path (after `/{_locale}`) | Who |
|---|---|---|
| `mark_app_wishlist_shop_index` | `GET /wishlist` | guest: all lists; customers are sent to their account |
| `mark_app_wishlist_shop_guest_{show,create,rename,delete}` | `/wishlist/lists...` | guests |
| `mark_app_wishlist_shop_account_{index,show,create,rename,delete}` | `/account/wishlists...` | logged-in customers |
| `mark_app_wishlist_shop_add` | `POST /wishlist/add/{code}` | everyone |
| `mark_app_wishlist_shop_remove` | `POST /wishlist/remove/{id}` | owner |
| `mark_app_wishlist_shop_move` | `POST /wishlist/move/{id}` | owner |
| `mark_app_wishlist_shop_cart` | `POST /wishlist/cart` | owner |
| `mark_app_wishlist_shop_visibility` | `POST /wishlist/visibility` | owner |
| `mark_app_wishlist_shop_restore` | `GET /wishlist/restore/{token}` | holder of the secret token |
| `mark_app_wishlist_shop_share` | `GET /wishlist/share/{token}` | anyone, public lists only |
| `mark_app_wishlist_shop_share_cart` | `POST /wishlist/share/{token}/cart` | anyone |

Customers and guests use the same controllers; only the pages differ (account layout with menu for customers, shop
layout for guests). A list of another visitor always answers `404`, never `403`.

## Configuration and limits

| Limit | Value | Where |
|---|---|---|
| Products per list | 100 | `WishlistManager::MAX_ITEMS` |
| Lists per customer | 20 | `WishlistManager::MAX_LISTS` |
| Lists per guest | 10 | `WishlistManager::MAX_GUEST_LISTS` |
| New guests (first list) per IP | 10 per hour | rate limiter `mark_app_wishlist_guest_create` |
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

Remove abandoned guests with all their lists: guests whose lists are all empty and older than 24 hours, and guests who have not
touched any list for 90 days. A guest is removed as a whole, never list by list. For example from a daily cron job:

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
