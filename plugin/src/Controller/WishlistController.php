<?php

declare(strict_types=1);

namespace Hackathon\SyliusWishlistPlugin\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Hackathon\SyliusWishlistPlugin\Entity\Wishlist;
use Hackathon\SyliusWishlistPlugin\Provider\WishlistProvider;
use Sylius\Bundle\OrderBundle\Adder\CartItemAdderInterface;
use Sylius\Bundle\OrderBundle\Factory\AddToCartCommandFactoryInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Calculator\ProductVariantPricesCalculatorInterface;
use Sylius\Component\Core\Factory\CartItemFactoryInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Inventory\Checker\AvailabilityCheckerInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Wishlist storefront actions. Every state change is a POST protected by a CSRF token, and every list or
 * item is looked up through the provider, so a visitor can only reach their own wishlists.
 */
#[Route('/{_locale}', requirements: ['_locale' => '^[A-Za-z]{2,4}(_([A-Za-z]{4}|[0-9]{3}))?(_([A-Za-z]{2}|[0-9]{3}))?$'])]
final class WishlistController extends AbstractController
{
    private const CSRF_ID = 'hackathon_wishlist';

    public function __construct(
        private readonly WishlistProvider $wishlistProvider,
        private readonly EntityManagerInterface $entityManager,
        private readonly CartContextInterface $cartContext,
        private readonly CartItemFactoryInterface $cartItemFactory,
        private readonly AddToCartCommandFactoryInterface $addToCartCommandFactory,
        private readonly CartItemAdderInterface $cartItemAdder,
        private readonly OrderItemQuantityModifierInterface $quantityModifier,
        private readonly ProductVariantPricesCalculatorInterface $priceCalculator,
        private readonly RepositoryInterface $variantRepository,
        private readonly ChannelContextInterface $channelContext,
        private readonly AvailabilityCheckerInterface $availabilityChecker,
        private readonly ValidatorInterface $validator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /** The default (first) wishlist. With no list yet, only the "create a wishlist" form is shown. */
    #[Route('/wishlist', name: 'hackathon_wishlist_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->renderWishlist($this->wishlistProvider->getWishlist());
    }

    #[Route('/wishlist/{id}', name: 'hackathon_wishlist_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): Response
    {
        $wishlist = $this->wishlistProvider->getWishlist($id);
        if (null === $wishlist) {
            throw $this->createNotFoundException();
        }

        return $this->renderWishlist($wishlist);
    }

