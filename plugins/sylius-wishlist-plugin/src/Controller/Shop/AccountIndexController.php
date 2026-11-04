<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Controller\Shop;

use MarkApp\SyliusWishlistPlugin\Entity\WishlistInterface;
use MarkApp\SyliusWishlistPlugin\Form\Type\WishlistType;
use MarkApp\SyliusWishlistPlugin\Repository\WishlistRepositoryInterface;
use MarkApp\SyliusWishlistPlugin\Service\WishlistAccessResolver;
use MarkApp\SyliusWishlistPlugin\Service\WishlistManager;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Webmozart\Assert\Assert;

final class AccountIndexController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly FormFactoryInterface $formFactory,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly WishlistAccessResolver $accessResolver,
        private readonly WishlistRepositoryInterface $repository,
        private readonly FactoryInterface $wishlistFactory,
    ) {
    }

    public function __invoke(): Response
    {
        $customer = $this->accessResolver->getCustomer();

        if (null === $customer) {
            throw new AccessDeniedHttpException();
        }

        // The draft is never persisted; it only gives the create form an empty name.
        $draft = $this->wishlistFactory->createNew();
        Assert::isInstanceOf($draft, WishlistInterface::class);
        $draft->setName('');

        $form = $this->formFactory->create(WishlistType::class, $draft, [
            'action' => $this->urlGenerator->generate('mark_app_wishlist_shop_account_create'),
        ]);

        return new Response($this->twig->render('@MarkAppSyliusWishlistPlugin/shop/account/wishlists/index.html.twig', [
            'wishlists' => $this->repository->findByCustomer($customer),
            'form' => $form->createView(),
            'max_lists' => WishlistManager::MAX_LISTS,
        ]));
    }
}
