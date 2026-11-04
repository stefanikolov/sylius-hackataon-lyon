<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Service;

final class TokenGenerator
{
    /**
     * Returns 48 hex characters (192 bits of entropy) from a cryptographically secure source.
     */
    public function generate(): string
    {
        return bin2hex(random_bytes(24));
    }
}
