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

    /** API Platform puts this on every request it serves, so it tells an API request from a storefront request. */
    public const API_ATTRIBUTE = '_api_resource_class';

    public const API_HEADER = 'X-Wishlist-Token';

    private const LIFETIME = '+90 days';

    public function read(Request $request): ?string
    {
        // The API identifies a guest by header only. A browser sends cookies by itself, so accepting the cookie there
        // would let any website act on the guest's lists.
        $value = $request->attributes->has(self::API_ATTRIBUTE)
            ? $request->headers->get(self::API_HEADER)
            : $request->cookies->get(self::NAME);

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
