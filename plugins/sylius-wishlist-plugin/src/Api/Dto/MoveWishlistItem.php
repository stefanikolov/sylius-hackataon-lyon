<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Api\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class MoveWishlistItem
{
    #[Assert\NotNull(message: 'The identifier of the target wishlist is required.')]
    #[Assert\Positive(message: 'The identifier of the target wishlist must be a positive number.')]
    public ?int $targetWishlistId = null;
}
