<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Api;

use MarkApp\SyliusWishlistPlugin\Api\Resource\WishlistResource;
use MarkApp\SyliusWishlistPlugin\Entity\WishlistInterface;
use MarkApp\SyliusWishlistPlugin\Entity\WishlistItemInterface;
use MarkApp\SyliusWishlistPlugin\Service\ProductAvailability;

/**
 * Builds the API view of a wishlist. Secret tokens are never part of it.
 */
final class WishlistResourceFactory
{
    public function __construct(private readonly ProductAvailability $productAvailability)
    {
    }

    public function create(WishlistInterface $wishlist): WishlistResource
    {
        $resource = new WishlistResource();
        $resource->id = $wishlist->getId();
        $resource->name = $wishlist->getName();
        $resource->public = $wishlist->isPublic();
        $resource->itemsCount = $wishlist->getItems()->count();
        $resource->createdAt = $wishlist->getCreatedAt()->format(\DATE_ATOM);
        $resource->updatedAt = $wishlist->getUpdatedAt()->format(\DATE_ATOM);
        // The share token is the public part of a public list, so the owner may hand it out.
        $resource->shareToken = $wishlist->isPublic() ? $wishlist->getShareToken() : null;
        $resource->items = array_map($this->item(...), $wishlist->getItems()->toArray());

        return $resource;
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
