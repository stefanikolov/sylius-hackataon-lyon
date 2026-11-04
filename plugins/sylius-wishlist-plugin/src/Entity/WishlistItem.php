<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Entity;

use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;

class WishlistItem implements WishlistItemInterface
{
    protected ?int $id = null;

    protected ?WishlistInterface $wishlist = null;

    protected ?ProductInterface $product = null;

    protected ?ProductVariantInterface $variant = null;

    protected \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWishlist(): ?WishlistInterface
    {
        return $this->wishlist;
    }

    public function setWishlist(?WishlistInterface $wishlist): void
    {
        $this->wishlist = $wishlist;
    }

    public function getProduct(): ?ProductInterface
    {
        return $this->product;
    }

    public function setProduct(?ProductInterface $product): void
    {
        $this->product = $product;
    }

    public function getVariant(): ?ProductVariantInterface
    {
        return $this->variant;
    }

    public function setVariant(?ProductVariantInterface $variant): void
    {
        $this->variant = $variant;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
