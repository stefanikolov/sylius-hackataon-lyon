<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Twig;

use MarkApp\SyliusWishlistPlugin\Entity\WishlistInterface;
use MarkApp\SyliusWishlistPlugin\Repository\WishlistRepositoryInterface;
use MarkApp\SyliusWishlistPlugin\Service\GuestCookie;
use MarkApp\SyliusWishlistPlugin\Service\ProductAvailability;
use MarkApp\SyliusWishlistPlugin\Service\WishlistAccessResolver;
use MarkApp\SyliusWishlistPlugin\Entity\WishlistItemInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class WishlistExtension extends AbstractExtension
{
    /** @var list<WishlistInterface>|null */
    private ?array $wishlists = null;

    private ?int $itemCount = null;

    public function __construct(
        private readonly WishlistAccessResolver $accessResolver,
        private readonly WishlistRepositoryInterface $repository,
        private readonly ProductAvailability $productAvailability,
        private readonly RequestStack $requestStack,
        private readonly GuestCookie $guestCookie,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('mark_app_wishlist_lists', $this->getWishlists(...)),
            new TwigFunction('mark_app_wishlist_is_purchasable', $this->isPurchasable(...)),
            new TwigFunction('mark_app_wishlist_item_count', $this->getItemCount(...)),
        ];
    }

    /**
     * @return list<WishlistInterface> the lists of the visitor, whether customer or guest, oldest first
     */
    public function getWishlists(): array
    {
        if (null === $this->wishlists) {
            $request = $this->requestStack->getCurrentRequest();
            $this->wishlists = null === $request ? [] : $this->accessResolver->getOwnedWishlists($request);
        }

        return $this->wishlists;
    }

    public function isPurchasable(WishlistItemInterface $item): bool
    {
        $variant = $item->getVariant();

        return null !== $variant && $this->productAvailability->isPurchasable($variant);
    }

    /**
     * The number of products on the visitor's wishlist(s): all lists of a customer, or the list of a guest.
     */
    public function getItemCount(): int
    {
        if (null !== $this->itemCount) {
            return $this->itemCount;
        }

        $customer = $this->accessResolver->getCustomer();

        if (null !== $customer) {
            return $this->itemCount = $this->repository->countItemsForCustomer($customer);
        }

        $request = $this->requestStack->getCurrentRequest();
        $accessToken = null === $request ? null : $this->guestCookie->read($request);

        return $this->itemCount = null === $accessToken ? 0 : $this->repository->countItemsForGuest($accessToken);
    }
}
