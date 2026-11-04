<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class RedirectResolver
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    /**
     * Returns the local path posted in "_redirect", or the given route. Only local paths to real pages are accepted,
     * so the field cannot be used as an open redirect.
     *
     * @param array<string, mixed> $parameters
     */
    public function resolve(Request $request, string $fallbackRoute, array $parameters = []): string
    {
        $target = $request->request->get('_redirect');

        if (
            \is_string($target)
            && str_starts_with($target, '/')
            && !str_starts_with($target, '//')
            && !str_contains($target, '\\')
            // Live component endpoints are internal URLs, not pages a visitor can be sent to.
            && !str_contains($target, '/_components/')
        ) {
            return $target;
        }

        return $this->urlGenerator->generate($fallbackRoute, $parameters);
    }
}
