<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Controller\Shop;

use MarkApp\SyliusWishlistPlugin\Service\Flasher;
use MarkApp\SyliusWishlistPlugin\Service\WishlistAccessResolver;
use MarkApp\SyliusWishlistPlugin\Service\WishlistManager;
use MarkApp\SyliusWishlistPlugin\Service\WriteGuard;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class DeleteController
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly WishlistAccessResolver $accessResolver,
        private readonly WishlistManager $manager,
        private readonly WriteGuard $guard,
        private readonly Flasher $flasher,
    ) {
    }

    public function __invoke(Request $request, int $id): RedirectResponse
    {
        if (null === $this->accessResolver->getCustomer()) {
            throw new AccessDeniedHttpException();
        }

        $this->guard->guardWrite($request, false);

        $wishlist = $this->accessResolver->findOwned($request, $id);

        if (null === $wishlist) {
            throw new NotFoundHttpException();
        }

        $this->manager->delete($wishlist);
        $this->flasher->add($request, 'success', 'mark_app_sylius_wishlist.flash.list_deleted');

        return new RedirectResponse($this->urlGenerator->generate('mark_app_wishlist_shop_account_index'));
    }
}
