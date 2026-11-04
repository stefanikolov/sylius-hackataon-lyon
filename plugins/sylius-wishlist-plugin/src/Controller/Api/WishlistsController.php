<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Controller\Api;

use MarkApp\SyliusWishlistPlugin\Api\WishlistSerializer;
use MarkApp\SyliusWishlistPlugin\Entity\WishlistInterface;
use MarkApp\SyliusWishlistPlugin\Enum\AddOutcome;
use MarkApp\SyliusWishlistPlugin\Enum\MoveOutcome;
use MarkApp\SyliusWishlistPlugin\Exception\WishlistLimitReachedException;
use MarkApp\SyliusWishlistPlugin\Exception\WishlistNameTakenException;
use MarkApp\SyliusWishlistPlugin\Service\GuestCookie;
use MarkApp\SyliusWishlistPlugin\Service\ProductAvailability;
use MarkApp\SyliusWishlistPlugin\Service\WishlistAccessResolver;
use MarkApp\SyliusWishlistPlugin\Service\WishlistManager;
use MarkApp\SyliusWishlistPlugin\Service\WriteGuard;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The wishlist API. It is a thin layer over the services the storefront uses, so both see the same data and the same
 * rules. A customer is identified by the Sylius JWT, a guest by the "X-Wishlist-Token" header. A list of someone else
 * answers 404, exactly as in the storefront.
 */
final class WishlistsController
{
    public function __construct(
        private readonly WishlistAccessResolver $accessResolver,
        private readonly WishlistManager $manager,
        private readonly WishlistSerializer $serializer,
        private readonly WriteGuard $guard,
        private readonly ValidatorInterface $validator,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ChannelContextInterface $channelContext,
        private readonly ProductAvailability $productAvailability,
        private readonly GuestCookie $guestCookie,
    ) {
    }

