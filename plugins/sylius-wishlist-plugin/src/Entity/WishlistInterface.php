<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Entity;

use Doctrine\Common\Collections\Collection;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Resource\Model\ResourceInterface;

interface WishlistInterface extends ResourceInterface
{
    public const DEFAULT_NAME = 'My Favorites';

    public function getName(): string;

    public function setName(string $name): void;

    /**
     * A wishlist without a customer is a guest wishlist, reachable only through its access token.
     */
    public function getCustomer(): ?CustomerInterface;

    public function setCustomer(?CustomerInterface $customer): void;

    public function isGuest(): bool;

    /**
     * Secret token of the guest who owns this list (stored in a cookie, usable as a restore link). A guest can own
     * several lists, which then all carry the same token. Customers' lists have none.
     */
    public function getAccessToken(): ?string;

    public function setAccessToken(?string $accessToken): void;

    /**
     * Public token that grants read-only access, effective only while the wishlist is public.
     */
    public function getShareToken(): string;

    public function setShareToken(string $shareToken): void;

    public function isPublic(): bool;

    public function setPublic(bool $public): void;

    public function getCreatedAt(): \DateTimeImmutable;

    public function getUpdatedAt(): \DateTimeImmutable;

    public function touch(): void;

    /**
     * @return Collection<int, WishlistItemInterface>
     */
    public function getItems(): Collection;

    public function addItem(WishlistItemInterface $item): void;

    public function removeItem(WishlistItemInterface $item): void;

    /**
     * A product occurs at most once per list, whatever its variant.
     */
    public function getItemForProduct(ProductInterface $product): ?WishlistItemInterface;
}
