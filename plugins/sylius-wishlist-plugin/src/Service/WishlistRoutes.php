<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Service;

/**
 * Customers manage their lists in their account (with the account menu), guests on shop pages. The actions are the same,
 * only the route names differ, so controllers and templates ask this class which routes belong to the current visitor.
 */
final class WishlistRoutes
{
    public function __construct(private readonly WishlistAccessResolver $accessResolver)
    {
    }

    /**
     * @return array{index: string, show: string, create: string, rename: string, delete: string}
     */
    public function names(): array
    {
        if (null !== $this->accessResolver->getCustomer()) {
            return [
                'index' => 'mark_app_wishlist_shop_account_index',
                'show' => 'mark_app_wishlist_shop_account_show',
                'create' => 'mark_app_wishlist_shop_account_create',
                'rename' => 'mark_app_wishlist_shop_account_rename',
                'delete' => 'mark_app_wishlist_shop_account_delete',
            ];
        }

        return [
            'index' => 'mark_app_wishlist_shop_index',
            'show' => 'mark_app_wishlist_shop_guest_show',
            'create' => 'mark_app_wishlist_shop_guest_create',
            'rename' => 'mark_app_wishlist_shop_guest_rename',
            'delete' => 'mark_app_wishlist_shop_guest_delete',
        ];
    }
}