    public function list(Request $request): JsonResponse
    {
        $wishlists = $this->accessResolver->getOwnedWishlists($request);

        return new JsonResponse(['wishlists' => array_map($this->serializer->summary(...), $wishlists)]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return new JsonResponse($this->serializer->detail($this->owned($request, $id)));
    }

    public function create(Request $request): JsonResponse
    {
        $this->guard->guardApiWrite($request);
        $name = trim((string) ($this->body($request)['name'] ?? ''));

        if ([] !== $violations = $this->nameViolations($name)) {
            return $this->invalid('Invalid wishlist name.', $violations);
        }

        $customer = $this->accessResolver->getCustomer();

        try {
            if (null !== $customer) {
                $wishlist = $this->manager->createCustomerWishlist($customer, $name);
            } else {
                $token = $this->guestCookie->read($request);

                if (null === $token) {
                    $this->guard->guardGuestCreate($request);
                }

                $wishlist = $this->manager->createGuestWishlist($token, $name);
            }
        } catch (WishlistLimitReachedException) {
            return $this->invalid('The maximum number of wishlists is reached.', ['name' => ['list_limit']], 'list_limit');
        } catch (WishlistNameTakenException) {
            return $this->invalid('You already have a wishlist with this name.', ['name' => ['name_taken']], 'name_taken');
        }

        $response = new JsonResponse($this->serializer->detail($wishlist), Response::HTTP_CREATED, [
            'Location' => '/api/v2/shop/wishlists/' . $wishlist->getId(),
        ]);

        if (null === $customer) {
            // The guest must keep this token: it is the only proof that these lists are theirs.
            $response->headers->set(GuestCookie::API_HEADER, (string) $wishlist->getAccessToken());
            $response->setData(['guestToken' => $wishlist->getAccessToken()] + $this->serializer->detail($wishlist));
        }

        return $response;
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->guard->guardApiWrite($request);
        $wishlist = $this->owned($request, $id);
        $data = $this->body($request);

        if (!\array_key_exists('name', $data) && !\array_key_exists('public', $data)) {
            return $this->invalid('Send "name" and/or "public".', ['name' => ['required']]);
        }

        if (\array_key_exists('public', $data)) {
            if (!\is_bool($data['public'])) {
                return $this->invalid('"public" must be true or false.', ['public' => ['invalid']]);
            }

            $this->manager->setPublic($wishlist, $data['public']);
        }

        if (\array_key_exists('name', $data)) {
            $name = trim((string) $data['name']);

            if ([] !== $violations = $this->nameViolations($name)) {
                return $this->invalid('Invalid wishlist name.', $violations);
            }

            try {
                $this->manager->rename($wishlist, $name);
            } catch (WishlistNameTakenException) {
                return $this->invalid('You already have a wishlist with this name.', ['name' => ['name_taken']], 'name_taken');
            }
        }

        return new JsonResponse($this->serializer->detail($wishlist));
    }

    public function delete(Request $request, int $id): Response
    {
        $this->guard->guardApiWrite($request);
        $this->manager->delete($this->owned($request, $id));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    public function addItem(Request $request, int $id): JsonResponse
    {
        $this->guard->guardApiWrite($request);
        $wishlist = $this->owned($request, $id);
        $data = $this->body($request);

        $product = $this->productRepository->findOneByChannelAndCode($this->channelContext->getChannel(), (string) ($data['productCode'] ?? ''));

        if (null === $product || !$product->isEnabled()) {
            return $this->invalid('Unknown product.', ['productCode' => ['unknown_product']], 'unknown_product');
        }

        $variant = $this->variant($product, isset($data['variantCode']) ? (string) $data['variantCode'] : null);

        if (null === $variant) {
            return $this->invalid('Unknown variant of this product.', ['variantCode' => ['unknown_variant']], 'unknown_variant');
        }

        $outcome = $this->manager->addVariant($wishlist, $variant);

        if (AddOutcome::ListFull === $outcome) {
            return $this->invalid('The wishlist is full.', ['items' => ['list_full']], 'list_full');
        }

        // 201 for a new entry; 200 when nothing was added (already there) or the remembered variant was replaced.
        return new JsonResponse(
            ['result' => $outcome->value, 'wishlist' => $this->serializer->detail($wishlist)],
            AddOutcome::Added === $outcome ? Response::HTTP_CREATED : Response::HTTP_OK,
        );
    }

    public function removeItem(Request $request, int $id, string $productCode): Response
    {
        $this->guard->guardApiWrite($request);
        $wishlist = $this->owned($request, $id);
        $item = $this->itemOf($wishlist, $productCode);

        $this->manager->removeItem($item);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    public function moveItem(Request $request, int $id, string $productCode): JsonResponse
    {
        $this->guard->guardApiWrite($request);
        $source = $this->owned($request, $id);
        $item = $this->itemOf($source, $productCode);
        $target = $this->owned($request, (int) ($this->body($request)['targetWishlistId'] ?? 0));

        $outcome = $this->manager->moveItem($item, $target);

        if (\in_array($outcome, [MoveOutcome::TargetFull, MoveOutcome::SameList], true)) {
            return $this->invalid('The product cannot be moved: ' . $outcome->value . '.', ['targetWishlistId' => [$outcome->value]], $outcome->value);
        }

        return new JsonResponse(['result' => $outcome->value, 'source' => $this->serializer->detail($source), 'target' => $this->serializer->detail($target)]);
    }

    private function owned(Request $request, int $id): WishlistInterface
    {
        $wishlist = $id > 0 ? $this->accessResolver->findOwned($request, $id) : null;

        if (null === $wishlist) {
            throw new NotFoundHttpException('Wishlist not found.');
        }

        return $wishlist;
    }

    private function itemOf(WishlistInterface $wishlist, string $productCode): \MarkApp\SyliusWishlistPlugin\Entity\WishlistItemInterface
    {
        $product = $this->productRepository->findOneByCode($productCode);
        $item = null === $product ? null : $wishlist->getItemForProduct($product);

        if (null === $item) {
            throw new NotFoundHttpException('Product not found on this wishlist.');
        }

        return $item;
    }

    private function variant(ProductInterface $product, ?string $code): ?ProductVariantInterface
    {
        if (null === $code || '' === $code) {
            return $this->productAvailability->getDefaultVariant($product);
        }

        foreach ($product->getEnabledVariants() as $variant) {
            if ($variant->getCode() === $code) {
                return $variant;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function body(Request $request): array
    {
        $content = trim($request->getContent());

        if ('' === $content) {
            return [];
        }

        $data = json_decode($content, true);

        if (!\is_array($data)) {
            throw new BadRequestHttpException('The request body must be a JSON object.');
        }

        return $data;
    }

    /**
     * Same rules as the name field of the storefront form: required, at most 100 characters.
     *
     * @return array<string, list<string>>
     */
    private function nameViolations(string $name): array
    {
        $messages = [];

        foreach ($this->validator->validate($name, [new NotBlank(message: 'A wishlist name is required.'), new Length(max: 100, maxMessage: 'A wishlist name can have at most 100 characters.')]) as $violation) {
            $messages[] = (string) $violation->getMessage();
        }

        return [] === $messages ? [] : ['name' => $messages];
    }

    /**
     * @param array<string, list<string>> $violations
     */
    private function invalid(string $message, array $violations, string $code = 'validation_failed'): JsonResponse
    {
        return new JsonResponse(['error' => ['code' => $code, 'message' => $message, 'violations' => $violations]], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
