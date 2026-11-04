<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Controller\Shop;

use MarkApp\SyliusWishlistPlugin\Service\Flasher;
use MarkApp\SyliusWishlistPlugin\Service\RedirectResolver;
use MarkApp\SyliusWishlistPlugin\Service\WishlistAccessResolver;
use MarkApp\SyliusWishlistPlugin\Service\WishlistManager;
use MarkApp\SyliusWishlistPlugin\Service\WriteGuard;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Switches a wishlist between private and public. Only the owner can do this.
 */
final class VisibilityController
{
    public function __construct(
        private readonly WishlistAccessResolver $accessResolver,
        private readonly WishlistManager $manager,
        private readonly WriteGuard $guard,
        private readonly Flasher $flasher,
        private readonly RedirectResolver $redirectResolver,
    ) {
    }

    public function __invoke(Request $request): RedirectResponse
    {
        $this->guard->guardWrite($request, null === $this->accessResolver->getCustomer());

        $id = (int) $request->request->get('id');
        $wishlist = $this->accessResolver->findOwned($request, $id > 0 ? $id : null);

        if (null === $wishlist) {
            throw new NotFoundHttpException();
        }

        $public = '1' === $request->request->get('public');
        $this->manager->setPublic($wishlist, $public);

        $this->flasher->add($request, 'success', $public ? 'mark_app_sylius_wishlist.flash.now_public' : 'mark_app_sylius_wishlist.flash.now_private');

        return new RedirectResponse($this->redirectResolver->resolve($request, 'mark_app_wishlist_shop_index'));
    }
}
