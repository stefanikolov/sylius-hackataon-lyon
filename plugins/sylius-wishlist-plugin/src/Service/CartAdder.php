<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Service;

use MarkApp\SyliusWishlistPlugin\Enum\CartOutcome;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Inventory\Checker\AvailabilityCheckerInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Webmozart\Assert\Assert;

/**
 * Puts a wishlist product into the regular Sylius cart. The wishlist itself is never touched.
 */
final class CartAdder
{
    public function __construct(
        private readonly ProductAvailability $productAvailability,
        private readonly CartContextInterface $cartContext,
        private readonly FactoryInterface $orderItemFactory,
        private readonly OrderItemQuantityModifierInterface $quantityModifier,
        private readonly OrderModifierInterface $orderModifier,
        private readonly AvailabilityCheckerInterface $availabilityChecker,
        private readonly RepositoryInterface $orderRepository,
    ) {
    }

    /**
     * Adds exactly the remembered variant, never another one.
     */
    public function add(ProductVariantInterface $variant): CartOutcome
    {
        if (!$this->productAvailability->isPurchasable($variant)) {
            return CartOutcome::Unavailable;
        }

        $cart = $this->cartContext->getCart();

        // The cart merges equal items, so the stock check must cover what is already in the cart.
        $quantity = 1;
        foreach ($cart->getItems() as $existingItem) {
            if ($existingItem->getVariant() === $variant) {
                $quantity += $existingItem->getQuantity();
            }
        }

        if (!$this->availabilityChecker->isStockSufficient($variant, $quantity)) {
            return CartOutcome::OutOfStock;
        }

        $item = $this->orderItemFactory->createNew();
        Assert::isInstanceOf($item, \Sylius\Component\Order\Model\OrderItemInterface::class);

        $item->setVariant($variant);
        $this->quantityModifier->modify($item, 1);

        $this->orderModifier->addToOrder($cart, $item);
        $this->orderRepository->add($cart);

        return CartOutcome::Added;
    }
}
