<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\EventListener;

use MarkApp\SyliusWishlistPlugin\Repository\WishlistRepositoryInterface;
use MarkApp\SyliusWishlistPlugin\Service\GuestCookie;
use MarkApp\SyliusWishlistPlugin\Service\WishlistManager;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * When a guest with wishlists logs in (or registers), the guest lists move into the customer's account.
 */
final class MergeGuestWishlistListener
{
    public function __construct(
        private readonly GuestCookie $guestCookie,
        private readonly WishlistRepositoryInterface $repository,
        private readonly WishlistManager $manager,
    ) {
    }

    public function __invoke(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof ShopUserInterface || null === $customer = $user->getCustomer()) {
            return;
        }

        $request = $event->getRequest();
        $accessToken = $this->guestCookie->read($request);

        if (null === $accessToken) {
            return;
        }

        $guestWishlists = $this->repository->findByGuestToken($accessToken);

        if ([] !== $guestWishlists) {
            $this->manager->mergeGuestIntoCustomer($guestWishlists, $customer);
        }

        // The cookie is useless from now on, whether or not a list was found.
        $request->attributes->set(ResponseListener::CLEAR_COOKIE_ATTRIBUTE, true);
    }
}
