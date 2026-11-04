<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Service;

use MarkApp\SyliusWishlistPlugin\Entity\WishlistInterface;
use MarkApp\SyliusWishlistPlugin\Entity\WishlistItemInterface;
use MarkApp\SyliusWishlistPlugin\Enum\AddOutcome;
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

    public function __construct(
        private readonly WishlistRepositoryInterface $repository,
        private readonly FactoryInterface $wishlistFactory,
        private readonly FactoryInterface $wishlistItemFactory,
        private readonly TokenGenerator $tokenGenerator,
    ) {
    }

    public function createGuestWishlist(): WishlistInterface
    {
        $wishlist = $this->newWishlist(WishlistInterface::DEFAULT_NAME);
        $wishlist->setAccessToken($this->tokenGenerator->generate());

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
     * Adds the chosen variant. The same product with another variant is a separate entry; the same variant is a duplicate.
     */
    public function addVariant(WishlistInterface $wishlist, ProductVariantInterface $variant): AddOutcome
    {
        if (null !== $wishlist->getItemForVariant($variant)) {
            return AddOutcome::AlreadyInList;
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

        if (null !== $customer && $this->repository->hasNameForCustomer($customer, $name, (int) $wishlist->getId())) {
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
     * Moves a guest wishlist into the customer's account: it becomes their first list, or its products
     * are merged into their default list. Products already present and items over the list limit are skipped.
     */
    public function mergeGuestIntoCustomer(WishlistInterface $guest, CustomerInterface $customer): void
    {
        $target = $this->repository->findByCustomer($customer)[0] ?? null;

        if (null === $target) {
            $guest->setCustomer($customer);
            $guest->setAccessToken(null);
            $guest->touch();
            $this->repository->add($guest);

            return;
        }

        foreach ($guest->getItems()->toArray() as $item) {
            $variant = $item->getVariant();

            if (null === $variant || null !== $target->getItemForVariant($variant)) {
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
