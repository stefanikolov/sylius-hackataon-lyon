<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Enum;

enum MoveOutcome: string
{
    case Moved = 'moved';
    /** The product was already on the target list, so it was only removed from the source list. */
    case AlreadyInTarget = 'already_in_target';
    case TargetFull = 'target_full';
    case SameList = 'same_list';
}
