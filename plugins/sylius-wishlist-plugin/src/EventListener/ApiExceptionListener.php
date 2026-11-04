<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\EventListener;

use MarkApp\SyliusWishlistPlugin\Service\GuestCookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Answers errors of the wishlist API as JSON, without internals: only HTTP errors the controllers throw on purpose
 * (not found, too many requests, bad request) are translated. Anything unexpected is left to Symfony.
 */
final class ApiExceptionListener
{
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if (true !== $event->getRequest()->attributes->get(GuestCookie::API_ATTRIBUTE) || !$exception instanceof HttpExceptionInterface) {
            return;
        }

        $status = $exception->getStatusCode();
        $message = '' !== $exception->getMessage() ? $exception->getMessage() : (Response::$statusTexts[$status] ?? 'Error');

        $event->setResponse(new JsonResponse(
            ['error' => ['code' => strtolower(str_replace(' ', '_', Response::$statusTexts[$status] ?? 'error')), 'message' => $message]],
            $status,
            $exception->getHeaders(),
        ));
    }
}
