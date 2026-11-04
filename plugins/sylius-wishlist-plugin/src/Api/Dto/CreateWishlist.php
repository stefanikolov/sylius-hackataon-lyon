<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Api\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateWishlist
{
    #[Assert\NotBlank(message: 'A wishlist name is required.', normalizer: 'trim')]
    #[Assert\Length(max: 100, maxMessage: 'A wishlist name can have at most 100 characters.')]
    public ?string $name = null;
}
