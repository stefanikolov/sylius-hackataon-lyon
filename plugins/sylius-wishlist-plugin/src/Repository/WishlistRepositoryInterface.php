<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Repository;

use MarkApp\SyliusWishlistPlugin\Entity\WishlistInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;

/**
 * @extends RepositoryInterface<WishlistInterface>
 */
interface WishlistRepositoryInterface extends RepositoryInterface
{
    /**
     * Finds a guest wishlist (no customer) by its secret access token.
     */
    public function findOneGuestByAccessToken(string $accessToken): ?WishlistInterface;

    /**
     * Finds a wishlist by its share token, but only when it is public.
     */
    public function findOnePublicByShareToken(string $shareToken): ?WishlistInterface;

    /**
     * @return list<WishlistInterface> ordered from oldest to newest
     */
    public function findByCustomer(CustomerInterface $customer): array;

    public function findOneByIdAndCustomer(int $id, CustomerInterface $customer): ?WishlistInterface;

    public function countByCustomer(CustomerInterface $customer): int;

    /**
     * The number of products on all lists of the customer, for the counter in the header.
     */
    public function countItemsForCustomer(CustomerInterface $customer): int;

    /**
     * The number of products on the guest wishlist that belongs to the given access token.
     */
    public function countItemsForGuest(string $accessToken): int;

    public function hasNameForCustomer(CustomerInterface $customer, string $name, ?int $excludedId = null): bool;

    /**
     * Guest wishlists that are empty and older than $emptyBefore, or untouched since $inactiveBefore.
     *
     * @return list<WishlistInterface>
     */
    public function findStaleGuestWishlists(\DateTimeImmutable $emptyBefore, \DateTimeImmutable $inactiveBefore): array;
}
