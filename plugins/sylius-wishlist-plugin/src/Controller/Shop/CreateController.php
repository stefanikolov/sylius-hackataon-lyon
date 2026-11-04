<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Controller\Shop;

use MarkApp\SyliusWishlistPlugin\Entity\WishlistInterface;
use MarkApp\SyliusWishlistPlugin\Exception\WishlistLimitReachedException;
use MarkApp\SyliusWishlistPlugin\Exception\WishlistNameTakenException;
use MarkApp\SyliusWishlistPlugin\Form\Type\WishlistType;
use MarkApp\SyliusWishlistPlugin\Service\Flasher;
use MarkApp\SyliusWishlistPlugin\Service\GuestCookie;
use MarkApp\SyliusWishlistPlugin\Service\WishlistAccessResolver;
use MarkApp\SyliusWishlistPlugin\Service\WishlistManager;
use MarkApp\SyliusWishlistPlugin\Service\WishlistRoutes;
use MarkApp\SyliusWishlistPlugin\Service\WriteGuard;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Webmozart\Assert\Assert;

/**
 * Creates a named list, for a customer or for a guest. A guest without a cookie becomes a new guest with this list.
 */
final class CreateController
{
    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly WishlistAccessResolver $accessResolver,
        private readonly WishlistManager $manager,
        private readonly WriteGuard $guard,
        private readonly Flasher $flasher,
        private readonly FactoryInterface $wishlistFactory,
        private readonly WishlistRoutes $routes,
        private readonly GuestCookie $guestCookie,
    ) {
    }

    public function __invoke(Request $request): RedirectResponse
    {
        $customer = $this->accessResolver->getCustomer();
        $routes = $this->routes->names();

        // The Symfony form validates its own CSRF token.
        $this->guard->guardWrite($request, null === $customer, false);

        $draft = $this->wishlistFactory->createNew();
        Assert::isInstanceOf($draft, WishlistInterface::class);

        $form = $this->formFactory->create(WishlistType::class, $draft);
        $form->handleRequest($request);

        $indexUrl = $this->urlGenerator->generate($routes['index']);

        if (!$form->isSubmitted() || !$form->isValid()) {
            // "draft" holds the trimmed name; an empty one is the usual mistake and gets its own message.
            $this->flasher->add($request, 'error', '' === $draft->getName() ? 'mark_app_sylius_wishlist.flash.name_required' : 'mark_app_sylius_wishlist.flash.name_too_long');

            return new RedirectResponse($indexUrl);
        }

        $accessToken = null;

        try {
            if (null !== $customer) {
                $wishlist = $this->manager->createCustomerWishlist($customer, $draft->getName());
            } else {
                $accessToken = $this->guestCookie->read($request);

                if (null === $accessToken) {
                    $this->guard->guardGuestCreate($request);
                }

                $wishlist = $this->manager->createGuestWishlist($accessToken, $draft->getName());
                $accessToken = $wishlist->getAccessToken();
            }
        } catch (WishlistLimitReachedException) {
            $this->flasher->add($request, 'error', 'mark_app_sylius_wishlist.flash.list_limit', ['%max%' => null !== $customer ? WishlistManager::MAX_LISTS : WishlistManager::MAX_GUEST_LISTS]);

            return new RedirectResponse($indexUrl);
        } catch (WishlistNameTakenException) {
            $this->flasher->add($request, 'error', 'mark_app_sylius_wishlist.flash.name_taken');

            return new RedirectResponse($indexUrl);
        }

        $this->flasher->add($request, 'success', 'mark_app_sylius_wishlist.flash.list_created');

        $response = new RedirectResponse($this->urlGenerator->generate($routes['show'], ['id' => $wishlist->getId()]));

        if (null !== $accessToken) {
            $response->headers->setCookie($this->guestCookie->create($accessToken, $request));
        }

        return $response;
    }
}
