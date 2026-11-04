<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\DependencyInjection;

use Sylius\Bundle\CoreBundle\DependencyInjection\PrependDoctrineMigrationsTrait;
use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

final class MarkAppSyliusWishlistExtension extends AbstractResourceExtension implements PrependExtensionInterface
{
    use PrependDoctrineMigrationsTrait;

    /**
     * @param array<array-key, mixed> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.xml');
    }

    public function prepend(ContainerBuilder $container): void
    {
        // The resources are registered here, before the other extensions load: the administration grid refers to the
        // parameter with the model class, and the grid extension would otherwise load before it exists.
        $config = $this->getCurrentConfiguration($container);
        $this->registerResources('mark_app_sylius_wishlist', $config['driver'], $config['resources'], $container);

        $this->prependDoctrineMigrations($container);

        // Limiters protect the write endpoints and the token lookups against bots and token guessing.
        $container->prependExtensionConfig('framework', [
            'rate_limiter' => [
                'mark_app_wishlist_guest_create' => ['policy' => 'sliding_window', 'limit' => 10, 'interval' => '1 hour'],
                'mark_app_wishlist_write' => ['policy' => 'sliding_window', 'limit' => 60, 'interval' => '1 minute'],
                'mark_app_wishlist_token_miss' => ['policy' => 'sliding_window', 'limit' => 20, 'interval' => '1 minute'],
            ],
        ]);
    }

    protected function getMigrationsNamespace(): string
    {
        // Own namespace: 'DoctrineMigrations' is the host project's key and would collide with it.
        return 'MarkApp\SyliusWishlistPlugin\Migrations';
    }

    protected function getMigrationsDirectory(): string
    {
        return '@MarkAppSyliusWishlistPlugin/src/Migrations';
    }

    protected function getNamespacesOfMigrationsExecutedBefore(): array
    {
        return [
            'Sylius\Bundle\CoreBundle\Migrations',
        ];
    }

    /**
     * @return array{driver: string, resources: array<string, mixed>}
     */
    private function getCurrentConfiguration(ContainerBuilder $container): array
    {
        /** @var ConfigurationInterface $configuration */
        $configuration = $this->getConfiguration([], $container);

        /** @var array{driver: string, resources: array<string, mixed>} $config */
        $config = $this->processConfiguration($configuration, $container->getExtensionConfig($this->getAlias()));

        return $config;
    }
}
