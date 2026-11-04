<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use MarkApp\SyliusWishlistPlugin\Api\Dto\AddWishlistItem;
use MarkApp\SyliusWishlistPlugin\Api\Dto\CreateWishlist;
use MarkApp\SyliusWishlistPlugin\Api\Dto\MoveWishlistItem;
use MarkApp\SyliusWishlistPlugin\Api\Dto\UpdateWishlist;
use MarkApp\SyliusWishlistPlugin\Api\Resource\WishlistResource;
use MarkApp\SyliusWishlistPlugin\Api\WishlistResourceFactory;
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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Changes wishlists by calling the storefront's WishlistManager, so the rules (limits, one entry per product, unique
 * names, moving) are the same everywhere. Input is validated by API Platform before it gets here.
 *
 * @implements ProcessorInterface<mixed, WishlistResource|null>
 */
final class WishlistProcessor implements ProcessorInterface
{
    /** Set on the request so a listener can return the guest token as a response header too. */
    public const GUEST_TOKEN_ATTRIBUTE = '_mark_app_wishlist_guest_token';

    public function __construct(
        private readonly WishlistAccessResolver $accessResolver,
        private readonly WishlistManager $manager,
        private readonly WishlistResourceFactory $factory,
        private readonly WriteGuard $guard,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ChannelContextInterface $channelContext,
        private readonly ProductAvailability $productAvailability,
        private readonly GuestCookie $guestCookie,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?WishlistResource
    {
        $request = $context['request'] ?? null;
        \assert($request instanceof Request);

        $this->guard->guardApiWrite($request);

        return match (true) {
            $data instanceof CreateWishlist => $this->create($request, $data),
            $data instanceof UpdateWishlist => $this->update($request, $data),
            $data instanceof AddWishlistItem => $this->addItem($request, $data),
            $data instanceof MoveWishlistItem => $this->moveItem($request, $data),
            'mark_app_wishlist_api_remove_item' === $operation->getName() => $this->removeItem($request),
            default => $this->delete($request),
        };
    }

    private function create(Request $request, CreateWishlist $input): WishlistResource
    {
        $name = trim((string) $input->name);
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
            throw $this->violation('name', 'The maximum number of wishlists is reached.', 'list_limit');
        } catch (WishlistNameTakenException) {
            throw $this->violation('name', 'You already have a wishlist with this name.', 'name_taken');
        }

        $resource = $this->factory->create($wishlist);

        if (null === $customer) {
            // The guest must keep this token: it is the only proof that these lists are theirs.
            $resource->guestToken = $wishlist->getAccessToken();
            $request->attributes->set(self::GUEST_TOKEN_ATTRIBUTE, $wishlist->getAccessToken());
        }

        return $resource;
    }

    private function update(Request $request, UpdateWishlist $input): WishlistResource
    {
        $wishlist = $this->owned($request, (int) $request->attributes->get('id'));

        if (null === $input->name && null === $input->public) {
            throw $this->violation('name', 'Send "name" and/or "public".', 'nothing_to_change');
        }

        if (null !== $input->public) {
            $this->manager->setPublic($wishlist, $input->public);
        }

        if (null !== $input->name) {
            try {
                $this->manager->rename($wishlist, trim($input->name));
            } catch (WishlistNameTakenException) {
                throw $this->violation('name', 'You already have a wishlist with this name.', 'name_taken');
            }
        }

        return $this->factory->create($wishlist);
    }

    private function delete(Request $request): null
    {
        $this->manager->delete($this->owned($request, (int) $request->attributes->get('id')));

        return null;
    }

    private function addItem(Request $request, AddWishlistItem $input): WishlistResource
    {
        $wishlist = $this->owned($request, (int) $request->attributes->get('id'));
        $product = $this->productRepository->findOneByChannelAndCode($this->channelContext->getChannel(), (string) $input->productCode);

        if (null === $product || !$product->isEnabled()) {
            throw $this->violation('productCode', 'Unknown product.', 'unknown_product');
        }

        $variant = $this->variant($product, $input->variantCode);

        if (null === $variant) {
            throw $this->violation('variantCode', 'Unknown variant of this product.', 'unknown_variant');
        }

        $outcome = $this->manager->addVariant($wishlist, $variant);

        if (AddOutcome::ListFull === $outcome) {
            throw $this->violation('items', 'The wishlist is full.', 'list_full');
        }

        // 201 for a new entry; 200 when nothing was added (already there) or the remembered variant was replaced.
        $request->attributes->set('_api_response_status', AddOutcome::Added === $outcome ? Response::HTTP_CREATED : Response::HTTP_OK);

        $resource = $this->factory->create($wishlist);
        $resource->result = $outcome->value;

        return $resource;
    }

    private function removeItem(Request $request): null
    {
        $item = $this->itemOf($this->owned($request, (int) $request->attributes->get('id')), (string) $request->attributes->get('productCode'));
        $this->manager->removeItem($item);

        return null;
    }

    private function moveItem(Request $request, MoveWishlistItem $input): WishlistResource
    {
        $source = $this->owned($request, (int) $request->attributes->get('id'));
        $item = $this->itemOf($source, (string) $request->attributes->get('productCode'));
        $target = $this->owned($request, (int) $input->targetWishlistId);

        $outcome = $this->manager->moveItem($item, $target);

        if (\in_array($outcome, [MoveOutcome::TargetFull, MoveOutcome::SameList], true)) {
            throw $this->violation('targetWishlistId', 'The product cannot be moved: ' . $outcome->value . '.', $outcome->value);
        }

        $request->attributes->set('_api_response_status', Response::HTTP_OK);

        $resource = $this->factory->create($target);
        $resource->result = $outcome->value;

        return $resource;
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
     * A 422 in the standard API Platform shape (violations with a property path), for rules that only the manager knows.
     */
    private function violation(string $path, string $message, string $code): ValidationException
    {
        return new ValidationException(new ConstraintViolationList([
            new ConstraintViolation($message, null, [], null, $path, null, null, $code),
        ]));
    }
}
