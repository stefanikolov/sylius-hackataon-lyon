<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Controller\Shop;

use MarkApp\SyliusWishlistPlugin\Service\CartAdder;
use MarkApp\SyliusWishlistPlugin\Service\CartReport;
use MarkApp\SyliusWishlistPlugin\Service\Flasher;
use MarkApp\SyliusWishlistPlugin\Service\RedirectResolver;
use MarkApp\SyliusWishlistPlugin\Service\WishlistAccessResolver;
use MarkApp\SyliusWishlistPlugin\Service\WriteGuard;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Adds one or several products of the visitor's own wishlist to the cart. The wishlist is left untouched.
 */
final class AddToCartController
{
    public function __construct(
        private readonly WishlistAccessResolver $accessResolver,
        private readonly WriteGuard $guard,
        private readonly CartAdder $cartAdder,
        private readonly CartReport $cartReport,
        private readonly Flasher $flasher,
        private readonly RedirectResolver $redirectResolver,
    ) {
    }

    public function __invoke(Request $request): RedirectResponse
    {
        $this->guard->guardWrite($request, null === $this->accessResolver->getCustomer());

        $id = (int) $request->request->get('wishlist');
        $wishlist = $this->accessResolver->findOwned($request, $id > 0 ? $id : null);

        if (null === $wishlist) {
            throw new NotFoundHttpException();
        }

        $this->cartReport->addSelected($request, $wishlist, $this->cartAdder, $this->flasher);

        return new RedirectResponse($this->redirectResolver->resolve($request, 'mark_app_wishlist_shop_index'));
    }
}
