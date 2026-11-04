<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Service;

use MarkApp\SyliusWishlistPlugin\Entity\WishlistInterface;
use MarkApp\SyliusWishlistPlugin\Entity\WishlistItemInterface;
use MarkApp\SyliusWishlistPlugin\Enum\AddOutcome;
use MarkApp\SyliusWishlistPlugin\Enum\MoveOutcome;
use MarkApp\SyliusWishlistPlugin\Exception\WishlistLimitReachedException;
use MarkApp\SyliusWishlistPlugin\Exception\WishlistNameTakenException;
use MarkApp\SyliusWishlistPlugin\Repository\WishlistRepositoryInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Webmozart\Assert\Assert;

/**
 * All write operations on wishlists live here, so limits and invariants are enforced in one place.
 */
final class WishlistManager
{
    public const MAX_ITEMS = 100;

    public const MAX_LISTS = 20;

    public const MAX_GUEST_LISTS = 10;

    public function __construct(
        private readonly WishlistRepositoryInterface $repository,
        private readonly FactoryInterface $wishlistFactory,
        private readonly FactoryInterface $wishlistItemFactory,
        private readonly TokenGenerator $tokenGenerator,
    ) {
    }

    /**
     * Creates a list for a guest. Without an access token the guest is new and gets one; with a token the list joins
     * the other lists of that guest.
     *
     * @throws WishlistLimitReachedException
     * @throws WishlistNameTakenException
     */
    public function createGuestWishlist(?string $accessToken = null, string $name = WishlistInterface::DEFAULT_NAME): WishlistInterface
    {
        $name = trim($name);

        if (null !== $accessToken) {
            if ($this->repository->countByGuestToken($accessToken) >= self::MAX_GUEST_LISTS) {
                throw new WishlistLimitReachedException();
            }

            if ($this->repository->hasNameForGuestToken($accessToken, $name)) {
                throw new WishlistNameTakenException();
            }
        }

        $wishlist = $this->newWishlist($name);
        $wishlist->setAccessToken($accessToken ?? $this->tokenGenerator->generate());

        $this->repository->add($wishlist);

        return $wishlist;
    }

    /**
     * @throws WishlistLimitReachedException
     * @throws WishlistNameTakenException
     */
    public function createCustomerWishlist(CustomerInterface $customer, string $name): WishlistInterface
    {
        $name = trim($name);

        if ($this->repository->countByCustomer($customer) >= self::MAX_LISTS) {
            throw new WishlistLimitReachedException();
        }

        if ($this->repository->hasNameForCustomer($customer, $name)) {
            throw new WishlistNameTakenException();
        }

        $wishlist = $this->newWishlist($name);
        $wishlist->setCustomer($customer);

        $this->repository->add($wishlist);

        return $wishlist;
    }

    /**
     * The default wishlist is the oldest one of the customer; it is created on first use.
     */
    public function getDefaultWishlist(CustomerInterface $customer): WishlistInterface
    {
        return $this->repository->findByCustomer($customer)[0]
            ?? $this->createCustomerWishlist($customer, WishlistInterface::DEFAULT_NAME);
    }

    /**
     * Adds the chosen variant. A product occurs once per list: adding it again with another variant replaces the
     * variant that was remembered, adding the same variant changes nothing.
     */
    public function addVariant(WishlistInterface $wishlist, ProductVariantInterface $variant): AddOutcome
    {
        $product = $variant->getProduct();
        Assert::notNull($product);

        $existing = $wishlist->getItemForProduct($product);

        if (null !== $existing) {
            if ($existing->getVariant() === $variant) {
                return AddOutcome::AlreadyInList;
            }

            $existing->setVariant($variant);
            $wishlist->touch();
            $this->repository->add($wishlist);

            return AddOutcome::VariantUpdated;
        }

        if ($wishlist->getItems()->count() >= self::MAX_ITEMS) {
            return AddOutcome::ListFull;
        }

        $wishlist->addItem($this->newItem($variant));
        $this->repository->add($wishlist);

        return AddOutcome::Added;
    }

    public function removeItem(WishlistItemInterface $item): void
    {
        $wishlist = $item->getWishlist();
        Assert::notNull($wishlist);

        $wishlist->removeItem($item);
        $this->repository->add($wishlist);
    }

