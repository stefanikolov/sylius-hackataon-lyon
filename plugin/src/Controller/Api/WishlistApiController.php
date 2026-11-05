<?php

declare(strict_types=1);

namespace Hackathon\SyliusWishlistPlugin\Controller\Api;

use Hackathon\SyliusWishlistPlugin\Entity\Wishlist;
use Hackathon\SyliusWishlistPlugin\Entity\WishlistItem;
use Hackathon\SyliusWishlistPlugin\Provider\WishlistProvider;
use Hackathon\SyliusWishlistPlugin\Service\WishlistManager;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * JSON API for wishlists. It lives under /api/v2/shop, the Sylius shop API, so a customer authenticates the
 * way Sylius already prescribes (a JWT from POST /api/v2/shop/customers/token) and no new firewall is needed.
 *
 * Ownership follows the storefront rules and uses the same services:
 *  - A customer (JWT) sees and changes only their own lists. The customer is never taken from the request body.
 *  - A guest sends the secret `X-Wishlist-Token` header (returned when the guest creates the first list).
 *    The server generates it; a token chosen by the client is never adopted.
 *  - A list of somebody else answers 404, exactly like a list that does not exist.
 *
 * The cookie of the storefront is deliberately ignored here, so a browser cannot be made to call this API.
 */
#[Route('/api/v2/shop/wishlists', name: 'hackathon_api_wishlist_')]
final class WishlistApiController
{
    public const GUEST_TOKEN_HEADER = 'X-Wishlist-Token';

    private const MESSAGES = [
        WishlistManager::ERROR_NAME_REQUIRED => 'A wishlist name is required.',
        WishlistManager::ERROR_NAME_TOO_LONG => 'The wishlist name cannot be longer than ' . Wishlist::MAX_NAME_LENGTH . ' characters.',
        WishlistManager::ERROR_NAME_TAKEN => 'You already have a wishlist with this name.',
    ];

    public function __construct(
        private readonly WishlistProvider $provider,
        private readonly WishlistManager $manager,
        private readonly RepositoryInterface $variantRepository,
        private readonly RepositoryInterface $productRepository,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->identify($request);

        return new JsonResponse(array_map(fn (Wishlist $wishlist): array => $this->summary($wishlist), $this->provider->getWishlists()));
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Request $request, int $id): Response
    {
        $this->identify($request);

        $wishlist = $this->provider->getWishlist($id);

        return null === $wishlist ? $this->notFound() : new JsonResponse($this->detail($wishlist));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $this->identify($request);

        $body = $this->jsonBody($request);
        if ($body instanceof Response) {
            return $body;
        }

        $name = $this->manager->normalizeName(is_string($body['name'] ?? null) ? $body['name'] : '');
        $error = $this->manager->validateName($name);
        if (null !== $error) {
            return $this->invalid(['name' => [self::MESSAGES[$error]]]);
        }

        $wishlist = $this->manager->create($name);
        $payload = $this->detail($wishlist);

        $response = new JsonResponse($payload, Response::HTTP_CREATED);
        $response->headers->set('Location', '/api/v2/shop/wishlists/' . $wishlist->getId());
        // A guest gets the token that identifies them in later calls.
        if (null !== $wishlist->getToken()) {
            $response->headers->set(self::GUEST_TOKEN_HEADER, $wishlist->getToken());
        }

        return $response;
    }

    #[Route('/{id}', name: 'rename', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function rename(Request $request, int $id): Response
    {
        $this->identify($request);

        $wishlist = $this->provider->getWishlist($id);
        if (null === $wishlist) {
            return $this->notFound();
        }

        $body = $this->jsonBody($request);
        if ($body instanceof Response) {
            return $body;
        }

        $name = $this->manager->normalizeName(is_string($body['name'] ?? null) ? $body['name'] : '');
        $error = $this->manager->validateName($name, $wishlist);
        if (null !== $error) {
            return $this->invalid(['name' => [self::MESSAGES[$error]]]);
        }

        $this->manager->rename($wishlist, $name);

        return new JsonResponse($this->detail($wishlist));
    }

