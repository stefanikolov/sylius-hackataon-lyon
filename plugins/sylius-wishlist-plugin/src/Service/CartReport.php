<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Service;

use MarkApp\SyliusWishlistPlugin\Entity\WishlistInterface;
use MarkApp\SyliusWishlistPlugin\Enum\CartOutcome;
use Symfony\Component\HttpFoundation\Request;

/**
 * Adds the entries selected in a posted form ("items[]") to the cart and reports the result as flash messages.
 */
final class CartReport
{
    public function addSelected(Request $request, WishlistInterface $wishlist, CartAdder $cartAdder, Flasher $flasher): void
    {
        $itemIds = array_map('intval', array_filter($request->request->all('items'), 'is_scalar'));

        if ([] === $itemIds) {
            $flasher->add($request, 'info', 'mark_app_sylius_wishlist.flash.select_products');

            return;
        }

        $added = 0;
        $skipped = 0;

        foreach ($wishlist->getItems() as $item) {
            // Only entries that really are on this list can be added, whatever the form posted.
            if (!\in_array((int) $item->getId(), $itemIds, true)) {
                continue;
            }

            $variant = $item->getVariant();

            null !== $variant && CartOutcome::Added === $cartAdder->add($variant) ? ++$added : ++$skipped;
        }

        if ($added > 0) {
            $flasher->add($request, 'success', 'mark_app_sylius_wishlist.flash.added_to_cart', ['%count%' => $added]);
        }

        if ($skipped > 0) {
            $flasher->add($request, 'error', 'mark_app_sylius_wishlist.flash.not_added_to_cart', ['%count%' => $skipped]);
        }
    }
}
