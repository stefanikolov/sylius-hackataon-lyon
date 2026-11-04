<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Api\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class AddWishlistItem
{
    #[Assert\NotBlank(message: 'A product code is required.')]
    public ?string $productCode = null;

    /** Optional: without it the default variant of the product is remembered. */
    public ?string $variantCode = null;
}
