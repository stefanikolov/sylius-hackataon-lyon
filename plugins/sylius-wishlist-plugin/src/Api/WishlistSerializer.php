<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Api;

use MarkApp\SyliusWishlistPlugin\Entity\WishlistInterface;
use MarkApp\SyliusWishlistPlugin\Entity\WishlistItemInterface;
use MarkApp\SyliusWishlistPlugin\Service\ProductAvailability;

/**
 * The JSON shape of a wishlist. Secret tokens are never part of it.
 */
final class WishlistSerializer
{
    public function __construct(private readonly ProductAvailability $productAvailability)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(WishlistInterface $wishlist): array
    {
        return [
            'id' => $wishlist->getId(),
            'name' => $wishlist->getName(),
            'public' => $wishlist->isPublic(),
            'itemsCount' => $wishlist->getItems()->count(),
            'createdAt' => $wishlist->getCreatedAt()->format(\DATE_ATOM),
            'updatedAt' => $wishlist->getUpdatedAt()->format(\DATE_ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(WishlistInterface $wishlist): array
    {
        return $this->summary($wishlist) + [
            // The share token is the public part of a public list, so the owner may hand it out.
            'shareToken' => $wishlist->isPublic() ? $wishlist->getShareToken() : null,
            'items' => array_map($this->item(...), $wishlist->getItems()->toArray()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function item(WishlistItemInterface $item): array
    {
        $product = $item->getProduct();
        $variant = $item->getVariant();

        return [
            'productCode' => $product?->getCode(),
            'name' => $product?->getName(),
            'variantCode' => $variant?->getCode(),
            'variantName' => $variant?->getName(),
            // Unavailable products stay in the list; this flag tells the client that they cannot be bought now.
            'available' => null !== $variant && $this->productAvailability->isPurchasable($variant),
            'addedAt' => $item->getCreatedAt()->format(\DATE_ATOM),
        ];
    }
}
