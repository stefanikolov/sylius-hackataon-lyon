<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Api\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use MarkApp\SyliusWishlistPlugin\Api\Resource\WishlistResource;
use MarkApp\SyliusWishlistPlugin\Api\WishlistResourceFactory;
use MarkApp\SyliusWishlistPlugin\Service\WishlistAccessResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Reads wishlists through the same ownership rules as the storefront: only the lists of the current customer or guest
 * can be found, so a list of someone else is simply "not found".
 *
 * @implements ProviderInterface<WishlistResource>
 */
final class WishlistProvider implements ProviderInterface
{
    public function __construct(
        private readonly WishlistAccessResolver $accessResolver,
        private readonly WishlistResourceFactory $factory,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $request = $context['request'] ?? null;

        if (!$request instanceof Request) {
            return $operation instanceof CollectionOperationInterface ? [] : null;
        }

        if ($operation instanceof CollectionOperationInterface) {
            return array_map($this->factory->create(...), $this->accessResolver->getOwnedWishlists($request));
        }

        $id = (int) ($uriVariables['id'] ?? 0);
        $wishlist = $id > 0 ? $this->accessResolver->findOwned($request, $id) : null;

        if (null === $wishlist) {
            throw new NotFoundHttpException('Wishlist not found.');
        }

        return $this->factory->create($wishlist);
    }
}
