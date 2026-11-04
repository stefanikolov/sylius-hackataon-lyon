<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Controller\Shop;

use MarkApp\SyliusWishlistPlugin\Entity\WishlistInterface;
use MarkApp\SyliusWishlistPlugin\Form\Type\WishlistType;
use MarkApp\SyliusWishlistPlugin\Service\WishlistAccessResolver;
use MarkApp\SyliusWishlistPlugin\Service\WishlistRoutes;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Webmozart\Assert\Assert;

/**
 * One list with its products. Another visitor's list gives the same 404 as a list that does not exist.
 */
final class ShowController
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

    public function __invoke(Request $request, int $id): Response
    {
        $wishlist = $this->accessResolver->findOwned($request, $id);

        if (null === $wishlist) {
            throw new NotFoundHttpException();
        }

        $routes = $this->routes->names();

        // A detached copy carries the name into the rename form, so the managed entity is never modified by the form.
        $draft = $this->wishlistFactory->createNew();
        Assert::isInstanceOf($draft, WishlistInterface::class);
        $draft->setName($wishlist->getName());

        $renameForm = $this->formFactory->create(WishlistType::class, $draft, [
            'action' => $this->urlGenerator->generate($routes['rename'], ['id' => $id]),
        ]);

        $template = null !== $this->accessResolver->getCustomer()
            ? '@MarkAppSyliusWishlistPlugin/shop/account/wishlists/show.html.twig'
            : '@MarkAppSyliusWishlistPlugin/shop/wishlist/show.html.twig';

        return new Response($this->twig->render($template, [
            'wishlist' => $wishlist,
            'rename_form' => $renameForm->createView(),
            'routes' => $routes,
        ]));
    }
}
