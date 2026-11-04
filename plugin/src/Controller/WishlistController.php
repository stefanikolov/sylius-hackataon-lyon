<?php

declare(strict_types=1);

namespace Hackathon\SyliusWishlistPlugin\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Hackathon\SyliusWishlistPlugin\Provider\WishlistProvider;
use Sylius\Bundle\OrderBundle\Adder\CartItemAdderInterface;
use Sylius\Bundle\OrderBundle\Factory\AddToCartCommandFactoryInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Calculator\ProductVariantPricesCalculatorInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Inventory\Checker\AvailabilityCheckerInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Core\Factory\CartItemFactoryInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Wishlist storefront actions. Every state change is a POST protected by a CSRF token.
 */
#[Route('/{_locale}', requirements: ['_locale' => '^[A-Za-z]{2,4}(_([A-Za-z]{4}|[0-9]{3}))?(_([A-Za-z]{2}|[0-9]{3}))?$'])]
final class WishlistController extends AbstractController
{
    private const CSRF_ID = 'hackathon_wishlist';

    public function __construct(
        private readonly WishlistProvider $wishlistProvider,
        private readonly EntityManagerInterface $entityManager,
        private readonly CartContextInterface $cartContext,
        private readonly CartItemFactoryInterface $cartItemFactory,
        private readonly AddToCartCommandFactoryInterface $addToCartCommandFactory,
        private readonly CartItemAdderInterface $cartItemAdder,
        private readonly OrderItemQuantityModifierInterface $quantityModifier,
        private readonly ProductVariantPricesCalculatorInterface $priceCalculator,
        private readonly RepositoryInterface $variantRepository,
        private readonly ChannelContextInterface $channelContext,
        private readonly AvailabilityCheckerInterface $availabilityChecker,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/wishlist', name: 'hackathon_wishlist_index', methods: ['GET'])]
    public function index(): Response
    {
        $channel = $this->channelContext->getChannel();
        $rows = [];

        foreach ($this->wishlistProvider->getWishlist()?->getItems() ?? [] as $item) {
            $variant = $item->getVariant();
            $product = $variant->getProduct();
            $context = ['channel' => $channel];

            $rows[] = [
                'item' => $item,
                'variant' => $variant,
                'product' => $product,
                'price' => $this->priceCalculator->calculate($variant, $context),
                'original_price' => $this->priceCalculator->calculateOriginal($variant, $context),
                'available' => $this->isPurchasable($variant),
            ];
        }

        return $this->render('@HackathonSyliusWishlistPlugin/shop/index.html.twig', ['rows' => $rows]);
    }

    #[Route('/wishlist/add/{variantId}', name: 'hackathon_wishlist_add', requirements: ['variantId' => '\d+'], methods: ['POST'])]
    public function add(Request $request, int $variantId): Response
    {
        if (!$this->isCsrfTokenValid(self::CSRF_ID, (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'hackathon_wishlist.flash.invalid_token');

            return $this->redirectBack($request);
        }

        $variant = $this->variantRepository->find($variantId);
        if (!$variant instanceof ProductVariantInterface || !$variant->isEnabled()) {
            $this->addFlash('error', 'hackathon_wishlist.flash.not_found');

            return $this->redirectBack($request);
        }

        // On the product page the customer picks options (size, colour...). Those are sent as options[code]=valueCode
        // and decide which variant is saved; the variant id in the URL is only the default one.
        $selectedOptions = $request->request->all('options');
        if ([] !== $selectedOptions) {
            $variant = $this->resolveVariantByOptions($variant, $selectedOptions);
            if (null === $variant) {
                $this->addFlash('error', 'hackathon_wishlist.flash.not_found');

                return $this->redirectBack($request);
            }
        }

        $wishlist = $this->wishlistProvider->getWishlist(create: true);
        if ($wishlist->addVariant($variant)) {
            $this->entityManager->flush();
            $this->addFlash('success', 'hackathon_wishlist.flash.added');
        } else {
            $this->addFlash('info', 'hackathon_wishlist.flash.already_added');
        }

        return $this->redirectBack($request);
    }

    #[Route('/wishlist/remove/{itemId}', name: 'hackathon_wishlist_remove', requirements: ['itemId' => '\d+'], methods: ['POST'])]
    public function remove(Request $request, int $itemId): Response
    {
        if (!$this->isCsrfTokenValid(self::CSRF_ID, (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'hackathon_wishlist.flash.invalid_token');

            return $this->redirectToRoute('hackathon_wishlist_index');
        }

        // Only items of the current visitor's wishlist can be removed.
        $wishlist = $this->wishlistProvider->getWishlist();
        foreach ($wishlist?->getItems() ?? [] as $item) {
            if ($item->getId() === $itemId) {
                $wishlist->removeItem($item);
                $this->entityManager->flush();
                $this->addFlash('success', 'hackathon_wishlist.flash.removed');

                break;
            }
        }

        return $this->redirectToRoute('hackathon_wishlist_index');
    }

    /**
     * Adds one or more wishlist entries to the cart. The wishlist itself is never modified and the cart
     * is only touched through Sylius' own cart item adder and its availability validation.
     */
    #[Route('/wishlist/add-to-cart', name: 'hackathon_wishlist_add_to_cart', methods: ['POST'])]
    public function addToCart(Request $request): Response
    {
        if (!$this->isCsrfTokenValid(self::CSRF_ID, (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'hackathon_wishlist.flash.invalid_token');

            return $this->redirectToRoute('hackathon_wishlist_index');
        }

        $selectedIds = array_map('intval', (array) $request->request->all('items'));
        if ([] === $selectedIds) {
            $this->addFlash('info', 'hackathon_wishlist.flash.nothing_selected');

            return $this->redirectToRoute('hackathon_wishlist_index');
        }

        $added = 0;
        $skipped = 0;

        foreach ($this->wishlistProvider->getWishlist()?->getItems() ?? [] as $item) {
            if (!in_array($item->getId(), $selectedIds, true)) {
                continue;
            }

            if ($this->addVariantToCart($item->getVariant())) {
                ++$added;
            } else {
                ++$skipped;
            }
        }

        if ($added > 0) {
            $this->addFlash('success', 'hackathon_wishlist.flash.added_to_cart');
        }
        if ($skipped > 0) {
            $this->addFlash('error', 'hackathon_wishlist.flash.not_added_to_cart');
        }

        return $this->redirectToRoute($added > 0 && 0 === $skipped ? 'sylius_shop_cart_summary' : 'hackathon_wishlist_index');
    }

    private function addVariantToCart(ProductVariantInterface $variant): bool
    {
        if (!$this->isPurchasable($variant)) {
            return false;
        }

        $cartItem = $this->cartItemFactory->createForProduct($variant->getProduct());
        $cartItem->setVariant($variant);
        $this->quantityModifier->modify($cartItem, 1);

        $command = $this->addToCartCommandFactory->createWithCartAndCartItem($this->cartContext->getCart(), $cartItem);

        // Same availability rules Sylius applies on the product page ("sylius" validation group).
        if (count($this->validator->validate($command, null, ['sylius'])) > 0) {
            return false;
        }

        $this->cartItemAdder->add($command);

        return true;
    }

    private function isPurchasable(ProductVariantInterface $variant): bool
    {
        $product = $variant->getProduct();

        return $variant->isEnabled()
            && null !== $product
            && $product->isEnabled()
            && $this->availabilityChecker->isStockAvailable($variant);
    }

    /**
     * Finds the enabled variant of the same product whose option values match the selection.
     *
     * @param array<array-key, mixed> $selectedOptions option code => option value code
     */
    private function resolveVariantByOptions(ProductVariantInterface $variant, array $selectedOptions): ?ProductVariantInterface
    {
        $product = $variant->getProduct();
        if (null === $product) {
            return null;
        }

        foreach ($product->getEnabledVariants() as $candidate) {
            $values = [];
            foreach ($candidate->getOptionValues() as $optionValue) {
                $values[$optionValue->getOptionCode()] = $optionValue->getCode();
            }

            $matches = true;
            foreach ($selectedOptions as $optionCode => $valueCode) {
                if (!is_string($valueCode) || ($values[(string) $optionCode] ?? null) !== $valueCode) {
                    $matches = false;

                    break;
                }
            }

            if ($matches) {
                return $candidate;
            }
        }

        return null;
    }

    /** Goes back to the page the visitor came from, but only when it is on this same site. */
    private function redirectBack(Request $request): RedirectResponse
    {
        $referer = (string) $request->headers->get('referer');
        if ('' !== $referer && str_starts_with($referer, $request->getSchemeAndHttpHost() . '/')) {
            return new RedirectResponse($referer);
        }

        return $this->redirectToRoute('hackathon_wishlist_index');
    }
}