    /**
     * @throws WishlistNameTakenException
     */
    public function rename(WishlistInterface $wishlist, string $name): void
    {
        $name = trim($name);
        $customer = $wishlist->getCustomer();
        $accessToken = $wishlist->getAccessToken();

        $taken = null !== $customer
            ? $this->repository->hasNameForCustomer($customer, $name, (int) $wishlist->getId())
            : (null !== $accessToken && $this->repository->hasNameForGuestToken($accessToken, $name, (int) $wishlist->getId()));

        if ($taken) {
            throw new WishlistNameTakenException();
        }

        $wishlist->setName($name);
        $wishlist->touch();
        $this->repository->add($wishlist);
    }

    public function setPublic(WishlistInterface $wishlist, bool $public): void
    {
        $wishlist->setPublic($public);
        $wishlist->touch();
        $this->repository->add($wishlist);
    }

    public function delete(WishlistInterface $wishlist): void
    {
        $this->repository->remove($wishlist);
    }

    /**
     * Moves an item to another list of the same owner, as one operation. When the target already holds the product, the
     * item is only removed from the source list.
     */
    public function moveItem(WishlistItemInterface $item, WishlistInterface $target): MoveOutcome
    {
        $source = $item->getWishlist();
        $variant = $item->getVariant();
        $product = $item->getProduct();
        Assert::notNull($source);
        Assert::notNull($variant);
        Assert::notNull($product);

        if ($source === $target) {
            return MoveOutcome::SameList;
        }

        $alreadyThere = null !== $target->getItemForProduct($product);

        if (!$alreadyThere && $target->getItems()->count() >= self::MAX_ITEMS) {
            return MoveOutcome::TargetFull;
        }

        if (!$alreadyThere) {
            $target->addItem($this->newItem($variant));
        }

        $source->removeItem($item);
        $this->repository->add($target);

        return $alreadyThere ? MoveOutcome::AlreadyInTarget : MoveOutcome::Moved;
    }

    /**
     * Moves the lists of a guest into the customer's account. A list whose name the customer already uses (any case) is
     * merged into that list; any other list becomes a list of the customer. When the customer is at the list limit,
     * the remaining lists are merged into their oldest list. Products already present are skipped, and so are products
     * over the list limit.
     *
     * @param list<WishlistInterface> $guestWishlists
     */
    public function mergeGuestIntoCustomer(array $guestWishlists, CustomerInterface $customer): void
    {
        $own = $this->repository->findByCustomer($customer);

        foreach ($guestWishlists as $guest) {
            $target = null;

            foreach ($own as $candidate) {
                if (mb_strtolower($candidate->getName()) === mb_strtolower($guest->getName())) {
                    $target = $candidate;

                    break;
                }
            }

            if (null === $target && \count($own) < self::MAX_LISTS) {
                $guest->setCustomer($customer);
                $guest->setAccessToken(null);
                $guest->touch();
                $this->repository->add($guest);
                $own[] = $guest;

                continue;
            }

            $target ??= $own[0];

            foreach ($guest->getItems()->toArray() as $item) {
                $product = $item->getProduct();
                $variant = $item->getVariant();

                if (null === $product || null === $variant || null !== $target->getItemForProduct($product)) {
                    continue;
                }

                if ($target->getItems()->count() >= self::MAX_ITEMS) {
                    break;
                }

                $target->addItem($this->newItem($variant));
            }

            $this->repository->add($target);
            $this->repository->remove($guest);
        }
    }

    /**
     * @return int the number of removed guest wishlists
     */
    public function deleteStaleGuestWishlists(\DateTimeImmutable $emptyBefore, \DateTimeImmutable $inactiveBefore): int
    {
        $stale = $this->repository->findStaleGuestWishlists($emptyBefore, $inactiveBefore);

        foreach ($stale as $wishlist) {
            $this->repository->remove($wishlist);
        }

        return \count($stale);
    }

    private function newWishlist(string $name): WishlistInterface
    {
        $wishlist = $this->wishlistFactory->createNew();
        Assert::isInstanceOf($wishlist, WishlistInterface::class);

        $wishlist->setName($name);
        $wishlist->setShareToken($this->tokenGenerator->generate());

        return $wishlist;
    }

    private function newItem(ProductVariantInterface $variant): WishlistItemInterface
    {
        $item = $this->wishlistItemFactory->createNew();
        Assert::isInstanceOf($item, WishlistItemInterface::class);

        $item->setProduct($variant->getProduct());
        $item->setVariant($variant);

        return $item;
    }
}
