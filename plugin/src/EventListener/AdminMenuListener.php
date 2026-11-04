<?php

declare(strict_types=1);

namespace Hackathon\SyliusWishlistPlugin\EventListener;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/** Adds "Wishlists" to the "Customers" group of the admin menu. */
#[AsEventListener(event: 'sylius.menu.admin.main')]
final class AdminMenuListener
{
    public function __invoke(MenuBuilderEvent $event): void
    {
        $customers = $event->getMenu()->getChild('customers');
        if (null === $customers) {
            return;
        }

        $customers
            ->addChild('hackathon_wishlists', ['route' => 'hackathon_admin_wishlist_index', 'extras' => ['routes' => [
                ['route' => 'hackathon_admin_wishlist_show'],
            ]]])
            ->setLabel('hackathon_wishlist.admin.menu')
            ->setLabelAttribute('icon', 'tabler:heart')
        ;
    }
}
