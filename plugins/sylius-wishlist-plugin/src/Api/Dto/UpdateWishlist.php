<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Api\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateWishlist
{
    #[Assert\NotBlank(message: 'A wishlist name is required.', normalizer: 'trim', allowNull: true)]
    #[Assert\Length(max: 100, maxMessage: 'A wishlist name can have at most 100 characters.')]
    public ?string $name = null;

    public ?bool $public = null;
}
