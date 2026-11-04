<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Menu;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

final class AccountMenuListener
{
    public function __invoke(MenuBuilderEvent $event): void
    {
        $event->getMenu()
            ->addChild('mark_app_wishlists', ['route' => 'mark_app_wishlist_shop_account_index'])
            ->setLabel('mark_app_sylius_wishlist.ui.my_wishlists')
            ->setLabelAttribute('icon', 'tabler:bookmark')
        ;
    }
}
