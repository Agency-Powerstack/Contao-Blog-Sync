<?php

declare(strict_types=1);

namespace AgencyPowerstack\ContaoBlogSyncBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Config\FileLocator;

class ContaoBlogSyncExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.yaml');

        // Register the frontend URL parameter with env var fallback
        $frontendUrl = $_SERVER['BLOG_SYNC_FRONTEND_URL']
            ?? $_ENV['BLOG_SYNC_FRONTEND_URL']
            ?? getenv('BLOG_SYNC_FRONTEND_URL')
            ?: 'https://app.agency-powerstack.com';

        $container->setParameter('blog_sync.frontend_url', $frontendUrl);
    }
}
