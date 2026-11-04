<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Service;

use MarkApp\SyliusWishlistPlugin\Entity\WishlistInterface;
use MarkApp\SyliusWishlistPlugin\Repository\WishlistRepositoryInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Customer\Context\CustomerContextInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Decides which wishlists the current visitor may manage: a logged-in customer owns their lists,
 * a guest owns the single list that matches the access token in their cookie.
 */
final class WishlistAccessResolver
{
    public function __construct(
        private readonly CustomerContextInterface $customerContext,
        private readonly WishlistRepositoryInterface $repository,
        private readonly GuestCookie $guestCookie,
    ) {
    }

    public function getCustomer(): ?CustomerInterface
    {
        $customer = $this->customerContext->getCustomer();

        return $customer instanceof CustomerInterface ? $customer : null;
    }

    public function findGuestWishlist(Request $request): ?WishlistInterface
    {
        $accessToken = $this->guestCookie->read($request);

        return null === $accessToken ? null : $this->repository->findOneGuestByAccessToken($accessToken);
    }

    /**
     * For a customer: the list with the given id, or their default list when no id is given.
     * For a guest: their own list (the id is ignored, a guest has one list).
     */
    public function findOwned(Request $request, ?int $id = null): ?WishlistInterface
    {
        $customer = $this->getCustomer();

        if (null === $customer) {
            return $this->findGuestWishlist($request);
        }

        return null === $id
            ? ($this->repository->findByCustomer($customer)[0] ?? null)
            : $this->repository->findOneByIdAndCustomer($id, $customer);
    }

    public function isOwner(Request $request, WishlistInterface $wishlist): bool
    {
        $customer = $this->getCustomer();

        if (null !== $customer) {
            return null !== $wishlist->getCustomer() && $wishlist->getCustomer()->getId() === $customer->getId();
        }

        $accessToken = $this->guestCookie->read($request);

        return $wishlist->isGuest() && null !== $accessToken && hash_equals((string) $wishlist->getAccessToken(), $accessToken);
    }
}
