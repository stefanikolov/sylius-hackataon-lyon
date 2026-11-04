<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;

/**
 * Adds flash messages in the "flashes" translation domain, which the Sylius shop layout renders.
 */
final class Flasher
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function add(Request $request, string $type, string $messageKey, array $parameters = []): void
    {
        $session = $request->getSession();

        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add($type, ['message' => $messageKey, 'parameters' => $parameters]);
        }
    }
}
