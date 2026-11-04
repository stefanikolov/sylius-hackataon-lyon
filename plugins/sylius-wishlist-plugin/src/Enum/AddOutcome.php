<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Enum;

enum AddOutcome: string
{
    case Added = 'added';
    case AlreadyInList = 'already_in_list';
    case ListFull = 'list_full';
}
