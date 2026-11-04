<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Menu;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

/**
 * Adds "Wishlists" to the Customers group of the administration menu.
 */
final class AdminMenuListener
{
    public function __invoke(MenuBuilderEvent $event): void
    {
        $customers = $event->getMenu()->getChild('customers');

        if (null === $customers) {
            return;
        }

        $customers
            ->addChild('mark_app_wishlists', [
                'route' => 'mark_app_sylius_wishlist_admin_wishlist_index',
                'extras' => ['routes' => [['route' => 'mark_app_sylius_wishlist_admin_wishlist_show']]],
            ])
            ->setLabel('mark_app_sylius_wishlist.ui.wishlists')
            ->setLabelAttribute('icon', 'tabler:bookmark')
        ;
    }
}
