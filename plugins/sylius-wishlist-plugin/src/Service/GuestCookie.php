<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Service;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

/**
 * Carries the secret access token of a guest wishlist between requests.
 */
final class GuestCookie
{
    public const NAME = 'mark_app_wishlist';

    private const LIFETIME = '+90 days';

    public function read(Request $request): ?string
    {
        $value = $request->cookies->get(self::NAME);

        return \is_string($value) && '' !== $value ? $value : null;
    }

    public function create(string $accessToken, Request $request): Cookie
    {
        return Cookie::create(
            self::NAME,
            $accessToken,
            new \DateTimeImmutable(self::LIFETIME),
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            Cookie::SAMESITE_LAX,
        );
    }
}
