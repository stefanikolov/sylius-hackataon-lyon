<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\EventListener;

use MarkApp\SyliusWishlistPlugin\Service\GuestCookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Keeps wishlist pages out of search indexes and clears the guest cookie once a guest list was merged.
 */
final class ResponseListener
{
    public const CLEAR_COOKIE_ATTRIBUTE = 'mark_app_wishlist_clear_cookie';

    public const ROUTE_PREFIX = 'mark_app_wishlist_';

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();
        $route = $request->attributes->get('_route');

        if (\is_string($route) && str_starts_with($route, self::ROUTE_PREFIX)) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        if (true === $request->attributes->get(self::CLEAR_COOKIE_ATTRIBUTE)) {
            $response->headers->clearCookie(GuestCookie::NAME, '/');
        }
    }
}
