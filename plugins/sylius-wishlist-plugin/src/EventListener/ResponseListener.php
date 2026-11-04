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

        // In debug mode API Platform adds a stack trace to error responses. A client of the wishlist API must not see
        // the internals of the application, so it is removed from the errors of this resource.
        if (
            \MarkApp\SyliusWishlistPlugin\Api\Resource\WishlistResource::class === $request->attributes->get(GuestCookie::API_ATTRIBUTE)
            && $response->getStatusCode() >= 400
            && str_contains((string) $response->headers->get('Content-Type'), 'json')
        ) {
            $data = json_decode((string) $response->getContent(), true);

            if (\is_array($data) && \array_key_exists('trace', $data)) {
                unset($data['trace']);
                $response->setContent((string) json_encode($data, \JSON_UNESCAPED_SLASHES));
            }
        }

        // A guest who just created a list through the API gets the token as a header as well as in the body.
        if (\is_string($token = $request->attributes->get(\MarkApp\SyliusWishlistPlugin\Api\State\WishlistProcessor::GUEST_TOKEN_ATTRIBUTE))) {
            $response->headers->set(GuestCookie::API_HEADER, $token);
        }

        if (true === $request->attributes->get(self::CLEAR_COOKIE_ATTRIBUTE)) {
            $response->headers->clearCookie(GuestCookie::NAME, '/');
        }
    }
}
