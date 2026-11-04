<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Service;

use MarkApp\SyliusWishlistPlugin\Entity\WishlistInterface;
use MarkApp\SyliusWishlistPlugin\Repository\WishlistRepositoryInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Customer\Context\CustomerContextInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Decides which wishlists the current visitor may manage: a logged-in customer owns their lists, a guest owns the
 * lists that carry the access token from their cookie. Every lookup by id also checks the owner, so one visitor can
 * never reach the list of another.
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

    /**
     * @return list<WishlistInterface> the lists of the guest, oldest first
     */
    public function findGuestWishlists(Request $request): array
    {
        $accessToken = $this->guestCookie->read($request);

        return null === $accessToken ? [] : $this->repository->findByGuestToken($accessToken);
    }

    /**
     * The oldest list of the guest: the one used when a product is added without choosing a list.
     */
    public function findGuestWishlist(Request $request): ?WishlistInterface
    {
        return $this->findGuestWishlists($request)[0] ?? null;
    }

    /**
     * @return list<WishlistInterface> the lists of the current visitor, whether customer or guest, oldest first
     */
    public function getOwnedWishlists(Request $request): array
    {
        $customer = $this->getCustomer();

        return null === $customer ? $this->findGuestWishlists($request) : $this->repository->findByCustomer($customer);
    }

    /**
     * The list with the given id, or the oldest list when no id is given. Only lists of the current visitor are found.
     */
    public function findOwned(Request $request, ?int $id = null): ?WishlistInterface
    {
        $customer = $this->getCustomer();

        if (null !== $customer) {
            return null === $id
                ? ($this->repository->findByCustomer($customer)[0] ?? null)
                : $this->repository->findOneByIdAndCustomer($id, $customer);
        }

        $accessToken = $this->guestCookie->read($request);

        if (null === $accessToken) {
            return null;
        }

        return null === $id
            ? ($this->repository->findByGuestToken($accessToken)[0] ?? null)
            : $this->repository->findOneByIdAndGuestToken($id, $accessToken);
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
