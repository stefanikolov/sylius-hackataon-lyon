<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Protects every write endpoint: CSRF token, rate limiting and, for guests, a honeypot and a time trap.
 */
final class WriteGuard
{
    public const CSRF_TOKEN_ID = 'mark_app_wishlist';

    public const HONEYPOT_FIELD = '_website';

    public const TIMESTAMP_FIELD = '_ts';

    /** A form submitted faster than this was not filled in by a human. */
    private const MIN_FORM_AGE_SECONDS = 1;

    public function __construct(
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly RateLimiterFactory $writeLimiter,
        private readonly RateLimiterFactory $guestCreateLimiter,
        private readonly RateLimiterFactory $tokenMissLimiter,
    ) {
    }

    /**
     * @param bool $checkCsrf set to false for Symfony forms, which validate their own CSRF token
     */
    public function guardWrite(Request $request, bool $guest, bool $checkCsrf = true): void
    {
        if ($checkCsrf) {
            $this->assertCsrf($request);
        }

        $this->consume($this->writeLimiter, $request);

        if ($guest) {
            $this->assertHuman($request);
        }
    }

    public function guardGuestCreate(Request $request): void
    {
        $this->consume($this->guestCreateLimiter, $request);
    }

    /**
     * Call before answering 404 for an unknown or private token: too many misses means token guessing.
     */
    public function registerTokenMiss(Request $request): void
    {
        $this->consume($this->tokenMissLimiter, $request);
    }

    private function assertCsrf(Request $request): void
    {
        $token = new CsrfToken(self::CSRF_TOKEN_ID, (string) $request->request->get('_token', ''));

        if (!$this->csrfTokenManager->isTokenValid($token)) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }
    }

    private function assertHuman(Request $request): void
    {
        if ('' !== (string) $request->request->get(self::HONEYPOT_FIELD, '')) {
            throw new BadRequestHttpException('Invalid request.');
        }

        $renderedAt = $request->request->get(self::TIMESTAMP_FIELD);

        if (!is_numeric($renderedAt) || time() - (int) $renderedAt < self::MIN_FORM_AGE_SECONDS) {
            throw new BadRequestHttpException('Invalid request.');
        }
    }

    private function consume(RateLimiterFactory $factory, Request $request): void
    {
        $limit = $factory->create($request->getClientIp())->consume();

        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException(max(1, $limit->getRetryAfter()->getTimestamp() - time()));
        }
    }
}
