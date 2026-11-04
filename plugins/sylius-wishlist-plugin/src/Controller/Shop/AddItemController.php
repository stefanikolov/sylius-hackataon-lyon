<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Controller\Shop;

use MarkApp\SyliusWishlistPlugin\Entity\WishlistInterface;
use MarkApp\SyliusWishlistPlugin\Enum\AddOutcome;
use MarkApp\SyliusWishlistPlugin\Service\Flasher;
use MarkApp\SyliusWishlistPlugin\Service\GuestCookie;
use MarkApp\SyliusWishlistPlugin\Service\ProductAvailability;
use MarkApp\SyliusWishlistPlugin\Service\RedirectResolver;
use MarkApp\SyliusWishlistPlugin\Service\WishlistAccessResolver;
use MarkApp\SyliusWishlistPlugin\Service\WishlistManager;
use MarkApp\SyliusWishlistPlugin\Service\WriteGuard;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Adds a product variant to a wishlist. Guests get a wishlist lazily: it is created here, on their first real add.
 */
final class AddItemController
{
    public function __construct(
        private readonly WishlistAccessResolver $accessResolver,
        private readonly WishlistManager $manager,
        private readonly WriteGuard $guard,
        private readonly Flasher $flasher,
        private readonly RedirectResolver $redirectResolver,
        private readonly GuestCookie $guestCookie,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ChannelContextInterface $channelContext,
        private readonly ProductAvailability $productAvailability,
    ) {
    }

    public function __invoke(Request $request, string $code): RedirectResponse
    {
        $customer = $this->accessResolver->getCustomer();
        $this->guard->guardWrite($request, null === $customer);

        $product = $this->productRepository->findOneByChannelAndCode($this->channelContext->getChannel(), $code);

        if (null === $product || !$product->isEnabled()) {
            throw new NotFoundHttpException();
        }

        $variant = $this->resolveVariant($request, $product);

        if (null === $variant) {
            // The visitor picked a combination that does not exist: tell them, and create nothing.
            $this->flasher->add($request, 'error', 'mark_app_sylius_wishlist.flash.variant_unavailable');

            return new RedirectResponse($this->redirectResolver->resolve($request, 'mark_app_wishlist_shop_index'));
        }

        $guestWishlist = null;

        if (null !== $customer) {
            $wishlistId = (int) $request->request->get('wishlist');
            $wishlist = $wishlistId > 0 ? $this->accessResolver->findOwned($request, $wishlistId) : $this->manager->getDefaultWishlist($customer);

            if (null === $wishlist) {
                throw new NotFoundHttpException();
            }
        } else {
            $wishlist = $this->accessResolver->findGuestWishlist($request);

            if (null === $wishlist) {
                $this->guard->guardGuestCreate($request);
                $wishlist = $this->manager->createGuestWishlist();
            }

            $guestWishlist = $wishlist;
        }

        $outcome = $this->manager->addVariant($wishlist, $variant);

        match ($outcome) {
            AddOutcome::Added => $this->flasher->add($request, 'success', 'mark_app_sylius_wishlist.flash.item_added', ['%list%' => $wishlist->getName()]),
            AddOutcome::AlreadyInList => $this->flasher->add($request, 'info', 'mark_app_sylius_wishlist.flash.item_exists', ['%list%' => $wishlist->getName()]),
            AddOutcome::ListFull => $this->flasher->add($request, 'error', 'mark_app_sylius_wishlist.flash.list_full', ['%max%' => WishlistManager::MAX_ITEMS]),
        };

        $response = new RedirectResponse($this->redirectResolver->resolve($request, 'mark_app_wishlist_shop_index'));

        if ($guestWishlist instanceof WishlistInterface && null !== $guestWishlist->getAccessToken()) {
            // Refreshed on every add, so the cookie expires 90 days after the last activity.
            $response->headers->setCookie($this->guestCookie->create($guestWishlist->getAccessToken(), $request));
        }

        return $response;
    }

    /**
     * The product page copies the visitor's current choice of the Sylius add-to-cart form into this request
     * ("sylius_shop_add_to_cart[cartItem][variant]"): an option map (option code => option value code) for products
     * selected by options, or a variant code for products selected from a list of variants. Without a choice
     * (a product listing, or JavaScript disabled) the default variant is remembered.
     */
    private function resolveVariant(Request $request, ProductInterface $product): ?ProductVariantInterface
    {
        $cartForm = $request->request->all()['sylius_shop_add_to_cart'] ?? null;
        $selection = \is_array($cartForm) && \is_array($cartForm['cartItem'] ?? null) ? ($cartForm['cartItem']['variant'] ?? null) : null;

        if (\is_string($selection) && '' !== $selection) {
            foreach ($product->getEnabledVariants() as $variant) {
                if ($variant->getCode() === $selection) {
                    return $variant;
                }
            }

            return null;
        }

        if (!\is_array($selection) || [] === $selection) {
            return $this->productAvailability->getDefaultVariant($product);
        }

        $chosen = array_map('strval', array_filter($selection, 'is_scalar'));
        ksort($chosen);

        foreach ($product->getEnabledVariants() as $variant) {
            $options = [];
            foreach ($variant->getOptionValues() as $optionValue) {
                $options[(string) $optionValue->getOptionCode()] = (string) $optionValue->getCode();
            }
            ksort($options);

            if ($options === $chosen) {
                return $variant;
            }
        }

        return null;
    }
}
