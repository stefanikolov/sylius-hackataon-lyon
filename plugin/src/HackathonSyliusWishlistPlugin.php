<?php

declare(strict_types=1);

namespace Hackathon\SyliusWishlistPlugin;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Wishlist plugin bundle.
 *
 * Everything the plugin needs is wired from here, so the host application only has to
 * register the bundle and import the routes. Doctrine mapping, migrations and Twig hooks are
 * prepended automatically.
 */
final class HackathonSyliusWishlistPlugin extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // Entities live inside the plugin; map them without touching the app config.
        $builder->prependExtensionConfig('doctrine', [
            'orm' => [
                'mappings' => [
                    'HackathonSyliusWishlistPlugin' => [
                        'type' => 'attribute',
                        'dir' => __DIR__ . '/Entity',
                        'prefix' => 'Hackathon\\SyliusWishlistPlugin\\Entity',
                        'is_bundle' => false,
                    ],
                ],
            ],
        ]);

        // Plugin migrations run together with the app ones (doctrine:migrations:migrate).
        $builder->prependExtensionConfig('doctrine_migrations', [
            'migrations_paths' => [
                'Hackathon\\SyliusWishlistPlugin\\Migrations' => __DIR__ . '/Migrations',
            ],
        ]);

        // Admin pages: the same sidebar and navbar as every other admin page, with the plugin's content.
        $adminPages = [];
        foreach (['index', 'show'] as $page) {
            $adminPages['sylius_admin.hackathon_wishlist.' . $page] = [
                'sidebar' => ['template' => '@SyliusAdmin/shared/crud/common/sidebar.html.twig', 'priority' => 200],
                'navbar' => ['template' => '@SyliusAdmin/shared/crud/common/navbar.html.twig', 'priority' => 100],
                'content' => ['template' => '@HackathonSyliusWishlistPlugin/admin/' . $page . '/content.html.twig', 'priority' => 0],
            ];
        }
        $builder->prependExtensionConfig('sylius_twig_hooks', ['hooks' => $adminPages]);

        // Wishlist buttons on the product page and product cards, wishlist link in the header.
        $builder->prependExtensionConfig('sylius_twig_hooks', [
            'hooks' => [
                'sylius_shop.product.show.content.info.summary' => [
                    'wishlist' => [
                        'template' => '@HackathonSyliusWishlistPlugin/shop/button.html.twig',
                        'priority' => 50,
                    ],
                ],
                'sylius_shop.shared.product.card' => [
                    'wishlist' => [
                        'template' => '@HackathonSyliusWishlistPlugin/shop/button.html.twig',
                        'priority' => -100,
                    ],
                ],
                'sylius_shop.base.header.content' => [
                    'wishlist' => [
                        'template' => '@HackathonSyliusWishlistPlugin/shop/header_link.html.twig',
                        'priority' => 150,
                    ],
                ],
            ],
        ]);
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import(__DIR__ . '/../config/services.php');
    }
}