    #[Route('/wishlist/create', name: 'hackathon_wishlist_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        if (!$this->isTokenValid($request)) {
            return $this->redirectToRoute('hackathon_wishlist_index');
        }

        $name = $this->validName((string) $request->request->get('name'));
        if (null === $name) {
            return $this->redirectToRoute('hackathon_wishlist_index');
        }

        $wishlist = $this->wishlistProvider->createWishlist($name);
        $this->addFlash('success', 'hackathon_wishlist.flash.created');

        return $this->redirectToRoute('hackathon_wishlist_show', ['id' => $wishlist->getId()]);
    }

    #[Route('/wishlist/{id}/rename', name: 'hackathon_wishlist_rename', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function rename(Request $request, int $id): Response
    {
        $wishlist = $this->wishlistProvider->getWishlist($id);
        if (null === $wishlist) {
            throw $this->createNotFoundException();
        }

        if ($this->isTokenValid($request)) {
            $name = $this->validName((string) $request->request->get('name'), $wishlist);
            if (null !== $name) {
                $wishlist->setName($name);
                $this->entityManager->flush();
                $this->addFlash('success', 'hackathon_wishlist.flash.renamed');
            }
        }

        return $this->redirectToRoute('hackathon_wishlist_show', ['id' => $id]);
    }

    /** GET shows the confirmation page, POST deletes the list. Products in the catalogue are never touched. */
    #[Route('/wishlist/{id}/delete', name: 'hackathon_wishlist_delete', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function delete(Request $request, int $id): Response
    {
        $wishlist = $this->wishlistProvider->getWishlist($id);
        if (null === $wishlist) {
            throw $this->createNotFoundException();
        }

        if (!$request->isMethod('POST')) {
            return $this->render('@HackathonSyliusWishlistPlugin/shop/delete.html.twig', ['wishlist' => $wishlist]);
        }

        if (!$this->isTokenValid($request)) {
            return $this->redirectToRoute('hackathon_wishlist_show', ['id' => $id]);
        }

        $this->wishlistProvider->deleteWishlist($wishlist);
        $this->addFlash('success', 'hackathon_wishlist.flash.deleted');

        return $this->redirectToRoute('hackathon_wishlist_index');
    }

    /**
     * Saves a variant into a list. The list comes from the "wishlist" field (the destination picked next to
     * the button); without it the default list is used, and created when the visitor has none yet.
     */
    #[Route('/wishlist/add/{variantId}', name: 'hackathon_wishlist_add', requirements: ['variantId' => '\d+'], methods: ['POST'])]
    public function add(Request $request, int $variantId): Response
    {
        if (!$this->isTokenValid($request)) {
            return $this->redirectBack($request);
        }

        $variant = $this->variantRepository->find($variantId);
        if (!$variant instanceof ProductVariantInterface || !$variant->isEnabled()) {
            $this->addFlash('error', 'hackathon_wishlist.flash.not_found');

            return $this->redirectBack($request);
        }

        // On the product page the customer picks options (size, colour...). Those are sent as options[code]=valueCode
        // and decide which variant is saved; the variant id in the URL is only the default one.
        $selectedOptions = $request->request->all('options');
        if ([] !== $selectedOptions) {
            $variant = $this->resolveVariantByOptions($variant, $selectedOptions);
            if (null === $variant) {
                $this->addFlash('error', 'hackathon_wishlist.flash.not_found');

                return $this->redirectBack($request);
            }
        }

        $destination = (string) $request->request->get('wishlist');
        if ('' !== $destination) {
            $wishlist = $this->wishlistProvider->getWishlist((int) $destination);
            if (null === $wishlist) {
                $this->addFlash('error', 'hackathon_wishlist.flash.not_found');

                return $this->redirectBack($request);
            }
        } else {
            $wishlist = $this->wishlistProvider->getWishlist(null, create: true);
        }

        if ($wishlist->addVariant($variant)) {
            $this->entityManager->flush();
            $this->addFlash('success', $this->translator->trans('hackathon_wishlist.flash.added_to', ['%name%' => $wishlist->getName()], 'flashes'));
        } else {
            $this->addFlash('info', $this->translator->trans('hackathon_wishlist.flash.already_added_to', ['%name%' => $wishlist->getName()], 'flashes'));
        }

        return $this->redirectBack($request);
    }

    #[Route('/wishlist/remove/{itemId}', name: 'hackathon_wishlist_remove', requirements: ['itemId' => '\d+'], methods: ['POST'])]
    public function remove(Request $request, int $itemId): Response
    {
        $item = $this->wishlistProvider->findItem($itemId);
        if (null === $item) {
            return $this->redirectToRoute('hackathon_wishlist_index');
        }

        $wishlist = $item->getWishlist();
        if ($this->isTokenValid($request)) {
            $wishlist->removeItem($item);
            $this->entityManager->flush();
            $this->addFlash('success', 'hackathon_wishlist.flash.removed');
        }

        return $this->redirectToRoute('hackathon_wishlist_show', ['id' => $wishlist->getId()]);
    }

    /** Moves an entry to another list of the same visitor. If the target already has the product it is just removed here. */
    #[Route('/wishlist/move/{itemId}', name: 'hackathon_wishlist_move', requirements: ['itemId' => '\d+'], methods: ['POST'])]
    public function move(Request $request, int $itemId): Response
    {
        $item = $this->wishlistProvider->findItem($itemId);
        if (null === $item) {
            return $this->redirectToRoute('hackathon_wishlist_index');
        }

        $source = $item->getWishlist();
        if (!$this->isTokenValid($request)) {
            return $this->redirectToRoute('hackathon_wishlist_show', ['id' => $source->getId()]);
        }

        $target = $this->wishlistProvider->getWishlist((int) $request->request->get('target'));
        if (null === $target || $target === $source) {
            $this->addFlash('error', 'hackathon_wishlist.flash.not_found');

            return $this->redirectToRoute('hackathon_wishlist_show', ['id' => $source->getId()]);
        }

        $target->addVariant($item->getVariant());
        $source->removeItem($item);
        $this->entityManager->flush();
        $this->addFlash('success', $this->translator->trans('hackathon_wishlist.flash.moved', ['%name%' => $target->getName()], 'flashes'));

        return $this->redirectToRoute('hackathon_wishlist_show', ['id' => $source->getId()]);
    }

    /**
     * Adds one or more wishlist entries to the cart. The wishlists themselves are never modified and the cart
     * is only touched through Sylius' own cart item adder and its availability validation.
     */
    #[Route('/wishlist/add-to-cart', name: 'hackathon_wishlist_add_to_cart', methods: ['POST'])]
    public function addToCart(Request $request): Response
    {
        $listId = (int) $request->request->get('list');
        $back = $listId > 0 ? $this->redirectToRoute('hackathon_wishlist_show', ['id' => $listId]) : $this->redirectToRoute('hackathon_wishlist_index');

        if (!$this->isTokenValid($request)) {
            return $back;
        }

        $selectedIds = array_map('intval', (array) $request->request->all('items'));
        if ([] === $selectedIds) {
            $this->addFlash('info', 'hackathon_wishlist.flash.nothing_selected');

            return $back;
        }

        $added = 0;
        $skipped = 0;

        foreach ($selectedIds as $itemId) {
            // Only entries of the visitor's own lists can be selected.
            $item = $this->wishlistProvider->findItem($itemId);
            if (null === $item) {
                continue;
            }

            if ($this->addVariantToCart($item->getVariant())) {
                ++$added;
            } else {
                ++$skipped;
            }
        }

        if ($added > 0) {
            $this->addFlash('success', 'hackathon_wishlist.flash.added_to_cart');
        }
        if ($skipped > 0) {
            $this->addFlash('error', 'hackathon_wishlist.flash.not_added_to_cart');
        }

        return $added > 0 && 0 === $skipped ? $this->redirectToRoute('sylius_shop_cart_summary') : $back;
    }

    private function renderWishlist(?Wishlist $current): Response
    {
        $channel = $this->channelContext->getChannel();
        $rows = [];

        foreach ($current?->getItems() ?? [] as $item) {
            $variant = $item->getVariant();
            $context = ['channel' => $channel];

            $rows[] = [
                'item' => $item,
                'variant' => $variant,
                'product' => $variant->getProduct(),
                'price' => $this->priceCalculator->calculate($variant, $context),
                'original_price' => $this->priceCalculator->calculateOriginal($variant, $context),
                'available' => $this->isPurchasable($variant),
            ];
        }

        return $this->render('@HackathonSyliusWishlistPlugin/shop/index.html.twig', [
            'wishlists' => $this->wishlistProvider->getWishlists(),
            'current' => $current,
            'rows' => $rows,
        ]);
    }

    private function isTokenValid(Request $request): bool
    {
        if ($this->isCsrfTokenValid(self::CSRF_ID, (string) $request->request->get('_csrf_token'))) {
            return true;
        }

        $this->addFlash('error', 'hackathon_wishlist.flash.invalid_token');

        return false;
    }

    /**
     * Trims the name and checks it. Returns null (after adding an error message) when it is empty or
     * only whitespace, too long, or already used by another list of the visitor.
     */
    private function validName(string $raw, ?Wishlist $renaming = null): ?string
    {
        $name = trim((string) preg_replace('/^[\s\p{Z}]+|[\s\p{Z}]+$/u', '', $raw));

        if ('' === $name) {
            $this->addFlash('error', 'hackathon_wishlist.flash.name_required');

            return null;
        }

        if (mb_strlen($name) > Wishlist::MAX_NAME_LENGTH) {
            $this->addFlash('error', 'hackathon_wishlist.flash.name_too_long');

            return null;
        }

        if ($this->wishlistProvider->hasName($name, $renaming)) {
            $this->addFlash('error', 'hackathon_wishlist.flash.name_taken');

            return null;
        }

        return $name;
    }

    private function addVariantToCart(ProductVariantInterface $variant): bool
    {
        if (!$this->isPurchasable($variant)) {
            return false;
        }

        $cartItem = $this->cartItemFactory->createForProduct($variant->getProduct());
        $cartItem->setVariant($variant);
        $this->quantityModifier->modify($cartItem, 1);

        $command = $this->addToCartCommandFactory->createWithCartAndCartItem($this->cartContext->getCart(), $cartItem);

        // Same availability rules Sylius applies on the product page ("sylius" validation group).
        if (count($this->validator->validate($command, null, ['sylius'])) > 0) {
            return false;
        }

        $this->cartItemAdder->add($command);

        return true;
    }

    private function isPurchasable(ProductVariantInterface $variant): bool
    {
        $product = $variant->getProduct();

        return $variant->isEnabled()
            && null !== $product
            && $product->isEnabled()
            && $this->availabilityChecker->isStockAvailable($variant);
    }

    /**
     * Finds the enabled variant of the same product whose option values match the selection.
     *
     * @param array<array-key, mixed> $selectedOptions option code => option value code
     */
    private function resolveVariantByOptions(ProductVariantInterface $variant, array $selectedOptions): ?ProductVariantInterface
    {
        $product = $variant->getProduct();
        if (null === $product) {
            return null;
        }

        foreach ($product->getEnabledVariants() as $candidate) {
            $values = [];
            foreach ($candidate->getOptionValues() as $optionValue) {
                $values[$optionValue->getOptionCode()] = $optionValue->getCode();
            }

            $matches = true;
            foreach ($selectedOptions as $optionCode => $valueCode) {
                if (!is_string($valueCode) || ($values[(string) $optionCode] ?? null) !== $valueCode) {
                    $matches = false;

                    break;
                }
            }

            if ($matches) {
                return $candidate;
            }
        }

        return null;
    }

    /** Goes back to the page the visitor came from, but only when it is on this same site. */
    private function redirectBack(Request $request): RedirectResponse
    {
        $referer = (string) $request->headers->get('referer');
        if ('' !== $referer && str_starts_with($referer, $request->getSchemeAndHttpHost() . '/')) {
            return new RedirectResponse($referer);
        }

        return $this->redirectToRoute('hackathon_wishlist_index');
    }
}
