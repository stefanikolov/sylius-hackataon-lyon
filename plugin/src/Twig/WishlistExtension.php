<?php

declare(strict_types=1);

namespace Hackathon\SyliusWishlistPlugin\Twig;

use Hackathon\SyliusWishlistPlugin\Entity\Wishlist;
use Hackathon\SyliusWishlistPlugin\Provider\WishlistProvider;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class WishlistExtension extends AbstractExtension
{
    public function __construct(private readonly WishlistProvider $wishlistProvider)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('hackathon_wishlist_count', $this->wishlistProvider->countItems(...)),
            new TwigFunction('hackathon_wishlists', $this->wishlistProvider->getWishlists(...)),
            new TwigFunction('hackathon_wishlist_has', $this->has(...)),
            new TwigFunction('hackathon_wishlist_in', $this->in(...)),
        ];
    }

    /** Is the variant saved in at least one of the visitor's lists? */
    public function has(?ProductVariantInterface $variant): bool
    {
        if (null === $variant) {
            return false;
        }

        foreach ($this->wishlistProvider->getWishlists() as $wishlist) {
            if ($wishlist->hasVariant($variant)) {
                return true;
            }
        }

        return false;
    }

    /** Is the variant saved in this particular list? */
    public function in(Wishlist $wishlist, ?ProductVariantInterface $variant): bool
    {
        return null !== $variant && $wishlist->hasVariant($variant);
    }
}
