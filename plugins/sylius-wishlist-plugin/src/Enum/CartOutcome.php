<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Enum;

enum CartOutcome: string
{
    case Added = 'added';
    case Unavailable = 'unavailable';
    case OutOfStock = 'out_of_stock';
}