    #[Route('/{id}', name: 'delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(Request $request, int $id): Response
    {
        $this->identify($request);

        $wishlist = $this->provider->getWishlist($id);
        if (null === $wishlist) {
            return $this->notFound();
        }

        $this->manager->delete($wishlist);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Adds a product to the list. The product is given by `variantId` or `variantCode`, or by `productId` /
     * `productCode` (its first enabled variant, or the one matching the optional `options` map).
     */
    #[Route('/{id}/items', name: 'add_item', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function addItem(Request $request, int $id): Response
    {
        $this->identify($request);

        $wishlist = $this->provider->getWishlist($id);
        if (null === $wishlist) {
            return $this->notFound();
        }

        $body = $this->jsonBody($request);
        if ($body instanceof Response) {
            return $body;
        }

        $variant = $this->findVariant($body);
        if (null === $variant) {
            return $this->invalid(['product' => ['Give an existing, enabled product with productId, productCode, variantId or variantCode.']]);
        }

        $added = $this->manager->add($wishlist, $variant);

        // 201 when a new entry was created, 200 when the product was already in the list (nothing changes).
        return new JsonResponse([
            'added' => $added,
            'message' => $added ? 'The product was added to the wishlist.' : 'The product is already in this wishlist.',
            'wishlist' => $this->detail($wishlist),
        ], $added ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    #[Route('/{id}/items/{itemId}', name: 'remove_item', requirements: ['id' => '\d+', 'itemId' => '\d+'], methods: ['DELETE'])]
    public function removeItem(Request $request, int $id, int $itemId): Response
    {
        $this->identify($request);

        $item = $this->findItem($id, $itemId);
        if (null === $item) {
            return $this->notFound();
        }

        $this->manager->remove($item);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /** Moves an entry to another list of the same owner: body {"targetWishlistId": 12}. */
    #[Route('/{id}/items/{itemId}/move', name: 'move_item', requirements: ['id' => '\d+', 'itemId' => '\d+'], methods: ['POST'])]
    public function moveItem(Request $request, int $id, int $itemId): Response
    {
        $this->identify($request);

        $item = $this->findItem($id, $itemId);
        if (null === $item) {
            return $this->notFound();
        }

        $body = $this->jsonBody($request);
        if ($body instanceof Response) {
            return $body;
        }

        $targetId = $body['targetWishlistId'] ?? null;
        $target = is_int($targetId) || (is_string($targetId) && ctype_digit($targetId)) ? $this->provider->getWishlist((int) $targetId) : null;
        if (null === $target) {
            return $this->invalid(['targetWishlistId' => ['Give the id of another wishlist of yours.']]);
        }

        $source = $item->getWishlist();
        if ($target === $source) {
            return $this->invalid(['targetWishlistId' => ['The product is already in this wishlist.']]);
        }

        $this->manager->move($item, $target);

        return new JsonResponse(['source' => $this->detail($source), 'target' => $this->detail($target)]);
    }

    /** Tells the provider where the guest identity comes from for this request: the header, never the cookie. */
    private function identify(Request $request): void
    {
        $this->provider->useGuestToken($request->headers->get(self::GUEST_TOKEN_HEADER));
    }

    private function findItem(int $wishlistId, int $itemId): ?WishlistItem
    {
        $wishlist = $this->provider->getWishlist($wishlistId);
        if (null === $wishlist) {
            return null;
        }

        foreach ($wishlist->getItems() as $item) {
            if ($item->getId() === $itemId) {
                return $item;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $body */
    private function findVariant(array $body): ?ProductVariantInterface
    {
        $variant = null;

        if (isset($body['variantId']) && (is_int($body['variantId']) || (is_string($body['variantId']) && ctype_digit($body['variantId'])))) {
            $variant = $this->variantRepository->find((int) $body['variantId']);
        } elseif (isset($body['variantCode']) && is_string($body['variantCode'])) {
            $variant = $this->variantRepository->findOneBy(['code' => $body['variantCode']]);
        } else {
            $product = null;
            if (isset($body['productId']) && (is_int($body['productId']) || (is_string($body['productId']) && ctype_digit($body['productId'])))) {
                $product = $this->productRepository->find((int) $body['productId']);
            } elseif (isset($body['productCode']) && is_string($body['productCode'])) {
                $product = $this->productRepository->findOneBy(['code' => $body['productCode']]);
            }

            if ($product instanceof ProductInterface) {
                $first = $product->getEnabledVariants()->first();
                $variant = $first instanceof ProductVariantInterface ? $first : null;

                $options = $body['options'] ?? null;
                if (null !== $variant && is_array($options) && [] !== $options) {
                    $variant = $this->manager->resolveVariantByOptions($variant, $options);
                }
            }
        }

        return $variant instanceof ProductVariantInterface && $variant->isEnabled() ? $variant : null;
    }

    /** @return array<string, mixed> */
    private function summary(Wishlist $wishlist): array
    {
        return [
            'id' => $wishlist->getId(),
            'name' => $wishlist->getName(),
            'itemCount' => $wishlist->getItems()->count(),
            'createdAt' => $wishlist->getCreatedAt()->format(\DATE_ATOM),
        ];
    }

    /** @return array<string, mixed> */
    private function detail(Wishlist $wishlist): array
    {
        $items = [];
        foreach ($wishlist->getItems() as $item) {
            $variant = $item->getVariant();
            $product = $variant->getProduct();

            $items[] = [
                'id' => $item->getId(),
                'productId' => $product?->getId(),
                'productCode' => $product?->getCode(),
                'name' => $product?->getName(),
                'variantId' => $variant->getId(),
                'variantCode' => $variant->getCode(),
                'variantName' => null !== $product && !$product->isSimple() ? ($variant->getName() ?? $variant->getCode()) : null,
                // Unavailable products stay in the list; this flag tells the client they cannot be bought now.
                'available' => $this->manager->isPurchasable($variant),
                'addedAt' => $item->getCreatedAt()->format(\DATE_ATOM),
            ];
        }

        return $this->summary($wishlist) + ['items' => $items];
    }

    /**
     * Reads a JSON object from the request body. Returns an error response when the content type is not JSON
     * (which also stops browsers from sending cross-site "simple" requests) or the body is not a JSON object.
     *
     * @return array<string, mixed>|Response
     */
    private function jsonBody(Request $request): array|Response
    {
        if ('json' !== $request->getContentTypeFormat()) {
            return $this->error(Response::HTTP_UNSUPPORTED_MEDIA_TYPE, 'unsupported_media_type', 'Send the body as JSON with the header "Content-Type: application/json".');
        }

        $content = trim($request->getContent());
        $data = '' === $content ? [] : json_decode($content, true);
        if (!is_array($data) || array_is_list($data) && [] !== $data) {
            return $this->error(Response::HTTP_BAD_REQUEST, 'invalid_json', 'The request body must be a JSON object.');
        }

        return $data;
    }

    /** @param array<string, list<string>> $violations */
    private function invalid(array $violations): JsonResponse
    {
        return $this->error(Response::HTTP_UNPROCESSABLE_ENTITY, 'validation_failed', 'The request is not valid.', ['violations' => $violations]);
    }

    private function notFound(): JsonResponse
    {
        // The same answer for "does not exist" and "belongs to somebody else", so nothing leaks.
        return $this->error(Response::HTTP_NOT_FOUND, 'not_found', 'Wishlist not found.');
    }

    /** @param array<string, mixed> $extra */
    private function error(int $status, string $code, string $message, array $extra = []): JsonResponse
    {
        return new JsonResponse(['error' => $code, 'message' => $message] + $extra, $status);
    }
}
