<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Controller\Shop;

use MarkApp\SyliusWishlistPlugin\Repository\WishlistRepositoryInterface;
use MarkApp\SyliusWishlistPlugin\Service\WriteGuard;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

/**
 * Read-only view of a public wishlist. A private or unknown token gives a 404, never a 403,
 * so nobody can tell whether a wishlist exists behind a token.
 */
final class ShareController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly WishlistRepositoryInterface $repository,
        private readonly WriteGuard $guard,
    ) {
    }

    public function __invoke(Request $request, string $token): Response
    {
        $wishlist = $this->repository->findOnePublicByShareToken($token);

        if (null === $wishlist) {
            $this->guard->registerTokenMiss($request);

            throw new NotFoundHttpException();
        }

        return new Response($this->twig->render('@MarkAppSyliusWishlistPlugin/shop/share/show.html.twig', [
            'wishlist' => $wishlist,
        ]));
    }
}
