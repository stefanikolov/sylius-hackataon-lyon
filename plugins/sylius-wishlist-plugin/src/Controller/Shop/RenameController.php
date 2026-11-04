<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Controller\Shop;

use MarkApp\SyliusWishlistPlugin\Entity\WishlistInterface;
use MarkApp\SyliusWishlistPlugin\Exception\WishlistNameTakenException;
use MarkApp\SyliusWishlistPlugin\Form\Type\WishlistType;
use MarkApp\SyliusWishlistPlugin\Service\Flasher;
use MarkApp\SyliusWishlistPlugin\Service\WishlistAccessResolver;
use MarkApp\SyliusWishlistPlugin\Service\WishlistManager;
use MarkApp\SyliusWishlistPlugin\Service\WriteGuard;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Webmozart\Assert\Assert;

final class RenameController
{
    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly WishlistAccessResolver $accessResolver,
        private readonly WishlistManager $manager,
        private readonly WriteGuard $guard,
        private readonly Flasher $flasher,
        private readonly FactoryInterface $wishlistFactory,
    ) {
    }

    public function __invoke(Request $request, int $id): RedirectResponse
    {
        if (null === $this->accessResolver->getCustomer()) {
            throw new AccessDeniedHttpException();
        }

        $this->guard->guardWrite($request, false, false);

        $wishlist = $this->accessResolver->findOwned($request, $id);

        if (null === $wishlist) {
            throw new NotFoundHttpException();
        }

        $draft = $this->wishlistFactory->createNew();
        Assert::isInstanceOf($draft, WishlistInterface::class);

        $form = $this->formFactory->create(WishlistType::class, $draft);
        $form->handleRequest($request);

        $showUrl = $this->urlGenerator->generate('mark_app_wishlist_shop_account_show', ['id' => $id]);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->flasher->add($request, 'error', 'mark_app_sylius_wishlist.flash.invalid_name');

            return new RedirectResponse($showUrl);
        }

        try {
            $this->manager->rename($wishlist, $draft->getName());
        } catch (WishlistNameTakenException) {
            $this->flasher->add($request, 'error', 'mark_app_sylius_wishlist.flash.name_taken');

            return new RedirectResponse($showUrl);
        }

        $this->flasher->add($request, 'success', 'mark_app_sylius_wishlist.flash.list_renamed');

        return new RedirectResponse($showUrl);
    }
}
