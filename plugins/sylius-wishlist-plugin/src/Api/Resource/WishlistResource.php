<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use MarkApp\SyliusWishlistPlugin\Api\Dto\AddWishlistItem;
use MarkApp\SyliusWishlistPlugin\Api\Dto\CreateWishlist;
use MarkApp\SyliusWishlistPlugin\Api\Dto\MoveWishlistItem;
use MarkApp\SyliusWishlistPlugin\Api\Dto\UpdateWishlist;
use MarkApp\SyliusWishlistPlugin\Api\State\WishlistProcessor;
use MarkApp\SyliusWishlistPlugin\Api\State\WishlistProvider;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * A wishlist of a customer (Sylius JWT) or of a guest ("X-Wishlist-Token" header). The resource is a view on the same
 * data and rules the storefront uses: the state provider and processor call the storefront services, so nothing about
 * lists, products or ownership is implemented twice. A list of someone else answers 404.
 */
#[ApiResource(
    shortName: 'Wishlist',
    description: 'A named wishlist of a customer or a guest.',
    operations: [
        new GetCollection(
            uriTemplate: '/shop/wishlists',
            name: 'mark_app_wishlist_api_get_collection',
            provider: WishlistProvider::class,
            normalizationContext: ['groups' => ['wishlist:summary']],
            description: 'Retrieves the wishlists of the current customer or guest.',
        ),
        new Get(
            uriTemplate: '/shop/wishlists/{id}',
            name: 'mark_app_wishlist_api_get',
            provider: WishlistProvider::class,
            normalizationContext: ['groups' => ['wishlist:summary', 'wishlist:detail']],
            description: 'Retrieves one wishlist with its items.',
        ),
        new Post(
            uriTemplate: '/shop/wishlists',
            name: 'mark_app_wishlist_api_post',
            input: CreateWishlist::class,
            processor: WishlistProcessor::class,
            normalizationContext: ['groups' => ['wishlist:summary', 'wishlist:detail', 'wishlist:created']],
            description: 'Creates a wishlist. A guest receives a guestToken, which is needed for every later request.',
        ),
        new Patch(
            uriTemplate: '/shop/wishlists/{id}',
            name: 'mark_app_wishlist_api_patch',
            read: false,
            input: UpdateWishlist::class,
            processor: WishlistProcessor::class,
            normalizationContext: ['groups' => ['wishlist:summary', 'wishlist:detail']],
            description: 'Renames a wishlist and/or makes it public or private.',
        ),
        new Delete(
            uriTemplate: '/shop/wishlists/{id}',
            name: 'mark_app_wishlist_api_delete',
            provider: WishlistProvider::class,
            processor: WishlistProcessor::class,
            description: 'Deletes a wishlist with its items. The products stay in the catalogue.',
        ),
        new Post(
            uriTemplate: '/shop/wishlists/{id}/items',
            name: 'mark_app_wishlist_api_add_item',
            read: false,
            input: AddWishlistItem::class,
            processor: WishlistProcessor::class,
            normalizationContext: ['groups' => ['wishlist:summary', 'wishlist:detail', 'wishlist:result']],
            description: 'Adds a product to a wishlist: 201 when added, 200 when it was already there or its variant was replaced.',
        ),
        new Delete(
            uriTemplate: '/shop/wishlists/{id}/items/{productCode}',
            name: 'mark_app_wishlist_api_remove_item',
            uriVariables: [
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
                'productCode' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            read: false,
            deserialize: false,
            processor: WishlistProcessor::class,
            description: 'Removes a product from a wishlist.',
        ),
        new Post(
            uriTemplate: '/shop/wishlists/{id}/items/{productCode}/move',
            name: 'mark_app_wishlist_api_move_item',
            uriVariables: [
                'id' => new Link(fromClass: self::class, identifiers: ['id']),
                'productCode' => new Link(fromClass: self::class, identifiers: ['id']),
            ],
            read: false,
            input: MoveWishlistItem::class,
            processor: WishlistProcessor::class,
            normalizationContext: ['groups' => ['wishlist:summary', 'wishlist:detail', 'wishlist:result']],
            description: 'Moves a product to another wishlist of the same owner.',
        ),
    ],
)]
final class WishlistResource
{
    #[ApiProperty(identifier: true)]
    #[Groups(['wishlist:summary'])]
    public ?int $id = null;

    #[Groups(['wishlist:summary'])]
    public string $name = '';

    #[Groups(['wishlist:summary'])]
    public bool $public = false;

    #[Groups(['wishlist:summary'])]
    public int $itemsCount = 0;

    #[Groups(['wishlist:summary'])]
    public ?string $createdAt = null;

    #[Groups(['wishlist:summary'])]
    public ?string $updatedAt = null;

    /** The public share token; only present when the list is public. */
    #[Groups(['wishlist:detail'])]
    public ?string $shareToken = null;

    /**
     * Each item: productCode, name, variantCode, variantName, available (false when the product cannot be bought now), addedAt.
     *
     * @var list<array<string, mixed>>
     */
    #[Groups(['wishlist:detail'])]
    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'object']])]
    public array $items = [];

    /** Only returned when a guest creates a list: the secret that identifies this guest in later requests. */
    #[Groups(['wishlist:created'])]
    public ?string $guestToken = null;

    /** Outcome of an item operation: added, already_in_list, variant_updated, moved, already_in_target. */
    #[Groups(['wishlist:result'])]
    public ?string $result = null;
}
