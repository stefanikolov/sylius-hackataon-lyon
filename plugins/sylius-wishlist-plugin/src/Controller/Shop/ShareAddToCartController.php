<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Controller\Shop;

use MarkApp\SyliusWishlistPlugin\Repository\WishlistRepositoryInterface;
use MarkApp\SyliusWishlistPlugin\Service\CartAdder;
use MarkApp\SyliusWishlistPlugin\Service\CartReport;
use MarkApp\SyliusWishlistPlugin\Service\Flasher;
use MarkApp\SyliusWishlistPlugin\Service\WishlistAccessResolver;
use MarkApp\SyliusWishlistPlugin\Service\WriteGuard;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Lets a visitor of a shared wishlist put products in their own cart. Nothing on the shared list changes.
 */
final class ShareAddToCartController
{
    public function __construct(
        private readonly WishlistRepositoryInterface $repository,
        private readonly WishlistAccessResolver $accessResolver,
        private readonly WriteGuard $guard,
        private readonly CartAdder $cartAdder,
        private readonly CartReport $cartReport,
        private readonly Flasher $flasher,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(Request $request, string $token): RedirectResponse
    {
        $this->guard->guardWrite($request, null === $this->accessResolver->getCustomer());

        $wishlist = $this->repository->findOnePublicByShareToken($token);

        if (null === $wishlist) {
            $this->guard->registerTokenMiss($request);

            throw new NotFoundHttpException();
        }

        $this->cartReport->addSelected($request, $wishlist, $this->cartAdder, $this->flasher);

        return new RedirectResponse($this->urlGenerator->generate('mark_app_wishlist_shop_share', ['token' => $token]));
    }
}
