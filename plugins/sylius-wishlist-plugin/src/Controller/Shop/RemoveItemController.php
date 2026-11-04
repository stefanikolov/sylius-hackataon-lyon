<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Controller\Shop;

use MarkApp\SyliusWishlistPlugin\Entity\WishlistItemInterface;
use MarkApp\SyliusWishlistPlugin\Service\Flasher;
use MarkApp\SyliusWishlistPlugin\Service\RedirectResolver;
use MarkApp\SyliusWishlistPlugin\Service\WishlistAccessResolver;
use MarkApp\SyliusWishlistPlugin\Service\WishlistManager;
use MarkApp\SyliusWishlistPlugin\Service\WriteGuard;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class RemoveItemController
{
    public function __construct(
        private readonly RepositoryInterface $itemRepository,
        private readonly WishlistAccessResolver $accessResolver,
        private readonly WishlistManager $manager,
        private readonly WriteGuard $guard,
        private readonly Flasher $flasher,
        private readonly RedirectResolver $redirectResolver,
    ) {
    }

    public function __invoke(Request $request, int $id): RedirectResponse
    {
        $this->guard->guardWrite($request, null === $this->accessResolver->getCustomer());

        $item = $this->itemRepository->find($id);
        $wishlist = $item instanceof WishlistItemInterface ? $item->getWishlist() : null;

        // A visitor who does not own the list gets the same 404 as for an unknown item.
        if (!$item instanceof WishlistItemInterface || null === $wishlist || !$this->accessResolver->isOwner($request, $wishlist)) {
            throw new NotFoundHttpException();
        }

        $this->manager->removeItem($item);
        $this->flasher->add($request, 'success', 'mark_app_sylius_wishlist.flash.item_removed');

        return new RedirectResponse($this->redirectResolver->resolve($request, 'mark_app_wishlist_shop_index'));
    }
}
