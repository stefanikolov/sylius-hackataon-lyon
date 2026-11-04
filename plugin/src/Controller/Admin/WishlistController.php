<?php

declare(strict_types=1);

namespace Hackathon\SyliusWishlistPlugin\Controller\Admin;

use Hackathon\SyliusWishlistPlugin\Repository\WishlistRepository;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Calculator\ProductVariantPricesCalculatorInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Read-only administration of customer wishlists.
 *
 * Only GET routes exist, so nothing can be created, changed or deleted from here. The routes live under the admin
 * path, which Sylius' admin firewall already protects (ROLE_ADMINISTRATION_ACCESS): storefront customers and
 * anonymous visitors are sent to the admin login.
 */
#[Route('/%sylius_admin.path_name%/wishlists')]
final class WishlistController extends AbstractController
{
    private const PER_PAGE = 20;

    public function __construct(
        private readonly WishlistRepository $wishlistRepository,
        private readonly ChannelRepositoryInterface $channelRepository,
        private readonly ProductVariantPricesCalculatorInterface $priceCalculator,
    ) {
    }

    #[Route('', name: 'hackathon_admin_wishlist_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $page = max(1, $request->query->getInt('page', 1));
        $total = $this->wishlistRepository->countForAdmin($search);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $pages);

        return $this->render('@HackathonSyliusWishlistPlugin/admin/index.html.twig', [
            'wishlists' => $this->wishlistRepository->findForAdmin($search, $page, self::PER_PAGE),
            'search' => $search,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
        ]);
    }

    #[Route('/{id}', name: 'hackathon_admin_wishlist_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): Response
    {
        $wishlist = $this->wishlistRepository->find($id);
        if (null === $wishlist) {
            throw $this->createNotFoundException();
        }

        // The admin has no storefront channel, so prices are shown for the first enabled one.
        $channel = $this->findChannel();
        $rows = [];
        foreach ($wishlist->getItems() as $item) {
            $variant = $item->getVariant();
            $rows[] = [
                'variant' => $variant,
                'product' => $variant->getProduct(),
                'price' => null !== $channel ? $this->priceCalculator->calculate($variant, ['channel' => $channel]) : null,
                'addedAt' => $item->getCreatedAt(),
            ];
        }

        return $this->render('@HackathonSyliusWishlistPlugin/admin/show.html.twig', [
            'wishlist' => $wishlist,
            'rows' => $rows,
            'channel' => $channel,
        ]);
    }

    private function findChannel(): ?ChannelInterface
    {
        $channels = $this->channelRepository->findBy(['enabled' => true], ['id' => 'ASC'], 1);
        $channel = $channels[0] ?? null;

        return $channel instanceof ChannelInterface ? $channel : null;
    }
}
