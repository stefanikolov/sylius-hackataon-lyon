<?php

declare(strict_types=1);

use Hackathon\SyliusWishlistPlugin\Controller\WishlistController;
use Hackathon\SyliusWishlistPlugin\EventListener\WishlistCookieListener;
use Hackathon\SyliusWishlistPlugin\Provider\WishlistProvider;
use Hackathon\SyliusWishlistPlugin\Repository\WishlistRepository;
use Hackathon\SyliusWishlistPlugin\Twig\WishlistExtension;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator $container): void {
    $services = $container->services();
    $services->defaults()->autowire()->autoconfigure();

    $services->set(WishlistRepository::class);
    $services->set(WishlistProvider::class);
    $services->set(WishlistCookieListener::class);
    $services->set(WishlistExtension::class);

    $services->set(WishlistController::class)
        ->arg('$cartContext', service('sylius.context.cart'))
        ->arg('$cartItemFactory', service('sylius.factory.cart_item'))
        ->arg('$addToCartCommandFactory', service('sylius.factory.add_to_cart_command'))
        ->arg('$cartItemAdder', service('sylius.adder.cart_item'))
        ->arg('$quantityModifier', service('sylius.modifier.order_item_quantity'))
        ->arg('$priceCalculator', service('sylius.calculator.product_variant_price'))
        ->arg('$variantRepository', service('sylius.repository.product_variant'))
        ->arg('$channelContext', service('sylius.context.channel'))
        ->arg('$availabilityChecker', service('sylius.checker.inventory.availability'))
        ->public()
        ->tag('controller.service_arguments');
};
