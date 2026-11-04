<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Controller\Shop;

use MarkApp\SyliusWishlistPlugin\Entity\WishlistItemInterface;
use MarkApp\SyliusWishlistPlugin\Enum\MoveOutcome;
use MarkApp\SyliusWishlistPlugin\Service\Flasher;
use MarkApp\SyliusWishlistPlugin\Service\RedirectResolver;
use MarkApp\SyliusWishlistPlugin\Service\WishlistAccessResolver;
use MarkApp\SyliusWishlistPlugin\Service\WishlistManager;
use MarkApp\SyliusWishlistPlugin\Service\WriteGuard;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Moves an entry from one list of the visitor to another. Both the entry and the target list must be the visitor's own.
 */
final class MoveItemController
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
        $source = $item instanceof WishlistItemInterface ? $item->getWishlist() : null;
        $targetId = (int) $request->request->get('target');

        if (!$item instanceof WishlistItemInterface || null === $source || !$this->accessResolver->isOwner($request, $source) || $targetId <= 0) {
            throw new NotFoundHttpException();
        }

        $target = $this->accessResolver->findOwned($request, $targetId);

        if (null === $target) {
            throw new NotFoundHttpException();
        }

        $parameters = ['%list%' => $target->getName(), '%from%' => $source->getName(), '%max%' => WishlistManager::MAX_ITEMS];

        match ($this->manager->moveItem($item, $target)) {
            MoveOutcome::Moved => $this->flasher->add($request, 'success', 'mark_app_sylius_wishlist.flash.item_moved', $parameters),
            MoveOutcome::AlreadyInTarget => $this->flasher->add($request, 'info', 'mark_app_sylius_wishlist.flash.item_move_exists', $parameters),
            MoveOutcome::TargetFull => $this->flasher->add($request, 'error', 'mark_app_sylius_wishlist.flash.list_full', $parameters),
            MoveOutcome::SameList => $this->flasher->add($request, 'info', 'mark_app_sylius_wishlist.flash.move_same_list', $parameters),
        };

        return new RedirectResponse($this->redirectResolver->resolve($request, 'mark_app_wishlist_shop_index'));
    }
}
