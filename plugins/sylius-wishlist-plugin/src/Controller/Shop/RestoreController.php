<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Controller\Shop;

use MarkApp\SyliusWishlistPlugin\Repository\WishlistRepositoryInterface;
use MarkApp\SyliusWishlistPlugin\Service\Flasher;
use MarkApp\SyliusWishlistPlugin\Service\GuestCookie;
use MarkApp\SyliusWishlistPlugin\Service\WishlistAccessResolver;
use MarkApp\SyliusWishlistPlugin\Service\WishlistManager;
use MarkApp\SyliusWishlistPlugin\Service\WriteGuard;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Restores access to a guest wishlist on another browser or device with the secret access token.
 * A logged-in customer who opens the link gets the guest list moved into their account instead.
 */
final class RestoreController
{
    public function __construct(
        private readonly WishlistRepositoryInterface $repository,
        private readonly WishlistAccessResolver $accessResolver,
        private readonly WishlistManager $manager,
        private readonly WriteGuard $guard,
        private readonly GuestCookie $guestCookie,
        private readonly Flasher $flasher,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(Request $request, string $token): RedirectResponse
    {
        $wishlist = $this->repository->findOneGuestByAccessToken($token);

        if (null === $wishlist) {
            $this->guard->registerTokenMiss($request);

            throw new NotFoundHttpException();
        }

        $customer = $this->accessResolver->getCustomer();

        if (null !== $customer) {
            $this->manager->mergeGuestIntoCustomer($wishlist, $customer);
            $this->flasher->add($request, 'success', 'mark_app_sylius_wishlist.flash.list_moved_to_account');

            return new RedirectResponse($this->urlGenerator->generate('mark_app_wishlist_shop_account_index'));
        }

        $this->flasher->add($request, 'success', 'mark_app_sylius_wishlist.flash.list_restored');

        $response = new RedirectResponse($this->urlGenerator->generate('mark_app_wishlist_shop_index'));
        $response->headers->setCookie($this->guestCookie->create($token, $request));

        return $response;
    }
}
