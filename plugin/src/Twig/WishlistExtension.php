<?php

declare(strict_types=1);

namespace Hackathon\SyliusWishlistPlugin\Twig;

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
            new TwigFunction('hackathon_wishlist_has', $this->has(...)),
        ];
    }

    public function has(?ProductVariantInterface $variant): bool
    {
        return null !== $variant && (bool) $this->wishlistProvider->getWishlist()?->hasVariant($variant);
    }
}
