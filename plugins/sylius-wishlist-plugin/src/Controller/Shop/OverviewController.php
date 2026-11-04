<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Controller\Shop;

use MarkApp\SyliusWishlistPlugin\Entity\WishlistInterface;
use MarkApp\SyliusWishlistPlugin\Form\Type\WishlistType;
use MarkApp\SyliusWishlistPlugin\Service\WishlistAccessResolver;
use MarkApp\SyliusWishlistPlugin\Service\WishlistManager;
use MarkApp\SyliusWishlistPlugin\Service\WishlistRoutes;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Webmozart\Assert\Assert;

/**
 * All lists of the visitor, with a form to create a new one. Customers see this inside their account; guests on a
 * shop page, and a customer who opens the public wishlist address is sent to the account page.
 */
final class OverviewController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly FormFactoryInterface $formFactory,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly WishlistAccessResolver $accessResolver,
        private readonly WishlistRoutes $routes,
        private readonly FactoryInterface $wishlistFactory,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $customer = $this->accessResolver->getCustomer();
        $routes = $this->routes->names();

        if (null !== $customer && 'mark_app_wishlist_shop_index' === $request->attributes->get('_route')) {
            return new RedirectResponse($this->urlGenerator->generate($routes['index']));
        }

        // The draft is never persisted; it only gives the create form an empty name.
        $draft = $this->wishlistFactory->createNew();
        Assert::isInstanceOf($draft, WishlistInterface::class);
        $draft->setName('');

        $form = $this->formFactory->create(WishlistType::class, $draft, [
            'action' => $this->urlGenerator->generate($routes['create']),
        ]);

        $template = null !== $customer
            ? '@MarkAppSyliusWishlistPlugin/shop/account/wishlists/index.html.twig'
            : '@MarkAppSyliusWishlistPlugin/shop/wishlist/index.html.twig';

        return new Response($this->twig->render($template, [
            'wishlists' => $this->accessResolver->getOwnedWishlists($request),
            'form' => $form->createView(),
            'max_lists' => null !== $customer ? WishlistManager::MAX_LISTS : WishlistManager::MAX_GUEST_LISTS,
            'routes' => $routes,
        ]));
    }
}
