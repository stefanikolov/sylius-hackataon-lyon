<?php

declare(strict_types=1);

namespace Hackathon\SyliusWishlistPlugin\Provider;

use Doctrine\ORM\EntityManagerInterface;
use Hackathon\SyliusWishlistPlugin\Entity\Wishlist;
use Hackathon\SyliusWishlistPlugin\Entity\WishlistItem;
use Hackathon\SyliusWishlistPlugin\Repository\WishlistRepository;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Customer\Context\CustomerContextInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Resolves the wishlists of the current visitor. Every access goes through here, so a visitor can
 * only ever see or change their own lists.
 *
 *  - Logged-in customer: lists are stored against the customer and survive logout/login.
 *  - Guest: lists are stored in the database under one random token kept in a cookie.
 *  - When a guest logs in, the guest lists are merged into the customer's (same name = same list, no duplicates).
 *  - The first list (oldest) is the default one: it is used when no destination is chosen.
 */
final class WishlistProvider
{
    public const COOKIE_NAME = 'hackathon_wishlist_token';
    public const REQUEST_ATTRIBUTE_SET_COOKIE = '_hackathon_wishlist_set_cookie';
    public const REQUEST_ATTRIBUTE_CLEAR_COOKIE = '_hackathon_wishlist_clear_cookie';

    /** @var list<Wishlist>|null */
    private ?array $resolved = null;

    /** Token generated during this request, before it reaches the client. */
    private ?string $newToken = null;

    /** API mode: the guest token comes from a request header instead of the cookie, and no cookie is written. */
    private bool $headerMode = false;

    private ?string $headerToken = null;

    public function __construct(
        private readonly WishlistRepository $wishlistRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly CustomerContextInterface $customerContext,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * Switches to API mode for this request: the guest identity is the given token (or none), not the cookie.
     * A cookie is never read or written, so a browser cannot be tricked into acting on an API call.
     */
    public function useGuestToken(?string $token): void
    {
        $this->headerMode = true;
        $this->headerToken = null !== $token && '' !== $token ? $token : null;
        $this->resolved = null;
    }

    /** @return list<Wishlist> all lists of the current visitor, default list first */
    public function getWishlists(): array
    {
        if (null !== $this->resolved) {
            return $this->resolved;
        }

        $customer = $this->customerContext->getCustomer();
        $token = $this->cookieToken();
        $guestLists = null !== $token ? $this->wishlistRepository->findByGuestToken($token) : [];

        if ($customer instanceof CustomerInterface) {
            $lists = $this->wishlistRepository->findByCustomer($customer);

            if ([] !== $guestLists) {
                $lists = $this->takeOverGuestLists($lists, $guestLists, $customer);
            }

            return $this->resolved = $lists;
        }

        return $this->resolved = $guestLists;
    }

    /**
     * Returns one of the visitor's lists: the one with the given id, or the default list when no id is given.
     * A list that belongs to somebody else is treated as if it did not exist.
     *
     * @param bool $create create the default list when the visitor has none (only when no id is given)
     */
    public function getWishlist(?int $id = null, bool $create = false): ?Wishlist
    {
        $lists = $this->getWishlists();

        if (null !== $id) {
            foreach ($lists as $list) {
                if ($list->getId() === $id) {
                    return $list;
                }
            }

            return null;
        }

        if ([] === $lists && $create) {
            return $this->createWishlist(Wishlist::DEFAULT_NAME);
        }

        return $lists[0] ?? null;
    }

    /** Finds an item that belongs to one of the visitor's lists. */
    public function findItem(int $itemId): ?WishlistItem
    {
        foreach ($this->getWishlists() as $list) {
            foreach ($list->getItems() as $item) {
                if ($item->getId() === $itemId) {
                    return $item;
                }
            }
        }

        return null;
    }

    public function createWishlist(string $name): Wishlist
    {
        $this->getWishlists();

        $wishlist = new Wishlist();
        $wishlist->setName($name);

        $customer = $this->customerContext->getCustomer();
        if ($customer instanceof CustomerInterface) {
            $wishlist->setCustomer($customer);
        } else {
            $wishlist->setToken($this->guestToken());
        }

        $this->entityManager->persist($wishlist);
        $this->entityManager->flush();
        $this->resolved[] = $wishlist;

        return $wishlist;
    }

    public function deleteWishlist(Wishlist $wishlist): void
    {
        $this->entityManager->remove($wishlist);
        $this->entityManager->flush();
        $this->resolved = array_values(array_filter($this->getWishlists(), static fn (Wishlist $list): bool => $list !== $wishlist));
    }

    /** Is there already a list with this name (ignoring case)? */
    public function hasName(string $name, ?Wishlist $except = null): bool
    {
        foreach ($this->getWishlists() as $list) {
            if ($list !== $except && 0 === strcasecmp($list->getName(), $name)) {
                return true;
            }
        }

        return false;
    }

    /** Number of saved entries over all lists, shown next to the header link. */
    public function countItems(): int
    {
        $count = 0;
        foreach ($this->getWishlists() as $list) {
            $count += $list->getItems()->count();
        }

        return $count;
    }

    private function cookieToken(): ?string
    {
        if ($this->headerMode) {
            return $this->headerToken;
        }

        $token = $this->requestStack->getMainRequest()?->cookies->get(self::COOKIE_NAME);

        return is_string($token) && '' !== $token ? $token : null;
    }

    /**
     * The token that identifies this guest. It is always one the server generated: either the token of the lists
     * the visitor already owns, or a new random one (sent back in a cookie, or to API clients in the response).
     * A token chosen by the client is never adopted.
     */
    private function guestToken(): string
    {
        $existing = $this->resolved[0] ?? null;
        if (null !== $existing && null !== $existing->getToken()) {
            return $existing->getToken();
        }

        if (null === $this->newToken) {
            $this->newToken = bin2hex(random_bytes(24));
            if (!$this->headerMode) {
                $this->requestStack->getMainRequest()?->attributes->set(self::REQUEST_ATTRIBUTE_SET_COOKIE, $this->newToken);
            }
        }

        return $this->newToken;
    }

    /**
     * @param list<Wishlist> $customerLists
     * @param list<Wishlist> $guestLists
     *
     * @return list<Wishlist>
     */
    private function takeOverGuestLists(array $customerLists, array $guestLists, CustomerInterface $customer): array
    {
        foreach ($guestLists as $guestList) {
            $target = null;
            foreach ($customerLists as $customerList) {
                if (0 === strcasecmp($customerList->getName(), $guestList->getName())) {
                    $target = $customerList;

                    break;
                }
            }

            if (null === $target) {
                // No list with that name yet: the guest list simply becomes the customer's.
                $guestList->setCustomer($customer);
                $guestList->setToken(null);
                $customerLists[] = $guestList;

                continue;
            }

            foreach ($guestList->getItems() as $item) {
                $target->addVariant($item->getVariant());
            }
            $this->entityManager->remove($guestList);
        }

        $this->entityManager->flush();
        if (!$this->headerMode) {
            $this->requestStack->getMainRequest()?->attributes->set(self::REQUEST_ATTRIBUTE_CLEAR_COOKIE, true);
        }

        return $customerLists;
    }
}
