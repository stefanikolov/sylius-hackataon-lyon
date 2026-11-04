<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Entity;

use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Resource\Model\ResourceInterface;

interface WishlistItemInterface extends ResourceInterface
{
    public function getWishlist(): ?WishlistInterface;

    public function setWishlist(?WishlistInterface $wishlist): void;

    public function getProduct(): ?ProductInterface;

    public function setProduct(?ProductInterface $product): void;

    public function getCreatedAt(): \DateTimeImmutable;
}
