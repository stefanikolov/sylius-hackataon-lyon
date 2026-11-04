<?php

declare(strict_types=1);

namespace Hackathon\SyliusWishlistPlugin\Provider;

use Doctrine\ORM\EntityManagerInterface;
use Hackathon\SyliusWishlistPlugin\Entity\Wishlist;
use Hackathon\SyliusWishlistPlugin\Repository\WishlistRepository;
use Sylius\Component\Customer\Context\CustomerContextInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Resolves the wishlist of the current visitor.
 *
 *  - Logged-in customer: the wishlist is stored against the customer, so it survives logout/login.
 *  - Guest: the wishlist is stored in the database under a random token that is kept in a cookie.
 *  - When a guest logs in, the guest wishlist is merged into the customer wishlist (no duplicates).
 */
final class WishlistProvider
{
    public const COOKIE_NAME = 'hackathon_wishlist_token';
    public const REQUEST_ATTRIBUTE_SET_COOKIE = '_hackathon_wishlist_set_cookie';
    public const REQUEST_ATTRIBUTE_CLEAR_COOKIE = '_hackathon_wishlist_clear_cookie';

    private ?Wishlist $resolved = null;

    public function __construct(
        private readonly WishlistRepository $wishlistRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly CustomerContextInterface $customerContext,
        private readonly RequestStack $requestStack,
    ) {
    }

    /** Returns the current wishlist, or null when there is none yet and $create is false. */
    public function getWishlist(bool $create = false): ?Wishlist
    {
        if (null !== $this->resolved) {
            return $this->resolved;
        }

        $request = $this->requestStack->getMainRequest();
        $customer = $this->customerContext->getCustomer();
        $token = $request?->cookies->get(self::COOKIE_NAME);
        $guestWishlist = is_string($token) && '' !== $token ? $this->wishlistRepository->findOneByGuestToken($token) : null;

        if (null !== $customer) {
            $wishlist = $this->wishlistRepository->findOneByCustomer($customer);

            if (null !== $guestWishlist) {
                $wishlist = $this->takeOverGuestWishlist($wishlist, $guestWishlist, $customer);
            }

            if (null === $wishlist && $create) {
                $wishlist = new Wishlist();
                $wishlist->setCustomer($customer);
                $this->entityManager->persist($wishlist);
                $this->entityManager->flush();
            }

            return $this->resolved = $wishlist;
        }

        if (null === $guestWishlist && $create) {
            $guestWishlist = new Wishlist();
            $guestWishlist->setToken(bin2hex(random_bytes(24)));
            $this->entityManager->persist($guestWishlist);
            $this->entityManager->flush();
            $request?->attributes->set(self::REQUEST_ATTRIBUTE_SET_COOKIE, $guestWishlist->getToken());
        }

        return $this->resolved = $guestWishlist;
    }

    public function countItems(): int
    {
        return $this->getWishlist()?->getItems()->count() ?? 0;
    }

    private function takeOverGuestWishlist(?Wishlist $customerWishlist, Wishlist $guestWishlist, object $customer): Wishlist
    {
        if (null === $customerWishlist) {
            // The customer has no wishlist yet: the guest one simply becomes theirs.
            $guestWishlist->setCustomer($customer);
            $guestWishlist->setToken(null);
            $result = $guestWishlist;
        } else {
            foreach ($guestWishlist->getItems() as $item) {
                $customerWishlist->addVariant($item->getVariant());
            }
            $this->entityManager->remove($guestWishlist);
            $result = $customerWishlist;
        }

        $this->entityManager->flush();
        $this->requestStack->getMainRequest()?->attributes->set(self::REQUEST_ATTRIBUTE_CLEAR_COOKIE, true);

        return $result;
    }
}
