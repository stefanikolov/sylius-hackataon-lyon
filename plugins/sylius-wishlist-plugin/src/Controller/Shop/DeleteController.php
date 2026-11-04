<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Controller\Shop;

use MarkApp\SyliusWishlistPlugin\Service\Flasher;
use MarkApp\SyliusWishlistPlugin\Service\WishlistAccessResolver;
use MarkApp\SyliusWishlistPlugin\Service\WishlistManager;
use MarkApp\SyliusWishlistPlugin\Service\WishlistRoutes;
use MarkApp\SyliusWishlistPlugin\Service\WriteGuard;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Deletes one list with its entries. Products of the shop catalogue and the visitor's other lists stay as they are.
 */
final class DeleteController
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly WishlistAccessResolver $accessResolver,
        private readonly WishlistManager $manager,
        private readonly WriteGuard $guard,
        private readonly Flasher $flasher,
        private readonly WishlistRoutes $routes,
    ) {
    }

    public function __invoke(Request $request, int $id): RedirectResponse
    {
        $this->guard->guardWrite($request, null === $this->accessResolver->getCustomer());

        $wishlist = $this->accessResolver->findOwned($request, $id);

        if (null === $wishlist) {
            throw new NotFoundHttpException();
        }

        $this->manager->delete($wishlist);
        $this->flasher->add($request, 'success', 'mark_app_sylius_wishlist.flash.list_deleted');

        return new RedirectResponse($this->urlGenerator->generate($this->routes->names()['index']));
    }
}
