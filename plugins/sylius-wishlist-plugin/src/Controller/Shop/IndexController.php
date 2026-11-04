<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Controller\Shop;

use MarkApp\SyliusWishlistPlugin\Service\WishlistAccessResolver;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * Shows the guest wishlist; customers are sent to their account, where they manage several lists.
 */
final class IndexController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly WishlistAccessResolver $accessResolver,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if (null !== $this->accessResolver->getCustomer()) {
            return new RedirectResponse($this->urlGenerator->generate('mark_app_wishlist_shop_account_index'));
        }

        return new Response($this->twig->render('@MarkAppSyliusWishlistPlugin/shop/wishlist/guest.html.twig', [
            'wishlist' => $this->accessResolver->findGuestWishlist($request),
        ]));
    }
}
