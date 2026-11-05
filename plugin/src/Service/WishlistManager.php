<?php

declare(strict_types=1);

namespace Hackathon\SyliusWishlistPlugin\Service;

use Doctrine\ORM\EntityManagerInterface;
use Hackathon\SyliusWishlistPlugin\Entity\Wishlist;
use Hackathon\SyliusWishlistPlugin\Entity\WishlistItem;
use Hackathon\SyliusWishlistPlugin\Provider\WishlistProvider;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Inventory\Checker\AvailabilityCheckerInterface;

/**
 * The wishlist business operations. The storefront controller and the API controller both go through here,
 * so the rules (name validation, no duplicates, moving, availability) exist exactly once.
 *
 * Who owns a list is decided by {@see WishlistProvider}; this class only works on lists it has been given.
 */
final class WishlistManager
{
    public const ERROR_NAME_REQUIRED = 'name_required';
    public const ERROR_NAME_TOO_LONG = 'name_too_long';
    public const ERROR_NAME_TAKEN = 'name_taken';

    public function __construct(
        private readonly WishlistProvider $provider,
        private readonly EntityManagerInterface $entityManager,
        private readonly AvailabilityCheckerInterface $availabilityChecker,
    ) {
    }

    /** Trims ordinary and Unicode whitespace from both ends. */
    public function normalizeName(string $raw): string
    {
        return (string) preg_replace('/^[\s\p{Z}]+|[\s\p{Z}]+$/u', '', $raw);
    }

    /**
     * Checks a (normalized) name. Returns an error code, or null when the name is fine.
     *
     * @param Wishlist|null $renaming the list being renamed, so it does not clash with its own name
     */
    public function validateName(string $name, ?Wishlist $renaming = null): ?string
    {
        if ('' === $name) {
            return self::ERROR_NAME_REQUIRED;
        }

        if (mb_strlen($name) > Wishlist::MAX_NAME_LENGTH) {
            return self::ERROR_NAME_TOO_LONG;
        }

        if ($this->provider->hasName($name, $renaming)) {
            return self::ERROR_NAME_TAKEN;
        }

        return null;
    }

    public function create(string $name): Wishlist
    {
        return $this->provider->createWishlist($name);
    }

    public function rename(Wishlist $wishlist, string $name): void
    {
        $wishlist->setName($name);
        $this->entityManager->flush();
    }

    /** Deletes the list and its entries. Products of the catalogue are never touched. */
    public function delete(Wishlist $wishlist): void
    {
        $this->provider->deleteWishlist($wishlist);
    }

    /** Saves the variant in the list unless it is already there. Returns true when a new entry was created. */
    public function add(Wishlist $wishlist, ProductVariantInterface $variant): bool
    {
        if (!$wishlist->addVariant($variant)) {
            return false;
        }

        $this->entityManager->flush();

        return true;
    }

    public function remove(WishlistItem $item): void
    {
        $item->getWishlist()->removeItem($item);
        $this->entityManager->flush();
    }

    /** Moves an entry to another list. If the target already has the product, the entry is just removed here. */
    public function move(WishlistItem $item, Wishlist $target): void
    {
        $target->addVariant($item->getVariant());
        $item->getWishlist()->removeItem($item);
        $this->entityManager->flush();
    }

    /** Can the variant be bought right now? Unavailable variants stay in the list but are flagged. */
    public function isPurchasable(ProductVariantInterface $variant): bool
    {
        $product = $variant->getProduct();

        return $variant->isEnabled()
            && null !== $product
            && $product->isEnabled()
            && $this->availabilityChecker->isStockAvailable($variant);
    }

    /**
     * Finds the enabled variant of the same product whose option values match the selection.
     *
     * @param array<array-key, mixed> $selectedOptions option code => option value code
     */
    public function resolveVariantByOptions(ProductVariantInterface $variant, array $selectedOptions): ?ProductVariantInterface
    {
        $product = $variant->getProduct();
        if (null === $product) {
            return null;
        }

        foreach ($product->getEnabledVariants() as $candidate) {
            $values = [];
            foreach ($candidate->getOptionValues() as $optionValue) {
                $values[$optionValue->getOptionCode()] = $optionValue->getCode();
            }

            $matches = true;
            foreach ($selectedOptions as $optionCode => $valueCode) {
                if (!is_string($valueCode) || ($values[(string) $optionCode] ?? null) !== $valueCode) {
                    $matches = false;

                    break;
                }
            }

            if ($matches) {
                return $candidate;
            }
        }

        return null;
    }
}
