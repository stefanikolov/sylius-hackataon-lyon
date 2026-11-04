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
     * All lists of the guest that owns the given secret token.
     *
     * @return list<WishlistInterface> ordered from oldest to newest
     */
    public function findByGuestToken(string $accessToken): array;

    public function findOneByIdAndGuestToken(int $id, string $accessToken): ?WishlistInterface;

    public function countByGuestToken(string $accessToken): int;

    public function hasNameForGuestToken(string $accessToken, string $name, ?int $excludedId = null): bool;

    /**
     * The query behind the administration grid: every list, with its owner fetched in the same query.
     */
    public function createAdminGridQueryBuilder(): \Doctrine\ORM\QueryBuilder;

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
     * The number of products on all lists of the guest that owns the given access token.
     */
    public function countItemsForGuest(string $accessToken): int;

    public function hasNameForCustomer(CustomerInterface $customer, string $name, ?int $excludedId = null): bool;

    /**
     * All lists of guests that are abandoned: guests whose lists are all empty and older than $emptyBefore, and guests
     * who have not touched any list since $inactiveBefore. A guest is removed as a whole, never list by list.
     *
     * @return list<WishlistInterface>
     */
    public function findStaleGuestWishlists(\DateTimeImmutable $emptyBefore, \DateTimeImmutable $inactiveBefore): array;
}
