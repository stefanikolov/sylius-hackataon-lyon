<?php

declare(strict_types=1);

namespace Hackathon\SyliusWishlistPlugin\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Hackathon\SyliusWishlistPlugin\Repository\WishlistRepository;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;

/**
 * A wishlist belongs either to a logged-in customer or to an anonymous visitor (identified by a cookie token).
 */
#[ORM\Entity(repositoryClass: WishlistRepository::class)]
#[ORM\Table(name: 'hackathon_wishlist')]
class Wishlist
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Anonymous identifier stored in the visitor's cookie. Empty once the wishlist belongs to a customer. */
    #[ORM\Column(length: 64, unique: true, nullable: true)]
    private ?string $token = null;

    #[ORM\OneToOne(targetEntity: CustomerInterface::class)]
    #[ORM\JoinColumn(nullable: true, unique: true, onDelete: 'CASCADE')]
    private ?CustomerInterface $customer = null;

    /** @var Collection<int, WishlistItem> */
    #[ORM\OneToMany(targetEntity: WishlistItem::class, mappedBy: 'wishlist', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $items;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(?string $token): void
    {
        $this->token = $token;
    }

    public function getCustomer(): ?CustomerInterface
    {
        return $this->customer;
    }

    public function setCustomer(?CustomerInterface $customer): void
    {
        $this->customer = $customer;
    }

    /** @return Collection<int, WishlistItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function findItemByVariant(ProductVariantInterface $variant): ?WishlistItem
    {
        foreach ($this->items as $item) {
            if ($item->getVariant() === $variant) {
                return $item;
            }
        }

        return null;
    }

    public function hasVariant(ProductVariantInterface $variant): bool
    {
        return null !== $this->findItemByVariant($variant);
    }

    /** Adds the variant unless it is already there. Returns true when a new entry was created. */
    public function addVariant(ProductVariantInterface $variant): bool
    {
        if ($this->hasVariant($variant)) {
            return false;
        }

        $this->items->add(new WishlistItem($this, $variant));

        return true;
    }

    public function removeItem(WishlistItem $item): void
    {
        $this->items->removeElement($item);
    }
}
