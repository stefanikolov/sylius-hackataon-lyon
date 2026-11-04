<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ProductInterface;

class Wishlist implements WishlistInterface
{
    protected ?int $id = null;

    protected string $name = self::DEFAULT_NAME;

    protected ?CustomerInterface $customer = null;

    protected ?string $accessToken = null;

    protected string $shareToken = '';

    protected bool $public = false;

    protected \DateTimeImmutable $createdAt;

    protected \DateTimeImmutable $updatedAt;

    /** @var Collection<int, WishlistItemInterface> */
    protected Collection $items;

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getCustomer(): ?CustomerInterface
    {
        return $this->customer;
    }

    public function setCustomer(?CustomerInterface $customer): void
    {
        $this->customer = $customer;
    }

    public function isGuest(): bool
    {
        return null === $this->customer;
    }

    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }

    public function setAccessToken(?string $accessToken): void
    {
        $this->accessToken = $accessToken;
    }

    public function getShareToken(): string
    {
        return $this->shareToken;
    }

    public function setShareToken(string $shareToken): void
    {
        $this->shareToken = $shareToken;
    }

    public function isPublic(): bool
    {
        return $this->public;
    }

    public function setPublic(bool $public): void
    {
        $this->public = $public;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(WishlistItemInterface $item): void
    {
        if ($this->items->contains($item)) {
            return;
        }

        $item->setWishlist($this);
        $this->items->add($item);
        $this->touch();
    }

    public function removeItem(WishlistItemInterface $item): void
    {
        if ($this->items->removeElement($item)) {
            $this->touch();
        }
    }

    public function getItemForProduct(ProductInterface $product): ?WishlistItemInterface
    {
        foreach ($this->items as $item) {
            if ($item->getProduct() === $product) {
                return $item;
            }
        }

        return null;
    }
}
