<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Service;

use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Inventory\Checker\AvailabilityCheckerInterface;
use Sylius\Component\Product\Resolver\ProductVariantResolverInterface;

/**
 * Knows which variant a wishlist entry starts with, and whether a remembered variant can be bought right now.
 */
final class ProductAvailability
{
    public function __construct(
        private readonly ProductVariantResolverInterface $variantResolver,
        private readonly AvailabilityCheckerInterface $availabilityChecker,
        private readonly ChannelContextInterface $channelContext,
    ) {
    }

    /**
     * The variant used when the customer did not pick one (for example from a product listing).
     * Stock is ignored on purpose: wanting an out-of-stock variant is a normal reason to use a wishlist.
     */
    public function getDefaultVariant(ProductInterface $product): ?ProductVariantInterface
    {
        $variant = $this->variantResolver->getVariant($product);

        if ($variant instanceof ProductVariantInterface) {
            return $variant;
        }

        $first = $product->getEnabledVariants()->first();

        return $first instanceof ProductVariantInterface ? $first : null;
    }

    public function isPurchasable(ProductVariantInterface $variant): bool
    {
        $product = $variant->getProduct();

        if (null === $product || !$product->isEnabled() || !$variant->isEnabled()) {
            return false;
        }

        try {
            $channel = $this->channelContext->getChannel();
        } catch (ChannelNotFoundException) {
            return false;
        }

        return $product->hasChannel($channel) && $this->availabilityChecker->isStockAvailable($variant);
    }
}
