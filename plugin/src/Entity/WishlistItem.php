<?php

declare(strict_types=1);

namespace Hackathon\SyliusWishlistPlugin\Entity;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\ProductVariantInterface;

/**
 * One saved product variant. The wishlist stores variants (not products), because a variant is
 * the thing that can actually be bought and added to the cart.
 */
#[ORM\Entity]
#[ORM\Table(name: 'hackathon_wishlist_item')]
#[ORM\UniqueConstraint(name: 'uniq_wishlist_variant', columns: ['wishlist_id', 'variant_id'])]
class WishlistItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Wishlist::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Wishlist $wishlist;

    #[ORM\ManyToOne(targetEntity: ProductVariantInterface::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ProductVariantInterface $variant;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Wishlist $wishlist, ProductVariantInterface $variant)
    {
        $this->wishlist = $wishlist;
        $this->variant = $variant;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWishlist(): Wishlist
    {
        return $this->wishlist;
    }

    public function getVariant(): ProductVariantInterface
    {
        return $this->variant;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
