<?php

declare(strict_types=1);

namespace Hackathon\SyliusWishlistPlugin\EventListener;

use Hackathon\SyliusWishlistPlugin\Provider\WishlistProvider;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/** Writes (or removes) the guest wishlist cookie after the provider asked for it. */
#[AsEventListener(event: 'kernel.response')]
final class WishlistCookieListener
{
    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        $token = $request->attributes->get(WishlistProvider::REQUEST_ATTRIBUTE_SET_COOKIE);
        if (is_string($token)) {
            $response->headers->setCookie(Cookie::create(
                WishlistProvider::COOKIE_NAME,
                $token,
                new \DateTimeImmutable('+1 year'),
                '/',
                null,
                $request->isSecure(),
                true,
                false,
                Cookie::SAMESITE_LAX,
            ));

            return;
        }

        if (true === $request->attributes->get(WishlistProvider::REQUEST_ATTRIBUTE_CLEAR_COOKIE)) {
            $response->headers->clearCookie(WishlistProvider::COOKIE_NAME, '/');
        }
    }
}
